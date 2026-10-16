<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

require_login($db, 'admin');

/* Changes are made with POST + CSRF token. A plain link must never delete data. */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if (!csrf_ok()) {
        set_flash('Your session expired. Try again.', 'error');
    } elseif ($id < 1) {
        set_flash('Invalid request.', 'error');
    } elseif ($action === 'disable_user') {
        if ($id === current_user_id()) {
            set_flash('You cannot disable your own account.', 'error');
        } else {
            $changed = db_exec($db, "UPDATE users SET is_deleted = 1 WHERE id = ? AND role_id <> (SELECT id FROM roles WHERE name = 'admin')", 'i', [$id]);
            set_flash($changed > 0 ? 'User disabled.' : 'Admin accounts cannot be disabled.', $changed > 0 ? 'ok' : 'error');
        }
    } elseif ($action === 'remove_gig') {
        db_exec($db, 'UPDATE gigs SET is_deleted = 1 WHERE id = ?', 'i', [$id]);
        set_flash('Gig removed from the marketplace.');
    } elseif ($action === 'remove_review') {
        db_exec($db, 'UPDATE reviews SET is_deleted = 1 WHERE id = ?', 'i', [$id]);
        set_flash('Review removed.');
    }
    redirect('admin-dashboard.php');
}

$counts = db_one($db, "SELECT
    (SELECT COUNT(*) FROM users WHERE is_deleted = 0) AS users,
    (SELECT COUNT(*) FROM gigs WHERE is_deleted = 0) AS gigs,
    (SELECT COUNT(*) FROM proposals WHERE is_deleted = 0) AS proposals,
    (SELECT COUNT(*) FROM reviews WHERE is_deleted = 0) AS reviews");

$users = db_all($db, 'SELECT u.id, u.name, u.email, r.name AS role, u.created_at
                      FROM users u JOIN roles r ON r.id = u.role_id
                      WHERE u.is_deleted = 0 ORDER BY u.created_at DESC LIMIT 200');
$gigs  = db_all($db, 'SELECT g.id, g.title, g.budget, g.status, u.name AS client_name
                      FROM gigs g JOIN users u ON g.client_id = u.id
                      WHERE g.is_deleted = 0 ORDER BY g.created_at DESC LIMIT 200');

$reviews = db_all($db, 'SELECT r.id, r.rating, r.comment, r.created_at, g.title, c.name AS reviewer_name, f.name AS freelancer_name
                  FROM reviews r
                  JOIN gigs g  ON g.id = r.gig_id
                  JOIN users c ON c.id = r.reviewer_id
                  JOIN users f ON f.id = r.freelancer_id
                  WHERE r.is_deleted = 0 ORDER BY r.created_at DESC LIMIT 200');

page_start('Admin dashboard');
?>
<div class="wide">
    <h1 style="font-size:2rem">Admin dashboard</h1>

    <div class="stats">
        <div class="stat"><b><?= (int)$counts['users'] ?></b><span>Active users</span></div>
        <div class="stat"><b><?= (int)$counts['gigs'] ?></b><span>Active gigs</span></div>
        <div class="stat"><b><?= (int)$counts['proposals'] ?></b><span>Proposals</span></div>
        <div class="stat"><b><?= (int)$counts['reviews'] ?></b><span>Reviews</span></div>
    </div>

    <div class="panel">
        <h2>Users</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Joined</th><th class="right">Action</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td>#<?= (int)$u['id'] ?></td>
                    <td><strong><?= e($u['name']) ?></strong></td>
                    <td><?= e($u['email']) ?></td>
                    <td><span class="badge badge-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
                    <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
                    <td class="right">
                        <?php if ($u['role'] !== 'admin'): ?>
                            <form class="inline" method="post" action="admin-dashboard.php" data-confirm="Disable this user account?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="disable_user">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit">Disable</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">Protected</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </div>

    <div class="panel">
        <h2>Gigs</h2>
        <?php if (!$gigs): ?><p class="muted">No gigs on the platform.</p><?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Title</th><th>Client</th><th>Budget</th><th>Status</th><th class="right">Action</th></tr></thead>
            <tbody>
            <?php foreach ($gigs as $g): ?>
                <tr>
                    <td>#<?= (int)$g['id'] ?></td>
                    <td><a class="link" href="view-gig.php?id=<?= (int)$g['id'] ?>"><?= e($g['title']) ?></a></td>
                    <td><?= e($g['client_name']) ?></td>
                    <td>$<?= number_format($g['budget'], 2) ?></td>
                    <td><span class="badge badge-<?= e($g['status']) ?>"><?= e(str_replace('_', ' ', $g['status'])) ?></span></td>
                    <td class="right">
                        <form class="inline" method="post" action="admin-dashboard.php" data-confirm="Remove this gig from the marketplace?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_gig">
                            <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Reviews</h2>
        <?php if (!$reviews): ?><p class="muted">No reviews on the platform yet.</p><?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Gig</th><th>Client</th><th>Freelancer</th><th>Rating</th><th>Comment</th><th class="right">Action</th></tr></thead>
            <tbody>
            <?php foreach ($reviews as $r): ?>
                <tr>
                    <td>#<?= (int)$r['id'] ?></td>
                    <td><?= e($r['title']) ?></td>
                    <td><?= e($r['reviewer_name']) ?></td>
                    <td><?= e($r['freelancer_name']) ?></td>
                    <td><?= stars($r['rating']) ?></td>
                    <td><?= e(mb_substr((string)$r['comment'], 0, 80)) ?></td>
                    <td class="right">
                        <form class="inline" method="post" action="admin-dashboard.php" data-confirm="Remove this review?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_review">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-danger btn-sm" type="submit">Remove</button>
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
