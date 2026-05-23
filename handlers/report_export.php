<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/report_helpers.php';

if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Not authorized.');
}

$type = (string) ($_GET['type'] ?? '');
$allowed = ['attendance', 'memberships', 'revenue'];
if (!in_array($type, $allowed, true)) {
    http_response_code(400);
    exit('Invalid export type.');
}

$range = reportDateRange($_GET);
$rows = reportExportRows(db(), $type, $range);
$filename = sprintf('fitsync_%s_%s_to_%s.csv', $type, $range['start'], $range['end']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
if ($rows) {
    fputcsv($out, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($out, $row);
    }
} else {
    fputcsv($out, ['message']);
    fputcsv($out, ['No records found for selected range.']);
}
fclose($out);
exit;
