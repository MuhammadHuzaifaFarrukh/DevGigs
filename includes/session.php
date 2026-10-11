<?php
/**
 * Session handling, CSRF protection, flash messages and login guards.
 * Login state lives in the server session. Only the session id is a cookie.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,          // JavaScript cannot read the session id
        'samesite' => 'Lax',         // blocks most cross-site form posts
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

/* Fallbacks so the site still works if the mbstring extension is switched off. */
if (!function_exists('mb_strlen')) {
    function mb_strlen($s) { return preg_match_all('/./us', (string)$s); }
}
if (!function_exists('mb_substr')) {
    function mb_substr($s, $start, $len = null) {
        $chars = preg_split('//u', (string)$s, -1, PREG_SPLIT_NO_EMPTY);
        return implode('', array_slice($chars, $start, $len));
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($s) { return strtoupper((string)$s); }
}

/** Escape text for safe output in HTML. */
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function current_user_id() {
    return (int)($_SESSION['user_id'] ?? 0);
}

function current_role() {
    return $_SESSION['user_role'] ?? null;
}

/* ---------- CSRF ---------- */
function csrf_token() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_ok() {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

/* ---------- Flash messages (shown once on the next page) ---------- */
function set_flash($message, $type = 'ok') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function pull_flash() {
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Require a logged-in, non-deleted user. Optionally require a role.
 * The role is re-read from the database, so a changed or disabled account
 * takes effect on the next request.
 */
function require_login($pdo, $role = null) {
    if (!is_logged_in()) {
        redirect('login.php');
    }
    $stmt = $pdo->prepare('SELECT name, role FROM users WHERE id = ? AND is_deleted = 0');
    $stmt->execute([current_user_id()]);
    $row = $stmt->fetch();

    if (!$row) {
        $_SESSION = [];
        session_destroy();
        redirect('login.php');
    }
    $_SESSION['user_name'] = $row['name'];
    $_SESSION['user_role'] = $row['role'];

    if ($role !== null && $row['role'] !== $role) {
        redirect('index.php');
    }
}
