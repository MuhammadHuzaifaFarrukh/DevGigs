<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

/* UPDATE a proposal. Only its freelancer, and only while it is pending and the gig is still open. */
require_login($db, 'freelancer');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    error_page(400, 'Invalid proposal ID.');
}

$prop = db_one($db, 'SELECT p.id, p.freelancer_id, p.cover_letter, p.bid_amount, p.status, g.id AS gig_id, g.title, g.status AS gig_status
                     FROM proposals p JOIN gigs g ON g.id = p.gig_id
                     WHERE p.id = ? AND p.is_deleted = 0 AND g.is_deleted = 0', 'i', [$id]);
if (!$prop) {
    error_page(404, 'Proposal not found.');
}
if ((int)$prop['freelancer_id'] !== current_user_id()) {
    error_page(403, 'You can only edit your own proposals.');
}
if ($prop['status'] !== 'pending' || $prop['gig_status'] !== 'open') {
    set_flash('This proposal can no longer be edited because the client has already responded or the gig is closed.', 'error');
    redirect('my-proposals.php');
}

$error = '';
$old   = ['cover_letter' => $prop['cover_letter'], 'bid_amount' => number_format((float)$prop['bid_amount'], 2, '.', '')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['cover_letter'] = trim($_POST['cover_letter'] ?? '');
    $old['bid_amount']   = trim($_POST['bid_amount'] ?? '');

    $error = first_error([
        csrf_ok() ? null : 'Your session expired. Reload the page and try again.',
        validate_text($old['cover_letter'], 'Cover letter', 20, 3000),
        validate_money($old['bid_amount'], 'Bid amount'),
    ]);

    if ($error === '') {
        try {
            db_exec($db, "UPDATE proposals SET cover_letter = ?, bid_amount = ?
                          WHERE id = ? AND freelancer_id = ? AND status = 'pending'",
                'ssii', [$old['cover_letter'], $old['bid_amount'], $id, current_user_id()]);
            set_flash('Proposal updated.');
            redirect('my-proposals.php');
        } catch (\mysqli_sql_exception $ex) {
            error_log('Edit proposal failed: ' . $ex->getMessage());
            $error = 'We could not save your changes. Please try again.';
        }
    }
}

page_start('Edit proposal');
?>
<div class="narrow">
    <div>
        <a class="back" href="my-proposals.php">&larr; Back to my proposals</a>
        <div class="panel">
            <h1>Edit proposal</h1>
            <p class="muted" style="margin:6px 0 24px">For <a class="link" href="view-gig.php?id=<?= (int)$prop['gig_id'] ?>"><?= e($prop['title']) ?></a></p>

            <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

            <form method="post" action="edit-proposal.php?id=<?= (int)$id ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="cover_letter">Cover letter</label>
                    <textarea class="input" id="cover_letter" name="cover_letter" rows="6" required minlength="20" maxlength="3000"><?= e($old['cover_letter']) ?></textarea>
                    <div class="hint">At least 20 characters.</div>
                </div>
                <div class="field">
                    <label for="bid_amount">Your bid (USD)</label>
                    <input class="input" type="number" id="bid_amount" name="bid_amount" value="<?= e($old['bid_amount']) ?>" required min="0.01" max="99999999.99" step="0.01">
                </div>
                <div class="btn-row">
                    <button class="btn btn-lg" type="submit">Save changes</button>
                    <a class="btn btn-ghost btn-lg" href="my-proposals.php">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php page_end();
