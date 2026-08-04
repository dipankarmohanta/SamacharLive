<?php
/**
 * Dev-server router for `php -S` (local development / preview only).
 * Serves existing static files directly and routes everything else
 * through the front controller (index.php). Production uses Apache
 * mod_rewrite / nginx instead; this file is not used there.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

// Emulate Apache mod_dir: redirect directory URLs without a trailing slash so
// relative links inside admin/ and reporter/ resolve correctly.
if ($path !== '/' && is_dir($file) && !str_ends_with($path, '/')) {
    $qs = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY);
    header('Location: ' . $path . '/' . ($qs ? '?' . $qs : ''), true, 301);
    exit;
}

if ($path !== '/') {
    if (is_file($file)) {
        return false;
    }
    if (is_dir($file) && is_file(rtrim($file, '/') . '/index.php')) {
        $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
        $_SERVER['SCRIPT_FILENAME'] = rtrim($file, '/') . '/index.php';
        require rtrim($file, '/') . '/index.php';
        return true;
    }
}

require __DIR__ . '/index.php';
