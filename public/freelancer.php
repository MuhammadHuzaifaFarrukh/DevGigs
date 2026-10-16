<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

/* Public freelancer profile: name, average rating and every review received. */
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$freelancer = ($id && $id > 0)
    ? db_one($db, "SELECT u.id, u.name, u.created_at FROM users u JOIN roles r ON r.id = u.role_id
                WHERE u.id = ? AND r.name = 'freelancer' AND u.is_deleted = 0", 'i', [$id])
    : null;

if (!$freelancer) {
    http_response_code(404);
    page_start('Freelancer not found');
    echo '<div class="narrow"><div class="panel empty">This freelancer does not exist. <a class="link" href="index.php">Back to gigs</a></div></div>';
    page_end();
    exit();
}

$rating  = freelancer_rating($db, (int)$freelancer['id']);
$reviews = db_all($db, 'SELECT r.rating, r.comment, r.created_at, g.id AS gig_id, g.title, u.name AS reviewer_name
                        FROM reviews r
                        JOIN gigs g  ON g.id = r.gig_id
                        JOIN users u ON u.id = r.reviewer_id
                        WHERE r.freelancer_id = ? AND r.is_deleted = 0
                        ORDER BY r.created_at DESC LIMIT 50', 'i', [(int)$freelancer['id']]);
$done = db_one($db, "SELECT COUNT(*) AS n FROM proposals p JOIN gigs g ON g.id = p.gig_id
                     WHERE p.freelancer_id = ? AND p.status = 'accepted' AND g.status = 'completed' AND p.is_deleted = 0", 'i', [(int)$freelancer['id']]);

page_start($freelancer['name']);
?>
<div class="narrow">
    <div>
        <a class="back" href="index.php#gigs">&larr; Back to gigs</a>
        <div class="panel">
            <div class="profile-head" style="margin-bottom:0">
                <div class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($freelancer['name'], 0, 1))) ?></div>
                <div>
                    <h1><?= e($freelancer['name']) ?></h1>
                    <span class="badge badge-freelancer">freelancer</span>
                    <div class="rating-line" style="margin-top:8px">
                        <?php if ($rating['count'] > 0): ?>
                            <?= stars($rating['avg']) ?> <strong><?= number_format($rating['avg'], 1) ?></strong>
                            (<?= $rating['count'] ?> review<?= $rating['count'] === 1 ? '' : 's' ?>)
                        <?php else: ?>
                            No reviews yet
                        <?php endif; ?>
                        &middot; <?= (int)$done['n'] ?> completed gig<?= (int)$done['n'] === 1 ? '' : 's' ?>
                        &middot; Member since <?= e(date('M Y', strtotime($freelancer['created_at']))) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <h2>Reviews (<?= $rating['count'] ?>)</h2>
        <?php if (!$reviews): ?><p class="muted">No client has reviewed this freelancer yet.</p><?php endif; ?>
        <?php foreach ($reviews as $r): ?>
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
</div>
<?php page_end();
