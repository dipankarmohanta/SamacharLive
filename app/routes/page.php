<?php
/**
 * Route: Static pages (About, Contact, Privacy...).
 */
$slug = $segments[1] ?? '';
if (!$slug) { Security::notFound(); }

$page = DB::fetch('SELECT * FROM pages WHERE slug = :s AND status = 1', ['s' => $slug]);
if (!$page) { Security::notFound('Page not found'); }

$pageTitle = $page['title'] . ' | ' . setting('site_name');
$seoTitle = $page['seo_title'] ?: $pageTitle;
$pageDescription = Security::truncate(strip_tags($page['content']), 160);
$seoDescription = $page['seo_description'] ?: $pageDescription;
$noIndex = (bool) $page['noindex'];
$canonical = $page['canonical_url'] ?: site_url('page/' . $page['slug']);
$ogType = 'article';
$schemaJson = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $page['seo_title'] ?: $page['title'],
    'description' => $seoDescription,
    'url' => $canonical,
    'mainEntity' => ['@type' => 'WebPageElement', 'headline' => $page['title']],
]);

require BASE_PATH . '/views/header.php';
?>
<script type="application/ld+json"><?php echo $schemaJson; ?></script>
<div class="container">
  <article class="page-content">
    <nav class="breadcrumb"><a href="/">Home</a> &raquo; <?php echo e($page['title']); ?></nav>
    <h1><?php echo e($page['title']); ?></h1>
    <div class="body"><?php echo $page['content']; ?></div>
  </article>
</div>
<?php require BASE_PATH . '/views/footer.php'; ?>
