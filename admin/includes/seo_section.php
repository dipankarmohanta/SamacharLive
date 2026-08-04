<?php
/**
 * Reusable collapsible "SEO Settings" section for add/edit forms.
 * Improves on-page SEO by letting authors set the search-engine fields.
 *
 * Expects:
 *   $seoVals                array   current values (entity row) with $_POST fallback
 *   $seoShowFocusKeyword    bool    show Focus Keyword field (news/reporter)
 *   $seoShowCanonical       bool    show Canonical URL field (news/pages)
 */
$seoVals = $seoVals ?? [];
$seoShowFocusKeyword = $seoShowFocusKeyword ?? false;
$seoShowCanonical = $seoShowCanonical ?? false;
$sv = static function (string $key) use ($seoVals): mixed {
    return $seoVals[$key] ?? $_POST[$key] ?? '';
};
?>
<details class="seo-box" id="seo-toggle">
  <summary>
    <span class="seo-summary-icon">&#9878;</span>
    SEO Settings
    <span class="seo-hint">optional &middot; improves search listing</span>
    <span class="seo-chevron">&#9660;</span>
  </summary>
  <div class="seo-body">
    <label for="seo_title">Meta Title</label>
    <input type="text" id="seo_title" name="seo_title" maxlength="200"
           value="<?php echo e($sv('seo_title')); ?>" placeholder="Leave blank to use the content title">
    <p class="hint">Shown as the title in search results (recommend 50-60 characters).</p>

    <label for="seo_description">Meta Description</label>
    <textarea id="seo_description" name="seo_description" rows="2" maxlength="320"
              placeholder="Short, keyword-rich summary shown in search results"><?php echo e($sv('seo_description')); ?></textarea>
    <p class="hint">Shown under the title in search results (recommend 150-160 characters).</p>

    <?php if ($seoShowFocusKeyword): ?>
    <label for="focus_keyword">Focus Keyword</label>
    <input type="text" id="focus_keyword" name="focus_keyword" maxlength="100"
           value="<?php echo e($sv('focus_keyword')); ?>" placeholder="e.g. odisha news, election 2026">
    <p class="hint">The main keyword or phrase this page should rank for.</p>
    <?php endif; ?>

    <?php if ($seoShowCanonical): ?>
    <label for="canonical_url">Canonical URL</label>
    <input type="url" id="canonical_url" name="canonical_url"
           value="<?php echo e($sv('canonical_url')); ?>" placeholder="Leave blank to auto-detect the page URL">
    <p class="hint">Use only if this content is republished and should point to another URL.</p>
    <?php endif; ?>

    <label class="seo-check" for="seo_noindex">
      <input type="checkbox" id="seo_noindex" name="noindex" value="1" <?php echo !empty($sv('noindex')) ? 'checked' : ''; ?>>
      Hide from search engines (noindex)
    </label>
  </div>
</details>
