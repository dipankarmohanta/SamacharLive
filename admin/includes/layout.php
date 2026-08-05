<?php
/**
 * Admin layout: header + sidebar + open main content.
 * Expects: $currentUser, $pageTitle
 */
if (!isset($pageTitle)) { $pageTitle = 'Admin'; }
$isAdmin = $currentUser['role'] === 'admin';

$navItems = [
    'dashboard.php'        => ['label' => 'Dashboard', 'icon' => '&#9632;'],
    'news.php'             => ['label' => 'News', 'icon' => '&#128240;'],
    'news.php?flag=breaking' => ['label' => 'Ticker', 'icon' => '&#128227;'],
    'news.php?flag=featured' => ['label' => 'Featured', 'icon' => '&#11088;'],
    'news_edit.php'        => ['label' => 'Add News', 'icon' => '&#10133;'],
    'categories.php'       => ['label' => 'Categories', 'icon' => '&#128193;'],
    'epapers.php'          => ['label' => 'Epaper', 'icon' => '&#128218;'],
    'pages.php'            => ['label' => 'Pages', 'icon' => '&#128196;'],
    'users.php'            => ['label' => 'Users', 'icon' => '&#128101;'],
    'settings.php'         => ['label' => 'Theme & Nav', 'icon' => '&#9881;'],
];
$userArea = $_SERVER['SCRIPT_NAME'] ?? '';
$active = basename($userArea);
if ($active === 'news.php' && ($_GET['flag'] ?? '') === 'breaking') { $active = 'news.php?flag=breaking'; }
if ($active === 'news.php' && ($_GET['flag'] ?? '') === 'featured') { $active = 'news.php?flag=featured'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($pageTitle); ?> | <?php echo e(setting('site_name', 'Admin')); ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?php echo e(favicon_url()); ?>">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/admin.css?v=20260805">
</head>
<body class="adm-body">
<div class="adm-wrapper">
  <aside class="adm-sidebar">
    <div class="brand"><?php echo e(setting('site_name', 'News')); ?> <span>Admin</span></div>
    <nav>
      <?php foreach ($navItems as $file => $item): ?>
        <?php if (!$isAdmin && in_array($file, ['users.php'], true)) { continue; } ?>
        <a href="/admin/<?php echo e($file); ?>" class="<?php echo $active === $file ? 'active' : ''; ?>">
          <?php echo $item['icon']; ?> <span><?php echo $item['label']; ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
    <div class="user-box">
      <strong><?php echo e($currentUser['display_name'] ?: $currentUser['username']); ?></strong><br>
      <?php echo ucfirst($currentUser['role']); ?> &middot; <a href="logout.php" style="color:#f9a825">Logout</a>
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
