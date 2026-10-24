(function () {
  'use strict';

  /* Ask before deleting. */
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute && e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); }
  });

  /* Installer: show MySQL fields only when MySQL is chosen. */
  var drv = document.getElementById('db_driver');
  var mysql = document.getElementById('mysql_fields');
  if (drv && mysql) {
    var sync = function () { mysql.hidden = drv.value !== 'mysql'; };
    drv.addEventListener('change', sync);
    sync();
  }

  /* Blog editor toolbar. */
  var body = document.getElementById('body');
  var bar = document.querySelector('.toolbar');
  if (body && bar) {
    var form = body.form;
    var status = bar.querySelector('.status');
    var file = document.createElement('input');
    file.type = 'file';
    file.accept = 'image/jpeg,image/png,image/webp,image/gif';
    file.hidden = true;
    bar.appendChild(file);

    var wrap = function (before, after, placeholder) {
      var s = body.selectionStart, t = body.selectionEnd;
      var sel = body.value.slice(s, t) || placeholder;
      body.setRangeText(before + sel + after, s, t, 'end');
      body.focus();
    };
    var insertLine = function (text) {
      var s = body.selectionStart;
      var pre = s > 0 && body.value[s - 1] !== '\n' ? '\n\n' : '';
      body.setRangeText(pre + text + '\n\n', s, body.selectionEnd, 'end');
      body.focus();
    };

    bar.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-act]');
      if (!b) { return; }
      e.preventDefault();
      var act = b.getAttribute('data-act');
      if (act === 'bold') { wrap('**', '**', 'bold text'); }
      else if (act === 'italic') { wrap('*', '*', 'italic text'); }
      else if (act === 'h2') { insertLine('## Heading'); }
      else if (act === 'quote') { insertLine('> Quote'); }
      else if (act === 'link') {
        var u = window.prompt('Link address (starting with https://)');
        if (u) { wrap('[', '](' + u + ')', 'link text'); }
      } else if (act === 'video') {
        var v = window.prompt('YouTube link');
        if (v) { insertLine(v); }
      } else if (act === 'image') { file.click(); }
    });

    file.addEventListener('change', function () {
      if (!file.files.length) { return; }
      var fd = new FormData();
      fd.append('file', file.files[0]);
      fd.append('_csrf', form.querySelector('input[name=_csrf]').value);
      status.textContent = 'Uploading…';
      fetch(form.getAttribute('data-upload'), { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.ok) {
            var credit = window.prompt('Photo credit (optional). Write the name, or Name | https://link', '');
            credit = credit ? credit.replace(/"/g, '').trim() : '';
            insertLine('![Describe the image](' + j.url + (credit ? ' "' + credit + '"' : '') + ')');
            status.textContent = 'Image added';
          }
          else { status.textContent = j.error || 'Upload failed'; }
        })
        .catch(function () { status.textContent = 'Upload failed'; })
        .then(function () { file.value = ''; });
    });
  }
})();
