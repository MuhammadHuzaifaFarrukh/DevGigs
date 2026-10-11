<?php
/**
 * Cookie-based user preferences.
 *
 *   theme          light | dark          (also written by js/main.js)
 *   gig_sort       newest | budget_high | budget_low
 *   remember_email the email typed on the login form (never the password)
 *
 * Cookies come from the browser, so every value is checked against a whitelist
 * before use. Login state is NOT stored here. It stays in the PHP session.
 */
const THEMES    = ['light', 'dark'];
const GIG_SORTS = ['newest', 'budget_high', 'budget_low'];

function set_pref($name, $value, $days = 365, $httponly = false) {
    setcookie($name, (string)$value, [
        'expires'  => time() + 86400 * $days,
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']),
        'httponly' => $httponly,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[$name] = (string)$value;
}

/** Read a cookie. If $allowed is given, anything outside it falls back to $default. */
function pref($name, $default = '', array $allowed = []) {
    $value = $_COOKIE[$name] ?? $default;
    if ($allowed && !in_array($value, $allowed, true)) {
        return $default;
    }
    return is_string($value) ? $value : $default;
}

function forget_pref($name) {
    setcookie($name, '', ['expires' => time() - 3600, 'path' => '/']);
    unset($_COOKIE[$name]);
}

/** Saved theme, or '' when the visitor has not chosen one yet. */
function get_theme() {
    return pref('theme', '', THEMES);
}
