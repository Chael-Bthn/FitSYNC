<?php
// ============================================================
//  FitSync — Generate QR Codes for Existing Accounts
//  generate_existing_qrcodes.php
// ============================================================

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/qr_helpers.php';

$pdo = db();
$stmt = $pdo->query('SELECT id FROM users');
$users = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "Starting QR Code generation for existing accounts...\n";
$successCount = 0;
$failCount = 0;

foreach ($users as $userId) {
    $userId = (int) $userId;
    echo "Generating QR Code for User ID: {$userId}... ";
    if (generateUserQrCode($userId)) {
        echo "SUCCESS\n";
        $successCount++;
    } else {
        echo "FAILED\n";
        $failCount++;
    }
}

echo "Completed. Success: {$successCount}, Failed: {$failCount}\n";
