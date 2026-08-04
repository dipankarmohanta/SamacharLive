<?php
/** Admin logout. */
require_once __DIR__ . '/../app/bootstrap.php';
Auth::start();
Auth::logout();
header('Location: /admin/index.php');
exit;
