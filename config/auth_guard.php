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
 */
function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: auth.php');
        exit;
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