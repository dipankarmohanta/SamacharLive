<?php
/**
 * Admin - Create / edit news article.
 */
require_once __DIR__ . '/includes/init.php';

$id = (int) ($_GET['id'] ?? 0);
$news = null;
if ($id) {
    $news = DB::fetch('SELECT * FROM news WHERE id = :id', ['id' => $id]);
    if (!$news) { die('News not found.'); }
}

$cats = DB::fetchAll('SELECT * FROM categories WHERE status = 1 ORDER BY sort_order, name');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::csrfValidate();

    $title       = trim((string) ($_POST['title'] ?? ''));
    $slug        = Security::slugify(trim((string) ($_POST['slug'] ?? '')) ?: $title);
    $excerpt     = trim((string) ($_POST['excerpt'] ?? ''));
    $content     = (string) ($_POST['content'] ?? '');
    $categoryId  = (int) ($_POST['category_id'] ?? 0);
    $tags        = trim((string) ($_POST['tags'] ?? ''));
    $status      = in_array($_POST['status'] ?? '', ['draft', 'pending', 'published'], true) ? $_POST['status'] : 'draft';
    $featured    = isset($_POST['featured']) ? 1 : 0;
    $breaking    = isset($_POST['breaking']) ? 1 : 0;
    $imageCaption = trim((string) ($_POST['image_caption'] ?? ''));
    $seoTitle    = trim((string) ($_POST['seo_title'] ?? ''));
    $seoDescription = trim((string) ($_POST['seo_description'] ?? ''));
    $focusKeyword = trim((string) ($_POST['focus_keyword'] ?? ''));
    $canonicalUrl = trim((string) ($_POST['canonical_url'] ?? ''));
    $noindex     = isset($_POST['noindex']) ? 1 : 0;

    if ($title === '') { $errors[] = 'Title is required.'; }
    if ($content === '') { $errors[] = 'Content is required.'; }
    if ($categoryId <= 0) { $errors[] = 'Please select a category.'; }

    // Ensure unique slug
    $slugCheck = DB::fetch('SELECT id FROM news WHERE slug = :s AND id <> :id', ['s' => $slug, 'id' => $id]);
    if ($slugCheck) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }

    // Image upload (new upload replaces existing)
    $image = $news['image'] ?? null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploaded = Security::uploadImage($_FILES['image'], 'news', 2048);
        if ($uploaded) {
            $image = $uploaded;
        } else {
            $errors[] = 'Image upload failed. Use JPG, PNG, GIF or WebP under 2MB.';
        }
    }
    $removeImage = isset($_POST['remove_image']) ? true : false;

    if (!$errors) {
        $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;
        if ($id) {
            DB::run(
                "UPDATE news SET title=:t, slug=:s, excerpt=:e, content=:c, image=:img, image_caption=:ic,
                 category_id=:cid, tags=:tg, status=:st, featured=:f, breaking=:b, published_at=:pa,
                 seo_title=:stt, seo_description=:sd, focus_keyword=:fk, canonical_url=:cu, noindex=:ni WHERE id=:id",
                ['t'=>$title,'s'=>$slug,'e'=>$excerpt,'c'=>$content,'img'=>$removeImage ? null : $image,'ic'=>$imageCaption,
                 'cid'=>$categoryId ?: null,'tg'=>$tags,'st'=>$status,'f'=>$featured,'b'=>$breaking,'pa'=>$publishedAt,'id'=>$id,
                 'stt'=>$seoTitle ?: null,'sd'=>$seoDescription ?: null,'fk'=>$focusKeyword ?: null,'cu'=>$canonicalUrl ?: null,'ni'=>$noindex]
            );
            flash_set('success', 'News updated.');
        } else {
            DB::run(
                "INSERT INTO news (title, slug, excerpt, content, image, image_caption, category_id, author_id, tags, status, featured, breaking, published_at,
                 seo_title, seo_description, focus_keyword, canonical_url, noindex)
                 VALUES (:t,:s,:e,:c,:img,:ic,:cid,:aid,:tg,:st,:f,:b,:pa,:stt,:sd,:fk,:cu,:ni)",
                ['t'=>$title,'s'=>$slug,'e'=>$excerpt,'c'=>$content,'img'=>$removeImage ? null : $image,'ic'=>$imageCaption,
                 'cid'=>$categoryId ?: null,'aid'=>$currentUser['id'],'tg'=>$tags,'st'=>$status,'f'=>$featured,'b'=>$breaking,'pa'=>$publishedAt,
                 'stt'=>$seoTitle ?: null,'sd'=>$seoDescription ?: null,'fk'=>$focusKeyword ?: null,'cu'=>$canonicalUrl ?: null,'ni'=>$noindex]
            );
            $id = (int) DB::conn()->lastInsertId();
            flash_set('success', 'News created.');
        }
        header('Location: news.php');
        exit;
    }
}

$pageTitle = $id ? 'Edit News' : 'Add News';
require_once __DIR__ . '/includes/layout.php';
?>
<div class="adm-card">
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?php echo e($err); ?></div><?php endforeach; ?>

  <form method="post" enctype="multipart/form-data" class="adm-form">
    <?php echo Security::csrfField(); ?>

    <label for="title">Title *</label>
    <input type="text" id="title" name="title" value="<?php echo e($news['title'] ?? $_POST['title'] ?? ''); ?>" required>
    <p class="hint">A strong, search-friendly headline.</p>

    <div class="row">
      <div>
        <label for="slug">Slug (auto-generated if left blank)</label>
        <input type="text" id="slug" name="slug" value="<?php echo e($news['slug'] ?? ''); ?>">
      </div>
      <div>
        <label for="category_id">Category *</label>
        <select id="category_id" name="category_id" required>
          <option value="">-- Select Category --</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?php echo (int) $c['id']; ?>" <?php echo (($news['category_id'] ?? $_POST['category_id'] ?? '') == $c['id']) ? 'selected' : ''; ?>>
              <?php echo e(str_repeat('&mdash; ', $c['depth'] ?? 0) . $c['name']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <label for="excerpt">Excerpt / Summary</label>
    <textarea id="excerpt" name="excerpt" rows="2" maxlength="500" placeholder="Short summary shown in cards (max 500 chars)"><?php echo e($news['excerpt'] ?? $_POST['excerpt'] ?? ''); ?></textarea>

    <label for="content">Content *</label>
    <textarea id="content" name="content" class="editor rich-editor" required><?php echo e($news['content'] ?? $_POST['content'] ?? ''); ?></textarea>
    <p class="hint">Visual editor: format text, add headings, lists, links, images and tables. Images can be uploaded directly from the editor.</p>

    <div class="row">
      <div>
        <label for="image">Featured Image</label>
        <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp">
        <p class="hint">JPG, PNG, GIF or WebP, max 2MB.</p>
        <?php if (($news['image'] ?? null) && !($removeImage ?? false)): ?>
          <div class="mt-1">
            <img class="preview-img" src="/<?php echo e(ltrim($news['image'], '/')); ?>" alt="">
            <label class="mt-1" style="display:flex; gap:6px; align-items:center">
              <input type="checkbox" name="remove_image" value="1"> Remove current image
            </label>
          </div>
        <?php endif; ?>
      </div>
      <div>
        <label for="image_caption">Image Caption</label>
        <input type="text" id="image_caption" name="image_caption" value="<?php echo e($news['image_caption'] ?? $_POST['image_caption'] ?? ''); ?>">
      </div>
    </div>

    <div class="row-3">
      <div>
        <label for="tags">Tags (comma separated)</label>
        <input type="text" id="tags" name="tags" value="<?php echo e($news['tags'] ?? $_POST['tags'] ?? ''); ?>" placeholder="politics, sports, odisha">
      </div>
      <div>
        <label for="status">Status</label>
        <select id="status" name="status">
          <option value="published" <?php echo ($news['status'] ?? 'draft') === 'published' ? 'selected' : ''; ?>>Published</option>
          <option value="pending" <?php echo ($news['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending Review</option>
          <option value="draft" <?php echo ($news['status'] ?? 'draft') === 'draft' ? 'selected' : ''; ?>>Draft</option>
        </select>
      </div>
    </div>

    <div style="display:flex; gap:24px; margin-top:14px">
      <label style="display:flex; gap:6px; align-items:center"><input type="checkbox" name="featured" value="1" <?php echo ($news['featured'] ?? 0) ? 'checked' : ''; ?>> Featured Story</label>
      <label style="display:flex; gap:6px; align-items:center"><input type="checkbox" name="breaking" value="1" <?php echo ($news['breaking'] ?? 0) ? 'checked' : ''; ?>> Breaking / Ticker</label>
    </div>

    <div class="adm-actions">
      <button type="submit" class="btn"><?php echo $id ? 'Save Changes' : 'Create News'; ?></button>
      <a href="news.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/includes/editor.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
