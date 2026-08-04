<?php
/**
 * Admin dashboard with stats.
 */
require_once __DIR__ . '/includes/init.php';

$stats = [
    'news'        => (int) DB::scalar("SELECT COUNT(*) FROM news"),
    'published'   => (int) DB::scalar("SELECT COUNT(*) FROM news WHERE status='published'"),
    'pending'     => (int) DB::scalar("SELECT COUNT(*) FROM news WHERE status='pending'"),
    'views'       => (int) DB::scalar("SELECT COALESCE(SUM(views),0) FROM news"),
    'categories'  => (int) DB::scalar("SELECT COUNT(*) FROM categories"),
    'users'       => (int) DB::scalar("SELECT COUNT(*) FROM users"),
    'reporters'   => (int) DB::scalar("SELECT COUNT(*) FROM users WHERE role='reporter'"),
    'epapers'     => (int) DB::scalar("SELECT COUNT(*) FROM epapers"),
];

$recent = DB::fetchAll(
    "SELECT n.id, n.title, n.status, n.views, n.published_at, u.username, c.name AS cat_name
     FROM news n LEFT JOIN users u ON u.id = n.author_id LEFT JOIN categories c ON c.id = n.category_id
     ORDER BY n.created_at DESC LIMIT 8"
);

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/layout.php';
?>
<div class="stat-grid">
  <div class="stat-card"><span class="num"><?php echo $stats['published']; ?></span><span class="lbl">Published</span></div>
  <div class="stat-card"><span class="num"><?php echo $stats['pending']; ?></span><span class="lbl">Pending Approval</span></div>
  <div class="stat-card"><span class="num"><?php echo number_format($stats['views']); ?></span><span class="lbl">Total Views</span></div>
  <div class="stat-card"><span class="num"><?php echo $stats['news']; ?></span><span class="lbl">Total News</span></div>
  <div class="stat-card"><span class="num"><?php echo $stats['categories']; ?></span><span class="lbl">Categories</span></div>
  <div class="stat-card"><span class="num"><?php echo $stats['reporters']; ?></span><span class="lbl">Reporters</span></div>
</div>

<div class="adm-card">
  <h2>Recent News</h2>
  <div class="adm-table-wrap">
  <table class="adm-table">
    <thead><tr><th>Title</th><th>Category</th><th>Author</th><th>Status</th><th>Views</th><th>Published</th><th></th></tr></thead>
    <tbody>
    <?php if (!$recent): ?><tr><td colspan="7" style="color:#9ca3af">No news yet. <a href="news_edit.php" style="color:#c62828">Add your first story</a>.</td></tr><?php endif; ?>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td style="max-width:320px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap"><?php echo e($r['title']); ?></td>
        <td><?php echo e($r['cat_name'] ?: '-'); ?></td>
        <td><?php echo e($r['username'] ?: '-'); ?></td>
        <td><span class="badge badge-<?php echo e($r['status']); ?>"><?php echo e($r['status']); ?></span></td>
        <td><?php echo number_format((int) $r['views']); ?></td>
        <td><?php echo e(fmt_date($r['published_at'])); ?></td>
        <td><a class="btn btn-secondary btn-sm" href="news_edit.php?id=<?php echo (int) $r['id']; ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
