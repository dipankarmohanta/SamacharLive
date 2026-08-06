<?php
/**
 * Admin - Third-party integrations (Meta pixel, Google AdSense, analytics...).
 * Each integration is a name + provider + script snippet injected into the page.
 */
require_once __DIR__ . '/includes/init.php';

$providers = [
    'google-adsense' => 'Google AdSense',
    'meta-pixel'     => 'Meta Pixel',
    'google-analytics' => 'Google Analytics / Tag Manager',
    'custom'         => 'Custom script',
];
$positions = [
    'head'        => 'Head (in <head>)',
    'body_top'    => 'Top of body (right after <body>)',
    'body_bottom' => 'Bottom of body (before </body>)',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::csrfValidate();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $provider = array_key_exists(($_POST['provider'] ?? ''), $providers) ? (string) $_POST['provider'] : 'custom';
        $position = array_key_exists(($_POST['position'] ?? ''), $positions) ? (string) $_POST['position'] : 'head';
        $code = trim((string) ($_POST['code'] ?? ''));

        if ($name === '' || $code === '') {
            flash_set('error', 'Name and script code are required.');
        } else {
            $vals = [
                'name' => mb_substr($name, 0, 150),
                'provider' => $provider,
                'position' => $position,
                'code' => $code,
            ];
            if ($id) {
                DB::run('UPDATE ad_integrations SET name=:name, provider=:provider, position=:position, code=:code WHERE id=:id', $vals + ['id' => $id]);
                flash_set('success', 'Integration updated.');
            } else {
                DB::run('INSERT INTO ad_integrations (name, provider, position, code) VALUES (:name,:provider,:position,:code)', $vals);
                flash_set('success', 'Integration created.');
            }
        }
    } elseif ($action === 'toggle' || $action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($action === 'delete') {
            DB::run('DELETE FROM ad_integrations WHERE id = :id', ['id' => $id]);
            flash_set('success', 'Integration deleted.');
        } else {
            DB::run('UPDATE ad_integrations SET status = 1 - status WHERE id = :id', ['id' => $id]);
            flash_set('success', 'Integration status changed.');
        }
    }
    header('Location: ads_integrations.php');
    exit;
}

$rows = DB::fetchAll('SELECT * FROM ad_integrations ORDER BY id ASC');
$editRow = null;
if (isset($_GET['edit'])) {
    $editRow = DB::fetch('SELECT * FROM ad_integrations WHERE id = :id', ['id' => (int) $_GET['edit']]);
    if ($editRow) {
        $editRow['provider'] = array_key_exists($editRow['provider'], $providers) ? $editRow['provider'] : 'custom';
        $editRow['position'] = array_key_exists($editRow['position'], $positions) ? $editRow['position'] : 'head';
    }
}

$pageTitle = '3rd Party Integration';
require_once __DIR__ . '/includes/layout.php';
?>
<div class="adm-card">
  <h2><?php echo $editRow ? 'Edit Integration: ' . e($editRow['name']) : 'Add Integration'; ?></h2>
  <form method="post" class="adm-form">
    <?php echo Security::csrfField(); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?php echo (int) ($editRow['id'] ?? 0); ?>">
    <div class="row-3">
      <div>
        <label>Name *</label>
        <input type="text" name="name" value="<?php echo e($editRow['name'] ?? ''); ?>" required placeholder="e.g. Google AdSense">
      </div>
      <div>
        <label>Provider</label>
        <select name="provider">
          <?php foreach ($providers as $key => $label): ?>
            <option value="<?php echo e($key); ?>" <?php echo (($editRow['provider'] ?? 'custom') === $key) ? 'selected' : ''; ?>><?php echo e($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Insert Position</label>
        <select name="position">
          <?php foreach ($positions as $key => $label): ?>
            <option value="<?php echo e($key); ?>" <?php echo (($editRow['position'] ?? 'head') === $key) ? 'selected' : ''; ?>><?php echo e($label); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <label>Script Code (HTML / JS) *</label>
    <textarea name="code" rows="8" class="editor" placeholder="<script>...</script>"><?php echo e($editRow['code'] ?? ''); ?></textarea>
    <p class="hint">Paste the full script or pixel snippet exactly as provided by the platform. It is injected into every public page at the chosen position while the integration is active.</p>
    <div class="adm-actions">
      <button class="btn" type="submit"><?php echo $editRow ? 'Save Integration' : 'Add Integration'; ?></button>
      <?php if ($editRow): ?><a class="btn btn-secondary" href="ads_integrations.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="adm-card">
  <h2>Active Integrations</h2>
  <div class="adm-table-wrap">
  <table class="adm-table">
    <thead><tr><th>Name</th><th>Provider</th><th>Position</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="5" style="color:#9ca3af">No integrations yet. Add your AdSense or Meta Pixel script above.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><strong><?php echo e($r['name']); ?></strong></td>
        <td><?php echo e($providers[$r['provider']] ?? $r['provider']); ?></td>
        <td><?php echo e($positions[$r['position']] ?? $r['position']); ?></td>
        <td><?php echo $r['status'] ? '<span class="badge badge-published">Active</span>' : '<span class="badge badge-draft">Inactive</span>'; ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn-secondary btn-sm" href="ads_integrations.php?edit=<?php echo (int) $r['id']; ?>">Edit</a>
          <form method="post" style="display:inline">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
            <button class="btn btn-secondary btn-sm" type="submit"><?php echo $r['status'] ? 'Deactivate' : 'Activate'; ?></button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this integration?')">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $r['id']; ?>">
            <button class="btn btn-danger btn-sm" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
