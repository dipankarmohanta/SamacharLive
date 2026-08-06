<?php
/**
 * Admin - Ad Settings: master on/off switch and placement selection.
 */
require_once __DIR__ . '/includes/init.php';
if ($currentUser['role'] !== 'admin') {
    http_response_code(403);
    die('Only administrators can change ad settings.');
}

$placements = Ads::placements();
Ads::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::csrfValidate();
    $selected = array_values(array_intersect(
        (array) ($_POST['placements'] ?? []),
        array_keys($placements)
    ));
    Settings::update([
        'ads_enabled'   => isset($_POST['ads_enabled']) ? '1' : '0',
        'ads_placements' => implode(',', $selected),
    ]);
    flash_set('success', 'Ad settings saved.');
    header('Location: ads_settings.php');
    exit;
}

$enabled = (bool) setting('ads_enabled', '1');
$selected = Ads::placementsOn();

$pageTitle = 'Ad Settings';
require_once __DIR__ . '/includes/layout.php';
?>
<div class="adm-card">
  <h2>Advertisement Settings</h2>
  <form method="post" class="adm-form">
    <?php echo Security::csrfField(); ?>
    <div style="display:flex; gap:8px; align-items:center; margin-bottom:6px">
      <input type="checkbox" name="ads_enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?>> Enable advertisements on the public site
    </div>
    <p class="hint">Turn this off to hide all ad slots site-wide. Ad management and third-party integration scripts can still be configured here.</p>

    <label style="margin-top:22px">Where should ads be inserted?</label>
    <div class="row-2-placement">
      <?php foreach ($placements as $key => $label): ?>
        <label class="placement-check">
          <input type="checkbox" name="placements[]" value="<?php echo e($key); ?>" <?php echo in_array($key, $selected, true) ? 'checked' : ''; ?>>
          <span><?php echo e($label); ?></span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="hint">Only placements with an active ad will show anything. Ads are displayed in the order they appear in Ad Management.</p>

    <div class="adm-actions"><button class="btn" type="submit">Save Ad Settings</button></div>
  </form>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
