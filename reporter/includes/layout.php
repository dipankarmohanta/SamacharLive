<?php
/**
 * Reporter layout: header + sidebar.
 * Expects: $currentUser, $pageTitle
 */
if (!isset($pageTitle)) { $pageTitle = 'Reporter Panel'; }
$navItems = [
    'index.php'      => ['label' => 'My News', 'icon' => '&#128240;'],
    'add-news.php'   => ['label' => 'Add News', 'icon' => '&#10133;'],
];
$active = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($pageTitle); ?> | <?php echo e(setting('site_name', 'Reporter')); ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?php echo e(favicon_url()); ?>">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="adm-body">
<div class="adm-wrapper">
  <aside class="adm-sidebar">
    <div class="brand"><?php echo e(setting('site_name', 'News')); ?> <span>Reporter</span></div>
    <nav>
      <?php foreach ($navItems as $file => $item): ?>
        <a href="/reporter/<?php echo e($file); ?>" class="<?php echo $active === $file ? 'active' : ''; ?>">
          <?php echo $item['icon']; ?> <span><?php echo $item['label']; ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="user-box">
      <strong><?php echo e($currentUser['display_name'] ?: $currentUser['username']); ?></strong><br>
      Reporter &middot; <a href="/admin/logout.php" style="color:#f9a825">Logout</a>
    </div>
  </aside>
  <div class="adm-main">
    <div class="adm-topbar">
      <h1><?php echo e($pageTitle); ?></h1>
      <a class="btn btn-outline btn-sm" href="/" target="_blank">&#128279; View Site</a>
    </div>
    <div class="adm-content">
      <?php if ($flash = flash_get()): ?>
        <div class="alert alert-<?php echo e($flash['type'] === 'error' ? 'error' : ($flash['type'] === 'success' ? 'success' : 'info')); ?>">
          <?php echo e($flash['message']); ?>
        </div>
      <?php endif; ?>
