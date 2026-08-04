<?php
/**
 * Admin - Static pages (About, Contact, Privacy...).
 */
require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::csrfValidate();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $slug = Security::slugify(trim((string) ($_POST['slug'] ?? '')) ?: $title);
        $content = (string) ($_POST['content'] ?? '');
        if ($title === '' || $content === '') {
            flash_set('error', 'Title and content are required.');
        } else {
            if ($id) {
                DB::run('UPDATE pages SET title=:t, slug=:s, content=:c WHERE id=:id', ['t'=>$title,'s'=>$slug,'c'=>$content,'id'=>$id]);
                flash_set('success', 'Page updated.');
            } else {
                DB::run('INSERT INTO pages (title, slug, content) VALUES (:t,:s,:c)', ['t'=>$title,'s'=>$slug,'c'=>$content]);
                flash_set('success', 'Page created.');
            }
        }
    } elseif ($action === 'toggle' || $action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($action === 'delete') {
            DB::run('DELETE FROM pages WHERE id = :id', ['id' => $id]);
            flash_set('success', 'Page deleted.');
        } else {
            DB::run('UPDATE pages SET status = 1 - status WHERE id = :id', ['id' => $id]);
            flash_set('success', 'Page status changed.');
        }
    }
    header('Location: pages.php');
    exit;
}

$pages = DB::fetchAll('SELECT * FROM pages ORDER BY id ASC');
$editPage = null;
if (isset($_GET['edit'])) {
    $editPage = DB::fetch('SELECT * FROM pages WHERE id = :id', ['id' => (int) $_GET['edit']]);
}

$pageTitle = 'Pages';
require_once __DIR__ . '/includes/layout.php';
?>
<div class="adm-card">
  <h2><?php echo $editPage ? 'Edit Page' : 'Add Page'; ?></h2>
  <form method="post" class="adm-form">
    <?php echo Security::csrfField(); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?php echo (int) ($editPage['id'] ?? 0); ?>">
    <div class="row">
      <div>
        <label>Title *</label>
        <input type="text" name="title" value="<?php echo e($editPage['title'] ?? ''); ?>" required>
      </div>
      <div>
        <label>Slug</label>
        <input type="text" name="slug" value="<?php echo e($editPage['slug'] ?? ''); ?>" placeholder="auto from title">
      </div>
    </div>
    <label>Content *</label>
    <textarea name="content" class="editor rich-editor" required><?php echo e($editPage['content'] ?? ''); ?></textarea>
    <p class="hint">Visual editor: use the toolbar to format text, add headings, lists, links, images and tables.</p>
    <p class="hint">Page URL will be: /page/your-slug</p>
    <div class="adm-actions">
      <button class="btn" type="submit"><?php echo $editPage ? 'Save' : 'Create Page'; ?></button>
      <?php if ($editPage): ?><a class="btn btn-secondary" href="pages.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/editor.php'; ?>

<div class="adm-card">
  <h2>All Pages</h2>
  <div class="adm-table-wrap">
  <table class="adm-table data-table">
    <thead><tr><th>Title</th><th>Slug</th><th>Status</th><th>Created</th><th class="no-sort">Actions</th></tr></thead>
    <tbody>
    <?php if (!$pages): ?><tr><td colspan="5" style="color:#9ca3af">No pages yet.</td></tr><?php endif; ?>
    <?php foreach ($pages as $p): ?>
      <tr>
        <td><?php echo e($p['title']); ?></td>
        <td><a href="/page/<?php echo e($p['slug']); ?>" target="_blank"><code>/page/<?php echo e($p['slug']); ?></code></a></td>
        <td><?php echo $p['status'] ? '<span class="badge badge-published">Active</span>' : '<span class="badge badge-draft">Hidden</span>'; ?></td>
        <td><?php echo e(fmt_date($p['created_at'])); ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn-secondary btn-sm" href="pages.php?edit=<?php echo (int) $p['id']; ?>">Edit</a>
          <form method="post" style="display:inline">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
            <button class="btn btn-secondary btn-sm" type="submit"><?php echo $p['status'] ? 'Hide' : 'Show'; ?></button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this page?')">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $p['id']; ?>">
            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
