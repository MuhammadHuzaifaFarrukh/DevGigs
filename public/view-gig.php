<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

$gig_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$gig_id || $gig_id < 1) {
    redirect('index.php');
}

$stmt = $pdo->prepare('SELECT g.*, u.name AS client_name FROM gigs g JOIN users u ON g.client_id = u.id WHERE g.id = ? AND g.is_deleted = 0');
$stmt->execute([$gig_id]);
$gig = $stmt->fetch();

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
    require_login($pdo);   // re-checks the account and refreshes the role from the database

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
            $dup = $pdo->prepare('SELECT id FROM proposals WHERE gig_id = ? AND freelancer_id = ?');
            $dup->execute([$gig_id, current_user_id()]);

            if ($dup->fetch()) {
                $error = 'You have already sent a proposal for this gig.';
            } else {
                $ins = $pdo->prepare("INSERT INTO proposals (gig_id, freelancer_id, cover_letter, bid_amount, status) VALUES (?, ?, ?, ?, 'pending')");
                $ins->execute([$gig_id, current_user_id(), $old['cover_letter'], $old['bid_amount']]);
                set_flash('Proposal sent. The client can now see it.');
                redirect('view-gig.php?id=' . $gig_id);
            }
        } catch (\PDOException $ex) {
            error_log('Proposal failed: ' . $ex->getMessage());
            $error = ($ex->getCode() === '23000')
                ? 'You have already sent a proposal for this gig.'
                : 'We could not save your proposal. Please try again.';
        }
    }
}

$role     = current_role();
$is_owner = is_logged_in() && current_user_id() === (int)$gig['client_id'];

$mine = null;
if ($role === 'freelancer') {
    $q = $pdo->prepare('SELECT bid_amount, status, created_at FROM proposals WHERE gig_id = ? AND freelancer_id = ? AND is_deleted = 0');
    $q->execute([$gig_id, current_user_id()]);
    $mine = $q->fetch();
}

$proposals = [];
if ($is_owner) {
    $q = $pdo->prepare('SELECT p.*, u.name AS freelancer_name, u.email AS freelancer_email
                        FROM proposals p JOIN users u ON p.freelancer_id = u.id
                        WHERE p.gig_id = ? AND p.is_deleted = 0 ORDER BY p.created_at DESC');
    $q->execute([$gig_id]);
    $proposals = $q->fetchAll();
}

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
                        <span><strong><?= e($p['freelancer_name']) ?></strong>
                            <span class="muted"> &middot; <a href="mailto:<?= e($p['freelancer_email']) ?>"><?= e($p['freelancer_email']) ?></a></span></span>
                        <span><span class="badge badge-<?= e($p['status']) ?>"><?= e($p['status']) ?></span>
                            <strong class="price" style="font-size:1.2rem;margin-left:8px">$<?= number_format($p['bid_amount'], 2) ?></strong></span>
                    </div>
                    <p><?= e($p['cover_letter']) ?></p>
                    <div class="hint">Sent <?= e(date('M j, Y', strtotime($p['created_at']))) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php page_end();
