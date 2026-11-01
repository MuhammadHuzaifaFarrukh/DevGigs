<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

/* DELETE a review. Only the client who wrote it. A real DELETE, so the client may write a new one
   (the table allows one review per gig). Admins moderate with a soft delete instead. */
require_login($db, 'client');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    error_page(400, 'Invalid review ID.');
}

$review = db_one($db, 'SELECT r.id, r.gig_id, r.reviewer_id, r.rating, g.title
                       FROM reviews r JOIN gigs g ON g.id = r.gig_id
                       WHERE r.id = ? AND r.is_deleted = 0', 'i', [$id]);
if (!$review) {
    error_page(404, 'Review not found.');
}
if ((int)$review['reviewer_id'] !== current_user_id()) {
    error_page(403, 'You can only delete your own reviews.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        set_flash('Your session expired. Try again.', 'error');
        redirect('delete-review.php?id=' . $id);
    }
    db_exec($db, 'DELETE FROM reviews WHERE id = ? AND reviewer_id = ?', 'ii', [$id, current_user_id()]);
    set_flash('Review deleted. You can write a new one.');
    redirect('view-gig.php?id=' . (int)$review['gig_id']);
}

page_start('Delete review');
?>
<div class="narrow">
    <div class="panel danger-zone">
        <h1 style="font-size:1.7rem">Delete this review?</h1>
        <p style="margin-top:12px">Your <?= stars($review['rating']) ?> review for <strong><?= e($review['title']) ?></strong> will be removed from the freelancer's profile.</p>
        <form method="post" action="delete-review.php?id=<?= (int)$id ?>">
            <?= csrf_field() ?>
            <div class="btn-row">
                <button class="btn btn-danger btn-lg" type="submit">Yes, delete review</button>
                <a class="btn btn-ghost btn-lg" href="view-gig.php?id=<?= (int)$review['gig_id'] ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php page_end();
