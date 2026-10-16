<?php

declare(strict_types=1);

/**
 * DevGigs - small helper functions used on every page.
 */

/** Escape a value for safe output inside HTML (prevents XSS). */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Send the browser to another page and stop running this script. */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit();
}

/** Star display such as ★★★★☆ (rating may be a decimal average; it is rounded for the stars). */
function stars($rating): string
{
    $n = max(0, min(5, (int) round((float) $rating)));
    return '<span class="stars" aria-label="' . e(number_format((float) $rating, 1)) . ' out of 5">'
         . str_repeat('&#9733;', $n) . '<span class="off">' . str_repeat('&#9733;', 5 - $n) . '</span></span>';
}

/** Average rating and review count for one freelancer. avg is null when there are no reviews. */
function freelancer_rating(mysqli $db, int $freelancerId): array
{
    $row = db_one($db, 'SELECT COUNT(*) AS n, AVG(rating) AS avg_rating FROM reviews WHERE freelancer_id = ? AND is_deleted = 0', 'i', [$freelancerId]);
    return ['count' => (int) ($row['n'] ?? 0), 'avg' => $row && $row['n'] ? (float) $row['avg_rating'] : null];
}

/* Fallbacks so the site still works if the mbstring extension is switched off. */
if (!function_exists('mb_strlen')) {
    function mb_strlen($string): int
    {
        return (int) preg_match_all('/./us', (string) $string);
    }
}
if (!function_exists('mb_substr')) {
    function mb_substr($string, $start, $length = null): string
    {
        $chars = preg_split('//u', (string) $string, -1, PREG_SPLIT_NO_EMPTY);
        return implode('', array_slice($chars, $start, $length));
    }
}
if (!function_exists('mb_strtoupper')) {
    function mb_strtoupper($string): string
    {
        return strtoupper((string) $string);
    }
}
