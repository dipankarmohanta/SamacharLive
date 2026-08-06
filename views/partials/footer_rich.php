<?php
/**
 * Footer layout preset: Rich.
 * Newsletter signup + four columns + social row + bottom copyright bar.
 * Variables provided by views/footer.php.
 */
?>
<div class="footer-rich">
  <div class="footer-rich-news">
    <div class="newsletter">
      <form action="/search" method="get" role="search">
        <input type="text" name="q" placeholder="Your email for updates..." aria-label="Email" value="">
        <button type="submit" aria-label="Subscribe">Subscribe</button>
      </form>
    </div>
    <p class="footer-rich-note">Get breaking news and top stories delivered to your inbox.</p>
  </div>
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
  <div class="footer-rich-social">
    <?php if ($fb = setting('facebook')): ?><a href="<?php echo e($fb); ?>" target="_blank" rel="noopener nofollow" aria-label="Facebook">Facebook</a><?php endif; ?>
    <?php if ($tw = setting('twitter')): ?><a href="<?php echo e($tw); ?>" target="_blank" rel="noopener nofollow" aria-label="Twitter">X</a><?php endif; ?>
    <?php if ($ig = setting('instagram')): ?><a href="<?php echo e($ig); ?>" target="_blank" rel="noopener nofollow" aria-label="Instagram">Instagram</a><?php endif; ?>
    <?php if ($yt = setting('youtube')): ?><a href="<?php echo e($yt); ?>" target="_blank" rel="noopener nofollow" aria-label="YouTube">YouTube</a><?php endif; ?>
  </div>
</div>
