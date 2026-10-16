<?php

declare(strict_types=1);

/** Shared page shell: <head>, navigation, flash message, footer. */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cookies.php';

function logo_svg(): string {
    return '<svg class="brand-mark" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="7" fill="currentColor"/>'
         . '<path d="M12 9h4.5a7 7 0 0 1 0 14H12z" fill="none" style="stroke:var(--bg)" stroke-width="2.4" stroke-linejoin="round"/></svg>';
}

function page_start(string $title): void {
    $theme = get_theme();
    $role  = current_role();
    $flash = pull_flash();
    ?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | DevGigs</title>
    <script>
        /* No saved preference yet: follow the device setting once, before first paint. */
        (function () {
            var d = document.documentElement;
            if (!d.getAttribute('data-theme')) {
                d.setAttribute('data-theme', matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            }
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,600;9..144,700&family=Instrument+Sans:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="css/style.css?v=5">
    <script src="js/main.js?v=3" defer></script>
</head>
<body>
<header class="nav">
    <div class="container">
        <a class="brand" href="index.php"><?= logo_svg() ?>DevGigs</a>
        <nav class="nav-links" aria-label="Main">
            <a href="index.php#gigs">Browse gigs</a>
            <?php if ($role === 'client'): ?><a href="create-gig.php">Post a gig</a><?php endif; ?>
            <?php if ($role === 'admin'): ?><a href="admin-dashboard.php">Admin</a><?php endif; ?>
        </nav>
        <div class="nav-end">
            <button type="button" class="icon-btn" data-theme-toggle aria-label="Switch light or dark theme">
                <svg class="moon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                <svg class="sun" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </button>
            <?php if (is_logged_in()): ?>
                <a class="btn btn-ghost btn-sm nav-user" href="profile.php"><?= e($_SESSION['user_name'] ?? 'Profile') ?></a>
                <a class="btn btn-ghost btn-sm nav-settings" href="settings.php">Settings</a>
                <a class="btn btn-sm" href="logout.php">Log out</a>
            <?php else: ?>
                <a class="btn btn-ghost btn-sm" href="login.php">Log in</a>
                <a class="btn btn-sm" href="signup.php">Sign up</a>
            <?php endif; ?>
        </div>
    </div>
</header>
<main>
<?php if ($flash): ?>
    <div class="container"><div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div></div>
<?php endif; ?>
<?php
}

function page_end(): void {
    ?>
</main>
<footer class="footer">
    <div class="container">
        <span>&copy; <?= date('Y') ?> DevGigs. Freelance marketplace MVP.</span>
    </div>
</footer>
</body>
</html>
<?php
}

/** Eye-toggle password input. */
function password_input(string $id, string $name, string $autocomplete, string $label = 'Password'): void {
    ?>
    <div class="field">
        <label for="<?= e($id) ?>"><?= e($label) ?></label>
        <div class="pw">
            <input class="input" type="password" id="<?= e($id) ?>" name="<?= e($name) ?>" required autocomplete="<?= e($autocomplete) ?>">
            <button type="button" data-toggle-pw="<?= e($id) ?>">Show</button>
        </div>
    </div>
    <?php
}
