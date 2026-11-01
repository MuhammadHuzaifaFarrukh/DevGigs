<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

/* UPDATE a gig. Only the client who posted it can do this, and only while it is still open. */
require_login($db, 'client');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    error_page(400, 'Invalid gig ID.');
}

$gig = db_one($db, 'SELECT id, client_id, title, description, budget, status FROM gigs WHERE id = ? AND is_deleted = 0', 'i', [$id]);
if (!$gig) {
    error_page(404, 'Gig not found.');
}
if ((int)$gig['client_id'] !== current_user_id()) {
    error_page(403, 'You can only edit your own gigs.');
}
if ($gig['status'] !== 'open') {
    set_flash('Only open gigs can be edited.', 'error');
    redirect('view-gig.php?id=' . $id);
}

$error = '';
$old   = ['title' => $gig['title'], 'description' => $gig['description'], 'budget' => number_format((float)$gig['budget'], 2, '.', '')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['title']       = trim($_POST['title'] ?? '');
    $old['description'] = trim($_POST['description'] ?? '');
    $old['budget']      = trim($_POST['budget'] ?? '');

    $error = first_error([
        csrf_ok() ? null : 'Your session expired. Reload the page and try again.',
        validate_text($old['title'], 'Title', 5, 150),
        validate_text($old['description'], 'Description', 20, 5000),
        validate_money($old['budget'], 'Budget'),
    ]);

    if ($error === '') {
        try {
            // The WHERE clause repeats the ownership and status checks, so the database enforces them too.
            db_exec($db, "UPDATE gigs SET title = ?, description = ?, budget = ?
                          WHERE id = ? AND client_id = ? AND status = 'open' AND is_deleted = 0",
                'sssii', [$old['title'], $old['description'], $old['budget'], $id, current_user_id()]);
            set_flash('Gig updated.');
            redirect('view-gig.php?id=' . $id);
        } catch (\mysqli_sql_exception $ex) {
            error_log('Edit gig failed: ' . $ex->getMessage());
            $error = 'We could not save your changes. Please try again.';
        }
    }
}

page_start('Edit gig');
?>
<div class="narrow">
    <div>
        <a class="back" href="view-gig.php?id=<?= (int)$id ?>">&larr; Back to gig</a>
        <div class="panel">
            <h1>Edit gig</h1>
            <p class="muted" style="margin:6px 0 24px">Changes show up straight away for everyone browsing gigs.</p>

            <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

            <form method="post" action="edit-gig.php?id=<?= (int)$id ?>">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="title">Title</label>
                    <input class="input" type="text" id="title" name="title" value="<?= e($old['title']) ?>" required minlength="5" maxlength="150">
                </div>
                <div class="field">
                    <label for="description">Description</label>
                    <textarea class="input" id="description" name="description" rows="6" required minlength="20" maxlength="5000"><?= e($old['description']) ?></textarea>
                    <div class="hint">At least 20 characters.</div>
                </div>
                <div class="field">
                    <label for="budget">Budget (USD)</label>
                    <input class="input" type="number" id="budget" name="budget" value="<?= e($old['budget']) ?>" required min="0.01" max="99999999.99" step="0.01">
                </div>
                <div class="btn-row">
                    <button class="btn btn-lg" type="submit">Save changes</button>
                    <a class="btn btn-ghost btn-lg" href="view-gig.php?id=<?= (int)$id ?>">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php page_end();
