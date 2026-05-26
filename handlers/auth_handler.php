<?php
// ============================================================
//  FitSync — Auth Handler
//  handlers/auth_handler.php
//
//  Actions: login | register | logout | get_csrf
// ============================================================

declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

// ── Only accept POST ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Read JSON or form-encoded body ────────────────────────
$raw    = file_get_contents('php://input');
$data   = json_decode($raw, true) ?? $_POST;
$action = trim($data['action'] ?? '');

// ── CSRF check ────────────────────────────────────────────
if ($action !== 'get_csrf') {
    $submittedToken = $data['csrf_token'] ?? '';
    $sessionToken   = $_SESSION['csrf_token'] ?? '';

    if (!$sessionToken || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page.']);
        exit;
    }
}

// ── Route ─────────────────────────────────────────────────
match ($action) {
    'get_csrf' => actionGetCsrf(),
    'login'    => actionLogin($data),
    'register' => actionRegister($data),
    'logout'   => actionLogout(),
    default    => badRequest(),
};

// ─────────────────────────────────────────────────────────
//  ACTION: CSRF token
// ─────────────────────────────────────────────────────────
function actionGetCsrf(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    echo json_encode(['success' => true, 'csrf_token' => $_SESSION['csrf_token']]);
    exit;
}

// ─────────────────────────────────────────────────────────
//  ACTION: Login
// ─────────────────────────────────────────────────────────
function actionLogin(array $data): void
{
    $email    = strtolower(trim($data['email']    ?? ''));
    $password = $data['password'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        respond(false, 'Please enter a valid email and password.');
    }

    $pdo  = db();
    $stmt = $pdo->prepare(
        'SELECT id, first_name, last_name, email, password_hash, is_active, role
         FROM users WHERE email = ? LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Always log the attempt
    logLogin($user['id'] ?? null, $email, false);

    if (!$user) {
        respond(false, 'Invalid email or password.');
    }
    if (!$user['is_active']) {
        respond(false, 'Your account has been deactivated. Please contact support.');
    }
    if (!password_verify($password, $user['password_hash'])) {
        respond(false, 'Invalid email or password.');
    }

    // ✅ Valid — start session
    session_regenerate_id(true);
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_name']  = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role']  = $user['role'];

    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
        ->execute([$user['id']]);

    $pdo->prepare(
        'UPDATE login_logs SET success = 1 WHERE user_id = ? ORDER BY id DESC LIMIT 1'
    )->execute([$user['id']]);

    $redirect = $user['role'] === 'admin' ? 'admin.php' : 'profile.php';
    respond(true, 'Login successful! Redirecting…', ['redirect' => $redirect]);
}

// ─────────────────────────────────────────────────────────
//  ACTION: Register
// ─────────────────────────────────────────────────────────
function actionRegister(array $data): void
{
    // ── Collect fields ────────────────────────────────────
    $firstName     = trim($data['first_name']   ?? '');
    $lastName      = trim($data['last_name']    ?? '');
    $email         = strtolower(trim($data['email'] ?? ''));
    $password      = $data['password']          ?? '';
    $confirm       = $data['confirm']           ?? '';
    $gender        = trim($data['gender']        ?? '');
    $birthdate     = trim($data['birthdate']    ?? '');
    $planSlug      = $data['plan']              ?? '6mo';
    $paymentMethod = $data['payment_method']    ?? 'cash';
    $branchId      = (int) ($data['branch_id'] ?? 0);

    // ── Validation ────────────────────────────────────────
    if ($firstName === '' || $lastName === '') {
        respond(false, 'Please enter your full name.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(false, 'Please enter a valid email address.');
    }
    if (strlen($password) < 8) {
        respond(false, 'Password must be at least 8 characters.');
    }
    if ($password !== $confirm) {
        respond(false, 'Passwords do not match.');
    }

    // Birthdate: optional but must be a valid past date if provided
    $birthdateValue = null;
    if ($birthdate !== '') {
        $bd = DateTime::createFromFormat('Y-m-d', $birthdate);
        if (!$bd || $bd > new DateTime()) {
            respond(false, 'Please enter a valid birthdate.');
        }
        // Must be at least 16 years old
        $age = (new DateTime())->diff($bd)->y;
        if ($age < 16) {
            respond(false, 'You must be at least 16 years old to register.');
        }
        $birthdateValue = $bd->format('Y-m-d');
    }

    $allowedGenders = ['male', 'female', 'nonbinary', 'other'];
    if (!in_array($gender, $allowedGenders, true)) {
        respond(false, 'Please select a valid gender.');
    }

    $allowedPlans = ['1mo', '3mo', '6mo', '12mo'];
    if (!in_array($planSlug, $allowedPlans, true)) {
        respond(false, 'Invalid plan selected.');
    }

    $allowedPayments = ['credit_card', 'debit_card', 'gcash', 'maya', 'bank_transfer', 'cash'];
    if (!in_array($paymentMethod, $allowedPayments, true)) {
        respond(false, 'Invalid payment method selected.');
    }

    $pdo = db();

    // Verify branch exists
    $branchStmt = $pdo->prepare('SELECT id FROM branches WHERE id = ? AND is_active = 1 LIMIT 1');
    $branchStmt->execute([$branchId]);
    if (!$branchStmt->fetch()) {
        respond(false, 'Please select a valid branch.');
    }

    // ── Email uniqueness ──────────────────────────────────
    $check = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) {
        respond(false, 'An account with this email already exists. Try logging in.');
    }

    // ── Hash password ─────────────────────────────────────
    $hash        = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $verifyToken = bin2hex(random_bytes(32));

    // ── Insert user (always member role) ──────────────────
    $insertUser = $pdo->prepare(
        'INSERT INTO users
            (role, first_name, last_name, email, password_hash, birthdate, gender, verification_token, is_active)
         VALUES ("member", ?, ?, ?, ?, ?, ?, ?, ? )'
    );
    // New registrations are created inactive and require admin approval
    $insertUser->execute([$firstName, $lastName, $email, $hash, $birthdateValue, $gender, $verifyToken, 0]);
    $userId = (int) $pdo->lastInsertId();

    // ── Membership ────────────────────────────────────────
    $planStmt = $pdo->prepare(
        'SELECT * FROM membership_plans WHERE slug = ? AND is_active = 1 LIMIT 1'
    );
    $planStmt->execute([$planSlug]);
    $plan = $planStmt->fetch();

    if ($plan) {
        $startsAt = date('Y-m-d');
        $endsAt   = date('Y-m-d', strtotime("+{$plan['duration_days']} days"));

        // Create membership as pending so admin can review/approve payment
        $pdo->prepare(
            'INSERT INTO memberships
                (user_id, plan_id, branch_id, starts_at, ends_at, amount_paid, payment_method, payment_status, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, "pending", "pending")'
        )->execute([
            $userId,
            $plan['id'],
            $branchId,
            $startsAt,
            $endsAt,
            $plan['price'],
            $paymentMethod,
        ]);

        // Notify admins via contact_messages so they see a registration approval notification
        try {
            $subj = 'New registration awaiting approval: ' . $firstName . ' ' . $lastName;
            $msg  = "A new member has registered and requires approval.\n\n" .
                    "Name: {$firstName} {$lastName}\n" .
                    "Email: {$email}\n" .
                    "Plan: {$plan['label']}\n" .
                    "Branch ID: {$branchId}\n\n" .
                    "Review and approve from the admin panel.";
            $pdo->prepare('INSERT INTO contact_messages (name, email, subject, message, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())')
                ->execute([$firstName . ' ' . $lastName, $email, $subj, $msg, 'new']);
        } catch (Throwable) {
            // non-fatal
        }
    }

    // Do not auto-activate account or auto-login — await admin approval
    respond(true, "Thanks for registering, {$firstName}. Your account and plan are pending admin approval. You will receive an email when approved.", ['redirect' => 'index.php']);
}

// ─────────────────────────────────────────────────────────
//  ACTION: Logout
// ─────────────────────────────────────────────────────────
function actionLogout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    respond(true, 'Logged out.', ['redirect' => 'index.php']);
}

// ─────────────────────────────────────────────────────────
//  HELPER: Log login attempt
// ─────────────────────────────────────────────────────────
function logLogin(?int $userId, string $email, bool $success): void
{
    try {
        db()->prepare(
            'INSERT INTO login_logs (user_id, email, ip_address, user_agent, success)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $userId,
            $email,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512),
            (int) $success,
        ]);
    } catch (Throwable) {
        // Non-fatal — never break auth if logging fails
    }
}

// ─────────────────────────────────────────────────────────
//  HELPER: JSON response + exit
// ─────────────────────────────────────────────────────────
function respond(bool $success, string $message, array $extra = []): never
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

function badRequest(): never
{
    http_response_code(400);
    respond(false, 'Unknown action.');
}
