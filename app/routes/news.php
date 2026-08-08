<?php
/**
 * Route: Single news article.
 */
$slug = $segments[1] ?? '';
if (!$slug) { Security::notFound(); }

$item = DB::fetch(
    "SELECT n.*, c.name AS cat_name, c.slug AS cat_slug, u.display_name, u.username
     FROM news n
     LEFT JOIN categories c ON c.id = n.category_id
     LEFT JOIN users u ON u.id = n.author_id
     WHERE n.slug = :s AND n.status = 'published'
       AND n.published_at IS NOT NULL AND n.published_at <= NOW()",
    ['s' => $slug]
);
if (!$item) { Security::notFound('Article not found'); }

// Increment view count (throttle by session to avoid spam)
if (!isset($_SESSION['viewed'][$item['id']])) {
    DB::run('UPDATE news SET views = views + 1 WHERE id = :id', ['id' => $item['id']]);
    $_SESSION['viewed'][$item['id']] = time();
    $item['views']++;
}

$tags = array_filter(array_map('trim', explode(',', (string) $item['tags'])));

$related = DB::fetchAll(
    "SELECT n.*, c.name AS cat_name, c.slug AS cat_slug
     FROM news n LEFT JOIN categories c ON c.id = n.category_id
     WHERE n.status='published' AND n.id <> :id
       AND (n.category_id = :cid OR n.tags LIKE :t)
       AND n.published_at IS NOT NULL AND n.published_at <= NOW()
     ORDER BY n.published_at DESC LIMIT 4",
    ['id' => $item['id'], 'cid' => $item['category_id'], 't' => '%' . ($tags[0] ?? '') . '%']
);

$pageTitle = $item['title'] . ' | ' . setting('site_name');
$seoTitle = $item['seo_title'] ?: $pageTitle;
$pageDescription = $item['excerpt'] ?: Security::truncate(strip_tags($item['content']), 160);
$seoDescription = $item['seo_description'] ?: $pageDescription;
$metaKeywords = $item['focus_keyword'];
$noIndex = (bool) $item['noindex'];
$canonical = $item['canonical_url'] ?: site_url('news/' . $item['slug']);
$ogType = 'article';
$ogImage = $item['image'] ? site_url('/' . ltrim($item['image'], '/')) : '';
$ogImageAlt = $item['image_caption'] ?: $item['title'];
$ogImageWidth = 1200;
$ogImageHeight = 675;
$articlePublished = $item['published_at'];
$articleModified = $item['updated_at'];
$articleAuthor = $item['display_name'] ?: $item['username'];
$articleSection = $item['cat_name'] ?? '';
$articleTags = $tags;
$siteLang = setting('site_lang', 'en') ?: 'en';
$publisherLogo = setting('site_logo') ? site_url('/' . ltrim(setting('site_logo'), '/')) : site_url('assets/img/favicon.svg');

$schemaJson = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'NewsArticle',
    'headline' => $item['seo_title'] ?: $item['title'],
    'description' => $seoDescription,
    'image' => $ogImage ?: [$publisherLogo],
    'datePublished' => $item['published_at'],
    'dateModified' => $item['updated_at'],
    'inLanguage' => $siteLang,
    'author' => ['@type' => 'Person', 'name' => $item['display_name'] ?: $item['username']],
    'publisher' => [
        '@type' => 'Organization',
        'name' => setting('site_name'),
        'logo' => ['@type' => 'ImageObject', 'url' => $publisherLogo],
    ],
    'mainEntityOfPage' => $canonical,
] + array_filter([
    'articleSection' => $articleSection !== '' ? $articleSection : null,
    'keywords' => $tags ? implode(', ', $tags) : null,
]));

$breadcrumbJson = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_values(array_filter([
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => site_url()],
        $item['cat_name'] ? ['@type' => 'ListItem', 'position' => 2, 'name' => $item['cat_name'], 'item' => site_url('category/' . $item['cat_slug'])] : null,
        ['@type' => 'ListItem', 'position' => 3, 'name' => $item['title'], 'item' => $canonical],
    ])),
]);

require BASE_PATH . '/views/header.php';
?>
<script type="application/ld+json"><?php echo $schemaJson; ?></script>
<script type="application/ld+json"><?php echo $breadcrumbJson; ?></script>

<div class="container">
  <?php Ads::render('article_top'); ?>
  <article class="article">
    <nav class="breadcrumb" aria-label="Breadcrumb">
      <a href="/">Home</a> &raquo;
      <?php if ($item['cat_name']): ?><a href="/category/<?php echo e($item['cat_slug']); ?>"><?php echo e($item['cat_name']); ?></a> &raquo;<?php endif; ?>
      <?php echo e(Security::truncate($item['title'], 40, '...')); ?>
    </nav>

    <h1><?php echo e($item['title']); ?></h1>

    <div class="byline">
      <div class="avatar"><?php echo e(mb_strtoupper(mb_substr($item['display_name'] ?: $item['username'], 0, 1))); ?></div>
      <div>
        <strong><?php echo e($item['display_name'] ?: $item['username']); ?></strong>
        <div class="meta">
          <span><?php echo e(fmt_date($item['published_at'], 'F j, Y g:i A')); ?></span>
          <span class="sep">|</span>
          <span>&#128065; <?php echo number_format((int) $item['views']); ?> views</span>
        </div>
      </div>
    </div>

    <?php if ($item['excerpt']): ?><p class="excerpt"><?php echo e($item['excerpt']); ?></p><?php endif; ?>

    <?php if ($item['image']): ?>
    <figure class="article-figure">
      <?php echo lazy_img('/' . ltrim($item['image'], '/'), e($item['title']), 'featured-img', '820', '461'); ?>
      <?php if ($item['image_caption']): ?><figcaption><?php echo e($item['image_caption']); ?></figcaption><?php endif; ?>
    </figure>
    <?php endif; ?>

    <div class="article-body">
      <?php echo Security::sanitizeHtml($item['content']); ?>
    </div>

    <?php if ($tags): ?>
    <div class="tag-list">
      <?php foreach ($tags as $t): ?><a href="/tag/<?php echo e(Security::slugify($t)); ?>">#<?php echo e($t); ?></a><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="share">
      <span>Share:</span>
      <a class="fb" target="_blank" rel="noopener nofollow" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo e(urlencode($canonical)); ?>">Facebook</a>
      <a class="tw" target="_blank" rel="noopener nofollow" href="https://twitter.com/intent/tweet?url=<?php echo e(urlencode($canonical)); ?>&text=<?php echo e(urlencode($item['title'])); ?>">X</a>
      <a class="wa" target="_blank" rel="noopener nofollow" href="https://wa.me/?text=<?php echo e(urlencode($item['title'] . ' ' . $canonical)); ?>">WhatsApp</a>
      <a class="ln" target="_blank" rel="noopener nofollow" href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo e(urlencode($canonical)); ?>">LinkedIn</a>
    </div>
  </article>

  <?php Ads::render('article_bottom'); ?>
</div>

<?php if ($related): ?>
<section class="section related">
  <div class="container">
    <div class="section-head"><h2>Related News</h2></div>
    <div class="grid grid-3">
      <?php foreach ($related as $item):
        include BASE_PATH . '/views/partials/news_card.php';
      endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php require BASE_PATH . '/views/footer.php'; ?>
