<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

/* UPDATE a review. Only the client who wrote it can change it. */
require_login($db, 'client');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    error_page(400, 'Invalid review ID.');
}

$review = db_one($db, 'SELECT r.id, r.gig_id, r.reviewer_id, r.rating, r.comment, g.title, f.name AS freelancer_name
                       FROM reviews r JOIN gigs g ON g.id = r.gig_id JOIN users f ON f.id = r.freelancer_id
                       WHERE r.id = ? AND r.is_deleted = 0', 'i', [$id]);
if (!$review) {
    error_page(404, 'Review not found.');
}
if ((int)$review['reviewer_id'] !== current_user_id()) {
    error_page(403, 'You can only edit your own reviews.');
}

$error = '';
$old   = ['rating' => (string)$review['rating'], 'comment' => (string)($review['comment'] ?? '')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['rating']  = trim($_POST['rating'] ?? '');
    $old['comment'] = trim($_POST['comment'] ?? '');

    $error = first_error([
        csrf_ok() ? null : 'Your session expired. Reload the page and try again.',
        validate_rating($old['rating']),
        $old['comment'] === '' ? null : validate_text($old['comment'], 'Comment', 1, 1000),
    ]);

    if ($error === '') {
        db_exec($db, 'UPDATE reviews SET rating = ?, comment = ? WHERE id = ? AND reviewer_id = ? AND is_deleted = 0',
            'isii', [(int)$old['rating'], $old['comment'] === '' ? null : $old['comment'], $id, current_user_id()]);
        set_flash('Review updated.');
        redirect('view-gig.php?id=' . (int)$review['gig_id']);
    }
}

page_start('Edit review');
?>
<div class="narrow">
    <div>
        <a class="back" href="view-gig.php?id=<?= (int)$review['gig_id'] ?>">&larr; Back to gig</a>
        <div class="panel">
            <h1>Edit review</h1>
            <p class="muted" style="margin:6px 0 24px">Your review of <strong><?= e($review['freelancer_name']) ?></strong> for <?= e($review['title']) ?></p>

            <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

            <form method="post" action="edit-review.php?id=<?= (int)$id ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <span class="label" id="rating-label">Rating</span>
                    <div class="star-input" role="radiogroup" aria-labelledby="rating-label">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>" required<?= $old['rating'] === (string)$i ? ' checked' : '' ?>>
                            <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i === 1 ? '' : 's' ?>">&#9733;<span class="sr-only" style="position:absolute;left:-9999px"><?= $i ?> star<?= $i === 1 ? '' : 's' ?></span></label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="field">
                    <label for="comment">Comment (optional)</label>
                    <textarea class="input" id="comment" name="comment" rows="4" maxlength="1000"><?= e($old['comment']) ?></textarea>
                </div>
                <div class="btn-row">
                    <button class="btn btn-lg" type="submit">Save review</button>
                    <a class="btn btn-ghost btn-lg" href="view-gig.php?id=<?= (int)$review['gig_id'] ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php page_end();
