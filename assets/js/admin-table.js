/* Lightweight data-table enhancer (search, sort, pagination) - no dependencies.
   Applied automatically to every table with class="data-table". */
(function () {
  'use strict';

  function init(table) {
    if (table.dataset.dtReady) return;
    table.dataset.dtReady = '1';

    var tbody = table.querySelector('tbody');
    if (!tbody) return;
    var allRows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
    var dataRows = allRows.filter(function (r) { return !r.querySelector('td[colspan]'); });
    var emptyRow = allRows.filter(function (r) { return r.querySelector('td[colspan]'); })[0] || null;

    var state = { page: 1, size: 10, query: '', sortCol: -1, sortDir: 1 };

    var wrap = document.createElement('div');
    wrap.className = 'dt-wrap';
    table.parentNode.insertBefore(wrap, table);

    var controls = document.createElement('div');
    controls.className = 'dt-controls';

    var lenLabel = document.createElement('label');
    lenLabel.className = 'dt-length';
    lenLabel.appendChild(document.createTextNode('Show '));
    var lenSelect = document.createElement('select');
    [5, 10, 25, 50, 100].forEach(function (n) {
      var o = document.createElement('option');
      o.value = n; o.textContent = n;
      if (n === 10) o.selected = true;
      lenSelect.appendChild(o);
    });
    lenLabel.appendChild(lenSelect);
    lenLabel.appendChild(document.createTextNode(' entries'));
    controls.appendChild(lenLabel);

    var searchBox = document.createElement('input');
    searchBox.type = 'search';
    searchBox.placeholder = 'Search...';
    searchBox.setAttribute('aria-label', 'Search table');
    searchBox.className = 'dt-search';
    controls.appendChild(searchBox);

    wrap.appendChild(controls);
    wrap.appendChild(table);

    var footer = document.createElement('div');
    footer.className = 'dt-footer';

    var info = document.createElement('div');
    info.className = 'dt-info';
    footer.appendChild(info);

    var pager = document.createElement('div');
    pager.className = 'dt-pager';
    footer.appendChild(pager);

    wrap.appendChild(footer);

    var headers = Array.prototype.slice.call(table.querySelectorAll('thead th'));
    headers.forEach(function (th, idx) {
      if (th.classList.contains('no-sort')) return;
      th.classList.add('sortable');
      th.addEventListener('click', function () {
        if (state.sortCol === idx) { state.sortDir = -state.sortDir; }
        else { state.sortCol = idx; state.sortDir = 1; }
        state.page = 1;
        render();
      });
    });

    function filteredRows() {
      var q = state.query.trim().toLowerCase();
      var rows = dataRows;
      if (q) {
        rows = rows.filter(function (r) {
          return r.textContent.toLowerCase().indexOf(q) !== -1;
        });
      }
      if (state.sortCol >= 0) {
        rows = rows.slice().sort(function (a, b) {
          var ca = a.cells[state.sortCol] ? a.cells[state.sortCol].textContent.trim() : '';
          var cb = b.cells[state.sortCol] ? b.cells[state.sortCol].textContent.trim() : '';
          var na = parseFloat(ca.replace(/[^0-9.\-]/g, ''));
          var nb = parseFloat(cb.replace(/[^0-9.\-]/g, ''));
          var useNum = !isNaN(na) && !isNaN(nb) && /^[0-9.,\- ]+$/.test(ca);
          var res = useNum ? (na - nb) : ca.localeCompare(cb, undefined, { sensitivity: 'base' });
          return res * state.sortDir;
        });
      }
      return rows;
    }

    function render() {
      var rows = filteredRows();
      var filtered = rows.length;
      var pages = Math.max(1, Math.ceil(filtered / state.size));
      if (state.page > pages) state.page = pages;

      var start = (state.page - 1) * state.size;
      var end = Math.min(start + state.size, filtered);

      if (emptyRow) emptyRow.style.display = filtered > 0 ? 'none' : '';

      dataRows.forEach(function (r) { r.style.display = 'none'; });
      rows.slice(start, end).forEach(function (r) { r.style.display = ''; });

      info.textContent = filtered === 0
        ? 'No matching records found'
        : 'Showing ' + (start + 1) + ' to ' + end + ' of ' + filtered + ' entries';

      headers.forEach(function (th, idx) {
        th.classList.remove('asc', 'desc');
        if (idx === state.sortCol) th.classList.add(state.sortDir === 1 ? 'asc' : 'desc');
      });

      pager.innerHTML = '';
      var prev = document.createElement('button');
      prev.type = 'button';
      prev.className = 'dt-btn' + (state.page === 1 ? ' disabled' : '');
      prev.textContent = '\u2039';
      prev.title = 'Previous';
      prev.disabled = state.page === 1;
      prev.addEventListener('click', function () { if (state.page > 1) { state.page--; render(); } });
      pager.appendChild(prev);

      var maxPages = 7;
      var startPage = Math.max(1, state.page - Math.floor(maxPages / 2));
      var endPage = Math.min(pages, startPage + maxPages - 1);
      startPage = Math.max(1, endPage - maxPages + 1);

      var ellipsis = function () {
        var s = document.createElement('span');
        s.className = 'dt-ellipsis';
        s.textContent = '\u2026';
        pager.appendChild(s);
      };
      var pageBtn = function (n) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'dt-btn' + (n === state.page ? ' current' : '');
        b.textContent = n;
        b.addEventListener('click', function () { state.page = n; render(); });
        pager.appendChild(b);
      };

      if (startPage > 1) { pageBtn(1); if (startPage > 2) ellipsis(); }
      for (var i = startPage; i <= endPage; i++) pageBtn(i);
      if (endPage < pages) { if (endPage < pages - 1) ellipsis(); pageBtn(pages); }

      var next = document.createElement('button');
      next.type = 'button';
      next.className = 'dt-btn' + (state.page === pages ? ' disabled' : '');
      next.textContent = '\u203a';
      next.title = 'Next';
      next.disabled = state.page === pages;
      next.addEventListener('click', function () { if (state.page < pages) { state.page++; render(); } });
      pager.appendChild(next);
    }

    lenSelect.addEventListener('change', function () { state.size = parseInt(this.value, 10) || 10; state.page = 1; render(); });
    searchBox.addEventListener('input', function () { state.query = this.value; state.page = 1; render(); });

    render();
  }

  function boot() {
    Array.prototype.forEach.call(document.querySelectorAll('table.data-table'), init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
