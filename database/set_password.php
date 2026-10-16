<?php
/**
 * Set a user's password from the command line. Use it for the admin account, or if anyone
 * forgets their password (there is no email reset in this MVP).
 *
 *   php database/set_password.php admin@example.com
 *
 * The new password is typed at a prompt, so it is not saved in your shell history.
 * This file refuses to run in a web request, so it cannot be abused through the browser.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Command line only.');
}

require_once __DIR__ . '/../config/database.php';      // gives $db
require_once __DIR__ . '/../includes/validation.php';

$email = $argv[1] ?? '';
if ($email === '') {
    fwrite(STDERR, "Usage: php database/set_password.php user@example.com\n");
    exit(1);
}

$user = db_one($db, 'SELECT id, email FROM users WHERE email = ? AND is_deleted = 0', 's', [$email]);
if (!$user) {
    fwrite(STDERR, "No active user with that email.\n");
    exit(1);
}

echo 'New password for ' . $user['email'] . ': ';
$password = rtrim((string) fgets(STDIN), "\r\n");

if ($error = validate_password($password)) {
    fwrite(STDERR, $error . "\n");
    exit(1);
}

db_exec($db, 'UPDATE users SET password = ? WHERE id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
echo "Password updated.\n";