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
        // Existing items: parallel arrays by row index
        $ids     = (array) ($_POST['mid'] ?? []);
        $labels  = (array) ($_POST['mlabel'] ?? []);
        $urls    = (array) ($_POST['murl'] ?? []);
        $parents = (array) ($_POST['mparent'] ?? []);
        $orders  = (array) ($_POST['morder'] ?? []);
        // New items
        $labelsNew  = (array) ($_POST['nlabel'] ?? []);
        $urlsNew    = (array) ($_POST['nurl'] ?? []);
        $parentsNew = (array) ($_POST['nparent'] ?? []);
        $ordersNew  = (array) ($_POST['norder'] ?? []);

        DB::run('DELETE FROM menus');
        $insert = DB::conn()->prepare('INSERT INTO menus (label, url, sort_order) VALUES (:l, :u, :o)');
        $mapping = []; // old_id => new_id
        $records = []; // new_id => [parent_old_id]

        foreach ($labels as $i => $label) {
            $label = trim((string) $label);
            if ($label === '') { continue; }
            $insert->execute([
                'l' => $label,
                'u' => trim((string) ($urls[$i] ?? '')) ?: '#',
                'o' => (int) ($orders[$i] ?? 0),
            ]);
            $newId = (int) DB::conn()->lastInsertId();
            $oldId = (int) ($ids[$i] ?? 0);
            if ($oldId > 0) { $mapping[$oldId] = $newId; }
            $records[$newId] = (int) ($parents[$i] ?? 0);
        }
        foreach ($labelsNew as $i => $label) {
            $label = trim((string) $label);
            if ($label === '') { continue; }
            $insert->execute([
                'l' => $label,
                'u' => trim((string) ($urlsNew[$i] ?? '')) ?: '#',
                'o' => (int) ($ordersNew[$i] ?? 99),
            ]);
            $newId = (int) DB::conn()->lastInsertId();
            $records[$newId] = (int) ($parentsNew[$i] ?? 0);
        }

        // Resolve parents: map old ids to new ids, ignore unresolved/self refs
        $update = DB::conn()->prepare('UPDATE menus SET parent_id = :p WHERE id = :id');
        foreach ($records as $newId => $parentOld) {
            $resolved = 0;
            if ($parentOld > 0 && isset($mapping[$parentOld])) {
                $resolved = $mapping[$parentOld] === $newId ? 0 : $mapping[$parentOld];
            }
            $update->execute(['p' => $resolved, 'id' => $newId]);
        }

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
        $themeValues['header_style'] = ($_POST['header_style'] ?? 'center') === 'left' ? 'left' : 'center';
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
    $generalValues['maintenance_mode'] = isset($_POST['maintenance_mode']) ? '1' : '0';
    Settings::update($generalValues);
    flash_set('success', 'Site settings saved.');
    header('Location: settings.php?tab=general');
    exit;
}

$menuItems = DB::fetchAll('SELECT * FROM menus ORDER BY sort_order ASC, id ASC');
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
    <div class="mt-1" style="display:flex; gap:8px; align-items:center">
      <input type="checkbox" name="maintenance_mode" value="1" <?php echo setting('maintenance_mode') ? 'checked' : ''; ?>> Enable maintenance mode (public site shows maintenance notice)
    </div>
    <div class="adm-actions"><button class="btn" type="submit">Save General Settings</button></div>
  </form>
</div>
<?php endif; ?>

<?php if ($activeTab === 'theme'): ?>
<div class="adm-card">
  <h2>Theme Customization</h2>
  <p style="color:#6b7280; font-size:.85rem; margin-bottom:14px">Choose your brand colors. Changes apply to the whole public site instantly.</p>
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
    <div class="row-3">
      <div>
        <label>Header Layout</label>
        <select name="header_style">
          <option value="center" <?php echo setting('header_style') === 'center' ? 'selected' : ''; ?>>Centered</option>
          <option value="left" <?php echo setting('header_style') === 'left' ? 'selected' : ''; ?>>Left aligned</option>
        </select>
      </div>
      <div>
        <label style="display:flex; gap:8px; align-items:center; margin-top:30px">
          <input type="checkbox" name="header_breaking" value="1" <?php echo setting('header_breaking', '1') ? 'checked' : ''; ?>> Show breaking news ticker
        </label>
      </div>
    </div>
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

    // Build id => item map plus per-id list of descendants (for cycle-safe parent options)
    $menuIndex = [];
    foreach ($allMenuItems as $mi) { $menuIndex[(int) $mi['id']] = $mi; }
    $descendants = [];
    foreach ($allMenuItems as $mi) { $descendants[(int) $mi['id']] = []; }
    $depthMap = [];
    foreach ($allMenuItems as $mi) {
        $id = (int) $mi['id'];
        $d = 0;
        $pid = (int) $mi['parent_id'];
        while ($pid > 0 && isset($menuIndex[$pid]) && $d < 20) {
            $d++;
            $pid = (int) $menuIndex[$pid]['parent_id'];
        }
        $depthMap[$id] = $d;
    }
    foreach ($allMenuItems as $mi) {
        $id = (int) $mi['id'];
        $pid = (int) $mi['parent_id'];
        while ($pid > 0 && isset($menuIndex[$pid])) {
            $descendants[$pid][] = $id;
            $pid = (int) $menuIndex[$pid]['parent_id'];
        }
    }

    function menuParentOptions(array $menuItems, array $depthMap, array $descendants, array $excludeIds): string
    {
        $out = '<option value="0">— Top Level —</option>';
        foreach ($menuItems as $mi) {
            $id = (int) $mi['id'];
            if (in_array($id, $excludeIds, true)) { continue; }
            $pad = str_repeat('&nbsp;&nbsp;&nbsp;', $depthMap[$id]);
            $out .= '<option value="' . $id . '">' . $pad . e($mi['label']) . '</option>';
        }
        return $out;
    }
?>
<div class="adm-card">
  <h2>Navigation Menu</h2>
  <p style="color:#6b7280; font-size:.85rem; margin-bottom:14px">Build a hierarchical menu like WordPress: set a "Parent" item to turn a menu entry into a drop-down sub-menu. Order numbers sort sibling items. Categories are appended automatically at the top level.</p>
  <form method="post" class="adm-form">
    <?php echo Security::csrfField(); ?>
    <input type="hidden" name="tab" value="menu">
    <label>Current Menu Items</label>
    <div id="menu-rows">
      <?php foreach ($menuItems as $item): ?>
      <div class="menu-row menu-grid">
        <input type="hidden" name="mid[]" value="<?php echo (int) $item['id']; ?>">
        <input type="text" name="mlabel[]" value="<?php echo e($item['label']); ?>" placeholder="Label">
        <input type="text" name="murl[]" value="<?php echo e($item['url']); ?>" placeholder="/page/about or https://...">
        <select name="mparent[]" title="Parent item">
          <?php
            $ex = [$item['id']];
            if (isset($descendants[$item['id']])) { $ex = array_merge($ex, $descendants[$item['id']]); }
            echo menuParentOptions($allMenuItems, $depthMap, $descendants, $ex);
          ?>
        </select>
        <input type="number" name="morder[]" value="<?php echo (int) $item['sort_order']; ?>" style="width:70px" title="Order">
        <button type="button" class="btn btn-danger btn-sm" onclick="this.parentElement.remove()">Remove</button>
      </div>
      <?php endforeach; ?>
    </div>
    <label>Add New Menu Item</label>
    <div id="new-rows">
      <div class="menu-row menu-grid">
        <input type="text" name="nlabel[]" placeholder="Label (e.g. About)">
        <input type="text" name="nurl[]" placeholder="/page/about">
        <select name="nparent[]" title="Parent item">
          <?php echo menuParentOptions($allMenuItems, $depthMap, $descendants, []); ?>
        </select>
        <input type="number" name="norder[]" value="99" style="width:70px" title="Order">
        <button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.remove()">Remove</button>
      </div>
    </div>
    <button type="button" class="btn btn-secondary btn-sm" onclick="addMenuRow()">+ Add Another</button>
    <div class="adm-actions"><button class="btn" type="submit">Save Menu</button></div>
  </form>
</div>

<style>
.menu-grid { display: grid; grid-template-columns: 1.1fr 1.6fr 1fr 70px auto; gap: 8px; align-items: center; }
.menu-grid input[type="text"], .menu-grid select { width: 100%; }
.menu-grid input[type="text"] { min-width: 0; }
@media (max-width: 900px) { .menu-grid { grid-template-columns: 1fr 1fr; } .menu-grid .btn { grid-column: 1 / -1; } }
</style>

<script>
function addMenuRow() {
  var d = document.createElement('div');
  d.className = 'menu-row menu-grid';
  d.innerHTML = '<input type="text" name="nlabel[]" placeholder="Label"><input type="text" name="nurl[]" placeholder="/page/about"><select name="nparent[]" title="Parent item"><?php echo menuParentOptions($allMenuItems, $depthMap, $descendants, []); ?></select><input type="number" name="norder[]" value="99" style="width:70px" title="Order"><button type="button" class="btn btn-secondary btn-sm" onclick="this.parentElement.remove()">Remove</button>';
  document.getElementById('new-rows').appendChild(d);
}
</script>
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
