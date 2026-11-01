<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/lists.php';
require_once __DIR__ . '/../config/database.php';

/* READ: every gig the logged-in client has posted, with search, status filter and pagination. */
require_login($db, 'client');

$q      = search_term();
$status = choice('status', ['all', 'open', 'in_progress', 'completed', 'cancelled'], 'all');

$where  = 'g.client_id = ? AND g.is_deleted = 0';
$types  = 'i';
$params = [current_user_id()];
if ($status !== 'all') {
    $where .= ' AND g.status = ?';
    $types .= 's';
    $params[] = $status;
}
if ($q !== '') {
    $where .= ' AND g.title LIKE ?';
    $types .= 's';
    $params[] = like_term($q);
}

$total = (int)db_one($db, "SELECT COUNT(*) AS n FROM gigs g WHERE $where", $types, $params)['n'];
$pg    = paginate($total, 10);
$gigs  = db_all($db, "SELECT g.id, g.title, g.budget, g.status, g.created_at,
                             (SELECT COUNT(*) FROM proposals p WHERE p.gig_id = g.id AND p.is_deleted = 0) AS proposal_count
                      FROM gigs g WHERE $where ORDER BY g.created_at DESC LIMIT ? OFFSET ?",
                $types . 'ii', array_merge($params, [$pg['perPage'], $pg['offset']]));

page_start('My gigs');
?>
<div class="wide">
    <div class="page-head">
        <h1>My gigs</h1>
        <a class="btn" href="create-gig.php">Post a gig</a>
    </div>

    <div class="panel">
        <form class="filters" method="get" action="my-gigs.php" role="search">
            <input class="input grow" type="search" name="q" value="<?= e($q) ?>" placeholder="Search your gig titles" aria-label="Search" maxlength="100">
            <select class="input" name="status" aria-label="Status" data-autosubmit>
                <?php foreach (['all' => 'All statuses', 'open' => 'Open', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-ghost" type="submit">Search</button>
            <?php if ($q !== '' || $status !== 'all'): ?><a class="link" href="my-gigs.php">Clear</a><?php endif; ?>
        </form>

        <?php if (!$gigs): ?>
            <p class="muted"><?= ($q !== '' || $status !== 'all') ? 'No gigs match this search.' : 'You have not posted a gig yet.' ?></p>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Title</th><th>Budget</th><th>Status</th><th>Proposals</th><th>Posted</th><th class="right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($gigs as $g): ?>
                    <tr>
                        <td><a class="link" href="view-gig.php?id=<?= (int)$g['id'] ?>"><?= e($g['title']) ?></a></td>
                        <td>$<?= number_format($g['budget'], 2) ?></td>
                        <td><span class="badge badge-<?= e($g['status']) ?>"><?= e(str_replace('_', ' ', $g['status'])) ?></span></td>
                        <td><?= (int)$g['proposal_count'] ?></td>
                        <td><?= e(date('M j, Y', strtotime($g['created_at']))) ?></td>
                        <td class="right"><div class="row-actions">
                            <a class="btn btn-ghost btn-sm" href="view-gig.php?id=<?= (int)$g['id'] ?>">View</a>
                            <?php if ($g['status'] === 'open'): ?><a class="btn btn-ghost btn-sm" href="edit-gig.php?id=<?= (int)$g['id'] ?>">Edit</a><?php endif; ?>
                            <?php if (in_array($g['status'], ['open', 'cancelled'], true)): ?><a class="btn btn-danger btn-sm" href="delete-gig.php?id=<?= (int)$g['id'] ?>">Delete</a><?php endif; ?>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table></div>
            <?= pager($pg) ?>
        <?php endif; ?>
    </div>
</div>
<?php page_end();
