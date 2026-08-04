<?php
/**
 * Admin - User management.
 */
require_once __DIR__ . '/includes/init.php';
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    die('Only administrators can manage users.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::csrfValidate();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $role = in_array($_POST['role'] ?? '', ['admin', 'editor', 'reporter'], true) ? $_POST['role'] : 'reporter';
        $password = (string) ($_POST['password'] ?? '');
        $status = isset($_POST['status']) ? 1 : 0;

        if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('error', 'Valid username and email are required.');
        } else {
            $exists = DB::fetch('SELECT id FROM users WHERE (username = :u OR email = :e) AND id <> :id', ['u'=>$username,'e'=>$email,'id'=>$id]);
            if ($exists) {
                flash_set('error', 'Username or email already in use.');
            } elseif ($id === 0 && $password === '') {
                flash_set('error', 'Password is required for new users.');
            } else {
                if ($id) {
                    DB::run('UPDATE users SET username=:u, email=:e, display_name=:d, role=:r, status=:st WHERE id=:id',
                        ['u'=>$username,'e'=>$email,'d'=>$displayName,'r'=>$role,'st'=>$status,'id'=>$id]);
                    if ($password !== '') {
                        DB::run('UPDATE users SET password=:p WHERE id=:id', ['p'=>password_hash($password, PASSWORD_DEFAULT),'id'=>$id]);
                    }
                    flash_set('success', 'User updated.');
                } else {
                    DB::run('INSERT INTO users (username, email, password, display_name, role, status) VALUES (:u,:e,:p,:d,:r,:st)',
                        ['u'=>$username,'e'=>$email,'p'=>password_hash($password, PASSWORD_DEFAULT),'d'=>$displayName,'r'=>$role,'st'=>$status]);
                    flash_set('success', 'User created.');
                }
            }
        }
    } elseif ($action === 'toggle' && (int) ($_POST['id'] ?? 0) !== (int) $currentUser['id']) {
        DB::run('UPDATE users SET status = 1 - status WHERE id = :id', ['id' => (int) $_POST['id']]);
        flash_set('success', 'User status changed.');
    } elseif ($action === 'delete' && (int) ($_POST['id'] ?? 0) !== (int) $currentUser['id']) {
        DB::run('UPDATE news SET author_id = NULL WHERE author_id = :id', ['id' => (int) $_POST['id']]);
        DB::run('DELETE FROM users WHERE id = :id', ['id' => (int) $_POST['id']]);
        flash_set('success', 'User deleted.');
    }
    header('Location: users.php');
    exit;
}

$users = DB::fetchAll(
    "SELECT u.*, (SELECT COUNT(*) FROM news n WHERE n.author_id = u.id) AS news_count
     FROM users u ORDER BY u.id ASC"
);
$editUser = null;
if (isset($_GET['edit'])) {
    $editUser = DB::fetch('SELECT * FROM users WHERE id = :id', ['id' => (int) $_GET['edit']]);
}

$pageTitle = 'Users';
require_once __DIR__ . '/includes/layout.php';
?>
<div class="adm-card">
  <h2><?php echo $editUser ? 'Edit User' : 'Add User'; ?></h2>
  <form method="post" class="adm-form">
    <?php echo Security::csrfField(); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?php echo (int) ($editUser['id'] ?? 0); ?>">
    <div class="row">
      <div><label>Username *</label><input type="text" name="username" value="<?php echo e($editUser['username'] ?? ''); ?>" required></div>
      <div><label>Email *</label><input type="email" name="email" value="<?php echo e($editUser['email'] ?? ''); ?>" required></div>
    </div>
    <div class="row">
      <div><label>Display Name</label><input type="text" name="display_name" value="<?php echo e($editUser['display_name'] ?? ''); ?>"></div>
      <div>
        <label>Role</label>
        <select name="role">
          <option value="reporter" <?php echo ($editUser['role'] ?? '') === 'reporter' ? 'selected' : ''; ?>>Reporter (can post news)</option>
          <option value="editor" <?php echo ($editUser['role'] ?? '') === 'editor' ? 'selected' : ''; ?>>Editor (manage content)</option>
          <option value="admin" <?php echo ($editUser['role'] ?? '') === 'admin' ? 'selected' : ''; ?>>Admin (full access)</option>
        </select>
      </div>
    </div>
    <div class="row">
      <div>
        <label>Password <?php echo $editUser ? '(leave blank to keep current)' : '*'; ?></label>
        <input type="password" name="password" autocomplete="new-password" <?php echo $editUser ? '' : 'required'; ?>>
      </div>
      <div>
        <label style="display:flex; gap:8px; align-items:center; margin-top:34px">
          <input type="checkbox" name="status" value="1" <?php echo ($editUser['status'] ?? 1) ? 'checked' : ''; ?>> Account active
        </label>
      </div>
    </div>
    <div class="adm-actions">
      <button class="btn" type="submit"><?php echo $editUser ? 'Save' : 'Create User'; ?></button>
      <?php if ($editUser): ?><a class="btn btn-secondary" href="users.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="adm-card">
  <h2>All Users</h2>
  <div class="adm-table-wrap">
  <table class="adm-table data-table">
    <thead><tr><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Stories</th><th>Status</th><th>Last Login</th><th class="no-sort">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u): ?>
      <tr>
        <td><strong><?php echo e($u['username']); ?></strong></td>
        <td><?php echo e($u['display_name'] ?: '-'); ?></td>
        <td><?php echo e($u['email']); ?></td>
        <td><span class="badge badge-<?php echo e($u['role']); ?>"><?php echo e($u['role']); ?></span></td>
        <td><?php echo (int) $u['news_count']; ?></td>
        <td><?php echo $u['status'] ? '<span class="badge badge-published">Active</span>' : '<span class="badge badge-draft">Disabled</span>'; ?></td>
        <td><?php echo e(fmt_date($u['last_login'])); ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn-secondary btn-sm" href="users.php?edit=<?php echo (int) $u['id']; ?>">Edit</a>
          <?php if ((int) $u['id'] !== (int) $currentUser['id']): ?>
          <form method="post" style="display:inline">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
            <button class="btn btn-secondary btn-sm" type="submit"><?php echo $u['status'] ? 'Disable' : 'Enable'; ?></button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete user <?php echo e($u['username']); ?>? Their news will be unassigned.')">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>">
            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
