<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

if (is_logged_in()) {
    redirect('profile.php');
}

$error = '';
$old   = ['name' => '', 'email' => '', 'phone' => '', 'role' => 'freelancer'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name']  = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $old['role']  = $_POST['role'] ?? '';
    $password     = $_POST['password'] ?? '';
    $confirm      = $_POST['confirm'] ?? '';

    $error = first_error([
        csrf_ok() ? null : 'Your session expired. Reload the page and try again.',
        validate_name($old['name']),
        validate_email($old['email']),
        validate_phone($old['phone']),
        validate_password($password),
        $password === $confirm ? null : 'Passwords do not match.',
        // Only these two roles can be chosen. Admins are created directly in the database.
        in_array($old['role'], ['client', 'freelancer'], true) ? null : 'Choose client or freelancer.',
    ]);

    if ($error === '') {
        try {
            if (db_one($db, 'SELECT id FROM users WHERE email = ?', 's', [$old['email']])) {
                $error = 'That email is already registered. Try logging in instead.';
            } else {
                db_exec($db, 'INSERT INTO users (name, email, phone, password, role_id) VALUES (?, ?, ?, ?, (SELECT id FROM roles WHERE name = ?))', 'sssss', [$old['name'], $old['email'], $old['phone'], password_hash($password, PASSWORD_DEFAULT), $old['role']]);
                set_flash('Account created. Log in to continue.');
                redirect('login.php');
            }
        } catch (\mysqli_sql_exception $ex) {
            error_log('Signup failed: ' . $ex->getMessage());
            $error = ((int)$ex->getCode() === 1062)
                ? 'That email is already registered. Try logging in instead.'
                : 'Something went wrong while creating your account. Please try again.';
        }
    }
}

page_start('Sign up');
?>
<div class="auth">
    <div class="panel">
        <h1>Create an account</h1>
        <p class="sub">Join DevGigs to hire or get hired.</p>

        <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

        <form method="post" action="signup.php">
            <?= csrf_field() ?>
            <div class="field">
                <label for="name">Full name</label>
                <input class="input" type="text" id="name" name="name" value="<?= e($old['name']) ?>" required minlength="2" maxlength="100" autocomplete="name">
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input class="input" type="email" id="email" name="email" value="<?= e($old['email']) ?>" required maxlength="100"
                       autocomplete="email" data-validate="email" pattern="[^\s@]+@[^\s@]+\.[A-Za-z]{2,}">
                <div class="field-msg" aria-live="polite"></div>
            </div>
            <div class="field">
                <label for="phone">Phone</label>
                <input class="input" type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>" required maxlength="20"
                       autocomplete="tel" data-validate="phone" pattern="\+?[0-9\s\-]{7,20}">
                <div class="field-msg" aria-live="polite"></div>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <div class="pw">
                    <input class="input" type="password" id="password" name="password" required minlength="8" maxlength="72"
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
            </div>
            <div class="field">
                <label for="confirm">Confirm password</label>
                <input class="input" type="password" id="confirm" name="confirm" required autocomplete="new-password" data-validate="confirm">
                <div class="field-msg" aria-live="polite"></div>
            </div>
            <div class="field">
                <span class="label">I want to join as</span>
                <div class="seg">
                    <label><input type="radio" name="role" value="freelancer"<?= $old['role'] !== 'client' ? ' checked' : '' ?>>
                        <span class="opt"><b>Freelancer</b><span>Browse gigs and send proposals</span></span></label>
                    <label><input type="radio" name="role" value="client"<?= $old['role'] === 'client' ? ' checked' : '' ?>>
                        <span class="opt"><b>Client</b><span>Post gigs and review proposals</span></span></label>
                </div>
            </div>
            <button class="btn btn-block btn-lg" type="submit">Create account</button>
        </form>
        <p class="alt">Already have an account? <a class="link" href="login.php">Log in</a></p>
    </div>
</div>
<?php page_end();
