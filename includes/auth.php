<?php

declare(strict_types=1);

/**
 * DevGigs - authentication and authorization.
 * The roles live in the `roles` table (admin, client, freelancer); users.role_id points to one.
 * The session stores only the user id and the role NAME. The role is re-read from the
 * database on every protected request (see require_login), so the session copy is never trusted.
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

/* ---------- Login state ---------- */

function is_logged_in(): bool
{
    return isset($_SESSION['user_id']);
}

function current_user_id(): int
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_role(): ?string
{
    return isset($_SESSION['user_role']) ? (string) $_SESSION['user_role'] : null;
}

/**
 * Store a successful login in the session.
 * The session id is replaced first, which prevents session fixation attacks.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int) $user['id'];
    $_SESSION['user_name']  = (string) $user['name'];
    $_SESSION['user_email'] = (string) $user['email'];
    $_SESSION['user_role']  = (string) $user['role'];
}

/** End the session and remove the session cookie from the browser. */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Require a logged-in, non-deleted user, and optionally a specific role.
 * The role is re-read from the database on every call, so a disabled account or a
 * changed role takes effect immediately.
 */
function require_login(mysqli $db, ?string $role = null): void
{
    if (!is_logged_in()) {
        redirect('login.php');
    }

    $row = db_one($db, 'SELECT u.name, r.name AS role
                        FROM users u JOIN roles r ON r.id = u.role_id
                        WHERE u.id = ? AND u.is_deleted = 0', 'i', [current_user_id()]);
    if ($row === null) {
        logout_user();
        redirect('login.php');
    }

    $_SESSION['user_name'] = $row['name'];
    $_SESSION['user_role'] = $row['role'];

    if ($role !== null && $row['role'] !== $role) {
        redirect('index.php');
    }
}