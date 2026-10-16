<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

/* Account settings for any logged-in user: change name / phone, or change password.
   Email and role cannot be changed here (email is the login id; role is fixed per account). */
require_login($db);

$uid  = current_user_id();
$user = db_one($db, 'SELECT id, name, email, phone, password FROM users WHERE id = ? AND is_deleted = 0', 'i', [$uid]);

$errors = ['profile' => '', 'password' => ''];
$old    = ['name' => $user['name'], 'phone' => (string)($user['phone'] ?? '')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $old['name']  = trim($_POST['name'] ?? '');
        $old['phone'] = trim($_POST['phone'] ?? '');

        $errors['profile'] = first_error([
            csrf_ok() ? null : 'Your session expired. Reload the page and try again.',
            validate_name($old['name']),
            validate_phone($old['phone']),
        ]);
        if ($errors['profile'] === '') {
            db_exec($db, 'UPDATE users SET name = ?, phone = ? WHERE id = ?', 'ssi', [$old['name'], $old['phone'], $uid]);
            $_SESSION['user_name'] = $old['name'];
            set_flash('Your details were updated.');
            redirect('settings.php');
        }
    } elseif ($action === 'change_password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm'] ?? '');

        $errors['password'] = first_error([
            csrf_ok() ? null : 'Your session expired. Reload the page and try again.',
            $current === '' ? 'Enter your current password.' : null,
            password_verify($current, $user['password']) ? null : 'Your current password is wrong.',
            validate_password($new),
            $new === $confirm ? null : 'The new passwords do not match.',
            $new !== $current ? null : 'Choose a password different from your current one.',
        ]);
        if ($errors['password'] === '') {
            db_exec($db, 'UPDATE users SET password = ? WHERE id = ?', 'si', [password_hash($new, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);   // new session id after a credential change
            set_flash('Password changed. Use the new one next time you log in.');
            redirect('settings.php');
        }
    }
}

page_start('Settings');
?>
<div class="narrow">
    <div>
        <a class="back" href="profile.php">&larr; Back to profile</a>
        <h1 style="font-size:2rem">Account settings</h1>
    </div>

    <div class="panel">
        <h2>Your details</h2>
        <?php if ($errors['profile']): ?><div class="alert alert-error" role="alert"><?= e($errors['profile']) ?></div><?php endif; ?>
        <form method="post" action="settings.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="field">
                <label for="name">Full name</label>
                <input class="input" type="text" id="name" name="name" value="<?= e($old['name']) ?>" required minlength="2" maxlength="100" autocomplete="name">
            </div>
            <div class="field">
                <label for="phone">Phone</label>
                <input class="input" type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" required maxlength="20"
                       autocomplete="tel" data-validate="phone" pattern="\+?[0-9\s\-]{7,20}">
                <div class="field-msg" aria-live="polite"></div>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input class="input" type="email" id="email" value="<?= e($user['email']) ?>" disabled>
                <div class="hint">Your email is your login and cannot be changed.</div>
            </div>
            <button class="btn" type="submit">Save changes</button>
        </form>
    </div>

    <div class="panel">
        <h2>Change password</h2>
        <?php if ($errors['password']): ?><div class="alert alert-error" role="alert"><?= e($errors['password']) ?></div><?php endif; ?>
        <form method="post" action="settings.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_password">
            <?php password_input('current_password', 'current_password', 'current-password', 'Current password'); ?>
            <div class="field">
                <label for="new_password">New password</label>
                <div class="pw">
                    <input class="input" type="password" id="new_password" name="new_password" required minlength="8" maxlength="72"
                           autocomplete="new-password" data-validate="password">
                    <button type="button" data-toggle-pw="new_password">Show</button>
                </div>
                <div class="meter" aria-hidden="true"><i data-level="0"></i></div>
                <ul class="rules">
                    <li data-rule="len">8 to 72 characters</li>
                    <li data-rule="upper">One uppercase letter</li>
                    <li data-rule="lower">One lowercase letter</li>
                    <li data-rule="num">One number</li>
                </ul>
                <div class="field-msg" aria-live="polite"></div>
            </div>
            <div class="field">
                <label for="confirm">Confirm new password</label>
                <input class="input" type="password" id="confirm" name="confirm" required autocomplete="new-password" data-validate="confirm">
                <div class="field-msg" aria-live="polite"></div>
            </div>
            <button class="btn" type="submit">Change password</button>
        </form>
    </div>
</div>
<?php page_end();