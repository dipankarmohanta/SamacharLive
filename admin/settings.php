<?php
/**
 * Admin - Settings: Site info, Theme customization, Navigation menu, SEO, Maintenance.
 */
require_once __DIR__ . '/includes/init.php';
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    die('Only administrators can change settings.');
}

$activeTab = $_GET['tab'] ?? 'general';
$allowedTabs = ['general', 'theme', 'menu', 'seo', 'social'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::csrfValidate();
    $tab = $_POST['tab'] ?? 'general';
    if (!in_array($tab, $allowedTabs, true)) { $tab = 'general'; }

    if ($tab === 'menu') {
        $raw = trim((string) ($_POST['menu_tree'] ?? ''));
        $nodes = json_decode($raw, true);
        if (!is_array($nodes)) {
            flash_set('error', 'Invalid menu data. Please try again.');
            header('Location: settings.php?tab=menu');
            exit;
        }

        $maxDepth = 3;
        $maxItems = 200;
        $count = 0;

        $sanitizeUrl = static function (string $url): string {
            $url = trim($url);
            if ($url === '' || $url === '#') { return '#'; }
            if (preg_match('~^https?://~i', $url)) { return $url; }
            if (str_starts_with($url, '/') && !str_starts_with($url, '//')) { return $url; }
            return '#';
        };

        DB::run('DELETE FROM menus');
        $insert = DB::conn()->prepare('INSERT INTO menus (label, url, parent_id, sort_order) VALUES (:l, :u, :p, :o)');

        $insertNodes = function (array $nodes, int $parentId, int $depth, int &$order) use (&$insertNodes, $insert, $sanitizeUrl, &$count, $maxDepth, $maxItems): void {
            foreach ($nodes as $node) {
                if ($count >= $maxItems) { return; }
                $label = trim((string) ($node['label'] ?? ''));
                if ($label === '') { continue; }
                $label = mb_substr($label, 0, 100);
                $url = $sanitizeUrl((string) ($node['url'] ?? ''));
                $insert->execute(['l' => $label, 'u' => $url, 'p' => $parentId, 'o' => $order++]);
                $count++;
                $newId = (int) DB::conn()->lastInsertId();
                $children = (array) ($node['children'] ?? []);
                if ($children && $depth < $maxDepth) {
                    $insertNodes($children, $newId, $depth + 1, $order);
                }
            }
        };

        $order = 0;
        $insertNodes($nodes, 0, 1, $order);

        flash_set('success', 'Navigation menu updated.');
        header('Location: settings.php?tab=menu');
        exit;
    }

    if ($tab === 'theme') {
        // Validate color hex values
        $colorFields = ['theme_primary', 'theme_secondary', 'theme_accent'];
        $bad = false;
        foreach ($colorFields as $f) {
            $v = trim((string) ($_POST[$f] ?? ''));
            if ($v !== '' && !preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $v)) { $bad = true; }
        }
        if ($bad) {
            flash_set('error', 'Invalid color value. Use hex format like #c62828.');
            header('Location: settings.php?tab=theme');
            exit;
        }
        $themeValues = [];
        foreach ($colorFields as $f) { $themeValues[$f] = trim((string) ($_POST[$f] ?? '')); }
        $headerPresets = ['classic', 'modern', 'compact'];
        $footerPresets = ['classic', 'minimal', 'rich'];
        $themeValues['header_style'] = in_array(($_POST['header_style'] ?? 'classic'), $headerPresets, true) ? $_POST['header_style'] : 'classic';
        $themeValues['footer_style'] = in_array(($_POST['footer_style'] ?? 'classic'), $footerPresets, true) ? $_POST['footer_style'] : 'classic';
        $themeValues['header_breaking'] = isset($_POST['header_breaking']) ? '1' : '0';
        Settings::update($themeValues);
        flash_set('success', 'Theme settings saved. The public site updates immediately.');
        header('Location: settings.php?tab=theme');
        exit;
    }

    if ($tab === 'seo') {
        Settings::update([
            'seo_meta_description' => trim((string) ($_POST['seo_meta_description'] ?? '')),
            'google_analytics'     => trim((string) ($_POST['google_analytics'] ?? '')),
            'site_lang'            => preg_replace('/[^a-z0-9\-]/i', '', (string) ($_POST['site_lang'] ?? 'en')) ?: 'en',
        ]);
        flash_set('success', 'SEO settings saved.');
        header('Location: settings.php?tab=seo');
        exit;
    }

    if ($tab === 'social') {
        Settings::update([
            'facebook'   => trim((string) ($_POST['facebook'] ?? '')),
            'twitter'    => trim((string) ($_POST['twitter'] ?? '')),
            'instagram'  => trim((string) ($_POST['instagram'] ?? '')),
            'youtube'    => trim((string) ($_POST['youtube'] ?? '')),
        ]);
        flash_set('success', 'Social links saved.');
        header('Location: settings.php?tab=social');
        exit;
    }

    // General tab: site info + logo upload
    $generalValues = [
        'site_name'    => trim((string) ($_POST['site_name'] ?? '')),
        'site_tagline' => trim((string) ($_POST['site_tagline'] ?? '')),
        'site_email'   => trim((string) ($_POST['site_email'] ?? '')),
        'site_phone'   => trim((string) ($_POST['site_phone'] ?? '')),
        'site_address' => trim((string) ($_POST['site_address'] ?? '')),
        'site_footer_text' => (string) ($_POST['site_footer_text'] ?? ''),
        'news_per_page'    => max(4, (int) ($_POST['news_per_page'] ?? 12)),
    ];
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $logo = Security::uploadImage($_FILES['logo'], 'logos', 1024);
        if ($logo) { $generalValues['site_logo'] = $logo; }
        else { flash_set('error', 'Logo upload failed. Use a valid image under 1MB.'); header('Location: settings.php?tab=general'); exit; }
    }
    if (isset($_POST['remove_logo'])) { $generalValues['site_logo'] = ''; }
    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] !== UPLOAD_ERR_NO_FILE) {
        $favicon = Security::uploadImage($_FILES['favicon'], 'logos', 512);
        if ($favicon) { $generalValues['site_favicon'] = $favicon; }
        else { flash_set('error', 'Favicon upload failed. Use a valid image under 512KB.'); header('Location: settings.php?tab=general'); exit; }
    }
    if (isset($_POST['remove_favicon'])) { $generalValues['site_favicon'] = ''; }
    $generalValues['maintenance_mode'] = isset($_POST['maintenance_mode']) ? '1' : '0';
    $generalValues['epaper_enabled'] = isset($_POST['epaper_enabled']) ? '1' : '0';
    Settings::update($generalValues);
    flash_set('success', 'Site settings saved.');
    header('Location: settings.php?tab=general');
    exit;
}

$pageTitle = 'Settings';
require_once __DIR__ . '/includes/layout.php';
?>
<div class="tabs">
  <a href="settings.php?tab=general" class="<?php echo $activeTab === 'general' ? 'active' : ''; ?>">General</a>
  <a href="settings.php?tab=theme" class="<?php echo $activeTab === 'theme' ? 'active' : ''; ?>">Theme</a>
  <a href="settings.php?tab=menu" class="<?php echo $activeTab === 'menu' ? 'active' : ''; ?>">Navigation</a>
  <a href="settings.php?tab=seo" class="<?php echo $activeTab === 'seo' ? 'active' : ''; ?>">SEO</a>
  <a href="settings.php?tab=social" class="<?php echo $activeTab === 'social' ? 'active' : ''; ?>">Social</a>
</div>

<?php if ($activeTab === 'general'): ?>
<div class="adm-card">
  <h2>Site Information</h2>
  <form method="post" enctype="multipart/form-data" class="adm-form">
    <?php echo Security::csrfField(); ?>
    <input type="hidden" name="tab" value="general">
    <div class="row">
      <div>
        <label>Site Name *</label>
        <input type="text" name="site_name" value="<?php echo e(setting('site_name')); ?>" required>
      </div>
      <div>
        <label>Tagline</label>
        <input type="text" name="site_tagline" value="<?php echo e(setting('site_tagline')); ?>">
      </div>
    </div>
    <div class="row-3">
      <div><label>Email</label><input type="email" name="site_email" value="<?php echo e(setting('site_email')); ?>"></div>
      <div><label>Phone</label><input type="text" name="site_phone" value="<?php echo e(setting('site_phone')); ?>"></div>
      <div><label>Address</label><input type="text" name="site_address" value="<?php echo e(setting('site_address')); ?>"></div>
    </div>
    <label>Footer Text (HTML allowed)</label>
    <textarea name="site_footer_text" rows="2"><?php echo e(setting('site_footer_text')); ?></textarea>
    <div class="row">
      <div>
        <label>News Per Page</label>
        <input type="number" name="news_per_page" value="<?php echo e(setting('news_per_page', '12')); ?>" min="4" max="60">
      </div>
      <div>
        <label>Site Logo</label>
        <input type="file" name="logo" accept="image/*">
        <?php if (setting('site_logo')): ?>
          <div class="mt-1" style="display:flex; gap:12px; align-items:center">
            <img class="preview-img" src="/<?php echo e(ltrim(setting('site_logo'), '/')); ?>" alt="logo">
            <label style="display:flex; gap:6px; align-items:center"><input type="checkbox" name="remove_logo" value="1"> Remove logo</label>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="row">
      <div>
        <label>Favicon</label>
        <input type="file" name="favicon" accept=".ico,image/png,image/x-icon,image/svg+xml">
        <p class="hint">Browser tab icon. Recommended: 32x32 px PNG, ICO, or SVG (max 512KB).</p>
        <?php if (setting('site_favicon')): ?>
          <div class="mt-1" style="display:flex; gap:12px; align-items:center">
            <img src="/<?php echo e(ltrim(setting('site_favicon'), '/')); ?>" alt="favicon" style="width:32px;height:32px;object-fit:contain">
            <label style="display:flex; gap:6px; align-items:center"><input type="checkbox" name="remove_favicon" value="1"> Remove favicon</label>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <div class="mt-1" style="display:flex; gap:8px; align-items:center">
      <input type="checkbox" name="maintenance_mode" value="1" <?php echo setting('maintenance_mode') ? 'checked' : ''; ?>> Enable maintenance mode (public site shows maintenance notice)
    </div>
    <div class="adm-actions"><button class="btn" type="submit">Save General Settings</button></div>
  </form>
</div>

<div class="adm-card">
  <h2>Feature Settings</h2>
  <form method="post" class="adm-form">
    <?php echo Security::csrfField(); ?>
    <input type="hidden" name="tab" value="general">
    <div class="mt-1" style="display:flex; gap:8px; align-items:center">
      <input type="checkbox" name="epaper_enabled" value="1" <?php echo setting('epaper_enabled', '1') ? 'checked' : ''; ?>> Enable E-paper feature
    </div>
    <p class="hint">Turn off to hide E-paper links on the public site and make /epaper return a 404 page. Existing issues stay saved in the admin panel.</p>
    <div class="adm-actions"><button class="btn" type="submit">Save Feature Settings</button></div>
  </form>
</div>
<?php endif; ?>

<?php if ($activeTab === 'theme'): ?>
<?php
    $headerPresets = [
        'classic' => [
            'name' => 'Classic',
            'desc' => 'Top bar, centered logo, full-width menu bar.',
            'thumb' => '<span class="pt-topbar"></span><span class="pt-header"><span class="pt-logo pt-logo-center"></span><span class="pt-actions"></span></span><span class="pt-nav"></span>',
        ],
        'modern' => [
            'name' => 'Modern',
            'desc' => 'Slim sticky row: logo left, inline menu, search right.',
            'thumb' => '<span class="pt-header"><span class="pt-logo"></span><span class="pt-inline-nav"></span><span class="pt-actions"></span></span>',
        ],
        'compact' => [
            'name' => 'Compact',
            'desc' => 'Thin top bar, tight header and compact menu.',
            'thumb' => '<span class="pt-topbar"></span><span class="pt-header pt-header-sm"><span class="pt-logo"></span><span class="pt-actions"></span></span><span class="pt-nav pt-nav-sm"></span>',
        ],
    ];
    $footerPresets = [
        'classic' => [
            'name' => 'Classic',
            'desc' => 'Four-column footer grid.',
            'thumb' => '<span class="pt-footer"><span class="pt-f-col pt-f-col1"></span><span class="pt-f-col"></span><span class="pt-f-col"></span><span class="pt-f-col"></span></span>',
        ],
        'minimal' => [
            'name' => 'Minimal',
            'desc' => 'Centered brand, tagline, social and copyright.',
            'thumb' => '<span class="pt-footer pt-footer-min"><span class="pt-f-brand"></span><span class="pt-f-social"></span></span>',
        ],
        'rich' => [
            'name' => 'Rich',
            'desc' => 'Newsletter signup plus columns and social row.',
            'thumb' => '<span class="pt-footer pt-footer-rich"><span class="pt-f-news"></span><span class="pt-f-row"><span class="pt-f-col pt-f-col1"></span><span class="pt-f-col"></span><span class="pt-f-col"></span></span></span>',
        ],
    ];

    function themePresetPicker(string $field, string $current, array $presets): void
    {
        echo '<div class="preset-grid">';
        foreach ($presets as $key => $p) {
            $active = $current === $key ? ' active' : '';
            echo '<label class="preset-card' . $active . '">';
            echo '<input type="radio" name="' . e($field) . '" value="' . e($key) . '"' . ($active ? ' checked' : '') . '>';
            echo '<span class="preset-thumb">' . $p['thumb'] . '</span>';
            echo '<span class="preset-name">' . e($p['name']) . '</span>';
            echo '<span class="preset-desc">' . e($p['desc']) . '</span>';
            echo '</label>';
        }
        echo '</div>';
    }

    $curHeader = setting('header_style', 'classic');
    if (!isset($headerPresets[$curHeader])) { $curHeader = 'classic'; }
    $curFooter = setting('footer_style', 'classic');
    if (!isset($footerPresets[$curFooter])) { $curFooter = 'classic'; }
?>
<div class="adm-card">
  <h2>Theme Customization</h2>
  <p style="color:#6b7280; font-size:.85rem; margin-bottom:14px">Pick a header and footer design, then tune your brand colors. Changes apply to the whole public site instantly.</p>
  <form method="post" class="adm-form">
    <?php echo Security::csrfField(); ?>
    <input type="hidden" name="tab" value="theme">
    <div class="row-3">
      <div>
        <label>Primary Color</label>
        <input type="color" name="theme_primary" value="<?php echo e(setting('theme_primary', '#c62828')); ?>" style="height:44px; padding:4px">
        <p class="hint">Header, buttons, links, badges</p>
      </div>
      <div>
        <label>Secondary Color</label>
        <input type="color" name="theme_secondary" value="<?php echo e(setting('theme_secondary', '#1a1a2e')); ?>" style="height:44px; padding:4px">
        <p class="hint">Top bar, footer background</p>
      </div>
      <div>
        <label>Accent Color</label>
        <input type="color" name="theme_accent" value="<?php echo e(setting('theme_accent', '#f9a825')); ?>" style="height:44px; padding:4px">
        <p class="hint">Breaking ticker, highlights</p>
      </div>
    </div>
    <label>Header Design</label>
    <?php themePresetPicker('header_style', $curHeader, $headerPresets); ?>
    <label>Footer Design</label>
    <?php themePresetPicker('footer_style', $curFooter, $footerPresets); ?>
    <label style="display:flex; gap:8px; align-items:center; margin-top:18px">
      <input type="checkbox" name="header_breaking" value="1" <?php echo setting('header_breaking', '1') ? 'checked' : ''; ?>> Show breaking news ticker
    </label>
    <div class="adm-actions"><button class="btn" type="submit">Save Theme</button></div>
  </form>
</div>

<div class="adm-card">
  <h2>Live Preview</h2>
  <p style="font-size:.85rem; color:#6b7280">Open the site in another tab: <a href="/" target="_blank"><?php echo e(setting('site_name')); ?>&rarr;</a></p>
</div>
<?php endif; ?>

<?php if ($activeTab === 'menu'): ?>
<?php
    $allMenuItems = DB::fetchAll('SELECT * FROM menus ORDER BY sort_order ASC, id ASC');

    // Build nested menu tree (pre-order) for the builder DOM.
    $menuById = [];
    foreach ($allMenuItems as $mi) { $menuById[(int) $mi['id']] = $mi; }
    $menuChildren = [];
    foreach ($allMenuItems as $mi) { $menuChildren[(int) $mi['parent_id']][] = $mi; }

    function menuBuilderNodes(array $items): array
    {
        $nodes = [];
        foreach ($items as $mi) {
            $node = [
                'key' => (int) $mi['id'],
                'label' => $mi['label'],
                'url' => $mi['url'],
                'children' => [],
            ];
            global $menuChildren;
            if (isset($menuChildren[(int) $mi['id']])) {
                $node['children'] = menuBuilderNodes($menuChildren[(int) $mi['id']]);
            }
            $nodes[] = $node;
        }
        return $nodes;
    }

    function menuBuilderDom(array $nodes): void
    {
        foreach ($nodes as $node) {
            $children = (array) ($node['children'] ?? []);
            echo '<li class="menu-builder-item" data-key="' . e((string) $node['key']) . '">';
            echo '<div class="menu-builder-bar">';
            echo '<span class="menu-builder-drag" aria-label="Drag to reorder">&#9776;</span>';
            echo '<span class="menu-builder-label">' . e((string) $node['label']) . '</span>';
            echo '<span class="menu-builder-url">' . e((string) $node['url']) . '</span>';
            echo '<span class="menu-builder-actions">';
            echo '<button type="button" class="btn btn-secondary btn-sm nb-edit">Edit</button>';
            echo '<button type="button" class="btn btn-secondary btn-sm nb-in" title="Make a sub-menu of the item above">Indent</button>';
            echo '<button type="button" class="btn btn-secondary btn-sm nb-out" title="Move out of its sub-menu">Outdent</button>';
            echo '<button type="button" class="btn btn-danger btn-sm nb-remove">Remove</button>';
            echo '</span>';
            echo '</div>';
            echo '<ol class="menu-builder-sub">';
            if ($children) { menuBuilderDom($children); }
            echo '</ol>';
            echo '</li>';
        }
    }

    $menuTreeNodes = menuBuilderNodes($menuChildren[0] ?? []);

    $pages = DB::fetchAll('SELECT id, title, slug FROM pages WHERE status = 1 ORDER BY title ASC');
    $cats = DB::fetchAll('SELECT id, name, slug FROM categories WHERE status = 1 ORDER BY name ASC');
    $sourceData = json_encode([
        'pages' => array_map(fn($p) => ['name' => $p['title'], 'slug' => $p['slug']], $pages),
        'categories' => array_map(fn($c) => ['name' => $c['name'], 'slug' => $c['slug']], $cats),
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<div class="adm-card">
  <h2>Navigation Menu</h2>
  <p style="color:#6b7280; font-size:.85rem; margin-bottom:14px">Build a menu like WordPress: drag items to reorder, or drag one item into another to make it a drop-down sub-menu. Use the Indent/Outdent buttons as a keyboard-friendly alternative.</p>
  <form method="post" id="menu-builder-form" class="adm-form">
    <?php echo Security::csrfField(); ?>
    <input type="hidden" name="tab" value="menu">
    <input type="hidden" name="menu_tree" id="menu_tree" value="">
    <div class="nav-builder">
      <div class="nav-builder-panel">
        <h3>Add Menu Items</h3>

        <label class="nb-label">Custom Link</label>
        <input type="text" id="nb-custom-label" placeholder="Label (e.g. About)">
        <input type="text" id="nb-custom-url" placeholder="URL (/page/about or https://...)" style="margin-top:6px">
        <button type="button" class="btn btn-sm nb-add" id="nb-add-custom">+ Add to Menu</button>

        <label class="nb-label">Pages</label>
        <div class="nb-source-list" id="nb-pages-list">
          <?php if ($pages): ?>
            <?php foreach ($pages as $i => $p): ?>
              <label><input type="checkbox" value="<?php echo $i; ?>"> <?php echo e($p['title']); ?></label>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="hint">No pages yet.</p>
          <?php endif; ?>
        </div>
        <?php if ($pages): ?><button type="button" class="btn btn-sm nb-add" id="nb-add-pages">Add Selected</button><?php endif; ?>

        <label class="nb-label">Categories</label>
        <div class="nb-source-list" id="nb-cats-list">
          <?php if ($cats): ?>
            <?php foreach ($cats as $i => $c): ?>
              <label><input type="checkbox" value="<?php echo $i; ?>"> <?php echo e($c['name']); ?></label>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="hint">No categories yet.</p>
          <?php endif; ?>
        </div>
        <?php if ($cats): ?><button type="button" class="btn btn-sm nb-add" id="nb-add-cats">Add Selected</button><?php endif; ?>
      </div>

      <div class="nav-builder-panel">
        <h3>Menu Structure</h3>
        <p class="hint" style="margin:-6px 0 10px">Drag the handle to reorder or nest. Categories are appended automatically at the top level on the public site.</p>
        <ol id="menu-structure">
          <?php menuBuilderDom($menuTreeNodes); ?>
        </ol>
        <div class="adm-actions"><button class="btn" type="submit">Save Menu</button></div>
      </div>
    </div>
  </form>
</div>

<script id="menu-source-data" type="application/json"><?php echo $sourceData; ?></script>
<script src="/assets/js/vendor/Sortable.min.js"></script>
<script src="/assets/js/menu-builder.js"></script>
<?php endif; ?>

<?php if ($activeTab === 'seo'): ?>
<div class="adm-card">
  <h2>SEO Settings</h2>
  <form method="post" class="adm-form">
    <?php echo Security::csrfField(); ?>
    <input type="hidden" name="tab" value="seo">
    <label>Default Meta Description</label>
    <textarea name="seo_meta_description" rows="3" maxlength="300"><?php echo e(setting('seo_meta_description')); ?></textarea>
    <p class="hint">Used on the homepage when no page-specific description exists.</p>
    <label>Google Analytics ID</label>
    <input type="text" name="google_analytics" value="<?php echo e(setting('google_analytics')); ?>" placeholder="G-XXXXXXXXXX">
    <label>Site Language (html lang + og:locale)</label>
    <input type="text" name="site_lang" value="<?php echo e(setting('site_lang', 'en')); ?>" placeholder="en">
    <p class="hint">BCP-47 language tag, e.g. en, hi, or-IN.</p>
    <div class="adm-actions"><button class="btn" type="submit">Save SEO</button></div>
  </form>
</div>
<?php endif; ?>

<?php if ($activeTab === 'social'): ?>
<div class="adm-card">
  <h2>Social Media Links</h2>
  <form method="post" class="adm-form">
    <?php echo Security::csrfField(); ?>
    <input type="hidden" name="tab" value="social">
    <div class="row">
      <div><label>Facebook</label><input type="url" name="facebook" value="<?php echo e(setting('facebook')); ?>" placeholder="https://facebook.com/..."></div>
      <div><label>X / Twitter</label><input type="url" name="twitter" value="<?php echo e(setting('twitter')); ?>" placeholder="https://x.com/..."></div>
    </div>
    <div class="row">
      <div><label>Instagram</label><input type="url" name="instagram" value="<?php echo e(setting('instagram')); ?>" placeholder="https://instagram.com/..."></div>
      <div><label>YouTube</label><input type="url" name="youtube" value="<?php echo e(setting('youtube')); ?>" placeholder="https://youtube.com/..."></div>
    </div>
    <div class="adm-actions"><button class="btn" type="submit">Save Social Links</button></div>
  </form>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
