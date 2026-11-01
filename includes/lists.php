<?php

declare(strict_types=1);

/**
 * DevGigs - helpers for list pages: search term and pagination.
 *
 * Pagination keeps pages fast and short. Only one page of rows is loaded from MySQL
 * (LIMIT ? OFFSET ?) instead of the whole table.
 */

/** Turn a search word into a LIKE pattern. % and _ typed by the visitor are escaped. */
function like_term(string $q): string
{
    return '%' . addcslashes($q, '%_\\') . '%';
}

/** Read and trim a search box value from ?q=. */
function search_term(): string
{
    return mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
}

/** Read a ?name= value that must be one of $allowed. Anything else becomes $default. */
function choice(string $name, array $allowed, string $default): string
{
    $value = $_GET[$name] ?? $default;
    return (is_string($value) && in_array($value, $allowed, true)) ? $value : $default;
}

/** Current page number from ?page=. Always a whole number of at least 1. */
function current_page(): int
{
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
    return ($page && $page > 0) ? $page : 1;
}

/** Work out the page, offset and page count for a list of $total rows. */
function paginate(int $total, int $perPage = 10): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min(current_page(), $pages);
    return ['page' => $page, 'pages' => $pages, 'perPage' => $perPage, 'offset' => ($page - 1) * $perPage, 'total' => $total];
}

/** "Showing 11-20 of 45" with Previous / Next and page numbers. Keeps the other query values. */
function pager(array $p, string $anchor = ''): string
{
    if ($p['total'] === 0) {
        return '';
    }
    $base = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $url  = function (int $n) use ($base, $anchor): string {
        return e($base . '?' . http_build_query(array_merge($_GET, ['page' => $n])) . $anchor);
    };
    $from = $p['offset'] + 1;
    $to   = min($p['offset'] + $p['perPage'], $p['total']);

    $html = '<div class="pager"><span>Showing ' . $from . '&ndash;' . $to . ' of ' . $p['total'] . '</span>';
    if ($p['pages'] > 1) {
        $html .= '<nav class="links" aria-label="Pages">';
        $html .= $p['page'] > 1 ? '<a href="' . $url($p['page'] - 1) . '">Previous</a>' : '<span class="disabled">Previous</span>';
        for ($n = max(1, $p['page'] - 2); $n <= min($p['pages'], $p['page'] + 2); $n++) {
            $html .= $n === $p['page'] ? '<span class="cur" aria-current="page">' . $n . '</span>' : '<a href="' . $url($n) . '">' . $n . '</a>';
        }
        $html .= $p['page'] < $p['pages'] ? '<a href="' . $url($p['page'] + 1) . '">Next</a>' : '<span class="disabled">Next</span>';
        $html .= '</nav>';
    }
    return $html . '</div>';
}
