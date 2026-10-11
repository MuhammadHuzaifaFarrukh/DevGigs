# DevGigs

PHP + MySQL freelance marketplace MVP.

## Folder layout (keep it exactly like this)
```
devgigs/
  config/database.php      DB connection (edit credentials here)
  includes/session.php     session, CSRF, flash messages, login guards
  includes/cookies.php     cookie preferences (theme, sort order, remembered email)
  includes/validation.php  server-side validation (email, password, phone, text, money)
  includes/layout.php      shared header, nav and footer
  public/                  point Apache / XAMPP at this folder
    *.php, css/style.css, js/main.js
  database/schema.sql      tables + indexes
```

## Setup
1. Run `database/schema.sql` (new DB) or its ALTER section (existing DB).
2. Edit `config/database.php` if your MySQL user or password differs.
3. Serve the `public/` folder, e.g. `php -S localhost:8000 -t public`.
4. Make an admin: `UPDATE users SET role='admin' WHERE email='you@example.com';`
