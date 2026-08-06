<?php
/**
 * Public site header.
 * Variables available: $pageTitle, $pageDescription, $canonical.
 * $categories, $breakingStories, $settings used internally.
 */
if (!isset($pageTitle)) { $pageTitle = setting('site_name', 'News Portal'); }
if (!isset($pageDescription)) { $pageDescription = setting('seo_meta_description', ''); }

// On-page SEO overrides (set by routes from per-item SEO fields)
$seoTitle = $seoTitle ?? $pageTitle;
$seoDescription = $seoDescription ?? $pageDescription;
$noIndex = !empty($noIndex);
$metaKeywords = trim((string) ($metaKeywords ?? ''));
$canonical = $canonical ?? site_url();
$ogType = $ogType ?? 'website';
$ogImage = $ogImage ?? '';
$ogImageAlt = $ogImageAlt ?? '';
$ogImageWidth = (int) ($ogImageWidth ?? 1200);
$ogImageHeight = (int) ($ogImageHeight ?? 630);
$articlePublished = $articlePublished ?? '';
$articleModified = $articleModified ?? '';
$articleAuthor = $articleAuthor ?? '';
$articleSection = $articleSection ?? '';
$articleTags = (array) ($articleTags ?? []);

$siteName = setting('site_name', 'News Portal');
$siteLang = setting('site_lang', 'en') ?: 'en';
$ogLocale = str_replace('-', '_', $siteLang);
$tagline = setting('site_tagline', '');
$logo = setting('site_logo');
$headerStyle = setting('header_style', 'classic');
$headerStyle = in_array($headerStyle, ['classic', 'modern', 'compact'], true) ? $headerStyle : 'classic';
$footerStyle = setting('footer_style', 'classic');
$footerStyle = in_array($footerStyle, ['classic', 'minimal', 'rich'], true) ? $footerStyle : 'classic';
$breakingOn = setting('header_breaking', '1');

$breakingStories = DB::fetchAll(
    "SELECT n.title, n.slug FROM news n
     WHERE n.status='published' AND n.published_at IS NOT NULL AND n.published_at <= NOW()
     ORDER BY n.published_at DESC LIMIT 12"
);

$menuTree = menu_tree();

$today = date('l, F j, Y');
?>
<!DOCTYPE html>
<html lang="<?php echo e($siteLang); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($seoTitle); ?></title>
<meta name="description" content="<?php echo e(meta_desc($seoDescription)); ?>">
<?php if ($metaKeywords !== ''): ?><meta name="keywords" content="<?php echo e($metaKeywords); ?>"><?php endif; ?>
<link rel="canonical" href="<?php echo e($canonical); ?>">
<meta name="robots" content="<?php echo $noIndex ? 'noindex, nofollow' : 'index, follow'; ?>">
<meta name="theme-color" content="<?php echo e(setting('theme_primary', '#c62828')); ?>">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="apple-touch-icon" href="/assets/img/icon-192.png">
<meta property="og:site_name" content="<?php echo e($siteName); ?>">
<meta property="og:type" content="<?php echo e($ogType); ?>">
<meta property="og:locale" content="<?php echo e($ogLocale); ?>">
<meta property="og:title" content="<?php echo e($seoTitle); ?>">
<meta property="og:description" content="<?php echo e(meta_desc($seoDescription)); ?>">
<meta property="og:url" content="<?php echo e($canonical); ?>">
<?php if ($ogImage !== ''): ?>
<meta property="og:image" content="<?php echo e($ogImage); ?>">
<meta property="og:image:alt" content="<?php echo e($ogImageAlt); ?>">
<meta property="og:image:width" content="<?php echo (int) $ogImageWidth; ?>">
<meta property="og:image:height" content="<?php echo (int) $ogImageHeight; ?>">
<?php endif; ?>
<?php if ($ogType === 'article'): ?>
<?php if ($articlePublished !== ''): ?><meta property="article:published_time" content="<?php echo e($articlePublished); ?>"><?php endif; ?>
<?php if ($articleModified !== ''): ?><meta property="article:modified_time" content="<?php echo e($articleModified); ?>"><?php endif; ?>
<?php if ($articleAuthor !== ''): ?><meta property="article:author" content="<?php echo e($articleAuthor); ?>"><?php endif; ?>
<?php if ($articleSection !== ''): ?><meta property="article:section" content="<?php echo e($articleSection); ?>"><?php endif; ?>
<?php foreach ($articleTags as $tag): ?><meta property="article:tag" content="<?php echo e($tag); ?>"><?php endforeach; ?>
<?php endif; ?>
<meta name="twitter:card" content="<?php echo $ogImage !== '' ? 'summary_large_image' : 'summary'; ?>">
<meta name="twitter:title" content="<?php echo e($seoTitle); ?>">
<meta name="twitter:description" content="<?php echo e(meta_desc($seoDescription)); ?>">
<?php if ($ogImage !== ''): ?><meta name="twitter:image" content="<?php echo e($ogImage); ?>"><?php endif; ?>
<link rel="icon" href="<?php echo e(favicon_url()); ?>">
<link rel="apple-touch-icon" href="<?php echo e(favicon_url()); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo asset('css/style.css?v=2'); ?>">
<?php require BASE_PATH . '/views/theme.php'; ?>
<?php if ($analytics = setting('google_analytics')): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo e($analytics); ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', '<?php echo e($analytics); ?>');
</script>
<?php endif; ?>
</head>
<body class="header-style-<?php echo e($headerStyle); ?> footer-style-<?php echo e($footerStyle); ?>">

<div class="offline-indicator" id="offline-indicator" role="status" hidden>You are offline &mdash; showing cached content</div>

<?php require BASE_PATH . '/views/partials/header_' . $headerStyle . '.php'; ?>

<?php if ($breakingOn && count($breakingStories) > 3): ?>
<div class="breaking">
  <div class="container">
    <span class="breaking-label">Breaking</span>
    <div class="breaking-track">
      <div class="breaking-items">
        <?php foreach (array_merge($breakingStories, $breakingStories) as $i => $story): ?>
          <a href="/news/<?php echo e($story['slug']); ?>"><?php echo e($story['title']); ?></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<main>
