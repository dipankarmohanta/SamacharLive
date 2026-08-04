<?php
/**
 * Theme CSS variables injected from admin settings.
 * Output: <style> with :root custom properties.
 */
$primary   = setting('theme_primary', '#c62828');
$secondary = setting('theme_secondary', '#1a1a2e');
$accent    = setting('theme_accent', '#f9a825');
$headerStyle = setting('header_style', 'center');
?>
<style>
:root {
  --primary: <?php echo e($primary); ?>;
  --primary-dark: <?php echo e($primary); ?>cc;
  --secondary: <?php echo e($secondary); ?>;
  --accent: <?php echo e($accent); ?>;
  --nav-bg: <?php echo e($primary); ?>;
}
<?php if ($headerStyle === 'left'): ?>
.header-style-left .site-header .container { justify-content: flex-start; }
<?php endif; ?>
</style>
