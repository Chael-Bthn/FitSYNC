<?php
// ============================================================
//  FitSync — Session Guard
//  /config/auth_guard.php
//
//  Include this at the TOP of any protected page.
//
//  Usage (member-only page):
//      require_once __DIR__ . '/config/auth_guard.php';
//      requireLogin();          // redirects to auth.php if not logged in
//      requireRole('member');   // redirects to profile.php if wrong role
//
//  Usage (admin-only page):
//      require_once __DIR__ . '/config/auth_guard.php';
//      requireRole('admin');    // redirects if not admin
// ============================================================

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/**
 * Redirect to login if there is no active session.
 * Also refreshes is_approved / pending_approval from the DB on every load
 * so admin approval takes effect immediately without requiring re-login.
 */
function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: auth.php');
        exit;
    }

    // Re-check approval status from DB so it reflects admin changes instantly
    try {
        require_once __DIR__ . '/db.php';
        $pdo  = db();
        $stmt = $pdo->prepare('SELECT is_approved, is_active FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $row  = $stmt->fetch();

        if ($row) {
            // If the account was deactivated after login, force logout
            if (!(int) $row['is_active']) {
                $_SESSION = [];
                session_destroy();
                header('Location: auth.php');
                exit;
            }
            // Keep pending_approval in sync with the actual DB value
            $_SESSION['pending_approval'] = !(int) $row['is_approved'];
        }
    } catch (Throwable) {
        // Non-fatal — fall back to session value if DB check fails
    }
}

/**
 * Require a specific role, redirect otherwise.
 *
 * @param string $role  'admin' | 'member'
 */
function requireRole(string $role): void
{
    requireLogin(); // must be logged in first

    if (($_SESSION['user_role'] ?? '') !== $role) {
        // Wrong role — send each role to their own dashboard
        $redirect = $_SESSION['user_role'] === 'admin' ? 'admin.php' : 'profile.php';
        header("Location: {$redirect}");
        exit;
    }
}

/**
 * Handy helper — current user array from session.
 */
function currentUser(): array
{
    return [
        'id'    => $_SESSION['user_id']    ?? null,
        'name'  => $_SESSION['user_name']  ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role'  => $_SESSION['user_role']  ?? '',
    ];
}