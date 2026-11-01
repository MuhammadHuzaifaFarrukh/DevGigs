<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

/* DELETE a user PERMANENTLY, with everything that belongs to them. This cannot be undone,
   so the page shows exactly what will go and asks for the admin's own password.
   (To lock someone out but keep their data, use Disable on the users list instead.) */
require_login($db, 'admin');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    error_page(400, 'Invalid user ID.');
}
$target = find_user($db, $id);
if (!$target) {
    error_page(404, 'User not found.');
}
if ($id === current_user_id() || $target['role'] === 'admin') {
    set_flash('Admin accounts, including your own, cannot be deleted.', 'error');
    redirect('admin-users.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $me = db_one($db, 'SELECT password FROM users WHERE id = ?', 'i', [current_user_id()]);
    $error = first_error([
        csrf_ok() ? null : 'Your session expired. Reload the page and try again.',
        (string)($_POST['admin_password'] ?? '') === '' ? 'Enter your admin password to confirm.' : null,
        password_verify((string)($_POST['admin_password'] ?? ''), $me['password']) ? null : 'Your admin password is wrong.',
    ]);

    if ($error === '') {
        try {
            destroy_user($db, $id);
            set_flash($target['name'] . ' and all of their data were permanently deleted.');
            redirect('admin-users.php');
        } catch (\Throwable $ex) {
            error_log('Destroy user failed: ' . $ex->getMessage());
            $error = 'The account could not be deleted. Nothing was changed.';
        }
    }
}

$foot = user_footprint($db, $id);

page_start('Delete user');
?>
<div class="narrow">
    <div>
        <a class="back" href="admin-users.php">&larr; Back to users</a>
        <div class="panel danger-zone">
            <h1 style="font-size:1.7rem">Permanently delete <?= e($target['name']) ?>?</h1>
            <p style="margin-top:12px"><?= e($target['email']) ?> &middot; <span class="badge badge-<?= e($target['role']) ?>"><?= e($target['role']) ?></span></p>
            <p style="margin-top:12px">This removes the account and everything linked to it. It cannot be undone.</p>
            <div class="facts">
                <div><b><?= $foot['gigs'] ?></b><span>gigs</span></div>
                <div><b><?= $foot['proposals'] ?></b><span>proposals</span></div>
                <div><b><?= $foot['reviews'] ?></b><span>reviews</span></div>
            </div>
            <p class="muted" style="margin-bottom:18px">Only want to stop them logging in? <a class="link" href="admin-users.php">Disable the account instead</a>. That keeps their data and can be reversed.</p>

            <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
            <form method="post" action="admin-user-delete.php?id=<?= (int)$id ?>">
                <?= csrf_field() ?>
                <?php password_input('admin_password', 'admin_password', 'current-password', 'Your admin password'); ?>
                <div class="btn-row">
                    <button class="btn btn-danger btn-lg" type="submit">Delete permanently</button>
                    <a class="btn btn-ghost btn-lg" href="admin-users.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php page_end();
