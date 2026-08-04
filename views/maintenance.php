<?php Security::sendSecurityHeaders(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Maintenance | <?php echo e(setting('site_name', 'News Portal')); ?></title>
<link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
</head>
<body>
<div class="error-page">
  <div class="code">&#9888;</div>
  <h1>We'll be back soon</h1>
  <p style="color:var(--muted)">Our website is under maintenance. Please check back shortly.</p>
</div>
</body>
</html>
