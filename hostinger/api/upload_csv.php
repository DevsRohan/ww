<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_fail('method_not_allowed', 405);
if (empty($_FILES['csv']['tmp_name'])) json_fail('no_file_uploaded');
if (($_FILES['csv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) json_fail('upload_error');

$mime = mime_content_type($_FILES['csv']['tmp_name']) ?: '';
$nameLower = strtolower($_FILES['csv']['name'] ?? '');
$okMime = in_array($mime, ['text/csv','text/plain','application/csv','application/vnd.ms-excel'], true);
if (!$okMime && !str_ends_with($nameLower, '.csv')) {
    json_fail('invalid_file_type', 415, ['mime' => $mime]);
}

$uploadsDir = $GLOBALS['APP']['paths']['uploads'];
ensure_dir($uploadsDir);
$safeName = uuid_v4() . '.csv';
$dest = $uploadsDir . '/' . $safeName;
if (!move_uploaded_file($_FILES['csv']['tmp_name'], $dest)) {
    json_fail('move_failed', 500);
}

try {
    $parsed = CsvParser::parseFile($dest);
} catch (\Throwable $e) {
    @unlink($dest);
    json_fail('parse_failed', 400, ['detail' => $e->getMessage()]);
}

$importId = DB::insert(
    'INSERT INTO csv_imports (filename, total_rows, status, uploaded_by) VALUES (?, ?, "processing", ?)',
    [$_FILES['csv']['name'], $parsed['total'], Auth::user()['id'] ?? null]
);

$inserted = 0; $duplicates = 0; $failed = 0;
$errors = $parsed['errors'];

DB::transaction(function() use ($parsed, &$inserted, &$duplicates, &$failed, &$errors) {
    foreach ($parsed['rows'] as $row) {
        try {
            $r = LeadRepository::upsert($row);
            if ($r['inserted']) $inserted++; else $duplicates++;
        } catch (\Throwable $e) {
            $failed++;
            $errors[] = $e->getMessage();
        }
    }
});

DB::execute(
    'UPDATE csv_imports SET imported_rows = ?, duplicate_rows = ?, failed_rows = ?, status = "completed", error_log = ?, completed_at = NOW() WHERE id = ?',
    [$inserted, $duplicates, $failed, $errors ? implode("\n", array_slice($errors, 0, 50)) : null, $importId]
);

@unlink($dest);

AppLogger::info('csv_imported', [
    'import_id' => $importId, 'inserted' => $inserted, 'duplicates' => $duplicates, 'failed' => $failed
], 'import');

// Count pending leads that need validation (frontend will trigger Validate All)
$pendingCount = 0;
if ($inserted > 0) {
    $row = DB::fetch("SELECT COUNT(*) AS c FROM leads WHERE whatsapp_status = 'pending'");
    $pendingCount = (int)($row['c'] ?? 0);
}

json_ok([
    'import_id'       => $importId,
    'total'           => $parsed['total'],
    'inserted'        => $inserted,
    'duplicates'      => $duplicates,
    'failed'          => $failed,
    'pending_validation' => $pendingCount,
    'errors'          => array_slice($errors, 0, 20),
]);
