/* WordPress-style drag-and-drop menu builder for the admin panel.
   Depends on SortableJS (assets/js/vendor/Sortable.min.js). */
(function () {
  'use strict';

  var MAX_DEPTH = 3;
  var root = document.getElementById('menu-structure');
  if (!root) return;

  var sourceData = null;
  var sourceEl = document.getElementById('menu-source-data');
  if (sourceEl) {
    try { sourceData = JSON.parse(sourceEl.textContent || '{}'); } catch (e) { sourceData = {}; }
  }

  var newCounter = 0;
  function nextNewKey() {
    newCounter++;
    return 'new:' + Date.now() + ':' + newCounter;
  }

  function itemDepth(li) {
    var d = 0;
    var el = li;
    while (el && el !== root) {
      if (el.classList && el.classList.contains('menu-builder-sub')) d++;
      el = el.parentElement;
    }
    return d + 1;
  }

  function createItemBar(key, label, url) {
    var bar = document.createElement('div');
    bar.className = 'menu-builder-bar';

    var drag = document.createElement('span');
    drag.className = 'menu-builder-drag';
    drag.setAttribute('aria-label', 'Drag to reorder');
    drag.textContent = '\u2630';

    var labelSpan = document.createElement('span');
    labelSpan.className = 'menu-builder-label';
    labelSpan.textContent = label;

    var urlSpan = document.createElement('span');
    urlSpan.className = 'menu-builder-url';
    urlSpan.textContent = url || '/';

    var actions = document.createElement('span');
    actions.className = 'menu-builder-actions';

    var editBtn = makeBtn('Edit', 'nb-edit');
    var outBtn = makeBtn('\u2192 Out', 'nb-out');
    var inBtn = makeBtn('Indent \u2192', 'nb-in');
    var delBtn = makeBtn('Remove', 'nb-remove');

    actions.appendChild(editBtn);
    actions.appendChild(inBtn);
    actions.appendChild(outBtn);
    actions.appendChild(delBtn);

    bar.appendChild(drag);
    bar.appendChild(labelSpan);
    bar.appendChild(urlSpan);
    bar.appendChild(actions);

    var item = document.createElement('li');
    item.className = 'menu-builder-item';
    item.setAttribute('data-key', key);
    item.appendChild(bar);

    var sub = document.createElement('ol');
    sub.className = 'menu-builder-sub';
    item.appendChild(sub);

    return item;
  }

  function makeBtn(text, cls) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'btn btn-secondary btn-sm ' + (cls || '');
    b.textContent = text;
    return b;
  }

  function makeInput(placeholder) {
    var i = document.createElement('input');
    i.type = 'text';
    i.className = 'menu-builder-edit-input';
    i.placeholder = placeholder;
    return i;
  }

  function ensureSortable(list) {
    if (!list || list.dataset.nbReady) return;
    list.dataset.nbReady = '1';
    new Sortable(list, {
      group: 'menu-builder',
      handle: '.menu-builder-drag',
      animation: 150,
      ghostClass: 'nb-ghost',
      chosenClass: 'nb-chosen',
      onEnd: function (evt) {
        if (itemDepth(evt.item) > MAX_DEPTH) {
          root.appendChild(evt.item);
        }
      }
    });
  }

  function commitEditors() {
    document.querySelectorAll('.menu-builder-label-input').forEach(function (input) {
      var bar = input.closest('.menu-builder-bar');
      var label = bar.querySelector('.menu-builder-label');
      var v = input.value.trim();
      input.remove();
      if (v !== '') label.textContent = v;
      label.style.display = '';
    });
    document.querySelectorAll('.menu-builder-url-input').forEach(function (input) {
      var bar = input.closest('.menu-builder-bar');
      var url = bar.querySelector('.menu-builder-url');
      var v = input.value.trim();
      input.remove();
      if (v !== '') url.textContent = v;
      url.style.display = '';
    });
  }

  function serializeList(list) {
    var items = [];
    Array.prototype.forEach.call(list.children, function (li) {
      var label = li.querySelector('.menu-builder-label');
      var url = li.querySelector('.menu-builder-url');
      var sub = li.querySelector('.menu-builder-sub');
      var node = {
        key: li.getAttribute('data-key'),
        label: label ? label.textContent.trim() : '',
        url: url ? url.textContent.trim() : '',
        children: (sub && sub.children.length) ? serializeList(sub) : []
      };
      items.push(node);
    });
    return items;
  }

  function serializeAll() {
    return JSON.stringify(serializeList(root));
  }

  /* ---- Event delegation on the structure list ---- */
  root.addEventListener('click', function (evt) {
    var btn = evt.target.closest('button');
    if (!btn) return;
    var item = evt.target.closest('.menu-builder-item');
    if (!item) return;

    if (btn.classList.contains('nb-remove')) {
      item.remove();
      return;
    }
    if (btn.classList.contains('nb-in')) {
      indentItem(item);
      return;
    }
    if (btn.classList.contains('nb-out')) {
      outdentItem(item);
      return;
    }
    if (btn.classList.contains('nb-edit')) {
      toggleEdit(item, btn);
    }
  });

  function toggleEdit(item, btn) {
    var bar = item.querySelector('.menu-builder-bar');
    var label = bar.querySelector('.menu-builder-label');
    var url = bar.querySelector('.menu-builder-url');

    if (bar.dataset.editing === '1') {
      commitEditors();
      bar.dataset.editing = '';
      btn.textContent = 'Edit';
      return;
    }

    var li = makeInput('Label');
    li.className += ' menu-builder-label-input';
    li.value = label.textContent;
    label.style.display = 'none';
    bar.insertBefore(li, url);

    var ui = makeInput('URL');
    ui.className += ' menu-builder-url-input';
    ui.value = url.textContent === '/' ? '' : url.textContent;
    url.style.display = 'none';
    bar.insertBefore(ui, url.nextSibling);

    bar.dataset.editing = '1';
    btn.textContent = 'Done';
    li.focus();
  }

  function indentItem(item) {
    if (itemDepth(item) >= MAX_DEPTH) return;
    var prev = item.previousElementSibling;
    if (!prev) return;
    var sub = prev.querySelector('.menu-builder-sub');
    if (!sub) {
      sub = document.createElement('ol');
      sub.className = 'menu-builder-sub';
      prev.appendChild(sub);
    }
    sub.appendChild(item);
    ensureSortable(sub);
  }

  function outdentItem(item) {
    var sub = item.parentElement;
    if (!sub || !sub.classList.contains('menu-builder-sub')) return;
    var parentLi = sub.parentElement;
    if (!parentLi || !parentLi.classList.contains('menu-builder-item')) return;
    var parentList = parentLi.parentElement;
    if (parentLi.nextElementSibling) {
      parentList.insertBefore(item, parentLi.nextElementSibling);
    } else {
      parentList.appendChild(item);
    }
  }

  /* ---- Add items from the left panel ---- */
  function addItem(key, label, url, parentList) {
    parentList = parentList || root;
    var item = createItemBar(key, label, url);
    parentList.appendChild(item);
    ensureSortable(item.querySelector('.menu-builder-sub'));
    return item;
  }

  var customBtn = document.getElementById('nb-add-custom');
  if (customBtn) {
    customBtn.addEventListener('click', function () {
      var label = document.getElementById('nb-custom-label');
      var url = document.getElementById('nb-custom-url');
      var l = label.value.trim();
      if (l === '') { label.focus(); return; }
      addItem(nextNewKey(), l, url.value.trim());
      label.value = '';
      url.value = '';
    });
  }

  function wireCheckAdd(btnId, listId, sourceKey, urlFn) {
    var btn = document.getElementById(btnId);
    if (!btn) return;
    btn.addEventListener('click', function () {
      var boxes = document.querySelectorAll(listId + ' input[type="checkbox"]:checked');
      Array.prototype.forEach.call(boxes, function (box) {
        var idx = box.value;
        var item = (sourceData[sourceKey] || [])[idx];
        if (!item) return;
        addItem(nextNewKey(), item.name || item.title, urlFn(item));
        box.checked = false;
      });
    });
  }

  wireCheckAdd('nb-add-pages', '#nb-pages-list', 'pages', function (p) {
    return '/page/' + (p.slug || '');
  });
  wireCheckAdd('nb-add-cats', '#nb-cats-list', 'categories', function (c) {
    return '/category/' + (c.slug || '');
  });

  /* ---- Wire the save form ---- */
  var form = document.getElementById('menu-builder-form');
  var treeInput = document.getElementById('menu_tree');
  if (form && treeInput) {
    form.addEventListener('submit', function () {
      commitEditors();
      treeInput.value = serializeAll();
    });
  }

  /* ---- Init sortables on server-rendered sub lists ---- */
  Array.prototype.forEach.call(root.querySelectorAll('.menu-builder-sub'), ensureSortable);
  new Sortable(root, {
    group: 'menu-builder',
    handle: '.menu-builder-drag',
    animation: 150,
    ghostClass: 'nb-ghost',
    chosenClass: 'nb-chosen',
    onEnd: function (evt) {
      if (itemDepth(evt.item) > MAX_DEPTH) {
        root.appendChild(evt.item);
      }
    }
  });
})();
