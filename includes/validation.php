<?php

declare(strict_types=1);

/**
 * Server-side validation. The browser rules in js/main.js mirror these exactly,
 * but this file is the one that counts, because the browser can be bypassed.
 * Each function returns an error message, or null when the value is valid.
 */
function validate_name(string $name): ?string 
{
    $len = mb_strlen($name);
    return ($len < 2 || $len > 100) ? 'Name must be between 2 and 100 characters.' : null;
}

function validate_email(string $email): ?string 
{
    if ($email === '') {
        return 'Email is required.';
    }
    if (mb_strlen($email) > 100) {
        return 'Email must be 100 characters or fewer.';
    }
    if (strpos($email, '@') === false) {
        return 'Email must contain an @ symbol.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^\s@]+@[^\s@]+\.[A-Za-z]{2,}$/', $email)) {
        return 'Enter a valid email, like name@example.com.';
    }
    return null;
}

function validate_phone(string $phone): ?string 
{
    return preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone)
        ? null
        : 'Phone must be 7-20 digits. A leading + and spaces or dashes are allowed.';
}

/** 8-72 characters (bcrypt ignores anything past 72 bytes), with upper, lower and a digit. */
function validate_password(string $password): ?string 
{
    $missing = [];
    if (strlen($password) < 8)           $missing[] = 'at least 8 characters';
    if (strlen($password) > 72)          $missing[] = 'no more than 72 characters';
    if (!preg_match('/[A-Z]/', $password)) $missing[] = 'an uppercase letter';
    if (!preg_match('/[a-z]/', $password)) $missing[] = 'a lowercase letter';
    if (!preg_match('/[0-9]/', $password)) $missing[] = 'a number';
    return $missing ? 'Password needs ' . implode(', ', $missing) . '.' : null;
}

function validate_text(string $value, string $label, int $min, int $max): ?string 
{
    $len = mb_strlen($value);
    if ($len < $min) return "$label must be at least $min characters.";
    if ($len > $max) return "$label must be $max characters or fewer.";
    return null;
}

/** Positive amount that fits DECIMAL(10,2), such as 250 or 249.99. */
function validate_money(string $value, string $label): ?string 
{
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $value) || (float)$value <= 0) {
        return "$label must be a positive amount up to 99,999,999.99.";
    }
    return null;
}

/** Star rating: a whole number from 1 to 5. */
function validate_rating(string $value): ?string 
{
    return preg_match('/^[1-5]$/', $value) ? null : 'Choose a rating from 1 to 5 stars.';
}

/** Return the first error from a list of checks, or '' if all passed. */
function first_error(array $checks): string 
{
    foreach ($checks as $message) 
    {
        if ($message) 
        {
            return $message;
        }
    }
    return '';
}
