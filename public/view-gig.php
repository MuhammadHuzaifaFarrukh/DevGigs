<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

$gig_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$gig_id || $gig_id < 1) {
    redirect('index.php');
}

$gig = db_one($db, 'SELECT g.*, u.name AS client_name FROM gigs g JOIN users u ON g.client_id = u.id WHERE g.id = ? AND g.is_deleted = 0', 'i', [$gig_id]);

if (!$gig) {
    http_response_code(404);
    page_start('Gig not found');
    echo '<div class="narrow"><div class="panel empty">This gig does not exist or was removed. <a class="link" href="index.php">Back to gigs</a></div></div>';
    page_end();
    exit();
}

$error = '';
$old   = ['cover_letter' => '', 'bid_amount' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_logged_in()) {
        redirect('login.php');
    }
    require_login($db);   // re-checks the account and refreshes the role from the database

    $action = (string)($_POST['action'] ?? 'propose');
    $owns   = current_user_id() === (int)$gig['client_id'];

    if ($action === 'accept_proposal' || $action === 'complete_gig') {
        /* Client-only steps that lead to a review: hire someone, then close the gig. */
        $pid = (int)($_POST['proposal_id'] ?? 0);
        if (!csrf_ok()) {
            set_flash('Your session expired. Reload the page and try again.', 'error');
        } elseif (!$owns) {
            set_flash('Only the client who posted this gig can do that.', 'error');
        } elseif ($action === 'accept_proposal') {
            $db->begin_transaction();
            try {
                $p = db_one($db, "SELECT id FROM proposals WHERE id = ? AND gig_id = ? AND status = 'pending' AND is_deleted = 0 FOR UPDATE", 'ii', [$pid, $gig_id]);
                $g = db_one($db, "SELECT id FROM gigs WHERE id = ? AND status = 'open' AND is_deleted = 0 FOR UPDATE", 'i', [$gig_id]);
                if (!$p || !$g) {
                    $db->rollback();
                    set_flash('That proposal can no longer be accepted.', 'error');
                } else {
                    db_exec($db, "UPDATE proposals SET status = 'accepted' WHERE id = ?", 'i', [$pid]);
                    db_exec($db, "UPDATE proposals SET status = 'rejected' WHERE gig_id = ? AND id <> ? AND status = 'pending'", 'ii', [$gig_id, $pid]);
                    db_exec($db, "UPDATE gigs SET status = 'in_progress' WHERE id = ?", 'i', [$gig_id]);
                    $db->commit();
                    set_flash('Proposal accepted. The gig is now in progress.');
                }
            } catch (\mysqli_sql_exception $ex) {
                $db->rollback();
                error_log('Accept failed: ' . $ex->getMessage());
                set_flash('We could not accept that proposal. Please try again.', 'error');
            }
        } else { // complete_gig
            $changed = db_exec($db, "UPDATE gigs SET status = 'completed' WHERE id = ? AND status = 'in_progress' AND is_deleted = 0", 'i', [$gig_id]);
            set_flash($changed > 0 ? 'Gig marked as completed. You can now review the freelancer.' : 'Only a gig in progress can be completed.', $changed > 0 ? 'ok' : 'error');
        }
        redirect('view-gig.php?id=' . $gig_id);
    }

    if ($action === 'submit_review') {
        $rating  = trim($_POST['rating'] ?? '');
        $comment = trim($_POST['comment'] ?? '');
        $hired   = db_one($db, "SELECT freelancer_id FROM proposals WHERE gig_id = ? AND status = 'accepted' AND is_deleted = 0", 'i', [$gig_id]);
        $rerror  = first_error([
            csrf_ok() ? null : 'Your session expired. Reload the page and try again.',
            $owns ? null : 'Only the client who posted this gig can review the freelancer.',
            $gig['status'] === 'completed' ? null : 'You can review only after the gig is completed.',
            $hired ? null : 'This gig has no accepted freelancer to review.',
            validate_rating($rating),
            $comment === '' ? null : validate_text($comment, 'Comment', 1, 1000),
        ]);
        if ($rerror === '') {
            try {
                db_exec($db, 'INSERT INTO reviews (gig_id, reviewer_id, freelancer_id, rating, comment) VALUES (?, ?, ?, ?, ?)',
                    'iiiis', [$gig_id, current_user_id(), (int)$hired['freelancer_id'], (int)$rating, $comment === '' ? null : $comment]);
                set_flash('Thank you. Your review was posted.');
            } catch (\mysqli_sql_exception $ex) {
                error_log('Review failed: ' . $ex->getMessage());
                set_flash((int)$ex->getCode() === 1062 ? 'You have already reviewed this gig.' : 'We could not save your review. Please try again.', 'error');
            }
        } else {
            set_flash($rerror, 'error');
        }
        redirect('view-gig.php?id=' . $gig_id);
    }

    $old['cover_letter'] = trim($_POST['cover_letter'] ?? '');
    $old['bid_amount']   = trim($_POST['bid_amount'] ?? '');

    $error = first_error([
        csrf_ok() ? null : 'Your session expired. Reload the page and try again.',
        current_role() === 'freelancer' ? null : 'Only freelancers can submit proposals.',
        $gig['status'] === 'open' ? null : 'This gig is no longer open for proposals.',
        validate_text($old['cover_letter'], 'Cover letter', 20, 3000),
        validate_money($old['bid_amount'], 'Bid amount'),
    ]);

    if ($error === '') {
        try {
            if (db_one($db, 'SELECT id FROM proposals WHERE gig_id = ? AND freelancer_id = ?', 'ii', [$gig_id, current_user_id()])) {
                $error = 'You have already sent a proposal for this gig.';
            } else {
                db_exec($db, "INSERT INTO proposals (gig_id, freelancer_id, cover_letter, bid_amount, status) VALUES (?, ?, ?, ?, 'pending')", 'iiss', [$gig_id, current_user_id(), $old['cover_letter'], $old['bid_amount']]);
                set_flash('Proposal sent. The client can now see it.');
                redirect('view-gig.php?id=' . $gig_id);
            }
        } catch (\mysqli_sql_exception $ex) {
            error_log('Proposal failed: ' . $ex->getMessage());
            $error = ((int)$ex->getCode() === 1062)
                ? 'You have already sent a proposal for this gig.'
                : 'We could not save your proposal. Please try again.';
        }
    }
}

$role     = current_role();
$is_owner = is_logged_in() && current_user_id() === (int)$gig['client_id'];

$mine = null;
if ($role === 'freelancer') {
    $mine = db_one($db, 'SELECT bid_amount, status, created_at FROM proposals WHERE gig_id = ? AND freelancer_id = ? AND is_deleted = 0', 'ii', [$gig_id, current_user_id()]);
}

$proposals = [];
if ($is_owner) {
    $proposals = db_all($db, 'SELECT p.*, u.name AS freelancer_name, u.email AS freelancer_email,
                            (SELECT AVG(r.rating) FROM reviews r WHERE r.freelancer_id = p.freelancer_id AND r.is_deleted = 0) AS avg_rating,
                            (SELECT COUNT(*) FROM reviews r WHERE r.freelancer_id = p.freelancer_id AND r.is_deleted = 0) AS review_count
                        FROM proposals p JOIN users u ON p.freelancer_id = u.id
                        WHERE p.gig_id = ? AND p.is_deleted = 0 ORDER BY p.created_at DESC', 'i', [$gig_id]);
}

/* Who was hired, and the review (if any) for this gig. */
$hired = db_one($db, "SELECT p.freelancer_id, u.name FROM proposals p JOIN users u ON u.id = p.freelancer_id
                      WHERE p.gig_id = ? AND p.status = 'accepted' AND p.is_deleted = 0", 'i', [$gig_id]);
$review = db_one($db, 'SELECT r.rating, r.comment, r.created_at, u.name AS reviewer_name
                       FROM reviews r JOIN users u ON u.id = r.reviewer_id
                       WHERE r.gig_id = ? AND r.is_deleted = 0', 'i', [$gig_id]);

page_start($gig['title']);
?>
<div class="narrow">
    <div>
        <a class="back" href="index.php#gigs">&larr; Back to gigs</a>
        <div class="panel">
            <div class="detail-top">
                <span class="badge badge-<?= e($gig['status']) ?>"><?= e(str_replace('_', ' ', $gig['status'])) ?></span>
                <span class="price">$<?= number_format($gig['budget'], 2) ?></span>
            </div>
            <h1><?= e($gig['title']) ?></h1>
            <p class="prose"><?= e($gig['description']) ?></p>
            <div class="foot-meta">Posted by <strong><?= e($gig['client_name']) ?></strong> on <?= e(date('M j, Y', strtotime($gig['created_at']))) ?></div>
        </div>
    </div>

    <?php if ($error): ?><div class="alert alert-error" role="alert" style="margin:0"><?= e($error) ?></div><?php endif; ?>

    <?php if ($role === 'freelancer' && $mine): ?>
        <div class="panel">
            <h2>Your proposal</h2>
            <p>You bid <strong>$<?= number_format($mine['bid_amount'], 2) ?></strong> on <?= e(date('M j, Y', strtotime($mine['created_at']))) ?>.
               Status: <span class="badge badge-<?= e($mine['status']) ?>"><?= e($mine['status']) ?></span></p>
        </div>
    <?php elseif ($role === 'freelancer' && $gig['status'] === 'open'): ?>
        <div class="panel">
            <h2>Send a proposal</h2>
            <form method="post" action="view-gig.php?id=<?= (int)$gig_id ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="cover_letter">Cover letter</label>
                    <textarea class="input" id="cover_letter" name="cover_letter" rows="5" required minlength="20" maxlength="3000" placeholder="Why you are a good fit for this project."><?= e($old['cover_letter']) ?></textarea>
                    <div class="hint">At least 20 characters.</div>
                </div>
                <div class="field">
                    <label for="bid_amount">Your bid (USD)</label>
                    <input class="input" type="number" id="bid_amount" name="bid_amount" value="<?= e($old['bid_amount']) ?>" required min="0.01" max="99999999.99" step="0.01">
                </div>
                <button class="btn btn-lg" type="submit">Send proposal</button>
            </form>
        </div>
    <?php elseif (!is_logged_in()): ?>
        <div class="panel" style="text-align:center">
            <p class="muted" style="margin-bottom:16px">Log in as a freelancer to send a proposal for this gig.</p>
            <a class="btn" href="login.php">Log in</a>
        </div>
    <?php elseif ($role === 'client' && !$is_owner): ?>
        <div class="panel"><p class="muted">Only freelancers can send proposals. You are signed in as a client.</p></div>
    <?php endif; ?>

    <?php if ($is_owner): ?>
        <div class="panel">
            <h2>Proposals received (<?= count($proposals) ?>)</h2>
            <?php if (!$proposals): ?>
                <p class="muted">No proposals yet. They will appear here as freelancers bid.</p>
            <?php endif; ?>
            <?php foreach ($proposals as $p): ?>
                <div class="proposal">
                    <div class="proposal-top">
                        <span><strong><a class="link" href="freelancer.php?id=<?= (int)$p['freelancer_id'] ?>"><?= e($p['freelancer_name']) ?></a></strong>
                            <span class="muted"> &middot; <a href="mailto:<?= e($p['freelancer_email']) ?>"><?= e($p['freelancer_email']) ?></a></span>
                            <span class="rating-line">
                            <?php if ((int)$p['review_count'] > 0): ?>
                                <?= stars($p['avg_rating']) ?> <?= number_format((float)$p['avg_rating'], 1) ?> (<?= (int)$p['review_count'] ?> review<?= (int)$p['review_count'] === 1 ? '' : 's' ?>)
                            <?php else: ?>
                                No reviews yet
                            <?php endif; ?>
                            </span></span>
                        <span><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span>
                            <strong class="price" style="font-size:1.2rem;margin-left:8px">$<?= number_format($p['bid_amount'], 2) ?></strong></span>
                    </div>
                    <p><?= e($p['cover_letter']) ?></p>
                    <div class="hint">Sent <?= e(date('M j, Y', strtotime($p['created_at']))) ?></div>
                    <?php if ($gig['status'] === 'open' && $p['status'] === 'pending'): ?>
                        <form class="actions" method="post" action="view-gig.php?id=<?= (int)$gig_id ?>" data-confirm="Accept this proposal? Other pending proposals will be rejected.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="accept_proposal">
                            <input type="hidden" name="proposal_id" value="<?= (int)$p['id'] ?>">
                            <button class="btn btn-sm" type="submit">Accept proposal</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($is_owner && $gig['status'] === 'in_progress' && $hired): ?>
        <div class="panel">
            <h2>Work in progress</h2>
            <p class="muted">Hired: <strong><?= e($hired['name']) ?></strong>. When the work is delivered, mark the gig as completed to leave a review.</p>
            <form class="actions" method="post" action="view-gig.php?id=<?= (int)$gig_id ?>" data-confirm="Mark this gig as completed?">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="complete_gig">
                <button class="btn" type="submit">Mark as completed</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($review): ?>
        <div class="panel">
            <h2>Review</h2>
            <div class="review">
                <div class="review-top">
                    <span><?= stars($review['rating']) ?> <strong><?= (int)$review['rating'] ?>/5</strong>
                        <?php if ($hired): ?><span class="muted"> for <a class="link" href="freelancer.php?id=<?= (int)$hired['freelancer_id'] ?>"><?= e($hired['name']) ?></a></span><?php endif; ?></span>
                    <span class="hint" style="margin:0">by <?= e($review['reviewer_name']) ?> &middot; <?= e(date('M j, Y', strtotime($review['created_at']))) ?></span>
                </div>
                <?php if ($review['comment'] !== null && $review['comment'] !== ''): ?><p><?= e($review['comment']) ?></p><?php endif; ?>
            </div>
        </div>
    <?php elseif ($is_owner && $gig['status'] === 'completed' && $hired): ?>
        <div class="panel">
            <h2>Review <?= e($hired['name']) ?></h2>
            <form method="post" action="view-gig.php?id=<?= (int)$gig_id ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="submit_review">
                <div class="field">
                    <span class="label" id="rating-label">Rating</span>
                    <div class="star-input" role="radiogroup" aria-labelledby="rating-label">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" required>
                            <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i === 1 ? '' : 's' ?>">&#9733;<span class="sr-only" style="position:absolute;left:-9999px"><?= $i ?> star<?= $i === 1 ? '' : 's' ?></span></label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="field">
                    <label for="comment">Comment (optional)</label>
                    <textarea class="input" id="comment" name="comment" rows="4" maxlength="1000" placeholder="How was it working with this freelancer?"></textarea>
                </div>
                <button class="btn btn-lg" type="submit">Post review</button>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php page_end();
