<?php
/**
 * Footer layout preset: Minimal.
 * Single centered column: brand, tagline, social icons, copyright.
 * Variables provided by views/footer.php.
 */
?>
<div class="footer-minimal">
  <div class="footer-brand"><?php echo e($siteName); ?></div>
  <p class="footer-tagline"><?php echo e(setting('site_tagline', '')); ?></p>
  <div class="footer-social">
    <?php if ($fb = setting('facebook')): ?><a href="<?php echo e($fb); ?>" target="_blank" rel="noopener nofollow" aria-label="Facebook">Facebook</a><?php endif; ?>
    <?php if ($tw = setting('twitter')): ?><a href="<?php echo e($tw); ?>" target="_blank" rel="noopener nofollow" aria-label="Twitter">X</a><?php endif; ?>
    <?php if ($ig = setting('instagram')): ?><a href="<?php echo e($ig); ?>" target="_blank" rel="noopener nofollow" aria-label="Instagram">Instagram</a><?php endif; ?>
    <?php if ($yt = setting('youtube')): ?><a href="<?php echo e($yt); ?>" target="_blank" rel="noopener nofollow" aria-label="YouTube">YouTube</a><?php endif; ?>
  </div>
  <?php if ($address || $email || $phone): ?>
  <p class="footer-contact">
    <?php if ($address): ?><?php echo e($address); ?><?php endif; ?>
    <?php if ($email): ?><?php echo $address ? ' &middot; ' : ''; ?><?php echo e($email); ?><?php endif; ?>
    <?php if ($phone): ?><?php echo ($address || $email) ? ' &middot; ' : ''; ?><?php echo e($phone); ?><?php endif; ?>
  </p>
  <?php endif; ?>
</div>
