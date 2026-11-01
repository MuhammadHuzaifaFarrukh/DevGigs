<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

/* CREATE a user (no ?id) or UPDATE one (?id=5). The admin chooses the role and sets the password. */
require_login($db, 'admin');

$id     = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEdit = $id !== null && $id !== false;
$target = null;
if ($isEdit) {
    if ($id < 1) {
        error_page(400, 'Invalid user ID.');
    }
    $target = find_user($db, $id);
    if (!$target) {
        error_page(404, 'User not found.');
    }
}

// An existing admin keeps the admin role, so the platform cannot lose its last admin by accident.
$roleLocked = $isEdit && $target['role'] === 'admin';
$error = '';
$old   = $isEdit
    ? ['name' => $target['name'], 'email' => $target['email'], 'phone' => (string)($target['phone'] ?? ''), 'role' => $target['role']]
    : ['name' => '', 'email' => '', 'phone' => '', 'role' => 'freelancer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name']  = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    if (!$roleLocked) {
        $old['role'] = (string)($_POST['role'] ?? '');
    }
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['confirm'] ?? '');
    $wantsPassword = !$isEdit || $password !== '';   // on edit, a blank password means "keep the current one"

    $error = first_error([
        csrf_ok() ? null : 'Your session expired. Reload the page and try again.',
        validate_name($old['name']),
        validate_email($old['email']),
        validate_phone($old['phone']),
        in_array($old['role'], ['admin', 'client', 'freelancer'], true) ? null : 'Choose a role.',
        $wantsPassword ? validate_password($password) : null,
        $wantsPassword && $password !== $confirm ? 'Passwords do not match.' : null,
        email_in_use($db, $old['email'], $isEdit ? (int)$id : 0) ? 'That email already belongs to another account.' : null,
    ]);

    if ($error === '') {
        try {
            $roleId = role_id_by_name($db, $old['role']);
            if ($isEdit) {
                db_exec($db, 'UPDATE users SET name = ?, email = ?, phone = ?, role_id = ? WHERE id = ?', 'sssii',
                    [$old['name'], $old['email'], $old['phone'], $roleId, $id]);
                if ($password !== '') {
                    db_exec($db, 'UPDATE users SET password = ? WHERE id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT), $id]);
                }
                if ((int)$id === current_user_id()) {
                    $_SESSION['user_name'] = $old['name'];
                }
                set_flash('User updated.' . ($password !== '' ? ' The new password is active.' : ''));
            } else {
                db_exec($db, 'INSERT INTO users (name, email, phone, password, role_id) VALUES (?, ?, ?, ?, ?)', 'ssssi',
                    [$old['name'], $old['email'], $old['phone'], password_hash($password, PASSWORD_DEFAULT), $roleId]);
                set_flash('User created. Give them the password you set; they can change it in Settings.');
            }
            redirect('admin-users.php');
        } catch (\mysqli_sql_exception $ex) {
            error_log('Admin user save failed: ' . $ex->getMessage());
            $error = ((int)$ex->getCode() === 1062) ? 'That email already belongs to another account.' : 'We could not save this user. Please try again.';
        }
    }
}

page_start($isEdit ? 'Edit user' : 'Add user');
?>
<div class="narrow">
    <div>
        <a class="back" href="admin-users.php">&larr; Back to users</a>
        <div class="panel">
            <h1><?= $isEdit ? 'Edit user' : 'Add user' ?></h1>
            <p class="muted" style="margin:6px 0 24px"><?= $isEdit ? 'Leave the password blank to keep the current one.' : 'Set a password and give it to the new user. They can change it later in Settings.' ?></p>

            <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

            <form method="post" action="admin-user-form.php<?= $isEdit ? '?id=' . (int)$id : '' ?>">
                <?= csrf_field() ?>
                <div class="form-grid">
                    <div class="field">
                        <label for="name">Full name</label>
                        <input class="input" type="text" id="name" name="name" value="<?= e($old['name']) ?>" required minlength="2" maxlength="100">
                    </div>
                    <div class="field">
                        <label for="role">Role</label>
                        <?php if ($roleLocked): ?>
                            <input class="input" type="text" id="role" value="admin" disabled>
                        <?php else: ?>
                            <select class="input" id="role" name="role">
                                <?php foreach (['freelancer', 'client', 'admin'] as $r): ?>
                                    <option value="<?= e($r) ?>"<?= $old['role'] === $r ? ' selected' : '' ?>><?= e(ucfirst($r)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="email">Email</label>
                        <input class="input" type="email" id="email" name="email" value="<?= e($old['email']) ?>" required maxlength="100"
                               autocomplete="off" data-validate="email" pattern="[^\s@]+@[^\s@]+\.[A-Za-z]{2,}">
                        <div class="field-msg" aria-live="polite"></div>
                    </div>
                    <div class="field">
                        <label for="phone">Phone</label>
                        <input class="input" type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" required maxlength="20"
                               autocomplete="off" data-validate="phone" pattern="\+?[0-9\s\-]{7,20}">
                        <div class="field-msg" aria-live="polite"></div>
                    </div>
                    <div class="field full">
                        <label for="password"><?= $isEdit ? 'New password (optional)' : 'Password' ?></label>
                        <div class="pw">
                            <input class="input" type="password" id="password" name="password"<?= $isEdit ? ' data-optional' : ' required' ?> minlength="8" maxlength="72"
                                   autocomplete="new-password" data-validate="password">
                            <button type="button" data-toggle-pw="password">Show</button>
                        </div>
                        <div class="meter" aria-hidden="true"><i data-level="0"></i></div>
                        <ul class="rules">
                            <li data-rule="len">8 to 72 characters</li>
                            <li data-rule="upper">One uppercase letter</li>
                            <li data-rule="lower">One lowercase letter</li>
                            <li data-rule="num">One number</li>
                        </ul>
                        <div class="field-msg" aria-live="polite"></div>
                        <div class="btn-row"><button type="button" class="btn btn-ghost btn-sm" data-generate-pw>Generate a strong password</button></div>
                    </div>
                    <div class="field full">
                        <label for="confirm">Confirm password</label>
                        <input class="input" type="password" id="confirm" name="confirm"<?= $isEdit ? '' : ' required' ?> autocomplete="new-password" data-validate="confirm">
                        <div class="field-msg" aria-live="polite"></div>
                    </div>
                </div>
                <div class="btn-row">
                    <button class="btn btn-lg" type="submit"><?= $isEdit ? 'Save changes' : 'Create user' ?></button>
                    <a class="btn btn-ghost btn-lg" href="admin-users.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php page_end();
