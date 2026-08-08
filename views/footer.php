<?php
/**
 * Public site footer.
 */
$siteName = setting('site_name', 'News Portal');
$address = setting('site_address', '');
$email = setting('site_email', '');
$phone = setting('site_phone', '');
$footerText = setting('site_footer_text', '');
$footerStyle = setting('footer_style', 'classic');
$footerStyle = in_array($footerStyle, ['classic', 'minimal', 'rich'], true) ? $footerStyle : 'classic';
$pages = DB::fetchAll("SELECT title, slug FROM pages WHERE status = 1 ORDER BY id ASC");
?>
</main>

<footer class="site-footer footer-style-<?php echo e($footerStyle); ?>">
  <div class="container">
    <?php Ads::render('footer'); ?>
    <?php require BASE_PATH . '/views/partials/footer_' . $footerStyle . '.php'; ?>
    <?php if ($footerText !== ''): ?>
    <div class="footer-bottom"><?php echo $footerText; ?></div>
    <?php endif; ?>
  </div>
</footer>

<script src="<?php echo asset('js/main.js?v=3'); ?>"></script>
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function () {
      /* SW registration blocked (e.g. insecure context) - site still works. */
    });
  }
</script>
<?php Ads::renderIntegrations('body_bottom'); ?>
</body>
</html>
