<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

require_login($pdo);

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([current_user_id()]);
$user = $stmt->fetch();
$role = $user['role'];

$myGigs = $myProposals = [];
if ($role === 'client') {
    $q = $pdo->prepare("SELECT g.id, g.title, g.budget, g.status,
            (SELECT COUNT(*) FROM proposals p WHERE p.gig_id = g.id AND p.is_deleted = 0) AS proposal_count
        FROM gigs g WHERE g.client_id = ? AND g.is_deleted = 0 ORDER BY g.created_at DESC LIMIT 10");
    $q->execute([$user['id']]);
    $myGigs = $q->fetchAll();
} elseif ($role === 'freelancer') {
    $q = $pdo->prepare("SELECT p.bid_amount, p.status, p.created_at, g.id AS gig_id, g.title
        FROM proposals p JOIN gigs g ON g.id = p.gig_id
        WHERE p.freelancer_id = ? AND p.is_deleted = 0 ORDER BY p.created_at DESC LIMIT 10");
    $q->execute([$user['id']]);
    $myProposals = $q->fetchAll();
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
        </div>
        <div class="info">
            <div><small>Email</small><?= e($user['email']) ?></div>
            <div><small>Phone</small><?= e($user['phone'] ?? 'Not provided') ?></div>
            <div><small>Member since</small><?= e(date('M j, Y', strtotime($user['created_at']))) ?></div>
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
    <?php else: ?>
        <div class="panel"><p>You are an administrator. <a class="link" href="admin-dashboard.php">Open the admin dashboard</a></p></div>
    <?php endif; ?>
</div>
<?php page_end();
