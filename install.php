<?php
/**
 * News Portal - Installer.
 * Creates tables, seeds settings/menus, creates default admin,
 * and (optionally) adds demo content.
 *
 * Usage:
 *   php install.php                 (seeds admin + demo content)
 *   php install.php --fresh         (drop & recreate tables first)
 *   php install.php --no-demo       (skip demo content)
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Refuse web execution: install is CLI-only for safety.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('Installer can only be run from the command line.');
}

require_once __DIR__ . '/config.php';

// DB connection without selecting a failing DB name is fine; schema file creates tables.
$pdo = new PDO('mysql:host=' . DB_HOST . ';charset=utf8mb4', DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$fresh = in_array('--fresh', $argv ?? [], true);
$noDemo = in_array('--no-demo', $argv ?? [], true);

if ($fresh) {
    $pdo->exec('DROP DATABASE IF EXISTS `' . DB_NAME . '`');
}
$pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$pdo->exec('USE `' . DB_NAME . '`');

echo "Installing schema...\n";
$schema = require __DIR__ . '/app/schema.php';
$pdo->exec($schema);
echo "Schema OK.\n";

$defaults = require __DIR__ . '/app/defaults.php';

// Settings
$ins = $pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
foreach ($defaults['settings'] as $k => $v) {
    $ins->execute(['k' => $k, 'v' => $v]);
}
echo "Settings seeded.\n";

// Menus (only if empty)
$count = (int) $pdo->query('SELECT COUNT(*) FROM menus')->fetchColumn();
if ($count === 0) {
    $mi = $pdo->prepare('INSERT INTO menus (label, url, sort_order) VALUES (:l, :u, :o)');
    foreach ($defaults['menus'] as $m) {
        $mi->execute(['l' => $m['label'], 'u' => $m['url'], 'o' => $m['sort_order']]);
    }
    echo "Menu seeded.\n";
}

// Admin user (only if no users exist)
$count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count === 0) {
    $pass = 'Admin@1234';
    $pdo->prepare('INSERT INTO users (username, email, password, role, display_name, status) VALUES (?,?,?,?,?,1)')
        ->execute(['admin', 'admin@example.com', password_hash($pass, PASSWORD_DEFAULT), 'admin', 'Administrator']);
    echo "Admin created: username=admin, password={$pass} (change after first login)\n";
}

// Contact page
$pc = (int) $pdo->query("SELECT COUNT(*) FROM pages WHERE slug='contact'")->fetchColumn();
if ($pc === 0) {
    $pdo->prepare('INSERT INTO pages (title, slug, content) VALUES (?,?,?)')
        ->execute(['Contact Us', 'contact',
            '<p>Reach our newsroom:</p><ul><li>Email: contact@samacharlive.local</li><li>Phone: +91 90000 00000</li><li>Address: Bhubaneswar, Odisha, India</li></ul><p>We welcome news tips, press releases and feedback.</p>']);
    echo "Contact page created.\n";
}

if ($noDemo) {
    echo "Done. Demo content skipped.\n";
    exit;
}

echo "Seeding demo content...\n";

// Categories
$cats = [
    ['National', 'national', 'Latest news from across the country.', 1],
    ['State', 'state', 'News from your state.', 2],
    ['Politics', 'politics', 'Political developments and analysis.', 3],
    ['Business', 'business', 'Markets, economy and business news.', 4],
    ['Sports', 'sports', 'Cricket, football and more.', 5],
    ['Entertainment', 'entertainment', 'Movies, music and celebrity news.', 6],
    ['Technology', 'technology', 'Tech news, gadgets and innovation.', 7],
    ['Education', 'education', 'Exams, results and education updates.', 8],
];
$catIds = [];
$ic = $pdo->prepare('INSERT IGNORE INTO categories (name, slug, description, sort_order) VALUES (?,?,?,?)');
foreach ($cats as $i => $c) {
    $ic->execute([$c[0], $c[1], $c[2], $c[3]]);
    $catIds[$c[1]] = (int) $pdo->lastInsertId();
}

$sampleNews = [
    [
        'title' => 'State launches new digital news portal to connect citizens faster',
        'slug' => 'state-launches-new-digital-news-portal',
        'cat' => 'state',
        'tags' => 'technology,state',
        'featured' => 1, 'breaking' => 1,
        'excerpt' => 'The new platform brings real-time updates, e-paper editions and verified reporting to readers across the state.',
        'content' => '<p>Citizens can now access live updates, verified reports and the full digital edition of the newspaper from any device.</p><h2>What readers get</h2><p>The portal offers a modern, mobile-first reading experience with fast load times and a secure platform.</p><blockquote>This is a major step towards transparent and instant journalism.</blockquote><p>Reporters across all districts contribute stories, which are reviewed by editors before publication to maintain quality and accuracy.</p><ul><li>Real-time breaking news alerts</li><li>Daily e-paper edition online</li><li>Category-wise browsing</li></ul>',
    ],
    [
        'title' => 'Markets rally as investors regain confidence in new fiscal policies',
        'slug' => 'markets-rally-investors-confidence-fiscal-policies',
        'cat' => 'business',
        'tags' => 'business,markets',
        'featured' => 0, 'breaking' => 0,
        'excerpt' => 'Benchmark indices climbed on strong quarterly earnings and positive global cues.',
        'content' => '<p>Benchmark indices closed higher today, buoyed by strong quarterly earnings from leading firms and optimistic global cues.</p><h2>Key highlights</h2><p>Banking and IT sectors led the gains, while investors await further policy announcements.</p><p>Analysts suggest a cautious approach in the short term.</p>',
    ],
    [
        'title' => 'Local cricket team qualifies for national championship final',
        'slug' => 'local-cricket-team-national-championship-final',
        'cat' => 'sports',
        'tags' => 'sports,cricket',
        'featured' => 1, 'breaking' => 0,
        'excerpt' => 'A spirited chase sealed the win and a place in the final scheduled for next month.',
        'content' => '<p>In a thrilling semi-final, the local team chased down 280 runs with two balls to spare, sealing their place in the national championship final.</p><h2>Match report</h2><p>Opener smashed a brilliant century while the bowlers kept the pressure on throughout the innings.</p><p>The final will be played next month at the state stadium.</p>',
    ],
    [
        'title' => 'New technology hub to create thousands of jobs in the region',
        'slug' => 'technology-hub-thousands-jobs-region',
        'cat' => 'technology',
        'tags' => 'technology,jobs',
        'featured' => 0, 'breaking' => 0,
        'excerpt' => 'The 500-acre hub will host startups and global tech companies, with an estimated 25,000 jobs over five years.',
        'content' => '<p>A new technology hub spanning 500 acres will open its first phase next year, hosting startups and global technology companies.</p><h2>Economic impact</h2><p>Officials estimate the hub will create up to 25,000 direct jobs over the next five years, boosting the local economy significantly.</p>',
    ],
    [
        'title' => 'New education policy to introduce skill-based learning from this session',
        'slug' => 'education-policy-skill-based-learning',
        'cat' => 'education',
        'tags' => 'education,policy',
        'featured' => 0, 'breaking' => 0,
        'excerpt' => 'Schools and colleges will roll out revised curricula focused on practical skills and digital literacy.',
        'content' => '<p>From this academic session, schools and colleges will roll out a revised curriculum focused on practical skills, digital literacy and critical thinking.</p><h2>What changes</h2><p>Students will get early exposure to coding, financial literacy and entrepreneurship alongside traditional subjects.</p>',
    ],
    [
        'title' => 'Film festival to showcase regional cinema from across the country',
        'slug' => 'film-festival-regional-cinema',
        'cat' => 'entertainment',
        'tags' => 'movies,entertainment',
        'featured' => 0, 'breaking' => 0,
        'excerpt' => 'A week-long festival will screen over 60 regional films, with workshops and masterclasses by acclaimed filmmakers.',
        'content' => '<p>A week-long film festival will showcase over 60 regional films from across the country, celebrating diverse languages and storytelling traditions.</p><p>The festival includes workshops, masterclasses and panel discussions with acclaimed filmmakers.</p>',
    ],
    [
        'title' => 'Parliament approves major infrastructure spending bill',
        'slug' => 'parliament-approves-infrastructure-spending-bill',
        'cat' => 'politics',
        'tags' => 'politics,parliament',
        'featured' => 0, 'breaking' => 1,
        'excerpt' => 'The bill allocates funds for highways, railways, and rural connectivity projects over the next three years.',
        'content' => '<p>Parliament today approved a major infrastructure spending bill allocating funds for highways, railways and rural connectivity projects over the next three years.</p><h2>Reactions</h2><p>Lawmakers from all parties welcomed the focus on regional development, though some raised concerns over implementation timelines.</p>',
    ],
    [
        'title' => 'Coastal cleanup drive collects 40 tonnes of waste in a day',
        'slug' => 'coastal-cleanup-drive-40-tonnes-waste',
        'cat' => 'state',
        'tags' => 'environment,state',
        'featured' => 0, 'breaking' => 0,
        'excerpt' => 'Thousands of volunteers joined the drive along the coastline, collecting 40 tonnes of plastic and waste.',
        'content' => '<p>Thousands of volunteers joined a massive cleanup drive along the coastline, collecting over 40 tonnes of plastic and waste in a single day.</p><p>Environmental groups are now calling for stricter single-use plastic regulations.</p>',
    ],
];

$authorId = (int) $pdo->query("SELECT id FROM users WHERE username='admin' LIMIT 1")->fetchColumn();
$ic2 = $pdo->prepare('INSERT INTO news (title, slug, excerpt, content, image, category_id, author_id, tags, status, featured, breaking, published_at, views)
    VALUES (?,?,?,?,NULL,?,?,?,?,?,?,NOW(),?)');
$demoCatId = null;
foreach ($sampleNews as $n) {
    $catId = $catIds[$n['cat']] ?? $catIds['state'];
    $views = random_int(100, 5000);
    $ic2->execute([$n['title'], $n['slug'], $n['excerpt'], $n['content'], $catId, $authorId, $n['tags'], 'published', $n['featured'], $n['breaking'], $views]);
}

echo "Demo content seeded.\n";
echo "\nInstallation complete!\n";
echo "  Frontend: http://localhost/\n";
echo "  Admin:    http://localhost/admin/  (admin / Admin@1234)\n";
echo "  Reporter: http://localhost/reporter/\n";
