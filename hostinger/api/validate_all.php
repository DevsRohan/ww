<?php
/**
 * Validate ALL pending leads — marks them as valid (smba platform can't check).
 * Called from dashboard "Validate All" button.
 */
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();
set_time_limit(300);

$rows = LeadRepository::pickPendingValidation(200);
if (!$rows) {
    json_ok(['message' => 'no_pending_leads', 'validated' => 0, 'total' => 0]);
}

$validated = 0;
$total = count($rows);

foreach ($rows as $row) {
    LeadRepository::setWhatsappStatus((int)$row['id'], 'valid');
    $validated++;
}

AppLogger::info('validate_all', ['validated' => $validated], 'validate');
json_ok(['validated' => $validated, 'total' => $total, 'message' => "All $validated leads marked as valid"]);
