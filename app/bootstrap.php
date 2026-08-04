<?php
/**
 * News Portal - Application bootstrap.
 * Loads config, DB, security, settings and helpers.
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/helpers.php';

Security::sendSecurityHeaders();

// Resolve site URL from request
if (!defined('SITE_URL')) {
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwarded === 'https';
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('SITE_URL', $scheme . '://' . $host);
}

date_default_timezone_set(APP_TIMEZONE);

// Settings singleton (public)
$settings = Settings::instance();
