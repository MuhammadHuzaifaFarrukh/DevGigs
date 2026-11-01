<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

/* Admin overview: platform numbers, the newest records, and review moderation.
   Users and gigs are managed on their own pages (admin-users.php, admin-gigs.php). */
require_login($db, 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_ok()) {
        set_flash('Your session expired. Try again.', 'error');
    } elseif ($id < 1) {
        set_flash('Invalid request.', 'error');
    } elseif ($action === 'remove_review') {
        db_exec($db, 'UPDATE reviews SET is_deleted = 1 WHERE id = ?', 'i', [$id]);
        set_flash('Review removed.');
    } elseif ($action === 'restore_review') {
        db_exec($db, 'UPDATE reviews SET is_deleted = 0 WHERE id = ?', 'i', [$id]);
        set_flash('Review restored.');
    }
    redirect('admin-dashboard.php');
}

$counts = db_one($db, "SELECT
    (SELECT COUNT(*) FROM users WHERE is_deleted = 0) AS users,
    (SELECT COUNT(*) FROM users WHERE is_deleted = 1) AS disabled_users,
    (SELECT COUNT(*) FROM gigs WHERE is_deleted = 0) AS gigs,
    (SELECT COUNT(*) FROM gigs WHERE is_deleted = 1) AS removed_gigs,
    (SELECT COUNT(*) FROM proposals WHERE is_deleted = 0) AS proposals,
    (SELECT COUNT(*) FROM reviews WHERE is_deleted = 0) AS reviews");

$newUsers = db_all($db, 'SELECT u.id, u.name, u.email, r.name AS role, u.created_at
                         FROM users u JOIN roles r ON r.id = u.role_id
                         WHERE u.is_deleted = 0 ORDER BY u.created_at DESC, u.id DESC LIMIT 5');
$newGigs  = db_all($db, 'SELECT g.id, g.title, g.budget, g.status, u.name AS client_name
                         FROM gigs g JOIN users u ON g.client_id = u.id
                         WHERE g.is_deleted = 0 ORDER BY g.created_at DESC, g.id DESC LIMIT 5');
$props    = db_all($db, 'SELECT p.id, p.bid_amount, p.status, p.created_at, g.id AS gig_id, g.title, f.name AS freelancer_name
                         FROM proposals p JOIN gigs g ON g.id = p.gig_id JOIN users f ON f.id = p.freelancer_id
                         WHERE p.is_deleted = 0 ORDER BY p.created_at DESC, p.id DESC LIMIT 8');
$reviews  = db_all($db, 'SELECT r.id, r.rating, r.comment, r.is_deleted, g.title, c.name AS reviewer_name, f.name AS freelancer_name
                         FROM reviews r
                         JOIN gigs g  ON g.id = r.gig_id
                         JOIN users c ON c.id = r.reviewer_id
                         JOIN users f ON f.id = r.freelancer_id
                         ORDER BY r.created_at DESC, r.id DESC LIMIT 20');

page_start('Admin dashboard');
?>
<div class="wide">
    <div class="page-head">
        <h1>Admin</h1>
        <a class="btn" href="admin-user-form.php">Add user</a>
    </div>
    <?php admin_tabs('dashboard'); ?>

    <div class="stats">
        <a class="stat" href="admin-users.php" style="text-decoration:none"><b><?= (int)$counts['users'] ?></b><span>Active users<?= (int)$counts['disabled_users'] ? ' (' . (int)$counts['disabled_users'] . ' disabled)' : '' ?></span></a>
        <a class="stat" href="admin-gigs.php" style="text-decoration:none"><b><?= (int)$counts['gigs'] ?></b><span>Live gigs<?= (int)$counts['removed_gigs'] ? ' (' . (int)$counts['removed_gigs'] . ' removed)' : '' ?></span></a>
        <div class="stat"><b><?= (int)$counts['proposals'] ?></b><span>Proposals</span></div>
        <div class="stat"><b><?= (int)$counts['reviews'] ?></b><span>Reviews</span></div>
    </div>

    <div class="panel">
        <div class="section-head" style="margin-bottom:8px"><h2 style="margin:0">Newest users</h2><a class="btn btn-ghost btn-sm" href="admin-users.php">Manage all users</a></div>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th></tr></thead>
            <tbody>
            <?php foreach ($newUsers as $u): ?>
                <tr>
                    <td>#<?= (int)$u['id'] ?></td>
                    <td><strong><?= e($u['name']) ?></strong></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge badge-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
                    <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>

    <div class="panel">
        <div class="section-head" style="margin-bottom:8px"><h2 style="margin:0">Newest gigs</h2><a class="btn btn-ghost btn-sm" href="admin-gigs.php">Manage all gigs</a></div>
        <?php if (!$newGigs): ?><p class="muted">No gigs on the platform.</p><?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Title</th><th>Client</th><th>Budget</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($newGigs as $g): ?>
                <tr>
                    <td>#<?= (int)$g['id'] ?></td>
                    <td><a class="link" href="view-gig.php?id=<?= (int)$g['id'] ?>"><?= e($g['title']) ?></a></td>
                    <td><?= e($g['client_name']) ?></td>
                    <td>$<?= number_format($g['budget'], 2) ?></td>
                    <td><span class="badge badge-<?= e($g['status']) ?>"><?= e(str_replace('_', ' ', $g['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Latest proposals</h2>
        <?php if (!$props): ?><p class="muted">No proposals yet.</p><?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Gig</th><th>Freelancer</th><th>Bid</th><th>Status</th><th>Sent</th></tr></thead>
            <tbody>
            <?php foreach ($props as $p): ?>
                <tr>
                    <td>#<?= (int)$p['id'] ?></td>
                    <td><a class="link" href="view-gig.php?id=<?= (int)$p['gig_id'] ?>"><?= e($p['title']) ?></a></td>
                    <td><?= e($p['freelancer_name']) ?></td>
                    <td>$<?= number_format($p['bid_amount'], 2) ?></td>
                    <td><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span></td>
                    <td><?= e(date('M j, Y', strtotime($p['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Review moderation</h2>
        <?php if (!$reviews): ?><p class="muted">No reviews on the platform yet.</p><?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Gig</th><th>Client</th><th>Freelancer</th><th>Rating</th><th>Comment</th><th class="right">Action</th></tr></thead>
            <tbody>
            <?php foreach ($reviews as $r): ?>
                <tr<?= $r['is_deleted'] ? ' class="is-off"' : '' ?>>
                    <td>#<?= (int)$r['id'] ?></td>
                    <td><?= e($r['title']) ?></td>
                    <td><?= e($r['reviewer_name']) ?></td>
                    <td><?= e($r['freelancer_name']) ?></td>
                    <td><?= stars($r['rating']) ?></td>
                    <td><?= e(mb_substr((string)$r['comment'], 0, 80)) ?><?= $r['is_deleted'] ? ' <span class="badge badge-removed">removed</span>' : '' ?></td>
                    <td class="right">
                        <form class="inline" method="post" action="admin-dashboard.php"<?= $r['is_deleted'] ? '' : ' data-confirm="Remove this review?"' ?>>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="<?= $r['is_deleted'] ? 'restore_review' : 'remove_review' ?>">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn <?= $r['is_deleted'] ? 'btn-ghost' : 'btn-danger' ?> btn-sm" type="submit"><?= $r['is_deleted'] ? 'Restore' : 'Remove' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
</div>
<?php page_end();
