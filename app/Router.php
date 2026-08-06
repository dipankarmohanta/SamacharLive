<?php
/**
 * News Portal - Minimal front controller router.
 * Routes: /, /category/:slug, /news/:slug, /epaper, /epaper/:id, /page/:slug, /search, /tag/:tag
 */

require_once __DIR__ . '/bootstrap.php';

// Resolve path from either REQUEST_URI (clean URLs) or ?route= (fallback)
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (isset($_GET['route'])) {
    $path = '/' . ltrim((string) $_GET['route'], '/');
}

// Strip base path if installed in a subdirectory
$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = '/' . trim($path, '/');
if ($path !== '/') {
    $path = rtrim($path, '/');
}

$segments = $path === '/' ? [] : array_values(array_filter(explode('/', $path)));

Security::secureSession();

$settings = Settings::instance();

// Maintenance mode gate (public visitors only)
if ($settings->bool('maintenance_mode') && (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin')) {
    http_response_code(503);
    require BASE_PATH . '/views/maintenance.php';
    exit;
}

$route = $segments[0] ?? 'home';

switch ($route) {
    case 'home':
    case '':
        require BASE_PATH . '/app/routes/home.php';
        break;

    case 'category':
        require BASE_PATH . '/app/routes/category.php';
        break;

    case 'news':
    case 'article':
        require BASE_PATH . '/app/routes/news.php';
        break;

    case 'epaper':
        if (!$settings->bool('epaper_enabled', true)) { Security::notFound('E-paper is not available.'); }
        require BASE_PATH . '/app/routes/epaper.php';
        break;

    case 'page':
        require BASE_PATH . '/app/routes/page.php';
        break;

    case 'search':
        require BASE_PATH . '/app/routes/search.php';
        break;

    case 'tag':
        require BASE_PATH . '/app/routes/tag.php';
        break;

    case 'sitemap':
        require BASE_PATH . '/app/routes/sitemap.php';
        break;

    default:
        Security::notFound();
}
