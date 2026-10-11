<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

if (is_logged_in()) {
    redirect('profile.php');
}

/* Prefill the email saved by the "remember my email" cookie, if it is still valid. */
$saved = pref('remember_email', '');
$email = validate_email($saved) ? '' : $saved;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $error = first_error([
        csrf_ok() ? null : 'Your session expired. Reload the page and try again.',
        validate_email($email),
        $password === '' ? 'Password is required.' : null,
    ]);

    if ($error === '') {
        $stmt = $pdo->prepare('SELECT id, name, email, password, role FROM users WHERE email = ? AND is_deleted = 0');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);   // new session id after login prevents session fixation
            $_SESSION['user_id']    = (int)$user['id'];
            $_SESSION['user_name']  = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role']  = $user['role'];

            if (!empty($_POST['remember'])) {
                set_pref('remember_email', $user['email'], 30, true);
            } else {
                forget_pref('remember_email');
            }
            redirect('profile.php');
        }
        $error = 'Wrong email or password.';
    }
}

page_start('Log in');
?>
<div class="auth">
    <div class="panel">
        <h1>Welcome back</h1>
        <p class="sub">Log in to manage your gigs and proposals.</p>

        <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

        <form method="post" action="login.php">
            <?= csrf_field() ?>
            <div class="field">
                <label for="email">Email</label>
                <input class="input" type="email" id="email" name="email" value="<?= e($email) ?>" required maxlength="100"
                       autocomplete="email" data-validate="email" pattern="[^\s@]+@[^\s@]+\.[A-Za-z]{2,}">
                <div class="field-msg" aria-live="polite"></div>
            </div>
            <?php password_input('password', 'password', 'current-password'); ?>
            <label class="check"><input type="checkbox" name="remember" value="1"<?= $email !== '' && $saved === $email ? ' checked' : '' ?>> Remember my email on this device</label>
            <button class="btn btn-block btn-lg" type="submit">Log in</button>
        </form>
        <p class="alt">New here? <a class="link" href="signup.php">Create an account</a></p>
    </div>
</div>
<?php page_end();
