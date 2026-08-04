<?php
/**
 * Admin - Categories management.
 */
require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::csrfValidate();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = Security::slugify(trim((string) ($_POST['slug'] ?? '')) ?: $name);
        $description = trim((string) ($_POST['description'] ?? ''));
        $parentId = (int) ($_POST['parent_id'] ?? 0);
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if ($name === '') {
            flash_set('error', 'Category name is required.');
        } else {
            $exists = DB::fetch('SELECT id FROM categories WHERE slug = :s AND id <> :id', ['s' => $slug, 'id' => $id]);
            if ($exists) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }

            $image = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $image = Security::uploadImage($_FILES['image'], 'news', 1024);
            }

            if ($id) {
                DB::run(
                    "UPDATE categories SET name=:n, slug=:s, description=:d, parent_id=:p, sort_order=:o,
                     image=COALESCE(:i, image) WHERE id=:id",
                    ['n'=>$name,'s'=>$slug,'d'=>$description,'p'=>$parentId ?: null,'o'=>$sortOrder,'i'=>$image,'id'=>$id]
                );
                flash_set('success', 'Category updated.');
            } else {
                DB::run(
                    "INSERT INTO categories (name, slug, description, image, parent_id, sort_order) VALUES (:n,:s,:d,:i,:p,:o)",
                    ['n'=>$name,'s'=>$slug,'d'=>$description,'i'=>$image,'p'=>$parentId ?: null,'o'=>$sortOrder]
                );
                flash_set('success', 'Category created.');
            }
        }
    } elseif ($action === 'toggle' || $action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($action === 'delete') {
            $count = (int) DB::scalar('SELECT COUNT(*) FROM news WHERE category_id = :id', ['id' => $id]);
            if ($count > 0) {
                flash_set('error', 'Cannot delete: ' . $count . ' news item(s) use this category. Reassign them first.');
            } else {
                DB::run('DELETE FROM categories WHERE id = :id', ['id' => $id]);
                flash_set('success', 'Category deleted.');
            }
        } else {
            DB::run('UPDATE categories SET status = 1 - status WHERE id = :id', ['id' => $id]);
            flash_set('success', 'Category status changed.');
        }
    }
    header('Location: categories.php');
    exit;
}

$cats = DB::fetchAll('SELECT * FROM categories ORDER BY sort_order, name');
$editCat = null;
if (isset($_GET['edit'])) {
    $editCat = DB::fetch('SELECT * FROM categories WHERE id = :id', ['id' => (int) $_GET['edit']]);
}
$tree = category_tree();

$pageTitle = 'Categories';
require_once __DIR__ . '/includes/layout.php';
?>
<div class="adm-card">
  <h2><?php echo $editCat ? 'Edit Category' : 'Add Category'; ?></h2>
  <form method="post" enctype="multipart/form-data" class="adm-form">
    <?php echo Security::csrfField(); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?php echo (int) ($editCat['id'] ?? 0); ?>">
    <div class="row-3">
      <div>
        <label>Name *</label>
        <input type="text" name="name" value="<?php echo e($editCat['name'] ?? ''); ?>" required>
      </div>
      <div>
        <label>Slug</label>
        <input type="text" name="slug" value="<?php echo e($editCat['slug'] ?? ''); ?>" placeholder="auto from name">
      </div>
      <div>
        <label>Parent Category</label>
        <select name="parent_id">
          <option value="0">None (top level)</option>
          <?php foreach ($tree as $c): ?>
            <?php if ($editCat && (int) $c['id'] === (int) $editCat['id']) { continue; } ?>
            <option value="<?php echo (int) $c['id']; ?>" <?php echo (($editCat['parent_id'] ?? 0) == $c['id']) ? 'selected' : ''; ?>>
              <?php echo e(str_repeat('&mdash; ', $c['depth']) . $c['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <label>Description</label>
    <textarea name="description" rows="2"><?php echo e($editCat['description'] ?? ''); ?></textarea>
    <div class="row">
      <div>
        <label>Sort Order</label>
        <input type="number" name="sort_order" value="<?php echo e($editCat['sort_order'] ?? 0); ?>">
      </div>
      <div>
        <label>Image</label>
        <input type="file" name="image" accept="image/*">
      </div>
    </div>
    <div class="adm-actions">
      <button class="btn" type="submit"><?php echo $editCat ? 'Save' : 'Add Category'; ?></button>
      <?php if ($editCat): ?><a class="btn btn-secondary" href="categories.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="adm-card">
  <h2>All Categories</h2>
  <div class="adm-table-wrap">
  <table class="adm-table data-table">
    <thead><tr><th>Name</th><th>Slug</th><th>Parent</th><th>Order</th><th>Status</th><th>Stories</th><th class="no-sort">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($tree as $c): ?>
      <tr>
        <td><?php echo e(str_repeat('&mdash; ', $c['depth']) . $c['name']); ?></td>
        <td><code><?php echo e($c['slug']); ?></code></td>
        <td><?php echo $c['parent_id'] ? e(DB::scalar('SELECT name FROM categories WHERE id = :id', ['id' => $c['parent_id']])) : '-'; ?></td>
        <td><?php echo (int) $c['sort_order']; ?></td>
        <td><?php echo $c['status'] ? '<span class="badge badge-published">Active</span>' : '<span class="badge badge-draft">Hidden</span>'; ?></td>
        <td><?php echo (int) DB::scalar('SELECT COUNT(*) FROM news WHERE category_id = :id', ['id' => $c['id']]); ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn-secondary btn-sm" href="categories.php?edit=<?php echo (int) $c['id']; ?>">Edit</a>
          <form method="post" style="display:inline">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
            <button class="btn btn-secondary btn-sm" type="submit"><?php echo $c['status'] ? 'Hide' : 'Show'; ?></button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this category?')">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $c['id']; ?>">
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
