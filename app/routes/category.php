<?php
/**
 * Route: Category listing.
 */
$slug = $segments[1] ?? '';
if (!$slug) { Security::notFound(); }

$cat = DB::fetch('SELECT * FROM categories WHERE slug = :s AND status = 1', ['s' => $slug]);
if (!$cat) { Security::notFound('Category not found'); }

$perPage = max(4, (int) (setting('news_per_page', '12') ?: 12));
$page = max(1, (int) ($_GET['page'] ?? 1));
$total = (int) DB::scalar(
    "SELECT COUNT(*) FROM news WHERE status='published' AND category_id = :cid AND published_at IS NOT NULL AND published_at <= NOW()",
    ['cid' => $cat['id']]
);
$pg = paginate($total, $perPage, $page);

$stories = DB::fetchAll(
    "SELECT n.*, c.name AS cat_name, c.slug AS cat_slug
     FROM news n LEFT JOIN categories c ON c.id = n.category_id
     WHERE n.status='published' AND n.category_id = :cid AND n.published_at IS NOT NULL AND n.published_at <= NOW()
     ORDER BY n.published_at DESC LIMIT :lim OFFSET :off",
    ['cid' => $cat['id'], 'lim' => $perPage, 'off' => $pg['offset']]
);

$pageTitle = $cat['name'] . ' | ' . setting('site_name');
$seoTitle = $cat['seo_title'] ?: $pageTitle;
$pageDescription = $cat['description'] ?: 'Latest news in ' . $cat['name'];
$seoDescription = $cat['seo_description'] ?: $pageDescription;
$noIndex = (bool) $cat['noindex'];
$canonical = site_url('category/' . $cat['slug']);
if ($page > 1) { $canonical .= '?page=' . $page; }

require BASE_PATH . '/views/header.php';
?>
<div class="category-head">
  <div class="container">
    <h1><?php echo e($cat['name']); ?></h1>
    <?php if ($cat['description']): ?><p><?php echo e($cat['description']); ?></p><?php endif; ?>
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
      <nav class="pagination" aria-label="Pagination">
        <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
          <?php if ($i === $pg['current']): ?><span class="current"><?php echo $i; ?></span>
          <?php else: ?><a href="/category/<?php echo e($cat['slug']); ?>?page=<?php echo $i; ?>"><?php echo $i; ?></a><?php endif; ?>
        <?php endfor; ?>
      </nav>
      <?php endif; ?>
      <?php else: ?>
      <div class="card" style="padding:40px; text-align:center; color:var(--muted)">No stories published in this category yet.</div>
      <?php endif; ?>
    </div>
    <?php require BASE_PATH . '/views/partials/sidebar.php'; ?>
  </div>
</div>

<?php require BASE_PATH . '/views/footer.php'; ?>
