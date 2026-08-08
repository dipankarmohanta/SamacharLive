<?php
/**
 * News Portal - Shared helper functions.
 */

/** Escaped output shortcut. */
function e(mixed $value): string
{
    return Security::e($value);
}

/** Global settings getter. */
function setting(string $key, ?string $default = null): ?string
{
    return Settings::instance()->get($key, $default);
}

/**
 * Allowed hosts for canonical/og/sitemap URLs.
 * Merges the APP_ALLOWED_HOSTS constant with the DB-stored custom_domains
 * setting (newline or comma separated). Entries may be exact hostnames or
 * subdomain wildcards with a leading dot (e.g. .example.com).
 */
function allowed_domains(): array
{
    $list = [];
    if (defined('APP_ALLOWED_HOSTS') && APP_ALLOWED_HOSTS !== '') {
        foreach (explode(',', APP_ALLOWED_HOSTS) as $d) {
            $d = strtolower(trim($d));
            if ($d !== '') {
                $list[] = $d;
            }
        }
    }
    foreach (preg_split('/[\r\n,]+/', (string) setting('custom_domains')) as $d) {
        $d = strtolower(trim($d));
        if ($d !== '') {
            $list[] = $d;
        }
    }
    return array_values(array_unique($list));
}

/** Relative asset URL. */
function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

/** URL for the site favicon (uploaded setting or the bundled default). */
function favicon_url(): string
{
    $fav = setting('site_favicon');
    if ($fav) {
        return '/' . ltrim($fav, '/');
    }
    return asset('img/favicon.svg');
}

/** Normalize text for a meta description (single line, ~160 chars). */
function meta_desc(?string $text, int $limit = 160): string
{
    $text = trim((string) $text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return Security::truncate($text, $limit, '');
}

/** Site URL for a route path. */
function site_url(string $path = ''): string
{
    return SITE_URL . '/' . ltrim($path, '/');
}

/** Format date nicely. */
function fmt_date(?string $date, string $format = 'M j, Y'): string
{
    if (empty($date)) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date($format, $ts) : '';
}

/** Human relative time. */
function time_ago(?string $datetime): string
{
    if (empty($datetime)) {
        return '';
    }
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . ' min ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . ' hour' . (floor($diff / 3600) > 1 ? 's' : '') . ' ago';
    }
    if ($diff < 604800) {
        return floor($diff / 86400) . ' day' . (floor($diff / 86400) > 1 ? 's' : '') . ' ago';
    }
    return date('M j, Y', $ts);
}

/** Build category list (with parent handling) for menus / selects. */
function category_tree(): array
{
    $cats = DB::fetchAll('SELECT * FROM categories WHERE status = 1 ORDER BY sort_order ASC, name ASC');
    $tree = [];
    $byParent = [];
    foreach ($cats as $c) {
        $byParent[(int) $c['parent_id']][] = $c;
    }
    $walk = function ($parentId, $depth) use (&$walk, &$byParent, &$tree) {
        foreach ($byParent[$parentId] ?? [] as $c) {
            $c['depth'] = $depth;
            $tree[] = $c;
            $walk((int) $c['id'], $depth + 1);
        }
    };
    $walk(0, 0);
    return $tree;
}

/** Site menu items (top nav) as a nested tree with parent/child hierarchy. */
function menu_tree(): array
{
    $rows = DB::fetchAll('SELECT * FROM menus WHERE status = 1 ORDER BY sort_order ASC, id ASC');
    $cats = DB::fetchAll('SELECT name, slug FROM categories WHERE status = 1 ORDER BY sort_order ASC');

    $items = [];
    foreach ($rows as $m) {
        if (!setting('epaper_enabled', '1') && ($m['url'] === '/epaper' || str_starts_with($m['url'], '/epaper'))) {
            continue;
        }
        $items[] = [
            'id' => (int) $m['id'],
            'label' => $m['label'],
            'url' => $m['url'],
            'parent_id' => (int) $m['parent_id'],
            'sort' => (int) $m['sort_order'],
            'kind' => 'menu',
        ];
    }
    foreach ($cats as $c) {
        $items[] = [
            'id' => 'cat-' . $c['slug'],
            'label' => $c['name'],
            'url' => '/category/' . $c['slug'],
            'parent_id' => 0,
            'sort' => 0,
            'kind' => 'category',
        ];
    }

    $byParent = [];
    $itemIds = [];
    foreach ($items as $it) { $itemIds[] = $it['id']; }
    $itemIds = array_flip($itemIds);
    foreach ($items as $it) {
        $pid = isset($itemIds[$it['parent_id']]) ? $it['parent_id'] : 0;
        $byParent[$pid][] = $it;
    }
    foreach ($byParent as &$children) {
        usort($children, fn($a, $b) => [$a['sort'], $a['label']] <=> [$b['sort'], $b['label']]);
    }
    unset($children);

    $build = function ($parentId) use (&$build, &$byParent): array {
        $tree = [];
        foreach ($byParent[$parentId] ?? [] as $child) {
            $child['children'] = $build($child['id']);
            $tree[] = $child;
        }
        return $tree;
    };

    return $build(0);
}

/** Recursively render a nested navigation menu (WordPress-style dropdowns). */
function render_nav_menu(array $tree, bool $root = true): void
{
    echo '<ul' . ($root ? ' class="nav-menu" id="nav-menu"' : ' class="dropdown"') . '>';
    foreach ($tree as $item) {
        $hasChildren = !empty($item['children']);
        $url = $item['url'] ?? '#';
        $isActive = ($url !== '/' && str_starts_with($_SERVER['REQUEST_URI'] ?? '', $url));
        $liClass = ($hasChildren ? 'has-sub' : '') . ($isActive ? ' active' : '');
        echo '<li' . ($liClass !== '' ? ' class="' . trim($liClass) . '"' : '') . '>';
        echo '<a href="' . e($url) . '"' . ($hasChildren ? ' aria-haspopup="true"' : '') . '>'
           . e($item['label'])
           . ($hasChildren ? ' <span class="caret">&#9662;</span>' : '')
           . '</a>';
        if ($hasChildren) {
            render_nav_menu($item['children'], false);
        }
        echo '</li>';
    }
    echo '</ul>';
}

/** Print a lazy-loading <img> tag with width/height hint to prevent CLS. */
function lazy_img(string $src, string $alt = '', string $class = '', string $width = '0', string $height = '0'): string
{
    $dim = '';
    if ($width && $height) {
        $dim = ' width="' . e($width) . '" height="' . e($height) . '"';
    }
    $classAttr = $class !== '' ? ' class="' . e($class) . '"' : '';
    $loading = ' loading="lazy" decoding="async"';
    return '<img src="' . e($src) . '" alt="' . e($alt) . '"' . $classAttr . $dim . $loading . '>';
}

/** JSON response helper. */
function json_response(mixed $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/** Flash message helpers (admin/reporter panels). */
function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (isset($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

/** Pagination helper. */
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current' => $page,
        'pages' => $pages,
        'offset' => ($page - 1) * $perPage,
    ];
}
