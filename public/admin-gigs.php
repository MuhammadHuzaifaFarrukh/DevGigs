<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/lists.php';
require_once __DIR__ . '/../config/database.php';

/* READ every gig, plus remove / restore. Admins moderate gigs; they do not create or edit them
   (clients do that from their own accounts). Removing is a soft delete, so it can be undone. */
require_login($db, 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');

    if (!csrf_ok()) {
        set_flash('Your session expired. Try again.', 'error');
    } elseif ($id < 1) {
        set_flash('Invalid request.', 'error');
    } elseif ($action === 'remove_gig') {
        db_exec($db, 'UPDATE gigs SET is_deleted = 1 WHERE id = ?', 'i', [$id]);
        set_flash('Gig removed from the marketplace. You can restore it from the Removed filter.');
    } elseif ($action === 'restore_gig') {
        db_exec($db, 'UPDATE gigs SET is_deleted = 0 WHERE id = ?', 'i', [$id]);
        set_flash('Gig restored.');
    }
    redirect('admin-gigs.php' . (isset($_POST['back']) && preg_match('/^[a-z0-9_=&%.+-]*$/i', (string)$_POST['back']) && $_POST['back'] !== '' ? '?' . $_POST['back'] : ''));
}

$q      = search_term();
$status = choice('status', ['all', 'open', 'in_progress', 'completed', 'cancelled'], 'all');
$state  = choice('state', ['active', 'removed', 'all'], 'active');

$where  = '1 = 1';
$types  = '';
$params = [];
if ($state === 'active')  { $where .= ' AND g.is_deleted = 0'; }
if ($state === 'removed') { $where .= ' AND g.is_deleted = 1'; }
if ($status !== 'all')    { $where .= ' AND g.status = ?'; $types .= 's'; $params[] = $status; }
if ($q !== '')            { $where .= ' AND (g.title LIKE ? OR u.name LIKE ?)'; $types .= 'ss'; $like = like_term($q); array_push($params, $like, $like); }

$total = (int)db_one($db, "SELECT COUNT(*) AS n FROM gigs g JOIN users u ON u.id = g.client_id WHERE $where", $types, $params)['n'];
$pg    = paginate($total, 15);
$gigs  = db_all($db, "SELECT g.id, g.title, g.budget, g.status, g.is_deleted, g.created_at, u.name AS client_name,
                             (SELECT COUNT(*) FROM proposals p WHERE p.gig_id = g.id AND p.is_deleted = 0) AS proposal_count
                      FROM gigs g JOIN users u ON u.id = g.client_id
                      WHERE $where ORDER BY g.created_at DESC, g.id DESC LIMIT ? OFFSET ?",
                $types . 'ii', array_merge($params, [$pg['perPage'], $pg['offset']]));

$backQuery = http_build_query(array_filter(['q' => $q, 'status' => $status !== 'all' ? $status : '', 'state' => $state !== 'active' ? $state : '', 'page' => $pg['page'] > 1 ? $pg['page'] : '']));

page_start('Manage gigs');
?>
<div class="wide">
    <div class="page-head"><h1>Admin</h1></div>
    <?php admin_tabs('gigs'); ?>

    <div class="panel">
        <form class="filters" method="get" action="admin-gigs.php" role="search">
            <input class="input grow" type="search" name="q" value="<?= e($q) ?>" placeholder="Search gig title or client" aria-label="Search gigs" maxlength="100">
            <select class="input" name="status" aria-label="Gig status" data-autosubmit>
                <?php foreach (['all' => 'All statuses', 'open' => 'Open', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="input" name="state" aria-label="Removed" data-autosubmit>
                <?php foreach (['active' => 'Live gigs', 'removed' => 'Removed', 'all' => 'Live and removed'] as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= $state === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-ghost" type="submit">Search</button>
            <?php if ($q !== '' || $status !== 'all' || $state !== 'active'): ?><a class="link" href="admin-gigs.php">Clear</a><?php endif; ?>
        </form>

        <?php if (!$gigs): ?>
            <p class="muted">No gigs match this search.</p>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Title</th><th>Client</th><th>Budget</th><th>Status</th><th>Proposals</th><th>Posted</th><th class="right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($gigs as $g): ?>
                <tr<?= $g['is_deleted'] ? ' class="is-off"' : '' ?>>
                    <td>#<?= (int)$g['id'] ?></td>
                    <td><a class="link" href="view-gig.php?id=<?= (int)$g['id'] ?>"><?= e($g['title']) ?></a></td>
                    <td><?= e($g['client_name']) ?></td>
                    <td>$<?= number_format($g['budget'], 2) ?></td>
                    <td><span class="badge badge-<?= e($g['status']) ?>"><?= e(str_replace('_', ' ', $g['status'])) ?></span>
                        <?= $g['is_deleted'] ? '<span class="badge badge-removed">removed</span>' : '' ?></td>
                    <td><?= (int)$g['proposal_count'] ?></td>
                    <td><?= e(date('M j, Y', strtotime($g['created_at']))) ?></td>
                    <td class="right">
                        <form class="inline" method="post" action="admin-gigs.php"<?= $g['is_deleted'] ? '' : ' data-confirm="Remove this gig from the marketplace?"' ?>>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="<?= $g['is_deleted'] ? 'restore_gig' : 'remove_gig' ?>">
                            <input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
                            <input type="hidden" name="back" value="<?= e($backQuery) ?>">
                            <button class="btn <?= $g['is_deleted'] ? 'btn-ghost' : 'btn-danger' ?> btn-sm" type="submit"><?= $g['is_deleted'] ? 'Restore' : 'Remove' ?></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?= pager($pg) ?>
        <?php endif; ?>
    </div>
</div>
<?php page_end();
