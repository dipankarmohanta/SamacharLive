<?php
/**
 * News Portal - Global Configuration
 * Database credentials and environment settings.
 */

// ---- Database ----
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'newsportal');
define('DB_USER', 'newsportal');
define('DB_PASS', 'Np@2026#secure');
define('DB_CHARSET', 'utf8mb4');

// ---- Paths ----
define('BASE_PATH', __DIR__);
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('UPLOAD_URL', 'uploads');

// ---- App ----
define('APP_ENV', 'production');        // production | development
define('APP_DEBUG', false);
define('APP_TIMEZONE', 'Asia/Kolkata');

// Optional: comma-separated list of hosts allowed for canonical/og/sitemap URLs.
// Leave empty to accept any syntactically valid Host header. Subdomain wildcards
// are supported with a leading dot, e.g. '.example.com'.
define('APP_ALLOWED_HOSTS', '');

// Session security hardening
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
$fwdProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $fwdProto === 'https';
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'httponly' => true,
    'secure'   => $secure,
    'samesite' => 'Lax',
]);
