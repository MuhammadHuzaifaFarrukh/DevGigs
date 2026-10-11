<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

require_login($pdo, 'admin');

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
            $stmt = $pdo->prepare("UPDATE users SET is_deleted = 1 WHERE id = ? AND role <> 'admin'");
            $stmt->execute([$id]);
            set_flash($stmt->rowCount() ? 'User disabled.' : 'Admin accounts cannot be disabled.', $stmt->rowCount() ? 'ok' : 'error');
        }
    } elseif ($action === 'remove_gig') {
        $stmt = $pdo->prepare('UPDATE gigs SET is_deleted = 1 WHERE id = ?');
        $stmt->execute([$id]);
        set_flash('Gig removed from the marketplace.');
    }
    redirect('admin-dashboard.php');
}

$counts = $pdo->query("SELECT
    (SELECT COUNT(*) FROM users WHERE is_deleted = 0) AS users,
    (SELECT COUNT(*) FROM gigs WHERE is_deleted = 0) AS gigs,
    (SELECT COUNT(*) FROM proposals WHERE is_deleted = 0) AS proposals")->fetch();

$users = $pdo->query('SELECT id, name, email, role, created_at FROM users WHERE is_deleted = 0 ORDER BY created_at DESC LIMIT 200')->fetchAll();
$gigs  = $pdo->query('SELECT g.id, g.title, g.budget, g.status, u.name AS client_name
                      FROM gigs g JOIN users u ON g.client_id = u.id
                      WHERE g.is_deleted = 0 ORDER BY g.created_at DESC LIMIT 200')->fetchAll();

page_start('Admin dashboard');
?>
<div class="wide">
    <h1 style="font-size:2rem">Admin dashboard</h1>

    <div class="stats">
        <div class="stat"><b><?= (int)$counts['users'] ?></b><span>Active users</span></div>
        <div class="stat"><b><?= (int)$counts['gigs'] ?></b><span>Active gigs</span></div>
        <div class="stat"><b><?= (int)$counts['proposals'] ?></b><span>Proposals</span></div>
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
</div>
<?php page_end();
