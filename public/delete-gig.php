<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

/* DELETE a gig. Confirmation page first, then a POST with a CSRF token.
   It is a "soft" delete (is_deleted = 1): the row stays in the database so nothing that points
   at it breaks, and an admin can restore it. */
require_login($db, 'client');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    error_page(400, 'Invalid gig ID.');
}

$gig = db_one($db, 'SELECT id, client_id, title, status FROM gigs WHERE id = ? AND is_deleted = 0', 'i', [$id]);
if (!$gig) {
    error_page(404, 'Gig not found.');
}
if ((int)$gig['client_id'] !== current_user_id()) {
    error_page(403, 'You can only delete your own gigs.');
}
if (!in_array($gig['status'], ['open', 'cancelled'], true)) {
    set_flash('A gig that is in progress or completed cannot be deleted. Cancel it first if the work will not go ahead.', 'error');
    redirect('view-gig.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        set_flash('Your session expired. Try again.', 'error');
        redirect('delete-gig.php?id=' . $id);
    }
    db_exec($db, "UPDATE gigs SET is_deleted = 1 WHERE id = ? AND client_id = ? AND status IN ('open', 'cancelled')", 'ii', [$id, current_user_id()]);
    set_flash('Gig deleted.');
    redirect('my-gigs.php');
}

$props = db_one($db, 'SELECT COUNT(*) AS n FROM proposals WHERE gig_id = ? AND is_deleted = 0', 'i', [$id]);

page_start('Delete gig');
?>
<div class="narrow">
    <div class="panel danger-zone">
        <h1 style="font-size:1.7rem">Delete this gig?</h1>
        <p style="margin-top:12px">You are about to delete <strong><?= e($gig['title']) ?></strong>.
            <?php if ((int)$props['n'] > 0): ?>
                It has <strong><?= (int)$props['n'] ?></strong> proposal<?= (int)$props['n'] === 1 ? '' : 's' ?>, which freelancers will no longer see.
            <?php endif; ?></p>
        <form method="post" action="delete-gig.php?id=<?= (int)$id ?>">
            <?= csrf_field() ?>
            <div class="btn-row">
                <button class="btn btn-danger btn-lg" type="submit">Yes, delete gig</button>
                <a class="btn btn-ghost btn-lg" href="view-gig.php?id=<?= (int)$id ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php page_end();
