<?php
/**
 * Admin - News list with filters.
 */
require_once __DIR__ . '/includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::csrfValidate();
    $action = $_POST['action'] ?? '';
    $id = (int) ($_POST['id'] ?? 0);
    if ($action === 'delete' && $id) {
        $row = DB::fetch('SELECT image FROM news WHERE id = :id', ['id' => $id]);
        DB::run('DELETE FROM news WHERE id = :id', ['id' => $id]);
        flash_set('success', 'News deleted.');
    } elseif ($action === 'publish' && $id) {
        DB::run(
            "UPDATE news SET status='published', published_at = COALESCE(published_at, NOW())
             WHERE id = :id",
            ['id' => $id]
        );
        flash_set('success', 'News published.');
    } elseif ($action === 'unpublish' && $id) {
        DB::run("UPDATE news SET status='draft' WHERE id = :id", ['id' => $id]);
        flash_set('success', 'News unpublished.');
    }
    header('Location: news.php');
    exit;
}

$statusFilter = $_GET['status'] ?? '';
$catFilter = (int) ($_GET['cat'] ?? 0);

$where = ["1=1"];
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, ['draft', 'pending', 'published'], true)) {
    $where[] = "n.status = :st";
    $params['st'] = $statusFilter;
}
if ($catFilter > 0) {
    $where[] = "n.category_id = :cid";
    $params['cid'] = $catFilter;
}
$whereSql = implode(' AND ', $where);

$rows = DB::fetchAll(
    "SELECT n.*, u.username, c.name AS cat_name
     FROM news n LEFT JOIN users u ON u.id = n.author_id LEFT JOIN categories c ON c.id = n.category_id
     WHERE $whereSql ORDER BY n.created_at DESC",
    $params
);
$cats = DB::fetchAll('SELECT * FROM categories ORDER BY name');

$pageTitle = 'News Management';
require_once __DIR__ . '/includes/layout.php';
?>
<div class="adm-card">
  <div class="toolbar">
    <form method="get" action="news.php">
      <select name="status" onchange="this.form.submit()">
        <option value="">All Status</option>
        <option value="published" <?php echo $statusFilter === 'published' ? 'selected' : ''; ?>>Published</option>
        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
        <option value="draft" <?php echo $statusFilter === 'draft' ? 'selected' : ''; ?>>Draft</option>
      </select>
      <select name="cat" onchange="this.form.submit()">
        <option value="0">All Categories</option>
        <?php foreach ($cats as $c): ?>
          <option value="<?php echo (int) $c['id']; ?>" <?php echo $catFilter === (int) $c['id'] ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
        <?php endforeach; ?>
      </select>
      <noscript><button class="btn btn-sm btn-secondary" type="submit">Filter</button></noscript>
    </form>
    <a class="btn" href="news_edit.php">&#10133; Add News</a>
  </div>

  <div class="adm-table-wrap">
  <table class="adm-table data-table">
    <thead><tr><th></th><th>Title</th><th>Category</th><th>Author</th><th>Status</th><th>Views</th><th>Featured</th><th>Published</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="9" style="color:#9ca3af">No news found.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?php if ($r['image']): ?><img class="thumb" src="/<?php echo e(ltrim($r['image'], '/')); ?>" alt="" loading="lazy"><?php else: ?>-<?php endif; ?></td>
        <td style="max-width:280px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap"><?php echo e($r['title']); ?></td>
        <td><?php echo e($r['cat_name'] ?: '-'); ?></td>
        <td><?php echo e($r['username'] ?: '-'); ?></td>
        <td><span class="badge badge-<?php echo e($r['status']); ?>"><?php echo e($r['status']); ?></span></td>
        <td><?php echo number_format((int) $r['views']); ?></td>
        <td><?php echo $r['featured'] ? '&#9733;' : ''; ?></td>
        <td><?php echo e(fmt_date($r['published_at'], 'M j, Y')); ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn-secondary btn-sm" href="news_edit.php?id=<?php echo (int) $r['id']; ?>">Edit</a>
          <?php if ($r['status'] === 'published'): ?>
            <form method="post" style="display:inline" onsubmit="return confirm('Unpublish this news?')">
              <?php echo Security::csrfField(); ?>
              <input type="hidden" name="action" value="unpublish"><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
              <button class="btn btn-secondary btn-sm" type="submit">Unpublish</button>
            </form>
          <?php elseif ($r['status'] === 'pending'): ?>
            <form method="post" style="display:inline">
              <?php echo Security::csrfField(); ?>
              <input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
              <button class="btn btn-success btn-sm" type="submit">Publish</button>
            </form>
          <?php endif; ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this news permanently?')">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
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
