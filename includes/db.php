<?php

declare(strict_types=1);

/**
 * DevGigs - mysqli helpers. Every query is a PREPARED statement, so user input is never
 * pasted into SQL (prevents SQL injection).
 *
 * $types is one letter per "?" placeholder:  i = integer,  d = decimal number,  s = string.
 * Example: db_all($db, 'SELECT * FROM gigs WHERE client_id = ? AND status = ?', 'is', [5, 'open']);
 */

/** Prepare, bind and execute a statement. */
function db_run(mysqli $db, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if ($types !== '') 
    {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}

/** SELECT many rows. Returns a list of associative arrays. */
function db_all(mysqli $db, string $sql, string $types = '', array $params = []): array
{
    $stmt = db_run($db, $sql, $types, $params);
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/** SELECT one row. Returns an associative array, or null when nothing matched. */
function db_one(mysqli $db, string $sql, string $types = '', array $params = []): ?array
{
    $stmt = db_run($db, $sql, $types, $params);
    $row  = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** INSERT / UPDATE / DELETE. Returns the number of rows changed. */
function db_exec(mysqli $db, string $sql, string $types = '', array $params = []): int
{
    $stmt    = db_run($db, $sql, $types, $params);
    $changed = $stmt->affected_rows;
    $stmt->close();
    return $changed;
}
