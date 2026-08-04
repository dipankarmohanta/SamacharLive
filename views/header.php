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

$siteName = setting('site_name', 'News Portal');
$tagline = setting('site_tagline', '');
$logo = setting('site_logo');
$headerStyle = setting('header_style', 'center');
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
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($seoTitle); ?></title>
<meta name="description" content="<?php echo e($seoDescription); ?>">
<?php if ($metaKeywords !== ''): ?><meta name="keywords" content="<?php echo e($metaKeywords); ?>"><?php endif; ?>
<link rel="canonical" href="<?php echo e($canonical); ?>">
<meta name="robots" content="<?php echo $noIndex ? 'noindex, nofollow' : 'index, follow'; ?>">
<meta name="theme-color" content="<?php echo e(setting('theme_primary', '#c62828')); ?>">
<meta property="og:site_name" content="<?php echo e($siteName); ?>">
<meta property="og:type" content="<?php echo e($ogType); ?>">
<meta property="og:title" content="<?php echo e($seoTitle); ?>">
<meta property="og:description" content="<?php echo e($seoDescription); ?>">
<meta property="og:url" content="<?php echo e($canonical); ?>">
<?php if (!empty($ogImage)): ?><meta property="og:image" content="<?php echo e($ogImage); ?>"><?php endif; ?>
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?php echo e($seoTitle); ?>">
<meta name="twitter:description" content="<?php echo e($seoDescription); ?>">
<link rel="icon" href="<?php echo asset('img/favicon.svg'); ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?php echo asset('css/style.css?v=1'); ?>">
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
<body class="header-style-<?php echo e($headerStyle); ?>">

<header class="topbar">
  <div class="container">
    <div class="topbar-date">&#128197; <?php echo e($today); ?></div>
    <div class="topbar-social">
      <?php if ($fb = setting('facebook')): ?><a href="<?php echo e($fb); ?>" target="_blank" rel="noopener nofollow" aria-label="Facebook">Facebook</a><?php endif; ?>
      <?php if ($tw = setting('twitter')): ?><a href="<?php echo e($tw); ?>" target="_blank" rel="noopener nofollow" aria-label="Twitter">X</a><?php endif; ?>
      <?php if ($ig = setting('instagram')): ?><a href="<?php echo e($ig); ?>" target="_blank" rel="noopener nofollow" aria-label="Instagram">Instagram</a><?php endif; ?>
      <?php if ($yt = setting('youtube')): ?><a href="<?php echo e($yt); ?>" target="_blank" rel="noopener nofollow" aria-label="YouTube">YouTube</a><?php endif; ?>
    </div>
  </div>
</header>

<header class="site-header">
  <div class="container">
    <a href="/" class="logo" aria-label="<?php echo e($siteName); ?> home">
      <?php if ($logo): ?>
        <img src="<?php echo e('/' . ltrim($logo, '/')); ?>" alt="<?php echo e($siteName); ?>" width="220" height="64">
      <?php else: ?>
        <span class="logo-text"><?php echo e($siteName); ?></span>
      <?php endif; ?>
      <?php if ($tagline): ?><span class="logo-tagline"><?php echo e($tagline); ?></span><?php endif; ?>
    </a>
    <div class="header-actions">
      <button class="icon-btn" id="theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">&#9790;</button>
      <a class="icon-btn" href="/search" aria-label="Search">&#128269;</a>
    </div>
  </div>
</header>

<nav class="navbar" id="navbar">
  <div class="container">
    <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">&#9776;</button>
    <?php render_nav_menu($menuTree); ?>
    <div class="nav-search">
      <form action="/search" method="get" role="search">
        <input type="text" name="q" placeholder="Search news..." aria-label="Search" value="<?php echo e($_GET['q'] ?? ''); ?>">
        <button type="submit" aria-label="Submit search">&#128269;</button>
      </form>
    </div>
  </div>
</nav>

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
