<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$rows = SettingsRepository::adminAll();
json_ok(['settings' => $rows]);
