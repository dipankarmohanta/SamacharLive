<?php
/**
 * Header layout preset: Modern.
 * No top bar. Slim sticky row: logo left, inline navigation center, search right.
 * Variables provided by views/header.php.
 */
?>
<header class="site-header modern-header">
  <div class="container">
    <a href="/" class="logo" aria-label="<?php echo e($siteName); ?> home">
      <?php if ($logo): ?>
        <img src="<?php echo e('/' . ltrim($logo, '/')); ?>" alt="<?php echo e($siteName); ?>" width="180" height="48">
      <?php else: ?>
        <span class="logo-text"><?php echo e($siteName); ?></span>
      <?php endif; ?>
      <?php if ($tagline): ?><span class="logo-tagline"><?php echo e($tagline); ?></span><?php endif; ?>
    </a>
    <nav class="modern-nav" aria-label="Primary">
      <button class="nav-toggle" id="nav-toggle" aria-label="Toggle navigation" aria-expanded="false">&#9776;</button>
      <?php render_nav_menu($menuTree); ?>
    </nav>
    <div class="header-actions">
      <button class="icon-btn" id="theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">&#9790;</button>
      <a class="icon-btn" href="/search" aria-label="Search">&#128269;</a>
    </div>
  </div>
</header>
