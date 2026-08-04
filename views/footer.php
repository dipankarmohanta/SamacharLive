<?php
/**
 * Public site footer.
 */
$siteName = setting('site_name', 'News Portal');
$address = setting('site_address', '');
$email = setting('site_email', '');
$phone = setting('site_phone', '');
$footerText = setting('site_footer_text', '');
$pages = DB::fetchAll("SELECT title, slug FROM pages WHERE status = 1 ORDER BY id ASC");
?>
</main>

<footer class="site-footer">
  <div class="container">
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
          <li><a href="/epaper">Epaper</a></li>
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
    <div class="footer-bottom"><?php echo $footerText; ?></div>
  </div>
</footer>

<script src="<?php echo asset('js/main.js?v=1'); ?>"></script>
</body>
</html>
