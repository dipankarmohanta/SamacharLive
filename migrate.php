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

// New tables introduced after first release (created for existing installs).
$newTables = [
    'ads' => "CREATE TABLE IF NOT EXISTS ads (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    'ad_integrations' => "CREATE TABLE IF NOT EXISTS ad_integrations (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      name VARCHAR(150) NOT NULL,
      provider VARCHAR(40) NOT NULL DEFAULT 'custom',
      position ENUM('head','body_top','body_bottom') NOT NULL DEFAULT 'head',
      code TEXT NOT NULL,
      status TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      KEY idx_position (position),
      KEY idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];
foreach ($newTables as $table => $ddl) {
    $pdo->exec($ddl);
    echo "  [ok]   ensured table $table\n";
}
$applied += count($newTables);

echo $applied > 0 ? "Migration complete ($applied item(s) applied).\n" : "Nothing to migrate.\n";
