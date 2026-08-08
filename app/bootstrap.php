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
require_once __DIR__ . '/Ads.php';

Security::sendSecurityHeaders();

// Resolve site URL from request
if (!defined('SITE_URL')) {
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwarded === 'https';
    $scheme = $https ? 'https' : 'http';

    // Validate HTTP_HOST before trusting it (Host header injection / cache poisoning).
    // Only a syntactically valid hostname[:port] is accepted; anything else falls
    // back to the first allow-listed host, or 'localhost'.
    $host = preg_replace('/[\r\n\0]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    if (!preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*(:\d{1,5})?$/i', $host)) {
        $host = '';
    }
    if ($host !== '' && defined('APP_ALLOWED_HOSTS') && APP_ALLOWED_HOSTS !== '') {
        $allowed = array_map('trim', explode(',', APP_ALLOWED_HOSTS));
        $inList = false;
        foreach ($allowed as $a) {
            $domain = ltrim($a, '.');
            if ($domain === '') {
                continue;
            }
            if (strcasecmp($a, $host) === 0 || strcasecmp($domain, $host) === 0
                || (str_starts_with($a, '.') && str_ends_with(strtolower($host), strtolower($a)))) {
                $inList = true;
                break;
            }
        }
        if (!$inList) {
            $host = ltrim($allowed[0], '.');
        }
    }
    define('SITE_URL', $scheme . '://' . ($host !== '' ? $host : 'localhost'));
}

date_default_timezone_set(APP_TIMEZONE);

// Settings singleton (public)
$settings = Settings::instance();
