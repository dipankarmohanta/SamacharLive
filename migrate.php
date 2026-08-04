<?php
/**
 * News Portal - Migration runner (CLI).
 * Adds the on-page SEO columns to news / pages / categories if missing.
 * Idempotent: safe to run multiple times.
 *
 * Usage: php migrate.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Migrations can only be run from the command line.');
}

require_once __DIR__ . '/config.php';

$pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$additions = [
    'categories' => [
        'seo_title'       => 'VARCHAR(200) DEFAULT NULL',
        'seo_description' => 'VARCHAR(320) DEFAULT NULL',
        'noindex'         => "TINYINT(1) NOT NULL DEFAULT 0",
    ],
    'news' => [
        'seo_title'       => 'VARCHAR(200) DEFAULT NULL',
        'seo_description' => 'VARCHAR(320) DEFAULT NULL',
        'focus_keyword'   => 'VARCHAR(100) DEFAULT NULL',
        'canonical_url'   => 'VARCHAR(255) DEFAULT NULL',
        'noindex'         => "TINYINT(1) NOT NULL DEFAULT 0",
    ],
    'pages' => [
        'seo_title'       => 'VARCHAR(200) DEFAULT NULL',
        'seo_description' => 'VARCHAR(320) DEFAULT NULL',
        'canonical_url'   => 'VARCHAR(255) DEFAULT NULL',
        'noindex'         => "TINYINT(1) NOT NULL DEFAULT 0",
    ],
];

$applied = 0;
foreach ($additions as $table => $cols) {
    $existing = $pdo->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = " . $pdo->quote(DB_NAME) . " AND TABLE_NAME = " . $pdo->quote($table))->fetchAll(PDO::FETCH_COLUMN);
    foreach ($cols as $col => $def) {
        if (in_array($col, $existing, true)) {
            echo "  [skip] $table.$col (already exists)\n";
            continue;
        }
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
        echo "  [ok]   added $table.$col\n";
        $applied++;
    }
}
echo $applied > 0 ? "Migration complete ($applied column(s) added).\n" : "Nothing to migrate.\n";
