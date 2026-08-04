<?php
/**
 * Route: Tag listing.
 */
$tag = $segments[1] ?? '';
if (!$tag) { Security::notFound(); }

$limit = max(4, (int) (setting('news_per_page', '12') ?: 12));
$page = max(1, (int) ($_GET['page'] ?? 1));
$like = '%' . $tag . '%';

$total = (int) DB::scalar(
    "SELECT COUNT(*) FROM news WHERE status='published' AND tags LIKE :t AND published_at IS NOT NULL AND published_at <= NOW()",
    ['t' => $like]
);
$pg = paginate($total, $limit, $page);

$stories = DB::fetchAll(
    "SELECT n.*, c.name AS cat_name, c.slug AS cat_slug
     FROM news n LEFT JOIN categories c ON c.id = n.category_id
     WHERE n.status='published' AND n.tags LIKE :t AND n.published_at IS NOT NULL AND n.published_at <= NOW()
     ORDER BY n.published_at DESC LIMIT :lim OFFSET :off",
    ['t' => $like, 'lim' => $limit, 'off' => $pg['offset']]
);

$pageTitle = '#' . $tag . ' | ' . setting('site_name');
$pageDescription = 'Articles tagged with #' . $tag . ' on ' . setting('site_name');
$canonical = site_url('tag/' . $tag);
$noIndex = $page > 1;

require BASE_PATH . '/views/header.php';
?>
<div class="category-head">
  <div class="container">
    <h1>#<?php echo e($tag); ?></h1>
    <p><?php echo (int) $total; ?> article(s) with this tag.</p>
  </div>
</div>

<div class="container">
  <div class="layout">
    <div>
      <?php if ($stories): ?>
      <div class="grid grid-2">
        <?php foreach ($stories as $item):
          include BASE_PATH . '/views/partials/news_card.php';
        endforeach; ?>
      </div>
      <?php if ($pg['pages'] > 1): ?>
      <nav class="pagination">
        <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
          <?php if ($i === $pg['current']): ?><span class="current"><?php echo $i; ?></span>
          <?php else: ?><a href="/tag/<?php echo e($tag); ?>?page=<?php echo $i; ?>"><?php echo $i; ?></a><?php endif; ?>
        <?php endfor; ?>
      </nav>
      <?php endif; ?>
      <?php else: ?>
      <div class="card" style="padding:40px; text-align:center; color:var(--muted)">No articles found for this tag.</div>
      <?php endif; ?>
    </div>
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
  </div>
</div>

<?php require BASE_PATH . '/views/footer.php'; ?>
