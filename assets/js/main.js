/* Samachar Live - public site scripts */
(function () {
  'use strict';

  /* Mobile navigation toggle */
  var toggle = document.getElementById('nav-toggle');
  var menu = document.getElementById('nav-menu');
  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      var open = menu.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* Native lazy loading is set on all <img> via the lazy_img() helper.
     Fallback for very old browsers: load lazily with IntersectionObserver. */
  if (!('loading' in HTMLImageElement.prototype) && 'IntersectionObserver' in window) {
    var imgs = document.querySelectorAll('img[data-src]');
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          var img = entry.target;
          img.src = img.getAttribute('data-src');
          io.unobserve(img);
        }
      });
    });
    imgs.forEach(function (img) { io.observe(img); });
  }

  /* Dark mode toggle (persisted in localStorage) */
  var darkBtn = document.getElementById('theme-toggle');
  if (darkBtn) {
    var root = document.documentElement;
    darkBtn.addEventListener('click', function () {
      var dark = root.classList.toggle('dark');
      localStorage.setItem('np-dark', dark ? '1' : '0');
    });
    if (localStorage.getItem('np-dark') === '1') {
      root.classList.add('dark');
    }
  }

  /* Auto-hide breaking ticker when not needed */
  var ticker = document.querySelector('.breaking-track');
  if (ticker) {
    var items = ticker.querySelector('.breaking-items');
    if (items && items.scrollWidth <= ticker.clientWidth * 1.5) {
      items.style.animation = 'none';
    }
  }

  /* Offline detection: grey out content and show a banner while offline */
  var offlineBar = document.getElementById('offline-indicator');
  function syncOnlineState() {
    var offline = !navigator.onLine;
    document.documentElement.classList.toggle('is-offline', offline);
    if (offlineBar) { offlineBar.hidden = !offline; }
  }
  if (offlineBar) {
    window.addEventListener('online', syncOnlineState);
    window.addEventListener('offline', syncOnlineState);
    syncOnlineState();
  }

  /* Share buttons: copy link + native share (covers Instagram, FB, etc.) */
  function shareFallbackCopy(text, done) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); done(); } catch (e) {}
    document.body.removeChild(ta);
  }
  function shareCopy(btn) {
    var url = btn.getAttribute('data-share-url');
    function done() {
      var original = btn.textContent;
      btn.textContent = 'Copied!';
      setTimeout(function () { btn.textContent = original; }, 2000);
    }
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(url).then(done, function () { shareFallbackCopy(url, done); });
    } else {
      shareFallbackCopy(url, done);
    }
  }
  Array.prototype.forEach.call(document.querySelectorAll('.share-copy'), function (btn) {
    btn.addEventListener('click', function () { shareCopy(btn); });
  });
  var igBtn = document.querySelector('.share-instagram');
  if (igBtn) {
    igBtn.addEventListener('click', function () {
      var url = igBtn.getAttribute('data-share-url');
      var title = igBtn.getAttribute('data-share-title');
      if (navigator.share) {
        navigator.share({ title: title, url: url }).catch(function () {});
      } else {
        shareCopy(igBtn);
      }
    });
  }
})();
