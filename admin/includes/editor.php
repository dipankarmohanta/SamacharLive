<?php
/**
 * TinyMCE visual (WYSIWYG) editor bootstrap.
 * Self-hosted (MIT) copy in /assets/tinymce - no API key, no external CDN.
 *
 * Include this right before the admin footer on any page that contains a
 * <textarea class="rich-editor">. Every such textarea becomes a rich editor.
 *
 * Images inserted from the editor are uploaded to /admin/upload_tinymce.php
 * (session + CSRF protected) and stored under /uploads/news/.
 */
$tinymceCsrf = Security::csrfToken();
?>
<script src="/assets/tinymce/tinymce.min.js" referrerpolicy="origin"></script>
<script>
(function () {
  var EDITOR_SELECTOR = 'textarea.rich-editor';
  var editors = document.querySelectorAll(EDITOR_SELECTOR);
  if (!editors.length || typeof tinymce === 'undefined') { return; }

  var defaults = {
    selector: EDITOR_SELECTOR,
    license_key: 'gpl',
    height: 520,
    menubar: 'edit insert view format tools',
    plugins: 'advlist autolink lists link image charmap anchor searchreplace visualblocks code fullscreen insertdatetime media table help wordcount quickbars',
    toolbar: 'undo redo | blocks | cols2 cols3 | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | blockquote | link unlink | image media table | removeformat | visualblocks code fullscreen',
    branding: true,
    promotion: false,
    convert_urls: false,
    relative_urls: false,
    remove_script_host: false,
    entity_encoding: 'raw',
    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 16px; line-height: 1.75; color: #2b2f3a; } img { max-width: 100%; height: auto; } .row-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin: 16px 0; } .row-cols.cols-3 { grid-template-columns: 1fr 1fr 1fr; } .row-cols .col { border: 1px dashed #cbd5e1; border-radius: 6px; padding: 8px; min-height: 40px; } @media (max-width: 640px) { .row-cols, .row-cols.cols-3 { grid-template-columns: 1fr; } }',
    images_upload_handler: function (blobInfo, progress) {
      return new Promise(function (resolve, reject) {
        var fd = new FormData();
        fd.append('file', blobInfo.blob(), blobInfo.filename());
        fetch('/admin/upload_tinymce.php', {
          method: 'POST',
          headers: { 'X-CSRF-Token': '<?php echo $tinymceCsrf; ?>' },
          body: fd
        }).then(function (r) { return r.json(); })
          .then(function (d) {
            if (d.location) { resolve(d.location); }
            else { reject(d.error || 'Upload failed'); }
          })
          .catch(function () { reject('Upload failed'); });
      });
    },
    setup: function (editor) {
      editor.on('change', function () { editor.save(); });

      // Custom "row / column" layout buttons (free; TinyMCE has no built-in grid)
      function insertCols(n) {
        var cols = '';
        for (var i = 1; i <= n; i++) {
          cols += '<div class="col"><p>Column ' + i + '</p></div>';
        }
        editor.insertContent('<div class="row-cols' + (n === 3 ? ' cols-3' : '') + '">' + cols + '</div>');
      }

      editor.ui.registry.addButton('cols2', {
        text: '2-COL',
        tooltip: 'Insert two-column layout',
        icon: 'table',
        onAction: function () { insertCols(2); }
      });
      editor.ui.registry.addButton('cols3', {
        text: '3-COL',
        tooltip: 'Insert three-column layout',
        icon: 'table',
        onAction: function () { insertCols(3); }
      });
    }
  };

  // Allow per-page overrides via window.tinymcePageOptions (optional)
  if (window.tinymcePageOptions) {
    for (var k in window.tinymcePageOptions) { defaults[k] = window.tinymcePageOptions[k]; }
  }
  tinymce.init(defaults);
})();
</script>
