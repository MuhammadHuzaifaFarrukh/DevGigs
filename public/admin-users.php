<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/lists.php';
require_once __DIR__ . '/../config/database.php';

/* READ all users, plus disable / restore. Create and edit are in admin-user-form.php,
   permanent delete is in admin-user-delete.php. */
require_login($db, 'admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int)($_POST['id'] ?? 0);
    $action = (string)($_POST['action'] ?? '');
    $target = $id > 0 ? find_user($db, $id) : null;

    if (!csrf_ok()) {
        set_flash('Your session expired. Try again.', 'error');
    } elseif (!$target) {
        set_flash('User not found.', 'error');
    } elseif ($id === current_user_id()) {
        set_flash('You cannot change your own account from here.', 'error');
    } elseif ($target['role'] === 'admin') {
        set_flash('Admin accounts are protected.', 'error');
    } elseif ($action === 'disable_user') {
        db_exec($db, 'UPDATE users SET is_deleted = 1 WHERE id = ?', 'i', [$id]);
        set_flash($target['name'] . ' was disabled. They can no longer log in.');
    } elseif ($action === 'restore_user') {
        db_exec($db, 'UPDATE users SET is_deleted = 0 WHERE id = ?', 'i', [$id]);
        set_flash($target['name'] . ' was restored.');
    }
    redirect('admin-users.php' . (isset($_POST['back']) && preg_match('/^[a-z0-9_=&%.+-]*$/i', (string)$_POST['back']) && $_POST['back'] !== '' ? '?' . $_POST['back'] : ''));
}

$q      = search_term();
$role   = choice('role', ['all', 'admin', 'client', 'freelancer'], 'all');
$state  = choice('state', ['active', 'disabled', 'all'], 'active');

$where  = '1 = 1';
$types  = '';
$params = [];
if ($state === 'active')   { $where .= ' AND u.is_deleted = 0'; }
if ($state === 'disabled') { $where .= ' AND u.is_deleted = 1'; }
if ($role !== 'all')       { $where .= ' AND r.name = ?'; $types .= 's'; $params[] = $role; }
if ($q !== '')             { $where .= ' AND (u.name LIKE ? OR u.email LIKE ?)'; $types .= 'ss'; $like = like_term($q); array_push($params, $like, $like); }

$total = (int)db_one($db, "SELECT COUNT(*) AS n FROM users u JOIN roles r ON r.id = u.role_id WHERE $where", $types, $params)['n'];
$pg    = paginate($total, 15);
$users = db_all($db, "SELECT u.id, u.name, u.email, u.phone, u.is_deleted, u.created_at, r.name AS role
                      FROM users u JOIN roles r ON r.id = u.role_id
                      WHERE $where ORDER BY u.created_at DESC, u.id DESC LIMIT ? OFFSET ?",
                $types . 'ii', array_merge($params, [$pg['perPage'], $pg['offset']]));

$backQuery = http_build_query(array_filter(['q' => $q, 'role' => $role !== 'all' ? $role : '', 'state' => $state !== 'active' ? $state : '', 'page' => $pg['page'] > 1 ? $pg['page'] : '']));

page_start('Manage users');
?>
<div class="wide">
    <div class="page-head">
        <h1>Admin</h1>
        <a class="btn" href="admin-user-form.php">Add user</a>
    </div>
    <?php admin_tabs('users'); ?>

    <div class="panel">
        <form class="filters" method="get" action="admin-users.php" role="search">
            <input class="input grow" type="search" name="q" value="<?= e($q) ?>" placeholder="Search name or email" aria-label="Search users" maxlength="100">
            <select class="input" name="role" aria-label="Role" data-autosubmit>
                <?php foreach (['all' => 'All roles', 'admin' => 'Admins', 'client' => 'Clients', 'freelancer' => 'Freelancers'] as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= $role === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="input" name="state" aria-label="Status" data-autosubmit>
                <?php foreach (['active' => 'Active', 'disabled' => 'Disabled', 'all' => 'Active and disabled'] as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= $state === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-ghost" type="submit">Search</button>
            <?php if ($q !== '' || $role !== 'all' || $state !== 'active'): ?><a class="link" href="admin-users.php">Clear</a><?php endif; ?>
        </form>

        <?php if (!$users): ?>
            <p class="muted">No users match this search.</p>
        <?php else: ?>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th>Joined</th><th class="right">Actions</th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): $isAdmin = $u['role'] === 'admin'; $isMe = (int)$u['id'] === current_user_id(); ?>
                <tr<?= $u['is_deleted'] ? ' class="is-off"' : '' ?>>
                    <td>#<?= (int)$u['id'] ?></td>
                    <td><strong><?= e($u['name']) ?></strong><?= $isMe ? ' <span class="muted">(you)</span>' : '' ?></td>
                    <td><?= e($u['email']) ?></td>
                    <td><?= e($u['phone'] ?? '') ?></td>
                    <td><span class="badge badge-<?= e($u['role']) ?>"><?= e($u['role']) ?></span></td>
                    <td><?= $u['is_deleted'] ? '<span class="badge badge-disabled">disabled</span>' : '<span class="badge badge-open">active</span>' ?></td>
                    <td><?= e(date('M j, Y', strtotime($u['created_at']))) ?></td>
                    <td class="right"><div class="row-actions">
                        <a class="btn btn-ghost btn-sm" href="admin-user-form.php?id=<?= (int)$u['id'] ?>">Edit</a>
                        <?php if (!$isAdmin && !$isMe): ?>
                            <form class="inline" method="post" action="admin-users.php"<?= $u['is_deleted'] ? '' : ' data-confirm="Disable this account? They will not be able to log in."' ?>>
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="<?= $u['is_deleted'] ? 'restore_user' : 'disable_user' ?>">
                                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                <input type="hidden" name="back" value="<?= e($backQuery) ?>">
                                <button class="btn btn-ghost btn-sm" type="submit"><?= $u['is_deleted'] ? 'Restore' : 'Disable' ?></button>
                            </form>
                            <a class="btn btn-danger btn-sm" href="admin-user-delete.php?id=<?= (int)$u['id'] ?>">Delete</a>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?= pager($pg) ?>
        <?php endif; ?>
    </div>
</div>
<?php page_end();
