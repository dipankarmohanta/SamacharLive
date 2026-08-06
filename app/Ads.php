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
        return DB::fetchAll(
            "SELECT * FROM ads
             WHERE status = 1 AND placement = :p
               AND (start_date IS NULL OR start_date <= CURDATE())
               AND (end_date IS NULL OR end_date >= CURDATE())
             ORDER BY id ASC",
            ['p' => $placement]
        );
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
        $rows = DB::fetchAll(
            "SELECT * FROM ad_integrations WHERE status = 1 AND position = :pos ORDER BY id ASC",
            ['pos' => $position]
        );
        foreach ($rows as $row) {
            echo $row['code'];
        }
    }
}
