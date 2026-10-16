<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

require_login($db);

$user = db_one($db, 'SELECT u.*, r.name AS role FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?', 'i', [current_user_id()]);
$role = $user['role'];

$myGigs = $myProposals = [];
if ($role === 'client') {
    $myGigs = db_all($db, "SELECT g.id, g.title, g.budget, g.status,
            (SELECT COUNT(*) FROM proposals p WHERE p.gig_id = g.id AND p.is_deleted = 0) AS proposal_count
        FROM gigs g WHERE g.client_id = ? AND g.is_deleted = 0 ORDER BY g.created_at DESC LIMIT 10", 'i', [(int)$user['id']]);
} elseif ($role === 'freelancer') {
    $myProposals = db_all($db, "SELECT p.bid_amount, p.status, p.created_at, g.id AS gig_id, g.title
        FROM proposals p JOIN gigs g ON g.id = p.gig_id
        WHERE p.freelancer_id = ? AND p.is_deleted = 0 ORDER BY p.created_at DESC LIMIT 10", 'i', [(int)$user['id']]);
}

$myRating = $myReviews = null;
if ($role === 'freelancer') {
    $myRating  = freelancer_rating($db, (int)$user['id']);
    $myReviews = db_all($db, 'SELECT r.rating, r.comment, r.created_at, g.id AS gig_id, g.title, u.name AS reviewer_name
        FROM reviews r JOIN gigs g ON g.id = r.gig_id JOIN users u ON u.id = r.reviewer_id
        WHERE r.freelancer_id = ? AND r.is_deleted = 0 ORDER BY r.created_at DESC LIMIT 10', 'i', [(int)$user['id']]);
}

page_start('My profile');
?>
<div class="wide">
    <div class="panel">
        <div class="profile-head">
            <div class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
            <div>
                <h1><?= e($user['name']) ?></h1>
                <span class="badge badge-<?= e($role) ?>"><?= e($role) ?></span>
            </div>
            <a class="btn btn-ghost btn-sm" href="settings.php" style="margin-left:auto">Settings</a>
        </div>
        <div class="info">
            <div><small>Email</small><?= e($user['email']) ?></div>
            <div><small>Phone</small><?= e($user['phone'] ?? 'Not provided') ?></div>
            <div><small>Member since</small><?= e(date('M j, Y', strtotime($user['created_at']))) ?></div>
            <?php if ($role === 'freelancer'): ?>
                <div><small>Rating</small>
                    <?php if ($myRating['count'] > 0): ?>
                        <?= stars($myRating['avg']) ?> <?= number_format($myRating['avg'], 1) ?> (<?= $myRating['count'] ?>)
                    <?php else: ?>No reviews yet<?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($role === 'client'): ?>
        <div class="panel">
            <div class="section-head" style="margin-bottom:8px"><h2 style="margin:0">My gigs</h2><a class="btn btn-sm" href="create-gig.php">Post a gig</a></div>
            <?php if (!$myGigs): ?>
                <p class="muted">You have not posted a gig yet.</p>
            <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr><th>Title</th><th>Budget</th><th>Status</th><th>Proposals</th></tr></thead>
                    <tbody>
                    <?php foreach ($myGigs as $g): ?>
                        <tr>
                            <td><a class="link" href="view-gig.php?id=<?= (int)$g['id'] ?>"><?= e($g['title']) ?></a></td>
                            <td>$<?= number_format($g['budget'], 2) ?></td>
                            <td><span class="badge badge-<?= e($g['status']) ?>"><?= e(str_replace('_', ' ', $g['status'])) ?></span></td>
                            <td><?= (int)$g['proposal_count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div>
    <?php elseif ($role === 'freelancer'): ?>
        <div class="panel">
            <div class="section-head" style="margin-bottom:8px"><h2 style="margin:0">My proposals</h2><a class="btn btn-sm" href="index.php#gigs">Browse gigs</a></div>
            <?php if (!$myProposals): ?>
                <p class="muted">You have not sent a proposal yet.</p>
            <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr><th>Gig</th><th>Your bid</th><th>Status</th><th>Sent</th></tr></thead>
                    <tbody>
                    <?php foreach ($myProposals as $p): ?>
                        <tr>
                            <td><a class="link" href="view-gig.php?id=<?= (int)$p['gig_id'] ?>"><?= e($p['title']) ?></a></td>
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
            <div class="section-head" style="margin-bottom:8px"><h2 style="margin:0">My reviews</h2><a class="btn btn-ghost btn-sm" href="freelancer.php?id=<?= (int)$user['id'] ?>">View public profile</a></div>
            <?php if (!$myReviews): ?>
                <p class="muted">No reviews yet. Clients can review you after a gig you worked on is completed.</p>
            <?php endif; ?>
            <?php foreach ($myReviews as $r): ?>
                <div class="review">
                    <div class="review-top">
                        <span><?= stars($r['rating']) ?> <strong><?= (int)$r['rating'] ?>/5</strong></span>
                        <span class="hint" style="margin:0">by <?= e($r['reviewer_name']) ?> &middot; <?= e(date('M j, Y', strtotime($r['created_at']))) ?></span>
                    </div>
                    <?php if ($r['comment'] !== null && $r['comment'] !== ''): ?><p><?= e($r['comment']) ?></p><?php endif; ?>
                    <div class="hint">For <a class="link" href="view-gig.php?id=<?= (int)$r['gig_id'] ?>"><?= e($r['title']) ?></a></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="panel"><p>You are an administrator. <a class="link" href="admin-dashboard.php">Open the admin dashboard</a></p></div>
    <?php endif; ?>
</div>
<?php page_end();
