<?php
/**
 * Admin - Advertisements: image/banner ads with validity dates and placement.
 */
require_once __DIR__ . '/includes/init.php';

$placements = Ads::placements();
Ads::ensureSchema();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::csrfValidate();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $info = trim((string) ($_POST['info'] ?? ''));
        $type = ($_POST['type'] ?? 'image') === 'banner' ? 'banner' : 'image';
        $placement = array_key_exists(($_POST['placement'] ?? ''), $placements) ? (string) $_POST['placement'] : 'home_top';
        $linkUrl = trim((string) ($_POST['link_url'] ?? ''));
        $code = (string) ($_POST['code'] ?? '');
        $startDate = trim((string) ($_POST['start_date'] ?? ''));
        $endDate = trim((string) ($_POST['end_date'] ?? ''));

        $isDate = static function (string $d): bool {
            return $d === '' || (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && (bool) strtotime($d));
        };

        $errors = [];
        if ($name === '') { $errors[] = 'Ad name is required.'; }
        if (!$isDate($startDate)) { $errors[] = 'Start date is invalid.'; }
        if (!$isDate($endDate)) { $errors[] = 'End date is invalid.'; }
        if ($type === 'image' && $linkUrl !== '' && !preg_match('~^(https?://|/)~i', $linkUrl)) { $errors[] = 'Link URL must start with http(s):// or /. '; }
        if ($type === 'banner' && trim($code) === '') { $errors[] = 'Banner code is required for banner ads.'; }

        $existing = $id ? DB::fetch('SELECT * FROM ads WHERE id = :id', ['id' => $id]) : null;
        if ($errors) {
            flash_set('error', implode(' ', $errors));
            header('Location: ads.php' . ($id ? '?edit=' . $id : ''));
            exit;
        }

        // Handle optional image upload (image type only).
        $image = $existing['image'] ?? '';
        if ($type === 'image') {
            if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $up = Security::uploadImage($_FILES['image'], 'ads', 2048);
                if ($up) { $image = $up; }
                else { flash_set('error', 'Image upload failed. Use a valid image under 2MB.'); header('Location: ads.php' . ($id ? '?edit=' . $id : '')); exit; }
            }
            if ($image === '') { flash_set('error', 'An ad image is required for image ads.'); header('Location: ads.php' . ($id ? '?edit=' . $id : '')); exit; }
            if (isset($_POST['remove_image'])) { $image = ''; }
            $code = '';
        } else {
            $image = '';
            $linkUrl = '';
        }

        $vals = [
            'name' => mb_substr($name, 0, 150),
            'info' => mb_substr($info, 0, 500) ?: null,
            'type' => $type,
            'image' => $image ?: null,
            'link_url' => $linkUrl ?: null,
            'code' => $code !== '' ? $code : null,
            'placement' => $placement,
            'start_date' => $startDate ?: null,
            'end_date' => $endDate ?: null,
        ];

        if ($id) {
            DB::run(
                "UPDATE ads SET name=:name, info=:info, type=:type, image=:image, link_url=:link_url,
                 code=:code, placement=:placement, start_date=:start_date, end_date=:end_date WHERE id=:id",
                $vals + ['id' => $id]
            );
            flash_set('success', 'Ad updated.');
        } else {
            DB::run(
                "INSERT INTO ads (name, info, type, image, link_url, code, placement, start_date, end_date)
                 VALUES (:name,:info,:type,:image,:link_url,:code,:placement,:start_date,:end_date)",
                $vals
            );
            flash_set('success', 'Ad created.');
        }
    } elseif ($action === 'toggle' || $action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($action === 'delete') {
            DB::run('DELETE FROM ads WHERE id = :id', ['id' => $id]);
            flash_set('success', 'Ad deleted.');
        } else {
            DB::run('UPDATE ads SET status = 1 - status WHERE id = :id', ['id' => $id]);
            flash_set('success', 'Ad status changed.');
        }
    }
    header('Location: ads.php');
    exit;
}

$ads = [];
$adsError = null;
try {
    $ads = DB::fetchAll('SELECT * FROM ads ORDER BY placement ASC, id DESC');
} catch (Throwable $e) {
    $adsError = $e->getMessage();
}
$editAd = null;
if (isset($_GET['edit'])) {
    $editAd = DB::fetch('SELECT * FROM ads WHERE id = :id', ['id' => (int) $_GET['edit']]);
    if ($editAd) { $editAd['placement'] = array_key_exists($editAd['placement'], $placements) ? $editAd['placement'] : 'home_top'; }
}

$pageTitle = 'Ad Management';
require_once __DIR__ . '/includes/layout.php';
?>
<div class="adm-card">
  <h2><?php echo $editAd ? 'Edit Ad: ' . e($editAd['name']) : 'Add Advertisement'; ?></h2>
  <form method="post" enctype="multipart/form-data" class="adm-form" id="ad-form">
    <?php echo Security::csrfField(); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?php echo (int) ($editAd['id'] ?? 0); ?>">
    <div class="row">
      <div>
        <label>Ad Name *</label>
        <input type="text" name="name" value="<?php echo e($editAd['name'] ?? ''); ?>" required placeholder="e.g. Header banner - March 2026">
      </div>
      <div>
        <label>Placement</label>
        <select name="placement">
          <?php foreach ($placements as $key => $label): ?>
            <option value="<?php echo e($key); ?>" <?php echo (($editAd['placement'] ?? 'home_top') === $key) ? 'selected' : ''; ?>><?php echo e($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <label>Type</label>
    <div class="row" style="max-width:520px">
      <label style="display:flex; align-items:center; gap:8px; margin:0">
        <input type="radio" name="type" value="image" <?php echo (($editAd['type'] ?? 'image') !== 'banner') ? 'checked' : ''; ?>> Image / Banner image
      </label>
      <label style="display:flex; align-items:center; gap:8px; margin:0">
        <input type="radio" name="type" value="banner" <?php echo (($editAd['type'] ?? '') === 'banner') ? 'checked' : ''; ?>> HTML / JS code (network banner)
      </label>
    </div>

    <div id="ad-field-image">
      <label>Ad Image</label>
      <input type="file" name="image" accept="image/*">
      <?php if ($editAd && $editAd['image']): ?>
        <div class="mt-1" style="display:flex; gap:12px; align-items:center">
          <img class="preview-img" src="/<?php echo e(ltrim($editAd['image'], '/')); ?>" alt="ad preview">
          <label style="display:flex; gap:6px; align-items:center"><input type="checkbox" name="remove_image" value="1"> Remove image</label>
        </div>
      <?php endif; ?>
      <label>Link URL (where the ad should take the visitor)</label>
      <input type="text" name="link_url" value="<?php echo e($editAd['link_url'] ?? ''); ?>" placeholder="https://example.com">
    </div>

    <div id="ad-field-code" style="display:none">
      <label>Banner Code (HTML / JS) *</label>
      <textarea name="code" rows="6" placeholder="<script async src=...></script>"><?php echo e($editAd['code'] ?? ''); ?></textarea>
      <p class="hint">Paste an AdSense / network ad unit or any custom HTML banner. It is inserted on the public site when this ad is active.</p>
    </div>

    <label>Info / Notes</label>
    <input type="text" name="info" value="<?php echo e($editAd['info'] ?? ''); ?>" placeholder="Client name, campaign notes, etc.">

    <div class="row">
      <div>
        <label>Valid From (start date)</label>
        <input type="date" name="start_date" value="<?php echo e($editAd['start_date'] ?? ''); ?>">
      </div>
      <div>
        <label>Valid Until (renew / expiry date)</label>
        <input type="date" name="end_date" value="<?php echo e($editAd['end_date'] ?? ''); ?>">
      </div>
    </div>
    <p class="hint">Leave dates empty for an ad that is always live. The ad only shows while today is within the validity window.</p>

    <div class="adm-actions">
      <button class="btn" type="submit"><?php echo $editAd ? 'Save Ad' : 'Create Ad'; ?></button>
      <?php if ($editAd): ?><a class="btn btn-secondary" href="ads.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="adm-card">
  <h2>All Advertisements</h2>
  <?php if ($adsError): ?>
    <div class="alert alert-error">Could not load advertisements: <?php echo e($adsError); ?></div>
  <?php endif; ?>
  <div class="adm-table-wrap">
  <table class="adm-table">
    <thead><tr><th>Preview</th><th>Name</th><th>Type</th><th>Placement</th><th>Validity</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (!$ads): ?><tr><td colspan="7" style="color:#9ca3af">No advertisements yet. Create one above.</td></tr><?php endif; ?>
    <?php foreach ($ads as $ad): ?>
      <?php $inWindow = ($ad['start_date'] === null || $ad['start_date'] <= date('Y-m-d')) && ($ad['end_date'] === null || $ad['end_date'] >= date('Y-m-d')); ?>
      <tr>
        <td>
          <?php if ($ad['type'] === 'image' && $ad['image']): ?>
            <img class="thumb" src="/<?php echo e(ltrim($ad['image'], '/')); ?>" alt="">
          <?php else: ?>
            <span style="color:#9ca3af; font-size:.8rem"><?php echo $ad['type'] === 'banner' ? '&#128444; code' : '&#128444; none'; ?></span>
          <?php endif; ?>
        </td>
        <td><strong><?php echo e($ad['name']); ?></strong><?php if ($ad['info']): ?><div class="hint" style="font-size:.72rem; color:#6b7280; max-width:260px"><?php echo e($ad['info']); ?></div><?php endif; ?></td>
        <td><?php echo $ad['type'] === 'banner' ? 'Banner code' : 'Image'; ?></td>
        <td><?php echo e(Ads::placementLabel($ad['placement'])); ?></td>
        <td style="white-space:nowrap">
          <?php if ($ad['start_date'] || $ad['end_date']): ?>
            <?php echo $ad['start_date'] ? e(fmt_date($ad['start_date'], 'M j, Y')) : '&infin;'; ?> &rarr; <?php echo $ad['end_date'] ? e(fmt_date($ad['end_date'], 'M j, Y')) : '&infin;'; ?>
            <?php if (!$inWindow && $ad['status'] == 1): ?><div style="color:#b45309; font-size:.72rem">Outside validity window</div><?php endif; ?>
          <?php else: ?>
            Always live
          <?php endif; ?>
        </td>
        <td><?php echo $ad['status'] ? '<span class="badge badge-published">Active</span>' : '<span class="badge badge-draft">Inactive</span>'; ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn-secondary btn-sm" href="ads.php?edit=<?php echo (int) $ad['id']; ?>">Edit</a>
          <form method="post" style="display:inline">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int) $ad['id']; ?>">
            <button class="btn btn-secondary btn-sm" type="submit"><?php echo $ad['status'] ? 'Deactivate' : 'Activate'; ?></button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this ad?')">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $ad['id']; ?>">
            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<script>
(function () {
  var form = document.getElementById('ad-form');
  function syncType() {
    var isBanner = document.querySelector('input[name="type"]:checked').value === 'banner';
    document.getElementById('ad-field-image').style.display = isBanner ? 'none' : '';
    document.getElementById('ad-field-code').style.display = isBanner ? '' : 'none';
  }
  form.querySelectorAll('input[name="type"]').forEach(function (r) { r.addEventListener('change', syncType); });
  syncType();
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
