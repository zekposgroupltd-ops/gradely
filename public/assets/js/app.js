/* GRADELY UI helpers. No inline handlers are used anywhere (strict Content-Security-Policy). */
(function () {
  'use strict';

  /* Confirm dialogs: <form data-confirm="Are you sure?"> */
  document.addEventListener('submit', function (e) {
    var msg = e.target && e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });

  /* Auto-submit filter forms when a select changes: <form data-autosubmit> */
  document.addEventListener('change', function (e) {
    var f = e.target && e.target.form;
    if (f && f.hasAttribute('data-autosubmit') && e.target.tagName === 'SELECT') f.submit();
  });

  /* Print / save-as-PDF buttons */
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-print]')) window.print();
  });

  /* Dependent arm select: <select data-arms-for="#class_id"> with <option data-class="3"> */
  document.querySelectorAll('select[data-arms-for]').forEach(function (arm) {
    var cls = document.querySelector(arm.getAttribute('data-arms-for'));
    if (!cls) return;
    function sync() {
      var chosen = cls.value;
      Array.prototype.forEach.call(arm.options, function (o) {
        var c = o.getAttribute('data-class');
        var show = !c || c === chosen;
        o.hidden = !show;
        o.disabled = !show;
      });
      var cur = arm.options[arm.selectedIndex];
      if (cur && cur.disabled) arm.value = '';
    }
    cls.addEventListener('change', sync);
    sync();
  });

  /* Score grid: live validation, row totals, Enter moves down the column */
  var grid = document.querySelector('[data-score-grid]');
  if (grid) {
    var inputs = Array.prototype.slice.call(grid.querySelectorAll('input.score-input'));
    var parse = function (v) { v = String(v).replace(',', '.').trim(); return v === '' ? null : Number(v); };
    var updateRow = function (tr) {
      var total = 0, filled = 0, count = 0, bad = false;
      tr.querySelectorAll('input.score-input').forEach(function (inp) {
        count++;
        var v = parse(inp.value), max = Number(inp.getAttribute('data-max'));
        var invalid = v !== null && (isNaN(v) || v < 0 || v > max);
        inp.classList.toggle('is-invalid', invalid);
        inp.setAttribute('aria-invalid', invalid ? 'true' : 'false');
        if (invalid) bad = true;
        if (v !== null && !isNaN(v)) { total += v; filled++; }
      });
      var cell = tr.querySelector('[data-row-total]');
      if (cell) cell.textContent = filled === 0 ? '—' : (Math.round(total * 100) / 100) + (filled < count ? ' *' : '');
      tr.classList.toggle('row-bad', bad);
    };
    grid.querySelectorAll('tbody tr').forEach(updateRow);
    grid.addEventListener('input', function (e) { if (e.target.classList.contains('score-input')) updateRow(e.target.closest('tr')); });
    grid.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' || !e.target.classList.contains('score-input')) return;
      e.preventDefault();
      var col = e.target.getAttribute('data-col');
      var same = inputs.filter(function (i) { return i.getAttribute('data-col') === col; });
      var next = same[same.indexOf(e.target) + 1];
      if (next) { next.focus(); next.select(); }
    });
    var form = grid.closest('form');
    if (form) form.addEventListener('submit', function (e) {
      if (grid.querySelector('.is-invalid')) {
        e.preventDefault();
        window.alert('Some scores are outside the allowed range. Fix the highlighted cells first.');
      }
    });
  }

  /* Copy-to-clipboard buttons: <button data-copy="#selector"> */
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-copy]');
    if (!b) return;
    var el = document.querySelector(b.getAttribute('data-copy'));
    if (el && navigator.clipboard) navigator.clipboard.writeText(el.textContent.trim());
  });
})();
