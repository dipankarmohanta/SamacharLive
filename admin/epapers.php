<?php
/**
 * Admin - Epaper issues management.
 */
require_once __DIR__ . '/includes/init.php';
require_once BASE_PATH . '/app/Epaper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::csrfValidate();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $issueDate = (string) ($_POST['issue_date'] ?? '');
        $pageCount = max(1, (int) ($_POST['page_count'] ?? 1));

        if ($title === '' || $issueDate === '') {
            flash_set('error', 'Title and issue date are required.');
        } else {
            $pdf = $_POST['existing_pdf'] ?? null;
            if (isset($_FILES['pdf']) && $_FILES['pdf']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploaded = Security::uploadPdf($_FILES['pdf'], 'epaper', 51200);
                if ($uploaded) {
                    $pdf = $uploaded;
                } else {
                    flash_set('error', 'PDF upload failed. Must be a valid PDF under 50MB.');
                    header('Location: epapers.php');
                    exit;
                }
            }

            if ($id) {
                DB::run('UPDATE epapers SET title=:t, issue_date=:d, pdf_file=:p WHERE id=:id',
                    ['t'=>$title,'d'=>$issueDate,'p'=>$pdf,'id'=>$id]);
            } else {
                $id = DB::insert('INSERT INTO epapers (title, issue_date, pdf_file, page_count) VALUES (:t,:d,:p,:pc)',
                    ['t'=>$title,'d'=>$issueDate,'p'=>$pdf,'pc'=>$pageCount]);
            }

            // Render pages & auto cover from the PDF
            if ($pdf && is_file(BASE_PATH . '/' . ltrim($pdf, '/'))) {
                $rendered = Epaper::renderPdf($id, $pdf);
                if ($rendered['page_images']) {
                    $cover = $rendered['cover'];
                    if (isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
                        $c = Security::uploadImage($_FILES['cover'], 'epaper', 2048);
                        if ($c) { $cover = $c; }
                    }
                    DB::run('UPDATE epapers SET cover_image=:c, page_count=:pc WHERE id=:id',
                        ['c'=>$cover,'pc'=>count($rendered['page_images']),'id'=>$id]);
                }
            } elseif (isset($_FILES['cover']) && $_FILES['cover']['error'] !== UPLOAD_ERR_NO_FILE) {
                $c = Security::uploadImage($_FILES['cover'], 'epaper', 2048);
                if ($c) { DB::run('UPDATE epapers SET cover_image=:c WHERE id=:id', ['c'=>$c,'id'=>$id]); }
            }

            flash_set('success', 'Epaper issue saved' . (isset($rendered['page_images']) ? ' (' . count($rendered['page_images']) . ' pages rendered).' : '.'));
        }
    } elseif ($action === 'delete') {
        DB::run('DELETE FROM epapers WHERE id = :id', ['id' => (int) ($_POST['id'] ?? 0)]);
        flash_set('success', 'Epaper issue deleted.');
    } elseif ($action === 'toggle') {
        DB::run('UPDATE epapers SET status = 1 - status WHERE id = :id', ['id' => (int) ($_POST['id'] ?? 0)]);
        flash_set('success', 'Issue visibility changed.');
    }
    header('Location: epapers.php');
    exit;
}

$issues = DB::fetchAll('SELECT * FROM epapers ORDER BY issue_date DESC');
$edit = null;
if (isset($_GET['edit'])) {
    $edit = DB::fetch('SELECT * FROM epapers WHERE id = :id', ['id' => (int) $_GET['edit']]);
}

$pageTitle = 'Epaper';
require_once __DIR__ . '/includes/layout.php';
?>
<?php if (!setting('epaper_enabled', '1')): ?>
<div class="alert alert-info">The E-paper feature is currently disabled. Public visitors get a 404 page and E-paper links are hidden. You can still manage issues here, then enable the feature in Settings &rarr; General &rarr; Feature Settings.</div>
<?php endif; ?>
<div class="adm-card">
  <h2><?php echo $edit ? 'Edit Issue' : 'Upload New Issue'; ?></h2>
  <form method="post" enctype="multipart/form-data" class="adm-form">
    <?php echo Security::csrfField(); ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?php echo (int) ($edit['id'] ?? 0); ?>">
    <div class="row-3">
      <div><label>Title *</label><input type="text" name="title" value="<?php echo e($edit['title'] ?? ''); ?>" required placeholder="e.g. Daily Edition"></div>
      <div><label>Issue Date *</label><input type="date" name="issue_date" value="<?php echo e($edit['issue_date'] ?? date('Y-m-d')); ?>" required></div>
      <div><label>Page Count</label><input type="number" name="page_count" value="<?php echo e($edit['page_count'] ?? 1); ?>" min="1"></div>
    </div>
    <div class="row">
      <div>
        <label>PDF File <?php echo $edit && $edit['pdf_file'] ? '(current: <?php echo e(basename($edit["pdf_file"])); ?>)' : ''; ?></label>
        <input type="file" name="pdf" accept="application/pdf">
        <p class="hint">PDF pages are automatically rendered into the online viewer.</p>
        <?php if ($edit && $edit['pdf_file']): ?><input type="hidden" name="existing_pdf" value="<?php echo e($edit['pdf_file']); ?>"><?php endif; ?>
      </div>
      <div>
        <label>Cover Image (optional, auto-generated from page 1)</label>
        <input type="file" name="cover" accept="image/*">
        <?php if ($edit && $edit['cover_image']): ?>
          <div class="mt-1"><img class="preview-img" src="/<?php echo e(ltrim($edit['cover_image'], '/')); ?>" alt="cover"></div>
        <?php endif; ?>
      </div>
    </div>
    <div class="adm-actions">
      <button class="btn" type="submit"><?php echo $edit ? 'Save Issue' : 'Upload Issue'; ?></button>
      <?php if ($edit): ?><a class="btn btn-secondary" href="epapers.php">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>

<div class="adm-card">
  <h2>All Issues</h2>
  <div class="adm-table-wrap">
  <table class="adm-table data-table">
    <thead><tr><th></th><th>Title</th><th>Issue Date</th><th>Pages</th><th>Status</th><th class="no-sort">Actions</th></tr></thead>
    <tbody>
    <?php if (!$issues): ?><tr><td colspan="6" style="color:#9ca3af">No issues uploaded yet.</td></tr><?php endif; ?>
    <?php foreach ($issues as $ep): ?>
      <tr>
        <td><?php if ($ep['cover_image']): ?><img class="thumb" style="width:40px;height:54px" src="/<?php echo e(ltrim($ep['cover_image'], '/')); ?>" alt="" loading="lazy"><?php endif; ?></td>
        <td><?php echo e($ep['title']); ?></td>
        <td><?php echo e(fmt_date($ep['issue_date'], 'M j, Y')); ?></td>
        <td><?php echo (int) $ep['page_count']; ?></td>
        <td><?php echo $ep['status'] ? '<span class="badge badge-published">Active</span>' : '<span class="badge badge-draft">Hidden</span>'; ?></td>
        <td style="white-space:nowrap">
          <a class="btn btn-secondary btn-sm" href="/epaper/view/<?php echo (int) $ep['id']; ?>" target="_blank">View</a>
          <a class="btn btn-secondary btn-sm" href="epapers.php?edit=<?php echo (int) $ep['id']; ?>">Edit</a>
          <form method="post" style="display:inline">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?php echo (int) $ep['id']; ?>">
            <button class="btn btn-secondary btn-sm" type="submit"><?php echo $ep['status'] ? 'Hide' : 'Show'; ?></button>
          </form>
          <form method="post" style="display:inline" onsubmit="return confirm('Delete this issue?')">
            <?php echo Security::csrfField(); ?>
            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?php echo (int) $ep['id']; ?>">
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
