<?php
// ============================================================
//  FitSync — QR Code Helpers
//  /includes/qr_helpers.php
// ============================================================

function generateUserQrCode(int $userId): bool
{
    $qrDir = __DIR__ . '/../qrcodes';
    if (!is_dir($qrDir)) {
        if (!mkdir($qrDir, 0777, true) && !is_dir($qrDir)) {
            error_log('[FitSync QR] Failed to create directory: ' . $qrDir);
            return false;
        }
    }

    $filePath = $qrDir . '/member' . $userId . '.png';
    $memberId = 'MBR-' . str_pad((string)$userId, 5, '0', STR_PAD_LEFT);

    // Call QR Server API to generate and download the QR code image
    $apiUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($memberId);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $imageData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $imageData) {
        if (file_put_contents($filePath, $imageData) !== false) {
            return true;
        }
    }

    // Fallback: try file_get_contents
    $imageData = @file_get_contents($apiUrl);
    if ($imageData) {
        if (file_put_contents($filePath, $imageData) !== false) {
            return true;
        }
    }

    error_log('[FitSync QR] Failed to download QR code for user ID: ' . $userId);
    return false;
}
