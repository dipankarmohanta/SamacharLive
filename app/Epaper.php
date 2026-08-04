<?php
/**
 * News Portal - Epaper helper.
 * Renders uploaded PDFs into page images (via pdftoppm) for the page-flip viewer.
 */

final class Epaper
{
    /**
     * Render a PDF (relative path under uploads) into page images.
     * Stores images at uploads/epaper/pages/<epaperId>/.
     * Returns ['page_images' => [...relative paths...], 'cover' => relative path|null].
     */
    public static function renderPdf(int $epaperId, string $pdfRelPath): array
    {
        $pdfAbs = BASE_PATH . '/' . ltrim($pdfRelPath, '/');
        if (!is_file($pdfAbs)) {
            return ['page_images' => [], 'cover' => null];
        }

        $outDir = UPLOAD_PATH . '/epaper/pages/' . $epaperId;
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        // Clean existing rendered pages for this issue
        foreach (glob($outDir . '/*.webp') ?: [] as $f) {
            @unlink($f);
        }

        // Render pages to jpg at 100 dpi for readable page images
        $escPrefix = escapeshellarg($outDir . '/page');
        @exec("timeout 120 pdftoppm -jpeg -r 100 " . escapeshellarg($pdfAbs) . " {$escPrefix} 2>&1", $output, $code);

        $pages = [];
        if ($code === 0) {
            $files = glob($outDir . '/page-*.jpg') ?: glob($outDir . '/page*.jpg') ?: [];
            sort($files);
            foreach ($files as $f) {
                $pages[] = 'uploads/epaper/pages/' . $epaperId . '/' . basename($f);
            }
        }

        $cover = $pages[0] ?? null;
        return ['page_images' => $pages, 'cover' => $cover];
    }

    /** Get rendered page images for an epaper issue (as relative URLs). */
    public static function pageImages(int $epaperId): array
    {
        $dir = UPLOAD_PATH . '/epaper/pages/' . $epaperId;
        $files = is_dir($dir) ? (glob($dir . '/page*.jpg') ?: []) : [];
        sort($files);
        return array_map(fn($f) => 'uploads/epaper/pages/' . $epaperId . '/' . basename($f), $files);
    }

    /** Extract text page count for a PDF. */
    public static function pdfPageCount(string $pdfRelPath): int
    {
        $abs = BASE_PATH . '/' . ltrim($pdfRelPath, '/');
        if (!is_file($abs)) {
            return 1;
        }
        $info = @shell_exec('timeout 30 pdfinfo ' . escapeshellarg($abs) . ' 2>/dev/null');
        if ($info && preg_match('/Pages:\s+(\d+)/', $info, $m)) {
            return max(1, (int) $m[1]);
        }
        return 1;
    }
}
