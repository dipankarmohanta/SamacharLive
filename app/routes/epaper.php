<?php
/**
 * Route: Epaper frontend.
 * /epaper              -> issue archive grid
 * /epaper/view/<id>    -> page-by-page viewer (like samajaepaper.in)
 */
require_once BASE_PATH . '/app/Epaper.php';

$sub = $segments[1] ?? '';

/* ---- Viewer ---- */
if ($sub === 'view') {
    $id = (int) ($segments[2] ?? 0);
    $epaper = DB::fetch('SELECT * FROM epapers WHERE id = :id AND status = 1', ['id' => $id]);
    if (!$epaper) { Security::notFound('Issue not found'); }

    $pages = Epaper::pageImages($epaper['id']);
    if (!$pages && $epaper['pdf_file']) {
        $pages = Epaper::renderPdf($epaper['id'], $epaper['pdf_file'])['page_images'];
        if ($pages) {
            DB::run('UPDATE epapers SET cover_image = :c, page_count = :pc WHERE id = :id', [
                'c' => $pages[0], 'pc' => count($pages), 'id' => $epaper['id'],
            ]);
            $epaper['cover_image'] = $pages[0];
            $epaper['page_count'] = count($pages);
        }
    }
    if (!$pages && $epaper['cover_image']) {
        $pages = ['/' . ltrim($epaper['cover_image'], '/')];
    }

    $pageTitle = $epaper['title'] . ' | Epaper | ' . setting('site_name');
    $pageDescription = 'Read ' . $epaper['title'] . ' e-paper online - ' . fmt_date($epaper['issue_date']);
    $canonical = site_url('epaper/view/' . $epaper['id']);

    require BASE_PATH . '/views/header.php';
    ?>
    <div class="viewer-toolbar">
      <div class="container">
        <a href="/epaper">&larr; All Issues</a>
        <div class="viewer-zoom">
          <button id="zoom-out" type="button" aria-label="Zoom out">&#8722;</button>
          <span id="zoom-label">100%</span>
          <button id="zoom-in" type="button" aria-label="Zoom in">&#43;</button>
        </div>
        <?php if ($epaper['pdf_file']): ?>
          <a href="/<?php echo e(ltrim($epaper['pdf_file'], '/')); ?>" target="_blank" rel="noopener">&#128196; Download PDF</a>
        <?php endif; ?>
      </div>
    </div>

    <div class="page-display" id="page-display">
      <?php if ($pages): ?>
        <?php foreach ($pages as $i => $pg): ?>
          <?php $src = str_starts_with($pg, 'http') ? $pg : '/' . ltrim($pg, '/'); ?>
          <div class="page-slot" data-page="<?php echo $i + 1; ?>">
            <?php echo lazy_img($src, $epaper['title'] . ' page ' . ($i + 1), 'epaper-page', '0', '0'); ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p style="color:#fff">No pages available for this issue.</p>
      <?php endif; ?>
    </div>

    <div class="viewer-nav">
      <?php if ($epaper['pdf_file']): ?>
        <a href="/<?php echo e(ltrim($epaper['pdf_file'], '/')); ?>" target="_blank" rel="noopener">&#128196; Read Full PDF</a>
      <?php endif; ?>
      <?php
      $next = DB::fetch("SELECT id, issue_date FROM epapers WHERE status = 1 AND issue_date > :d ORDER BY issue_date ASC LIMIT 1", ['d' => $epaper['issue_date']]);
      $prev = DB::fetch("SELECT id, issue_date FROM epapers WHERE status = 1 AND issue_date < :d ORDER BY issue_date DESC LIMIT 1", ['d' => $epaper['issue_date']]);
      ?>
      <?php if ($prev): ?><a href="/epaper/view/<?php echo $prev['id']; ?>">&larr; <?php echo e(fmt_date($prev['issue_date'])); ?></a><?php endif; ?>
      <?php if ($next): ?><a href="/epaper/view/<?php echo $next['id']; ?>"><?php echo e(fmt_date($next['issue_date'])); ?> &rarr;</a><?php endif; ?>
    </div>

    <script>
    (function () {
      var display = document.getElementById('page-display');
      var imgs = display.querySelectorAll('img');
      var label = document.getElementById('zoom-label');
      var zoom = 100;
      function apply() {
        imgs.forEach(function (img) {
          var w = 680 * (zoom / 100);
          img.style.width = w + 'px';
        });
        label.textContent = zoom + '%';
      }
      document.getElementById('zoom-in').addEventListener('click', function () {
        zoom = Math.min(zoom + 10, 200); apply();
      });
      document.getElementById('zoom-out').addEventListener('click', function () {
        zoom = Math.max(zoom - 10, 50); apply();
      });
      apply();
    })();
    </script>
    <?php
    require BASE_PATH . '/views/footer.php';
    exit;
}

/* ---- Archive grid ---- */
$issues = DB::fetchAll(
    "SELECT * FROM epapers WHERE status = 1 ORDER BY issue_date DESC LIMIT 40"
);

$pageTitle = 'Epaper | ' . setting('site_name');
$pageDescription = 'Read the latest digital edition of ' . setting('site_name') . ' e-paper online.';
$canonical = site_url('epaper');

require BASE_PATH . '/views/header.php';
?>
<div class="epaper-hero">
  <div class="container">
    <h1>&#128218; E-Paper</h1>
    <p>Read the digital edition of <?php echo e(setting('site_name')); ?> - free online</p>
  </div>
</div>

<section class="section">
  <div class="container">
    <?php if ($issues): ?>
    <div class="epaper-grid">
      <?php foreach ($issues as $issue): ?>
      <div class="epaper-card">
        <div class="cover">
          <?php
          $cover = $issue['cover_image'] ? '/' . ltrim($issue['cover_image'], '/') : asset('img/epaper-placeholder.svg');
          echo lazy_img($cover, $issue['title'] . ' cover', 'cover-img', '300', '400');
          ?>
          <div class="read"><a href="/epaper/view/<?php echo (int) $issue['id']; ?>">Read Now</a></div>
        </div>
        <div class="info">
          <h3><?php echo e($issue['title']); ?></h3>
          <p><?php echo e(fmt_date($issue['issue_date'], 'D, F j, Y')); ?> &middot; <?php echo (int) $issue['page_count']; ?> pages</p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card" style="padding:40px; text-align:center; color:var(--muted)">
      No editions published yet. Please check back soon.
    </div>
    <?php endif; ?>
  </div>
</section>

<?php require BASE_PATH . '/views/footer.php'; ?>
