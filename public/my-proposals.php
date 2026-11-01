<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/lists.php';
require_once __DIR__ . '/../config/database.php';

/* READ: every proposal the logged-in freelancer has sent, with filter and pagination. */
require_login($db, 'freelancer');

$status = choice('status', ['all', 'pending', 'accepted', 'rejected'], 'all');

$where  = 'p.freelancer_id = ? AND p.is_deleted = 0 AND g.is_deleted = 0';
$types  = 'i';
$params = [current_user_id()];
if ($status !== 'all') {
    $where .= ' AND p.status = ?';
    $types .= 's';
    $params[] = $status;
}

$total = (int)db_one($db, "SELECT COUNT(*) AS n FROM proposals p JOIN gigs g ON g.id = p.gig_id WHERE $where", $types, $params)['n'];
$pg    = paginate($total, 10);
$rows  = db_all($db, "SELECT p.id, p.bid_amount, p.status, p.created_at, g.id AS gig_id, g.title, g.status AS gig_status
                      FROM proposals p JOIN gigs g ON g.id = p.gig_id
                      WHERE $where ORDER BY p.created_at DESC LIMIT ? OFFSET ?",
                $types . 'ii', array_merge($params, [$pg['perPage'], $pg['offset']]));

page_start('My proposals');
?>
<div class="wide">
    <div class="page-head">
        <h1>My proposals</h1>
        <a class="btn" href="index.php#gigs">Browse gigs</a>
    </div>

    <div class="panel">
        <form class="filters" method="get" action="my-proposals.php">
            <select class="input" name="status" aria-label="Status" data-autosubmit>
                <?php foreach (['all' => 'All statuses', 'pending' => 'Pending', 'accepted' => 'Accepted', 'rejected' => 'Rejected'] as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <noscript><button class="btn btn-ghost" type="submit">Filter</button></noscript>
            <?php if ($status !== 'all'): ?><a class="link" href="my-proposals.php">Clear</a><?php endif; ?>
        </form>

        <?php if (!$rows): ?>
            <p class="muted"><?= $status !== 'all' ? 'No proposals with that status.' : 'You have not sent a proposal yet.' ?></p>
        <?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Gig</th><th>Your bid</th><th>Status</th><th>Sent</th><th class="right">Actions</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><a class="link" href="view-gig.php?id=<?= (int)$r['gig_id'] ?>"><?= e($r['title']) ?></a></td>
                        <td>$<?= number_format($r['bid_amount'], 2) ?></td>
                        <td><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
                        <td><?= e(date('M j, Y', strtotime($r['created_at']))) ?></td>
                        <td class="right"><div class="row-actions">
                            <?php if ($r['status'] === 'pending' && $r['gig_status'] === 'open'): ?>
                                <a class="btn btn-ghost btn-sm" href="edit-proposal.php?id=<?= (int)$r['id'] ?>">Edit</a>
                            <?php endif; ?>
                            <?php if ($r['status'] === 'pending'): ?>
                                <a class="btn btn-danger btn-sm" href="delete-proposal.php?id=<?= (int)$r['id'] ?>">Withdraw</a>
                            <?php endif; ?>
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
