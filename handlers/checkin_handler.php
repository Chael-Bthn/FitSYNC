<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/auth_guard.php';
requireRole('admin');
require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

function jsonOut(bool $ok, string $msg, array $extra = []): never
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonOut(false, 'Method not allowed.');
}

$body = (array) json_decode(file_get_contents('php://input'), true);

// CSRF check
$csrfToken = trim((string) ($body['csrf_token'] ?? ''));
if (!$csrfToken || !hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrfToken)) {
    jsonOut(false, 'Invalid CSRF token.');
}

$action = trim((string) ($body['action'] ?? ''));
$pdo    = db();

// ── checkin ──────────────────────────────────────────────────────────────────
if ($action === 'checkin') {
    $userId   = (int) ($body['user_id']   ?? 0);
    $branchId = (int) ($body['branch_id'] ?? 0);

    if ($userId <= 0 || $branchId <= 0) {
        jsonOut(false, 'Invalid user or branch.');
    }

    // Verify user exists and is an active member
    $user = $pdo->prepare(
        'SELECT u.id, u.first_name, u.last_name, u.is_active, u.is_approved
         FROM users u WHERE u.id = ? AND u.role = "member" LIMIT 1'
    );
    $user->execute([$userId]);
    $u = $user->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        jsonOut(false, 'Member not found.');
    }
    if (!(int) $u['is_active'] || !(int) $u['is_approved']) {
        jsonOut(false, 'Member account is inactive or not approved.');
    }

    // Verify active membership
    $memStmt = $pdo->prepare(
        'SELECT id FROM memberships
         WHERE user_id = ? AND status = "active" AND payment_status = "paid"
           AND starts_at <= CURDATE() AND ends_at >= CURDATE()
         LIMIT 1'
    );
    $memStmt->execute([$userId]);
    if (!$memStmt->fetch()) {
        jsonOut(false, 'Member does not have an active membership.');
    }

    // Prevent duplicate check-in within 1 hour
    $dupStmt = $pdo->prepare(
        'SELECT id FROM attendance_logs
         WHERE user_id = ? AND check_in_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
         LIMIT 1'
    );
    $dupStmt->execute([$userId]);
    if ($dupStmt->fetch()) {
        jsonOut(false, 'Member was already checked in within the last hour.');
    }

    // Insert attendance log
    $ins = $pdo->prepare(
        'INSERT INTO attendance_logs (user_id, branch_id, check_in_at) VALUES (?, ?, NOW())'
    );
    $ins->execute([$userId, $branchId]);

    jsonOut(true, 'Check-in recorded successfully.', [
        'name' => trim($u['first_name'] . ' ' . $u['last_name']),
    ]);
}

jsonOut(false, 'Unknown action.');