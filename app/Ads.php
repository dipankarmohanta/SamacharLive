<?php
/**
 * News Portal - Advertisement helper.
 * Renders ad slots and third-party integration scripts on the public site.
 */

final class Ads
{
    /** All available ad placements (key => admin-facing label). */
    public static function placements(): array
    {
        return [
            'header'         => 'Header (below the main navigation)',
            'home_top'       => 'Homepage (below the hero section)',
            'category_top'   => 'Category pages (above the story list)',
            'article_top'    => 'Article pages (above the story)',
            'article_bottom' => 'Article pages (below the story)',
            'sidebar_top'    => 'Sidebar (top)',
            'sidebar_bottom' => 'Sidebar (bottom)',
            'footer'         => 'Footer (above the copyright bar)',
        ];
    }

    public static function placementLabel(string $key): string
    {
        $map = self::placements();
        return $map[$key] ?? $key;
    }

    /** Master on/off switch from Settings -> Ad Settings. */
    public static function enabled(): bool
    {
        return (bool) setting('ads_enabled', '1');
    }

    /** Placements the admin chose to enable (array of placement keys). */
    public static function placementsOn(): array
    {
        $raw = (string) setting('ads_placements', '');
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public static function placementOn(string $placement): bool
    {
        return self::enabled() && in_array($placement, self::placementsOn(), true);
    }

    /** Active (live + valid date window) ads for a placement. */
    public static function active(string $placement): array
    {
        if (!self::placementOn($placement)) { return []; }
        try {
            return DB::fetchAll(
                "SELECT * FROM ads
                 WHERE status = 1 AND placement = :p
                   AND (start_date IS NULL OR start_date <= CURDATE())
                   AND (end_date IS NULL OR end_date >= CURDATE())
                 ORDER BY id ASC",
                ['p' => $placement]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Output every active ad for a placement. */
    public static function render(string $placement): void
    {
        foreach (self::active($placement) as $ad) {
            echo '<div class="ad-slot" data-placement="' . e($placement) . '">';
            if ($ad['type'] === 'banner' && trim((string) $ad['code']) !== '') {
                echo $ad['code'];
            } else {
                $img = $ad['image'] ? '/' . ltrim($ad['image'], '/') : asset('img/placeholder.svg');
                $alt = trim((string) $ad['name']) !== '' ? $ad['name'] : 'advertisement';
                $href = trim((string) $ad['link_url']) !== '' ? $ad['link_url'] : '#';
                echo '<a href="' . e($href) . '" target="_blank" rel="noopener nofollow sponsored">';
                echo lazy_img($img, $alt, 'ad-img', '0', '0');
                echo '</a>';
            }
            echo '</div>';
        }
    }

    /** Output integration scripts (Meta pixel, AdSense, analytics...) for a position. */
    public static function renderIntegrations(string $position): void
    {
        try {
            $rows = DB::fetchAll(
                "SELECT * FROM ad_integrations WHERE status = 1 AND position = :pos ORDER BY id ASC",
                ['pos' => $position]
            );
        } catch (Throwable $e) {
            return;
        }
        foreach ($rows as $row) {
            echo $row['code'];
        }
    }

    /**
     * Idempotently create the ads tables if they do not exist.
     * Used so the feature works on existing installs without needing to
     * run php migrate.php manually (e.g. shared cPanel hosting).
     */
    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) { return; }
        $done = true;
        try {
            DB::run(
                "CREATE TABLE IF NOT EXISTS ads (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  name VARCHAR(150) NOT NULL,
                  info VARCHAR(500) DEFAULT NULL,
                  type ENUM('image','banner') NOT NULL DEFAULT 'image',
                  image VARCHAR(255) DEFAULT NULL,
                  link_url VARCHAR(500) DEFAULT NULL,
                  code TEXT DEFAULT NULL,
                  placement VARCHAR(40) NOT NULL DEFAULT 'home_top',
                  start_date DATE DEFAULT NULL,
                  end_date DATE DEFAULT NULL,
                  status TINYINT(1) NOT NULL DEFAULT 1,
                  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  KEY idx_placement (placement),
                  KEY idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            DB::run(
                "CREATE TABLE IF NOT EXISTS ad_integrations (
                  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                  name VARCHAR(150) NOT NULL,
                  provider VARCHAR(40) NOT NULL DEFAULT 'custom',
                  position ENUM('head','body_top','body_bottom') NOT NULL DEFAULT 'head',
                  code TEXT NOT NULL,
                  status TINYINT(1) NOT NULL DEFAULT 1,
                  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                  KEY idx_position (position),
                  KEY idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            // If DDL is not permitted, the admin pages will surface a clear
            // message instead of a silent 500.
        }
    }
}
