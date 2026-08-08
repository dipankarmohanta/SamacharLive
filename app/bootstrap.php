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

// Load settings early so host validation can use DB-stored custom domains.
$settings = Settings::instance();

Security::sendSecurityHeaders();

// Resolve site URL from request
if (!defined('SITE_URL')) {
    $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $forwarded === 'https';
    $scheme = $https ? 'https' : 'http';

    // Validate HTTP_HOST before trusting it (Host header injection / cache poisoning).
    $host = preg_replace('/[\r\n\0]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
    $hostValid = preg_match('/^[a-z0-9]([a-z0-9\-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]*[a-z0-9])?)*(:\d{1,5})?$/i', $host);
    $hostClean = $hostValid ? (preg_replace('/:\d+$/', '', $host) ?? '') : '';

    $allowed = allowed_domains();
    $hostAllowed = false;
    foreach ($allowed as $a) {
        $domain = ltrim($a, '.');
        if ($domain === '') {
            continue;
        }
        if (strcasecmp($a, $hostClean) === 0 || strcasecmp($domain, $hostClean) === 0
            || (str_starts_with($a, '.') && str_ends_with(strtolower($hostClean), strtolower($a)))) {
            $hostAllowed = true;
            break;
        }
    }

    // When a domain allowlist is configured, only those hosts are trusted and
    // any unknown host falls back to the primary (first) allowed domain so
    // canonical/og/sitemap URLs can't be poisoned. Without an allowlist any
    // syntactically valid host is accepted (local/dev/preview setups).
    if (!$hostValid || ($allowed !== [] && !$hostAllowed)) {
        $host = ltrim($allowed[0] ?? '', '.');
    }
    define('SITE_URL', $scheme . '://' . ($host !== '' ? $host : 'localhost'));
}

date_default_timezone_set(APP_TIMEZONE);
