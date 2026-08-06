<?php
/** Close admin layout. */
?>
    </div>
  </div>
</div>
<script src="/assets/js/admin-table.js?v=20260805"></script>
<script>
(function () {
  var toggle = document.querySelector('.nav-toggle');
  if (!toggle) return;
  toggle.addEventListener('click', function () {
    var section = document.getElementById('nav-ad');
    if (!section) return;
    var open = section.classList.toggle('open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });
})();
</script>
</body>
</html>
