<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

// Ends the session and deletes the session cookie.
// Preference cookies (theme, sort order) are kept on purpose.
logout_user();
redirect('login.php');
