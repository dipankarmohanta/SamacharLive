<?php
/**
 * Reporter - My news list.
 */
require_once __DIR__ . '/includes/init.php';

$myNews = DB::fetchAll(
    "SELECT n.*, c.name AS cat_name FROM news n
     LEFT JOIN categories c ON c.id = n.category_id
     WHERE n.author_id = :uid ORDER BY n.created_at DESC",
    ['uid' => $currentUser['id']]
);

$pageTitle = 'My News';
require_once __DIR__ . '/includes/layout.php';
?>
<div class="adm-card">
  <div class="toolbar">
    <p style="color:#6b7280; font-size:.88rem">News you submitted. Published items appear live; new submissions go to the editor for review.</p>
    <a class="btn" href="/reporter/add-news.php">&#10133; Add News</a>
  </div>
  <div class="adm-table-wrap">
  <table class="adm-table data-table">
    <thead><tr><th></th><th>Title</th><th>Category</th><th>Status</th><th>Views</th><th>Created</th><th class="no-sort">Action</th></tr></thead>
    <tbody>
    <?php if (!$myNews): ?><tr><td colspan="7" style="color:#9ca3af">You haven't submitted any news yet.</td></tr><?php endif; ?>
    <?php foreach ($myNews as $n): ?>
      <tr>
        <td><?php if ($n['image']): ?><img class="thumb" src="/<?php echo e(ltrim($n['image'], '/')); ?>" alt="" loading="lazy"><?php else: ?>-<?php endif; ?></td>
        <td style="max-width:300px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap"><?php echo e($n['title']); ?></td>
        <td><?php echo e($n['cat_name'] ?: '-'); ?></td>
        <td><span class="badge badge-<?php echo e($n['status']); ?>"><?php echo e($n['status']); ?></span></td>
        <td><?php echo number_format((int) $n['views']); ?></td>
        <td><?php echo e(fmt_date($n['created_at'], 'M j, Y')); ?></td>
        <td>
          <?php if (in_array($n['status'], ['draft', 'pending'], true)): ?>
            <a class="btn btn-secondary btn-sm" href="/reporter/add-news.php?id=<?php echo (int) $n['id']; ?>">Edit</a>
          <?php else: ?>
            <a class="btn btn-secondary btn-sm" href="/news/<?php echo e($n['slug']); ?>" target="_blank">View</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
