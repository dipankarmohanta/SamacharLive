<?php
/**
 * Footer layout preset: Classic.
 * Four-column grid: About / Quick Links / Categories / Pages.
 * Variables provided by views/footer.php.
 */
?>
<div class="footer-grid">
  <div class="about">
    <h4><?php echo e($siteName); ?></h4>
    <p><?php echo e(setting('site_tagline', '')); ?></p>
    <?php if ($address): ?><p>&#128205; <?php echo e($address); ?></p><?php endif; ?>
    <?php if ($email): ?><p>&#9993; <?php echo e($email); ?></p><?php endif; ?>
    <?php if ($phone): ?><p>&#128222; <?php echo e($phone); ?></p><?php endif; ?>
  </div>
  <div>
    <h4>Quick Links</h4>
    <ul>
      <li><a href="/">Home</a></li>
      <?php if (setting('epaper_enabled', '1')): ?><li><a href="/epaper">Epaper</a></li><?php endif; ?>
      <li><a href="/search">Search</a></li>
    </ul>
  </div>
  <div>
    <h4>Categories</h4>
    <ul>
      <?php foreach (array_slice(category_tree(), 0, 6) as $c): ?>
        <li><a href="/category/<?php echo e($c['slug']); ?>"><?php echo e($c['name']); ?></a></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <div>
    <h4>Pages</h4>
    <ul>
      <?php foreach ($pages as $p): ?>
        <li><a href="/page/<?php echo e($p['slug']); ?>"><?php echo e($p['title']); ?></a></li>
      <?php endforeach; ?>
      <?php if (!$pages): ?><li><a href="/page/about">About Us</a></li><?php endif; ?>
    </ul>
  </div>
</div>
