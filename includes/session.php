<?php

declare(strict_types=1);

/**
 * DevGigs - server-side sessions.
 *
 * This file only handles the SESSION itself (start, CSRF token, flash messages).
 * Who is logged in and what they may do lives in includes/auth.php.
 *
 * HOW IT WORKS
 *   - session_start() creates (or resumes) a session. PHP keeps the session DATA
 *     (user id, role, CSRF token...) in a file on the server.
 *   - The browser only receives the session ID, stored in the cookie DEVGIGS_SID.
 *     On every request the browser sends that cookie back and PHP finds the data.
 *   - Session IDs are NOT stored in the database. PHP's default file storage is enough
 *     for a single server. A database store is only needed to share sessions between
 *     several servers or to list and revoke active sessions.
 */

require_once __DIR__ . '/helpers.php';

const SESSION_NAME = 'DEVGIGS_SID';

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');   // refuse session ids the server did not create
    ini_set('session.use_only_cookies', '1');  // never accept a session id from the URL

    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,                          // cookie ends when the browser is closed
        'path'     => '/',
        'httponly' => true,                       // JavaScript cannot read the session id
        'samesite' => 'Lax',                      // blocks most cross-site form posts
        'secure'   => !empty($_SERVER['HTTPS']),  // HTTPS-only when the site runs on HTTPS
    ]);
    session_start();
}

/* ---------- CSRF protection (a secret token stored in the session) ---------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && is_string($_POST['csrf'])
        && hash_equals((string) $_SESSION['csrf'], $_POST['csrf']);
}

/* ---------- Flash messages (shown once, on the next page) ---------- */

function set_flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function pull_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}
