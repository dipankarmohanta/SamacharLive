<?php Security::sendSecurityHeaders(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>404 - Not Found | <?php echo e(setting('site_name', 'News Portal')); ?></title>
<link rel="stylesheet" href="<?php echo asset('css/style.css'); ?>">
</head>
<body>
<div class="error-page">
  <div class="code">404</div>
  <h1>Page Not Found</h1>
  <p style="color:var(--muted)">The page you are looking for doesn't exist or has been moved.</p>
  <a href="/">Go Back Home</a>
</div>
</body>
</html>
