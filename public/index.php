<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/database.php';

/* Sort order is a cookie preference: choosing one once keeps it on the next visit. */
$sorts = [
    'newest'      => 'g.created_at DESC',
    'budget_high' => 'g.budget DESC',
    'budget_low'  => 'g.budget ASC',
];
$sortLabels = [
    'newest'      => 'Newest first',
    'budget_high' => 'Budget: high to low',
    'budget_low'  => 'Budget: low to high',
];
if (isset($_GET['sort']) && isset($sorts[$_GET['sort']])) {
    set_pref('gig_sort', $_GET['sort']);
}
$sort = pref('gig_sort', 'newest', GIG_SORTS);
$q    = mb_substr(trim($_GET['q'] ?? ''), 0, 100);

$sql = "SELECT g.id, g.title, g.description, g.budget, g.created_at, u.name AS client_name
        FROM gigs g JOIN users u ON g.client_id = u.id
        WHERE g.is_deleted = 0 AND g.status = 'open'";
$params = [];
$types  = '';
if ($q !== '') {
    $sql .= ' AND (g.title LIKE ? OR g.description LIKE ?)';
    $like = '%' . addcslashes($q, '%_\\') . '%';
    $params = [$like, $like];
    $types  = 'ss';
}
$sql .= ' ORDER BY ' . $sorts[$sort] . ' LIMIT 50';
$gigs = db_all($db, $sql, $types, $params);

/* Live numbers for the stat bar */
$stats = db_one($db, "SELECT
    (SELECT COUNT(*) FROM gigs WHERE is_deleted = 0 AND status = 'open') AS open_gigs,
    (SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id WHERE u.is_deleted = 0 AND r.name = 'freelancer') AS freelancers,
    (SELECT COUNT(*) FROM proposals WHERE is_deleted = 0) AS proposals");

$role = current_role();
page_start('Find trusted developers');
?>
<section class="hero" data-parallax>
    <div class="hero-bg" aria-hidden="true">
        <picture>
            <source srcset="img/hero.webp" type="image/webp">
            <img src="img/hero.jpg" alt="" width="1024" height="1024" fetchpriority="high" decoding="async">
        </picture>
    </div>
    <div class="container hero-copy">
        <div class="hero-text">
            <h1>Find trusted developers for the work that matters.</h1>
            <p class="lead">DevGigs connects clients with independent software professionals. Post a gig, review proposals, and choose the right person for the job.</p>
            <div class="hero-actions">
                <?php if (!is_logged_in()): ?>
                    <a class="btn btn-lg" href="signup.php">Get started</a>
                <?php elseif ($role === 'client'): ?>
                    <a class="btn btn-lg" href="create-gig.php">Post a gig</a>
                <?php endif; ?>
                <a class="btn btn-ghost btn-lg" href="#gigs">Browse open gigs</a>
            </div>
        </div>
    </div>
</section>

<div class="container">
    <div class="statbar">
        <div><b><?= (int)$stats['open_gigs'] ?></b><span>Open gigs</span></div>
        <div><b><?= (int)$stats['freelancers'] ?></b><span>Freelancers</span></div>
        <div><b><?= (int)$stats['proposals'] ?></b><span>Proposals sent</span></div>
    </div>
</div>

<section class="section" id="gigs">
    <div class="container">
        <div class="section-head">
            <h2>Open gigs</h2>
            <?php if ($role === 'client'): ?><a class="btn btn-sm" href="create-gig.php">Post a gig</a><?php endif; ?>
        </div>

        <form class="toolbar" method="get" action="index.php#gigs" role="search">
            <input class="input grow" type="search" name="q" value="<?= e($q) ?>" placeholder="Search title or description" aria-label="Search gigs" maxlength="100">
            <select class="input" name="sort" aria-label="Sort gigs" data-autosubmit>
                <?php foreach ($sortLabels as $key => $label): ?>
                    <option value="<?= e($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-ghost" type="submit">Search</button>
        </form>

        <?php if (!$gigs): ?>
            <div class="gig-list"><div class="empty">
                <?php if ($q !== ''): ?>
                    No open gigs match "<?= e($q) ?>". <a class="link" href="index.php#gigs">Clear search</a>
                <?php else: ?>
                    No open gigs right now. Clients can post one from their account.
                <?php endif; ?>
            </div></div>
        <?php else: ?>
            <div class="gig-list">
                <?php foreach ($gigs as $g): ?>
                    <article class="gig">
                        <div>
                            <h3><a href="view-gig.php?id=<?= (int)$g['id'] ?>"><?= e($g['title']) ?></a></h3>
                            <p><?= e($g['description']) ?></p>
                            <div class="gig-meta">
                                <span>Posted by <?= e($g['client_name']) ?></span>
                                <span><?= e(date('M j, Y', strtotime($g['created_at']))) ?></span>
                            </div>
                        </div>
                        <div class="gig-side">
                            <span class="price">$<?= number_format($g['budget'], 2) ?></span>
                            <a class="btn btn-ghost btn-sm" href="view-gig.php?id=<?= (int)$g['id'] ?>">View and apply</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container">
        <div class="section-head"><h2>How it works</h2></div>
        <div class="steps">
            <div class="step"><div class="step-n">1</div><h3>Sign up with a role</h3><p>Choose client to hire or freelancer to work. Your role decides what you can do on the platform.</p></div>
            <div class="step"><div class="step-n">2</div><h3>Post or bid</h3><p>Clients post a gig with a budget. Freelancers send a cover letter and a bid amount.</p></div>
            <div class="step"><div class="step-n">3</div><h3>Review proposals</h3><p>Clients read every proposal on their gig page and compare bids side by side.</p></div>
        </div>
    </div>
</section>
<?php page_end();
