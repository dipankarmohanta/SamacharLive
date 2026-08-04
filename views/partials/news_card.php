<?php
/**
 * Reusable news card partial.
 * Expects: $item (news row), optional $showExcerpt
 */
$showExcerpt = $showExcerpt ?? true;
$catName = $item['cat_name'] ?? '';
$catSlug = $item['cat_slug'] ?? '';
$thumb = $item['image'] ? '/' . ltrim($item['image'], '/') : null;
$imgHtml = lazy_img($thumb ?? asset('img/placeholder.svg'), e($item['title']), 'card-img-top');
$date = $item['published_at'] ?? $item['created_at'];
?>
<article class="card">
  <a class="card-img" href="/news/<?php echo e($item['slug']); ?>" aria-label="<?php echo e($item['title']); ?>">
    <?php echo $imgHtml; ?>
  </a>
  <div class="card-body">
    <?php if ($catName): ?><a class="cat-badge" href="/category/<?php echo e($catSlug); ?>"><?php echo e($catName); ?></a><?php endif; ?>
    <h3><a href="/news/<?php echo e($item['slug']); ?>"><?php echo e($item['title']); ?></a></h3>
    <?php if ($showExcerpt && !empty($item['excerpt'])): ?><p class="excerpt"><?php echo e($item['excerpt']); ?></p><?php endif; ?>
    <div class="meta">
      <span><?php echo e(time_ago($date)); ?></span>
      <?php if ((int) ($item['views'] ?? 0) > 0): ?><span class="sep">|</span><span>&#128065; <?php echo number_format((int) $item['views']); ?></span><?php endif; ?>
    </div>
  </div>
</article>
