<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

/* DELETE (withdraw) a proposal. Only its freelancer, and only while it is pending.
   This is a real DELETE, not a soft delete: nothing else points at a pending proposal, and the
   one-bid-per-gig rule means a soft-deleted row would stop the freelancer bidding again. */
require_login($db, 'freelancer');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    error_page(400, 'Invalid proposal ID.');
}

$prop = db_one($db, 'SELECT p.id, p.freelancer_id, p.status, p.bid_amount, g.title
                     FROM proposals p JOIN gigs g ON g.id = p.gig_id
                     WHERE p.id = ? AND p.is_deleted = 0', 'i', [$id]);
if (!$prop) {
    error_page(404, 'Proposal not found.');
}
if ((int)$prop['freelancer_id'] !== current_user_id()) {
    error_page(403, 'You can only withdraw your own proposals.');
}
if ($prop['status'] !== 'pending') {
    set_flash('Only a pending proposal can be withdrawn.', 'error');
    redirect('my-proposals.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        set_flash('Your session expired. Try again.', 'error');
        redirect('delete-proposal.php?id=' . $id);
    }
    db_exec($db, "DELETE FROM proposals WHERE id = ? AND freelancer_id = ? AND status = 'pending'", 'ii', [$id, current_user_id()]);
    set_flash('Proposal withdrawn.');
    redirect('my-proposals.php');
}

page_start('Withdraw proposal');
?>
<div class="narrow">
    <div class="panel danger-zone">
        <h1 style="font-size:1.7rem">Withdraw this proposal?</h1>
        <p style="margin-top:12px">Your bid of <strong>$<?= number_format($prop['bid_amount'], 2) ?></strong> on <strong><?= e($prop['title']) ?></strong> will be deleted.
            You can send a new proposal later if the gig is still open.</p>
        <form method="post" action="delete-proposal.php?id=<?= (int)$id ?>">
            <?= csrf_field() ?>
            <div class="btn-row">
                <button class="btn btn-danger btn-lg" type="submit">Yes, withdraw</button>
                <a class="btn btn-ghost btn-lg" href="my-proposals.php">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php page_end();
