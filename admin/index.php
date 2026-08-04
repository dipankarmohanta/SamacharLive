<?php
/**
 * Admin login.
 */
require_once __DIR__ . '/../app/bootstrap.php';
Auth::start();

if (Auth::id()) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::csrfValidate();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $error = Auth::attempt($username, $password);
        if ($error === null) {
            $role = $_SESSION['role'] ?? 'reporter';
            if ($role === 'reporter') {
                header('Location: /reporter/index.php');
            } else {
                header('Location: /admin/dashboard.php');
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login | <?php echo e(setting('site_name', 'News Portal')); ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="<?php echo e(favicon_url()); ?>">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="auth-body">
<div class="auth-card">
  <div class="logo">
    <div class="logo-text"><?php echo e(setting('site_name', 'News Portal')); ?></div>
    <div class="logo-tagline">Admin Panel</div>
  </div>
  <h1>Sign In</h1>
  <p class="sub">Access the administration dashboard</p>
  <?php if ($error): ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
  <form method="post" action="/admin/index.php" autocomplete="off">
    <?php echo Security::csrfField(); ?>
    <label for="username">Username or Email</label>
    <input type="text" id="username" name="username" value="<?php echo e($_POST['username'] ?? 'admin'); ?>" required autofocus>
    <label for="password">Password</label>
    <input type="password" id="password" name="password" value="Admin@1234" required>
    <button type="submit">Login</button>
  </form>
  <p style="text-align:center; margin-top:18px; font-size:.8rem; color:#6b7280">
    <a href="/" style="color:#c62828">&larr; Back to website</a>
  </p>
</div>
</body>
</html>
