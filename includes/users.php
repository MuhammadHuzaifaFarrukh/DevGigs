<?php

declare(strict_types=1);

/**
 * DevGigs - user account helpers used by the admin pages and account deletion.
 */

require_once __DIR__ . '/db.php';

/** id of a role row ('admin', 'client' or 'freelancer'), or null if it does not exist. */
function role_id_by_name(mysqli $db, string $role): ?int
{
    $row = db_one($db, 'SELECT id FROM roles WHERE name = ?', 's', [$role]);
    return $row ? (int) $row['id'] : null;
}

/** True when another account already uses this email. $exceptId lets an edit keep its own email. */
function email_in_use(mysqli $db, string $email, int $exceptId = 0): bool
{
    return db_one($db, 'SELECT id FROM users WHERE email = ? AND id <> ?', 'si', [$email, $exceptId]) !== null;
}

/** One user with the role name, or null. Includes disabled accounts. */
function find_user(mysqli $db, int $id): ?array
{
    return db_one($db, 'SELECT u.id, u.name, u.email, u.phone, u.password, u.is_deleted, u.created_at, r.name AS role
                        FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', 'i', [$id]);
}

/** How much data belongs to a user. Shown before a permanent delete so nothing is a surprise. */
function user_footprint(mysqli $db, int $userId): array
{
    $gigs = db_one($db, 'SELECT COUNT(*) AS n FROM gigs WHERE client_id = ?', 'i', [$userId]);
    $props = db_one($db, 'SELECT COUNT(*) AS n FROM proposals
                          WHERE freelancer_id = ? OR gig_id IN (SELECT id FROM gigs WHERE client_id = ?)', 'ii', [$userId, $userId]);
    $revs = db_one($db, 'SELECT COUNT(*) AS n FROM reviews
                         WHERE reviewer_id = ? OR freelancer_id = ? OR gig_id IN (SELECT id FROM gigs WHERE client_id = ?)', 'iii', [$userId, $userId, $userId]);
    return ['gigs' => (int) $gigs['n'], 'proposals' => (int) $props['n'], 'reviews' => (int) $revs['n']];
}

/**
 * PERMANENTLY delete a user and everything that points at them.
 * The foreign keys forbid deleting a parent row first, so the order is children -> parents:
 * reviews, then proposals, then the user's gigs, then the user. One transaction, so either
 * everything is removed or nothing is.
 */
function destroy_user(mysqli $db, int $userId): void
{
    $db->begin_transaction();
    try {
        db_exec($db, 'DELETE FROM reviews WHERE reviewer_id = ? OR freelancer_id = ? OR gig_id IN (SELECT id FROM gigs WHERE client_id = ?)', 'iii', [$userId, $userId, $userId]);
        db_exec($db, 'DELETE FROM proposals WHERE freelancer_id = ? OR gig_id IN (SELECT id FROM gigs WHERE client_id = ?)', 'ii', [$userId, $userId]);
        db_exec($db, 'DELETE FROM gigs WHERE client_id = ?', 'i', [$userId]);
        db_exec($db, 'DELETE FROM users WHERE id = ?', 'i', [$userId]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
