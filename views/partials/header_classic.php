<?php
/**
 * Header layout preset: Classic.
 * Top bar + centered logo header + full-width navbar + breaking ticker.
 * Variables provided by views/header.php.
 */
?>
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
