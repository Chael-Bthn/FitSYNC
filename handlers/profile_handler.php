<?php
// ============================================================
//  FitSync — Profile Handler
//  handlers/profile_handler.php
//
//  Actions: update_profile | change_password | submit_feedback | log_attendance
// ============================================================

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/attendance_helpers.php';
require_once __DIR__ . '/../includes/membership_helpers.php';
require_once __DIR__ . '/../includes/schedule_helpers.php';

header('Content-Type: application/json');

// ── Auth check ────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated.']);
    exit;
}

// ── Only POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true) ?? $_POST;
$action = trim($data['action'] ?? '');

// ── CSRF check ────────────────────────────────────────────
$submittedToken = $data['csrf_token'] ?? '';
$sessionToken   = $_SESSION['csrf_token'] ?? '';
if (!$sessionToken || !hash_equals($sessionToken, $submittedToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page.']);
    exit;
}

$userId = (int) $_SESSION['user_id'];

match ($action) {
    'update_profile'  => actionUpdateProfile($data, $userId),
    'change_password' => actionChangePassword($data, $userId),
    'submit_feedback' => actionSubmitFeedback($data, $userId),
    'renew_membership'=> actionRenewMembership($data, $userId),
    'book_class'      => actionBookClass($data, $userId),
    'cancel_booking'  => actionCancelBooking($data, $userId),
    default           => respond(false, 'Unknown action.'),
};

// ─────────────────────────────────────────────────────────
//  ACTION: Update profile
// ─────────────────────────────────────────────────────────
function actionUpdateProfile(array $data, int $userId): void
{
    $firstName = trim($data['first_name'] ?? '');
    $lastName  = trim($data['last_name']  ?? '');
    $gender    = trim($data['gender']     ?? '');
    $birthdate = trim($data['birthdate']  ?? '');

    if ($firstName === '' || $lastName === '') {
        respond(false, 'First and last name are required.');
    }
    if (strlen($firstName) > 64 || strlen($lastName) > 64) {
        respond(false, 'Name is too long.');
    }

    $allowedGenders = ['male', 'female', 'nonbinary', 'other', ''];
    if (!in_array($gender, $allowedGenders, true)) {
        respond(false, 'Invalid gender value.');
    }

    $birthdateValue = null;
    if ($birthdate !== '') {
        $bd = DateTime::createFromFormat('Y-m-d', $birthdate);
        if (!$bd || $bd >= new DateTime()) {
            respond(false, 'Please enter a valid birthdate.');
        }
        $age = (new DateTime())->diff($bd)->y;
        if ($age < 16) {
            respond(false, 'You must be at least 16 years old.');
        }
        $birthdateValue = $bd->format('Y-m-d');
    }

    $pdo = db();
    $pdo->prepare(
        'UPDATE users
         SET first_name = ?, last_name = ?, gender = ?, birthdate = ?, updated_at = NOW()
         WHERE id = ?'
    )->execute([
        $firstName,
        $lastName,
        $gender ?: null,
        $birthdateValue,
        $userId,
    ]);

    // Update session name
    $_SESSION['user_name'] = $firstName . ' ' . $lastName;

    respond(true, 'Profile updated successfully.');
}

// ─────────────────────────────────────────────────────────
//  ACTION: Change password
// ─────────────────────────────────────────────────────────
function actionChangePassword(array $data, int $userId): void
{
    $currentPw = $data['current_password'] ?? '';
    $newPw     = $data['new_password']     ?? '';
    $confirmPw = $data['confirm_password'] ?? '';

    if ($currentPw === '') {
        respond(false, 'Please enter your current password.');
    }
    if (strlen($newPw) < 8) {
        respond(false, 'New password must be at least 8 characters.');
    }
    if ($newPw !== $confirmPw) {
        respond(false, 'New passwords do not match.');
    }

    $pdo  = db();
    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($currentPw, $user['password_hash'])) {
        respond(false, 'Current password is incorrect.');
    }

    if (password_verify($newPw, $user['password_hash'])) {
        respond(false, 'New password must be different from your current password.');
    }

    $newHash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?')
        ->execute([$newHash, $userId]);

    respond(true, 'Password updated successfully. Use it next time you log in.');
}

// ─────────────────────────────────────────────────────────
//  ACTION: Submit feedback
// ─────────────────────────────────────────────────────────
function actionSubmitFeedback(array $data, int $userId): void
{
    $branchId = (int) ($data['branch_id'] ?? 0);
    $rating   = (int) ($data['rating']    ?? 0);
    $body     = trim($data['body']        ?? '');

    if ($rating < 1 || $rating > 5) {
        respond(false, 'Please select a rating between 1 and 5 stars.');
    }
    if ($body === '') {
        respond(false, 'Please write your review before submitting.');
    }
    if (strlen($body) > 2000) {
        respond(false, 'Review is too long (max 2000 characters).');
    }

    $pdo = db();

    // Verify branch exists
    $branchStmt = $pdo->prepare('SELECT id, name FROM branches WHERE id = ? AND is_active = 1 LIMIT 1');
    $branchStmt->execute([$branchId]);
    $branch = $branchStmt->fetch();
    if (!$branch) {
        respond(false, 'Invalid branch selected.');
    }

    $pdo->prepare(
        'INSERT INTO feedback (user_id, branch_id, rating, body, is_visible, created_at)
         VALUES (?, ?, ?, ?, 1, NOW())'
    )->execute([$userId, $branchId, $rating, $body]);

    // Build a card to inject into the DOM without a page reload
    $stars      = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
    $dateStr    = date('M j, Y');
    $safeBody   = htmlspecialchars($body, ENT_QUOTES, 'UTF-8');
    $safeBranch = htmlspecialchars($branch['name'], ENT_QUOTES, 'UTF-8');

    $card = <<<HTML
    <div class="feedback-card mb-2">
        <div class="d-flex justify-content-between align-items-start mb-1">
            <div class="feedback-stars">{$stars}</div>
            <span style="font-size:.7rem;color:var(--text-dimmed)">{$dateStr}</span>
        </div>
        <div style="font-size:.85rem;color:var(--text-muted);line-height:1.7;margin:.4rem 0">"{$safeBody}"</div>
        <div style="font-size:.72rem;color:var(--text-dimmed)">
            <i class="ti ti-map-pin" style="font-size:.8rem"></i> {$safeBranch}
        </div>
    </div>
    HTML;

    respond(true, 'Thank you! Your review has been submitted.', ['card' => $card]);
}

// Attendance logging action removed

function actionRenewMembership(array $data, int $userId): void
{
    if (($_SESSION['user_role'] ?? '') !== 'member') {
        http_response_code(403);
        respond(false, 'Only members can renew memberships.');
    }

    $planId = (int) ($data['plan_id'] ?? 0);
    $paymentMethod = trim((string) ($data['payment_method'] ?? 'cash'));
    $allowedPayments = ['credit_card', 'debit_card', 'gcash', 'maya', 'bank_transfer', 'cash'];
    if (!in_array($paymentMethod, $allowedPayments, true)) {
        respond(false, 'Invalid payment method selected.');
    }

    $pdo = db();
    expireOldMemberships($pdo);

    $planStmt = $pdo->prepare(
        'SELECT id, label, duration_days, price
         FROM membership_plans
         WHERE id = ? AND is_active = 1
         LIMIT 1'
    );
    $planStmt->execute([$planId]);
    $plan = $planStmt->fetch();
    if (!$plan) {
        respond(false, 'Invalid plan selected.');
    }

    $pendingStmt = $pdo->prepare(
        'SELECT id FROM memberships
         WHERE user_id = ? AND payment_status = "pending" AND status = "pending"
         LIMIT 1'
    );
    $pendingStmt->execute([$userId]);
    if ($pendingStmt->fetch()) {
        respond(false, 'You already have a renewal pending approval.');
    }

    $latest = getLatestMembership($pdo, $userId);
    $branchId = (int) ($latest['branch_id'] ?? 1);
    $today = new DateTimeImmutable('today');
    $active = getActiveMembership($pdo, $userId);
    $start = $active
        ? (new DateTimeImmutable($active['ends_at']))->modify('+1 day')
        : $today;
    $end = $start->modify('+' . (int) $plan['duration_days'] . ' days');

    $stmt = $pdo->prepare(
        'INSERT INTO memberships
            (user_id, plan_id, branch_id, starts_at, ends_at, amount_paid, payment_method, payment_status, status, payment_ref)
         VALUES (?, ?, ?, ?, ?, ?, ?, "pending", "pending", ?)'
    );
    $stmt->execute([
        $userId,
        (int) $plan['id'],
        $branchId,
        $start->format('Y-m-d'),
        $end->format('Y-m-d'),
        (float) $plan['price'],
        $paymentMethod,
        'RNW-' . strtoupper(bin2hex(random_bytes(4))),
    ]);

    // Notify admins: create a contact message so admins see the renewal in their inbox
    try {
        $userStmt = $pdo->prepare('SELECT first_name, last_name, email FROM users WHERE id = ? LIMIT 1');
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        $subject = sprintf('Membership renewal request — %s %s', $user['first_name'] ?? '', $user['last_name'] ?? '');
        $message = sprintf("Member: %s %s (%s)\nPlan: %s\nStarts: %s\nEnds: %s\nAmount: ₱%s\nBranch ID: %d\nPayment method: %s\nReference: %s",
            $user['first_name'] ?? '',
            $user['last_name'] ?? '',
            $user['email'] ?? '',
            $plan['label'] ?? ('Plan ' . $planId),
            $start->format('Y-m-d'),
            $end->format('Y-m-d'),
            number_format((float)$plan['price'], 2),
            $branchId,
            $paymentMethod,
            'RNW-' . strtoupper(bin2hex(random_bytes(4)))
        );

        $pdo->prepare(
            'INSERT INTO contact_messages (name, email, phone, subject, message, status, created_at)
             VALUES (?, ?, NULL, ?, ?, "new", NOW())'
        )->execute([
            trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            $user['email'] ?? '',
            $subject,
            $message,
        ]);

        // Attempt to send a quick email to all admins (best-effort)
        try {
            $adminStmt = $pdo->query('SELECT email FROM users WHERE role = "admin" AND is_active = 1');
            $adminEmails = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
            if ($adminEmails) {
                $to = implode(',', $adminEmails);
                $mailSubject = $subject;
                $mailBody = $message;
                // Use PHP mail() if available/configured — ignore failures
                if (function_exists('mail')) {
                    @mail($to, $mailSubject, $mailBody);
                }
            }
        } catch (Throwable) {
            // ignore email errors
        }
    } catch (Throwable) {
        // ignore notification errors — renewal must not fail because notification fails
    }

    respond(true, 'Renewal submitted. An admin will approve your payment.', [
        'reload' => true,
    ]);
}

function actionBookClass(array $data, int $userId): void
{
    if (($_SESSION['user_role'] ?? '') !== 'member') {
        http_response_code(403);
        respond(false, 'Only members can reserve classes.');
    }

    $scheduleId = (int) ($data['schedule_id'] ?? 0);
    if ($scheduleId <= 0) {
        respond(false, 'Invalid class schedule.');
    }

    $pdo = db();
    expireOldMemberships($pdo);
    if (!hasActiveMembership($pdo, $userId)) {
        respond(false, 'An active membership is required before reserving classes.');
    }

    $result = scheduleReserveClass($pdo, $userId, $scheduleId);
    respond((bool) $result['success'], (string) $result['message'], ['reload' => (bool) $result['success']]);
}

function actionCancelBooking(array $data, int $userId): void
{
    if (($_SESSION['user_role'] ?? '') !== 'member') {
        http_response_code(403);
        respond(false, 'Only members can cancel bookings.');
    }

    $bookingId = (int) ($data['booking_id'] ?? 0);
    if ($bookingId <= 0) {
        respond(false, 'Invalid booking.');
    }

    $result = scheduleCancelBooking(db(), $userId, $bookingId);
    respond((bool) $result['success'], (string) $result['message'], ['reload' => (bool) $result['success']]);
}

function respond(bool $success, string $message, array $extra = []): never
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}
