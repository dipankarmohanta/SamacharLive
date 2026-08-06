<?php
/**
 * Route: Homepage.
 */

// Hero: most recent breaking/featured
$hero = DB::fetch(
    "SELECT n.*, c.name AS cat_name, c.slug AS cat_slug
     FROM news n LEFT JOIN categories c ON c.id = n.category_id
     WHERE n.status='published' AND n.published_at IS NOT NULL AND n.published_at <= NOW()
     ORDER BY n.breaking DESC, n.featured DESC, n.published_at DESC LIMIT 1"
);

// Side hero cards
$heroSide = DB::fetchAll(
    "SELECT n.*, c.name AS cat_name, c.slug AS cat_slug
     FROM news n LEFT JOIN categories c ON c.id = n.category_id
     WHERE n.status='published' AND n.published_at IS NOT NULL AND n.published_at <= NOW()
     ORDER BY n.published_at DESC LIMIT 4"
);

// Latest grid
$latest = DB::fetchAll(
    "SELECT n.*, c.name AS cat_name, c.slug AS cat_slug
     FROM news n LEFT JOIN categories c ON c.id = n.category_id
     WHERE n.status='published' AND n.published_at IS NOT NULL AND n.published_at <= NOW()
     ORDER BY n.published_at DESC LIMIT 12"
);

// Sections: top categories with 4 items each
$cats = DB::fetchAll('SELECT * FROM categories WHERE status = 1 ORDER BY sort_order ASC LIMIT 5');
$sections = [];
foreach ($cats as $cat) {
    $items = DB::fetchAll(
        "SELECT n.*, c.name AS cat_name, c.slug AS cat_slug
         FROM news n LEFT JOIN categories c ON c.id = n.category_id
         WHERE n.status='published' AND n.published_at IS NOT NULL AND n.published_at <= NOW()
           AND n.category_id = :cid
         ORDER BY n.published_at DESC LIMIT 4",
        ['cid' => $cat['id']]
    );
    if ($items) {
        $sections[] = ['cat' => $cat, 'items' => $items];
    }
}

$pageTitle = setting('site_name') . ' - ' . setting('site_tagline');
$pageDescription = setting('seo_meta_description');
$ogImage = $hero && $hero['image'] ? site_url('/' . ltrim($hero['image'], '/')) : '';
$ogImageAlt = $hero ? $hero['title'] : '';

require BASE_PATH . '/views/header.php';
?>
<h1 class="sr-only"><?php echo e($pageTitle); ?></h1>
<div class="hero">
  <div class="container">
    <div class="hero-grid">
      <?php if ($hero): ?>
      <a class="hero-main" href="/news/<?php echo e($hero['slug']); ?>">
        <?php $hi = $hero['image'] ? '/' . ltrim($hero['image'], '/') : asset('img/placeholder.svg'); ?>
        <?php echo lazy_img($hi, e($hero['title']), '', '1024', '576'); ?>
        <div class="hero-overlay">
          <?php if ($hero['cat_name']): ?><span class="cat-badge"><?php echo e($hero['cat_name']); ?></span><?php endif; ?>
          <h2><?php echo e($hero['title']); ?></h2>
          <div class="meta"><?php echo e(time_ago($hero['published_at'])); ?></div>
        </div>
      </a>
      <?php endif; ?>
      <div class="hero-side">
        <?php foreach ($heroSide as $i => $s): ?>
        <a class="hero-card" href="/news/<?php echo e($s['slug']); ?>">
          <?php $t = $s['image'] ? '/' . ltrim($s['image'], '/') : asset('img/placeholder.svg'); ?>
          <?php echo lazy_img($t, e($s['title']), '', '108', '76'); ?>
          <div>
            <h4><?php echo e($s['title']); ?></h4>
            <div class="meta"><?php echo e(time_ago($s['published_at'])); ?></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<?php Ads::render('home_top'); ?>

<section class="section">
  <div class="container">
    <div class="section-head">
      <h2>Latest News</h2>
      <a class="see-all" href="/search">View All &rarr;</a>
    </div>
    <div class="grid grid-3">
      <?php foreach ($latest as $item):
        include BASE_PATH . '/views/partials/news_card.php';
      endforeach; ?>
    </div>
  </div>
</section>

<?php foreach ($sections as $sec): ?>
<section class="section">
  <div class="container">
    <div class="section-head">
      <h2><?php echo e($sec['cat']['name']); ?></h2>
      <a class="see-all" href="/category/<?php echo e($sec['cat']['slug']); ?>">View All &rarr;</a>
    </div>
    <div class="grid grid-3">
      <?php foreach ($sec['items'] as $item):
        include BASE_PATH . '/views/partials/news_card.php';
      endforeach; ?>
    </div>
  </div>
</section>
<?php endforeach; ?>

<?php require BASE_PATH . '/views/footer.php'; ?>
