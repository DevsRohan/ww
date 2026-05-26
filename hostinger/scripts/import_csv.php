<?php
/**
 * CLI CSV importer.
 * Usage:  php scripts/import_csv.php /absolute/path/to/file.csv
 * Useful for bulk imports too large for the upload form.
 */
require_once __DIR__ . '/../config/bootstrap.php';
set_time_limit(0);

if (PHP_SAPI !== 'cli') { http_response_code(403); echo "cli_only"; exit; }
$file = $argv[1] ?? null;
if (!$file || !is_readable($file)) {
    fwrite(STDERR, "Usage: php import_csv.php /path/to/file.csv\n");
    exit(1);
}
echo "Importing: $file\n";
$parsed = CsvParser::parseFile($file);
echo "Total rows: " . $parsed['total'] . "\n";
$inserted = 0; $duplicates = 0; $failed = 0;
DB::transaction(function() use ($parsed, &$inserted, &$duplicates, &$failed) {
    foreach ($parsed['rows'] as $row) {
        try {
            $r = LeadRepository::upsert($row);
            $r['inserted'] ? $inserted++ : $duplicates++;
        } catch (\Throwable $e) { $failed++; }
    }
});
echo "Inserted=$inserted Duplicates=$duplicates Failed=$failed\n";
AppLogger::info('csv_imported_cli', ['inserted'=>$inserted,'dup'=>$duplicates,'fail'=>$failed], 'import');
