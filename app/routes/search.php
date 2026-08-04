<?php
/**
 * Route: Search.
 */
$q = trim((string) ($_GET['q'] ?? ''));
$limit = max(4, (int) (setting('news_per_page', '12') ?: 12));
$page = max(1, (int) ($_GET['page'] ?? 1));

$results = [];
$total = 0;
$pg = ['pages' => 1, 'current' => 1, 'offset' => 0];

if ($q !== '') {
    if (mb_strlen($q) < 2) {
        $error = 'Please enter at least 2 characters.';
    } else {
        $like = '%' . $q . '%';
        $total = (int) DB::scalar(
            "SELECT COUNT(*) FROM news WHERE status='published'
             AND published_at IS NOT NULL AND published_at <= NOW()
             AND (title LIKE :q1 OR excerpt LIKE :q2 OR content LIKE :q3 OR tags LIKE :q4)",
            ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like]
        );
        $pg = paginate($total, $limit, $page);
        $results = DB::fetchAll(
            "SELECT n.*, c.name AS cat_name, c.slug AS cat_slug
             FROM news n LEFT JOIN categories c ON c.id = n.category_id
             WHERE n.status='published' AND n.published_at IS NOT NULL AND n.published_at <= NOW()
               AND (n.title LIKE :q1 OR n.excerpt LIKE :q2 OR n.content LIKE :q3 OR n.tags LIKE :q4)
             ORDER BY n.published_at DESC LIMIT :lim OFFSET :off",
            ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'lim' => $limit, 'off' => $pg['offset']]
        );
    }
}

$pageTitle = 'Search' . ($q ? ': ' . $q : '') . ' | ' . setting('site_name');
$pageDescription = 'Search news articles on ' . setting('site_name');
$canonical = site_url('search');

require BASE_PATH . '/views/header.php';
?>
<div class="search-hero">
  <div class="container">
    <h1>Search News</h1>
    <form class="search-box" action="/search" method="get" role="search">
      <input type="text" name="q" placeholder="Search for news, topics, tags..." value="<?php echo e($q); ?>" autofocus>
      <button type="submit" aria-label="Search">&#128269;</button>
    </form>
  </div>
</div>

<div class="container" style="padding-top:24px">
  <?php if ($q === ''): ?>
    <div class="card" style="padding:40px; text-align:center; color:var(--muted)">Type a keyword above to search.</div>
  <?php elseif (isset($error)): ?>
    <div class="alert alert-error"><?php echo e($error); ?></div>
  <?php elseif (!$results): ?>
    <div class="card" style="padding:40px; text-align:center; color:var(--muted)">
      No results found for "<strong><?php echo e($q); ?></strong>".
    </div>
  <?php else: ?>
    <p style="margin-bottom:16px; color:var(--muted)"><?php echo (int) $total; ?> result(s) for "<strong><?php echo e($q); ?></strong>"</p>
    <div class="grid grid-2">
      <?php foreach ($results as $item):
        include BASE_PATH . '/views/partials/news_card.php';
      endforeach; ?>
    </div>
    <?php if ($pg['pages'] > 1): ?>
    <nav class="pagination">
      <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <?php if ($i === $pg['current']): ?><span class="current"><?php echo $i; ?></span>
        <?php else: ?><a href="/search?q=<?php echo e(urlencode($q)); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a><?php endif; ?>
      <?php endfor; ?>
    </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php require BASE_PATH . '/views/footer.php'; ?>
