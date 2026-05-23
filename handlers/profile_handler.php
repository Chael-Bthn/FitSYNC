<?php
// ============================================================
//  FitSync — Profile Handler
//  handlers/profile_handler.php
//
//  Actions: update_profile | change_password | submit_feedback
// ============================================================

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';

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

// ─────────────────────────────────────────────────────────
//  HELPER
// ─────────────────────────────────────────────────────────
function respond(bool $success, string $message, array $extra = []): never
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}