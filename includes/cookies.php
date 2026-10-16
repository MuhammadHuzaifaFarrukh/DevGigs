<?php

declare(strict_types=1);

/**
 * DevGigs - cookies (small values stored in the visitor's BROWSER).
 *
 * Cookies set by this project (visible in DevTools > Application > Cookies):
 *   DEVGIGS_SID     the session id, created by includes/session.php (HttpOnly)
 *   theme           light | dark                           set by js/main.js
 *   gig_sort        newest | budget_high | budget_low      set by index.php
 *   remember_email  the email typed on the login form      set by login.php
 *
 * Cookies are sent by the browser, so they can be edited by the visitor. Every value is
 * checked against a whitelist before it is used. Passwords and login state are never
 * stored in a cookie.
 */

const THEMES    = ['light', 'dark'];
const GIG_SORTS = ['newest', 'budget_high', 'budget_low'];

const COOKIE_THEME    = 'theme';
const COOKIE_SORT     = 'gig_sort';
const COOKIE_EMAIL    = 'remember_email';
const COOKIE_LIFETIME = 365;   // days

/** Save a cookie in the browser and make it readable straight away in this request. */
function set_pref(string $name, string $value, int $days = COOKIE_LIFETIME, bool $httponly = false): void
{
    setcookie($name, $value, [
        'expires'  => time() + 86400 * $days,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => $httponly,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[$name] = $value;
}

/** Read a cookie. If $allowed is given, any other value falls back to $default. */
function pref(string $name, string $default = '', array $allowed = []): string
{
    $value = $_COOKIE[$name] ?? $default;
    if (!is_string($value)) {
        return $default;
    }
    if ($allowed !== [] && !in_array($value, $allowed, true)) {
        return $default;
    }
    return $value;
}

/** Delete a cookie from the browser. */
function forget_pref(string $name): void
{
    setcookie($name, '', ['expires' => time() - 3600, 'path' => '/']);
    unset($_COOKIE[$name]);
}

/** Saved theme, or '' when the visitor has not chosen one yet. */
function get_theme(): string
{
    return pref(COOKIE_THEME, '', THEMES);
}
