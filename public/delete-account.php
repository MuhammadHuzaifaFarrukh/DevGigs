<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

/* DELETE your own account. Needs your password. It disables the account (is_deleted = 1): you can no
   longer log in, and your gigs and proposals disappear from the site. Admin accounts cannot be
   deleted here, so the platform can never be left without an admin. */
require_login($db);
if (current_role() === 'admin') {
    set_flash('Admin accounts cannot be deleted from here.', 'error');
    redirect('settings.php');
}

$me    = db_one($db, 'SELECT id, password FROM users WHERE id = ? AND is_deleted = 0', 'i', [current_user_id()]);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $error = first_error([
        csrf_ok() ? null : 'Your session expired. Reload the page and try again.',
        $password === '' ? 'Enter your password to confirm.' : null,
        password_verify($password, $me['password']) ? null : 'That password is wrong.',
    ]);

    if ($error === '') {
        db_exec($db, 'UPDATE users SET is_deleted = 1 WHERE id = ?', 'i', [(int)$me['id']]);
        logout_user();
        session_start();    // fresh session so the goodbye message can be shown
        set_flash('Your account has been deleted.');
        redirect('index.php');
    }
}

page_start('Delete account');
?>
<div class="narrow">
    <div>
        <a class="back" href="settings.php">&larr; Back to settings</a>
        <div class="panel danger-zone">
            <h1 style="font-size:1.7rem">Delete your account?</h1>
            <p style="margin:12px 0 20px">You will be logged out straight away and will not be able to log in again. Your gigs, proposals and reviews will no longer be visible on the site.</p>
            <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="post" action="delete-account.php">
                <?= csrf_field() ?>
                <?php password_input('password', 'password', 'current-password', 'Confirm with your password'); ?>
                <div class="btn-row">
                    <button class="btn btn-danger btn-lg" type="submit">Delete my account</button>
                    <a class="btn btn-ghost btn-lg" href="settings.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php page_end();
