<?php
/**
 * Route: XML sitemap.
 */
header('Content-Type: application/xml; charset=utf-8');
$base = rtrim(site_url(), '/');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Static
echo '<url><loc>' . e($base . '/') . '</loc><priority>1.0</priority></url>' . "\n";
if (setting('epaper_enabled', '1')) {
    echo '<url><loc>' . e($base . '/epaper') . '</loc><priority>0.9</priority></url>' . "\n";
}

// Categories
foreach (DB::fetchAll("SELECT slug, name FROM categories WHERE status = 1 AND noindex = 0") as $c) {
    echo '<url><loc>' . e($base . '/category/' . $c['slug']) . '</loc><priority>0.8</priority></url>' . "\n";
}

// Pages
foreach (DB::fetchAll("SELECT slug FROM pages WHERE status = 1 AND noindex = 0") as $p) {
    echo '<url><loc>' . e($base . '/page/' . $p['slug']) . '</loc><priority>0.6</priority></url>' . "\n";
}

// News
foreach (DB::fetchAll(
    "SELECT slug, published_at FROM news WHERE status='published' AND noindex = 0 AND published_at IS NOT NULL AND published_at <= NOW() ORDER BY published_at DESC LIMIT 5000"
) as $n) {
    $lastmod = $n['published_at'] ? date('Y-m-d', strtotime($n['published_at'])) : '';
    echo '<url><loc>' . e($base . '/news/' . $n['slug']) . '</loc><lastmod>' . $lastmod . '</lastmod><priority>0.7</priority></url>' . "\n";
}

echo '</urlset>';
exit;
