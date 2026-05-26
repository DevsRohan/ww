<?php
require_once __DIR__ . '/../config/bootstrap.php';
Auth::requireApi();

$level  = $_GET['level']  ?? '';
$source = $_GET['source'] ?? '';
$limit  = max(1, min(500, (int)($_GET['limit'] ?? 100)));

$where = ['1=1'];
$params = [];
if ($level)  { $where[] = 'level = ?';  $params[] = $level; }
if ($source) { $where[] = 'source = ?'; $params[] = $source; }

$rows = DB::fetchAll(
    'SELECT id, level, source, message, context, created_at
     FROM logs WHERE ' . implode(' AND ', $where) . '
     ORDER BY id DESC LIMIT ' . $limit,
    $params
);
foreach ($rows as &$r) {
    if ($r['context']) $r['context'] = json_decode($r['context'], true);
}
json_ok(['logs' => $rows]);
