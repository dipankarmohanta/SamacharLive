<?php
/**
 * Sidebar widget: latest news, popular, tags, social follow.
 */
$sidebarLatest = DB::fetchAll(
    "SELECT n.title, n.slug, n.image, n.published_at FROM news n
     WHERE n.status='published' AND n.published_at IS NOT NULL AND n.published_at <= NOW()
     ORDER BY n.published_at DESC LIMIT 6"
);
$sidebarPopular = DB::fetchAll(
    "SELECT n.title, n.slug, n.image, n.views FROM news n
     WHERE n.status='published' AND n.published_at IS NOT NULL AND n.published_at <= NOW()
     ORDER BY n.views DESC LIMIT 6"
);
$allTags = [];
foreach (DB::fetchAll("SELECT tags FROM news WHERE status='published' AND tags IS NOT NULL AND tags <> '' ORDER BY published_at DESC LIMIT 50") as $row) {
    foreach (array_filter(array_map('trim', explode(',', $row['tags']))) as $t) {
        $allTags[$t] = true;
    }
}
$allTags = array_slice(array_keys($allTags), 0, 12);
?>
<aside class="sidebar">
  <?php if ($sidebarPopular): ?>
  <div class="widget">
    <h3>Most Read</h3>
    <ul class="widget-list">
      <?php foreach ($sidebarPopular as $i => $s): ?>
      <li>
        <?php $t = $s['image'] ? '/' . ltrim($s['image'], '/') : asset('img/placeholder.svg'); ?>
        <?php echo lazy_img($t, e($s['title']), '', '80', '58'); ?>
        <div>
          <a href="/news/<?php echo e($s['slug']); ?>"><?php echo e($s['title']); ?></a>
          <div class="meta">&#128065; <?php echo number_format((int) $s['views']); ?> views</div>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="widget">
    <h3>Latest News</h3>
    <ul class="widget-list">
      <?php foreach ($sidebarLatest as $i => $s): ?>
      <li>
        <?php $t = $s['image'] ? '/' . ltrim($s['image'], '/') : asset('img/placeholder.svg'); ?>
        <?php echo lazy_img($t, e($s['title']), '', '80', '58'); ?>
        <div>
          <a href="/news/<?php echo e($s['slug']); ?>"><?php echo e($s['title']); ?></a>
          <div class="meta"><?php echo e(time_ago($s['published_at'])); ?></div>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <?php if ($allTags): ?>
  <div class="widget">
    <h3>Popular Tags</h3>
    <div class="tag-cloud">
      <?php foreach ($allTags as $t): ?><a href="/tag/<?php echo e(Security::slugify($t)); ?>">#<?php echo e($t); ?></a><?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="widget">
    <h3>Follow Us</h3>
    <div class="social-follow">
      <?php if (setting('facebook')): ?><a class="fb" href="<?php echo e(setting('facebook')); ?>" target="_blank" rel="noopener nofollow">Facebook</a><?php endif; ?>
      <?php if (setting('twitter')): ?><a class="tw" href="<?php echo e(setting('twitter')); ?>" target="_blank" rel="noopener nofollow">X / Twitter</a><?php endif; ?>
      <?php if (setting('instagram')): ?><a class="ig" href="<?php echo e(setting('instagram')); ?>" target="_blank" rel="noopener nofollow">Instagram</a><?php endif; ?>
      <?php if (setting('youtube')): ?><a class="yt" href="<?php echo e(setting('youtube')); ?>" target="_blank" rel="noopener nofollow">YouTube</a><?php endif; ?>
    </div>
  </div>
</aside>
