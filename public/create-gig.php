<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

require_login($db, 'client');

$error = '';
$old   = ['title' => '', 'description' => '', 'budget' => ''];

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
            db_exec($db, "INSERT INTO gigs (client_id, title, description, budget, status) VALUES (?, ?, ?, ?, 'open')", 'isss', [current_user_id(), $old['title'], $old['description'], $old['budget']]);
            set_flash('Your gig is live.');
            redirect('view-gig.php?id=' . $db->insert_id);
        } catch (\mysqli_sql_exception $ex) {
            error_log('Create gig failed: ' . $ex->getMessage());
            $error = 'We could not save your gig. Please try again.';
        }
    }
}

page_start('Post a gig');
?>
<div class="narrow">
    <div>
        <a class="back" href="index.php">&larr; Back to gigs</a>
        <div class="panel">
            <h1>Post a gig</h1>
            <p class="muted" style="margin:6px 0 24px">Describe the work clearly so freelancers can bid with confidence.</p>

            <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

            <form method="post" action="create-gig.php">
                <?= csrf_field() ?>
                <div class="field">
                    <label for="title">Title</label>
                    <input class="input" type="text" id="title" name="title" value="<?= e($old['title']) ?>" required minlength="5" maxlength="150" placeholder="e.g. Build a PHP booking form with MySQL">
                </div>
                <div class="field">
                    <label for="description">Description</label>
                    <textarea class="input" id="description" name="description" rows="6" required minlength="20" maxlength="5000" placeholder="What needs to be built, the tech stack, and what done looks like."><?= e($old['description']) ?></textarea>
                    <div class="hint">At least 20 characters.</div>
                </div>
                <div class="field">
                    <label for="budget">Budget (USD)</label>
                    <input class="input" type="number" id="budget" name="budget" value="<?= e($old['budget']) ?>" required min="0.01" max="99999999.99" step="0.01" placeholder="500.00">
                </div>
                <button class="btn btn-lg" type="submit">Publish gig</button>
            </form>
        </div>
    </div>
</div>
<?php page_end();
