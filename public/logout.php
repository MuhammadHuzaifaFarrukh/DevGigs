<?php
require_once __DIR__ . '/../includes/session.php';

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

session_destroy();

// Preference cookies (theme, sort order) are kept on purpose. Only the login is cleared.
header('Location: login.php');
exit();
