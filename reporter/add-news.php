<?php
/**
 * Reporter - Add / edit own news.
 * Reporters submit as "pending"; only editors/admins publish.
 */
require_once __DIR__ . '/includes/init.php';

$id = (int) ($_GET['id'] ?? 0);
$news = null;
if ($id) {
    $news = DB::fetch('SELECT * FROM news WHERE id = :id AND author_id = :aid', ['id' => $id, 'aid' => $currentUser['id']]);
    if (!$news) { die('News not found or you do not own it.'); }
    if ($news['status'] === 'published') { header('Location: index.php'); exit; }
}

$cats = DB::fetchAll('SELECT * FROM categories WHERE status = 1 ORDER BY sort_order, name');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Security::csrfValidate();
    $title = trim((string) ($_POST['title'] ?? ''));
    $slug = Security::slugify(trim((string) ($_POST['slug'] ?? '')) ?: $title);
    $excerpt = trim((string) ($_POST['excerpt'] ?? ''));
    $content = (string) ($_POST['content'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $tags = trim((string) ($_POST['tags'] ?? ''));
    $imageCaption = trim((string) ($_POST['image_caption'] ?? ''));
    $seoTitle = trim((string) ($_POST['seo_title'] ?? ''));
    $seoDescription = trim((string) ($_POST['seo_description'] ?? ''));
    $focusKeyword = trim((string) ($_POST['focus_keyword'] ?? ''));

    if ($title === '') { $errors[] = 'Title is required.'; }
    if ($content === '') { $errors[] = 'Content is required.'; }
    if ($categoryId <= 0) { $errors[] = 'Please select a category.'; }

    $slugCheck = DB::fetch('SELECT id FROM news WHERE slug = :s AND id <> :id', ['s' => $slug, 'id' => $id]);
    if ($slugCheck) { $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4); }

    $image = $news['image'] ?? null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploaded = Security::uploadImage($_FILES['image'], 'news', 2048);
        if ($uploaded) { $image = $uploaded; }
        else { $errors[] = 'Image upload failed. Use JPG, PNG, GIF or WebP under 2MB.'; }
    }
    $removeImage = isset($_POST['remove_image']) ? true : false;

    if (!$errors) {
        if ($id) {
            DB::run(
                "UPDATE news SET title=:t, slug=:s, excerpt=:e, content=:c, image=:img, image_caption=:ic,
                 category_id=:cid, tags=:tg, seo_title=:st, seo_description=:sd, focus_keyword=:fk WHERE id=:id",
                ['t'=>$title,'s'=>$slug,'e'=>$excerpt,'c'=>$content,'img'=>$removeImage ? null : $image,'ic'=>$imageCaption,
                 'cid'=>$categoryId ?: null,'tg'=>$tags,'id'=>$id,
                 'st'=>$seoTitle ?: null,'sd'=>$seoDescription ?: null,'fk'=>$focusKeyword ?: null]
            );
            flash_set('success', 'News updated. It remains under review until an editor publishes it.');
        } else {
            DB::run(
                "INSERT INTO news (title, slug, excerpt, content, image, image_caption, category_id, author_id, tags, status,
                 seo_title, seo_description, focus_keyword)
                 VALUES (:t,:s,:e,:c,:img,:ic,:cid,:aid,:tg,'pending',:st,:sd,:fk)",
                ['t'=>$title,'s'=>$slug,'e'=>$excerpt,'c'=>$content,'img'=>$removeImage ? null : $image,'ic'=>$imageCaption,
                 'cid'=>$categoryId ?: null,'aid'=>$currentUser['id'],'tg'=>$tags,
                 'st'=>$seoTitle ?: null,'sd'=>$seoDescription ?: null,'fk'=>$focusKeyword ?: null]
            );
            flash_set('success', 'News submitted for review. An editor will publish it after approval.');
        }
        header('Location: index.php');
        exit;
    }
}

$pageTitle = $id ? 'Edit News' : 'Add News';
require_once __DIR__ . '/includes/layout.php';
?>
<div class="adm-card">
  <p style="font-size:.85rem; color:#6b7280; margin-bottom:14px">
    Your submission goes to the editor for review and will appear on the site once approved.
  </p>
  <?php foreach ($errors as $err): ?><div class="alert alert-error"><?php echo e($err); ?></div><?php endforeach; ?>

  <form method="post" enctype="multipart/form-data" class="adm-form">
    <?php echo Security::csrfField(); ?>
    <label for="title">Title *</label>
    <input type="text" id="title" name="title" value="<?php echo e($news['title'] ?? $_POST['title'] ?? ''); ?>" required>
    <p class="hint">Write a clear, factual headline.</p>

    <div class="row">
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
      <div>
        <label for="tags">Tags (comma separated)</label>
        <input type="text" id="tags" name="tags" value="<?php echo e($news['tags'] ?? $_POST['tags'] ?? ''); ?>" placeholder="politics, sports">
      </div>
    </div>

    <label for="excerpt">Excerpt / Summary</label>
    <textarea id="excerpt" name="excerpt" rows="2" maxlength="500" placeholder="Short summary (max 500 chars)"><?php echo e($news['excerpt'] ?? $_POST['excerpt'] ?? ''); ?></textarea>

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

    <?php
    $seoVals = $news ?? [];
    $seoShowFocusKeyword = true;
    $seoShowCanonical = false;
    require __DIR__ . '/../admin/includes/seo_section.php';
    ?>

    <div class="adm-actions">
      <button type="submit" class="btn"><?php echo $id ? 'Save Changes' : 'Submit for Review'; ?></button>
      <a href="index.php" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../admin/includes/editor.php'; ?>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
