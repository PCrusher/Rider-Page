(function () {
  'use strict';
  var box = document.getElementById('qr-account');
  if (!box || typeof qrcode === 'undefined') { return; }
  box.hidden = false;

  var ask = document.getElementById('qr-ask');
  var pw = document.getElementById('qr-pw');
  var show = document.getElementById('qr-show');
  var shown = document.getElementById('qr-shown');
  var img = document.getElementById('qr-img');
  var msg = document.getElementById('qr-msg');
  var again = document.getElementById('qr-again');
  var totp = document.getElementById('qr-totp');
  var timer = null, poller = null, left = 0, id = 0, busy = false;

  function stop() { clearInterval(timer); clearInterval(poller); busy = false; }
  function end(text) {
    stop();
    img.innerHTML = '';
    msg.textContent = text;
    again.hidden = false;
  }
  function tick() { msg.textContent = 'Scan the code with your phone. It is valid for ' + Math.max(left, 0) + ' seconds.'; }

  function begin() {
    stop();
    var fd = new FormData();
    fd.append('_csrf', box.getAttribute('data-csrf'));
    fd.append('current', pw.value);
    if (totp) { fd.append('code', totp.value); }
    ask.hidden = true;
    shown.hidden = false;
    again.hidden = true;
    img.innerHTML = '';
    msg.textContent = 'Loading…';
    fetch(box.getAttribute('data-new'), { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        pw.value = '';
        if (totp) { totp.value = ''; }
        if (!j.ok) { end(j.error || 'A code could not be created.'); return; }
        id = j.id;
        left = j.ttl;
        var q = qrcode(0, 'M');
        q.addData(j.url);
        q.make();
        img.innerHTML = q.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
        tick();
        timer = setInterval(function () { left -= 1; if (left > 0) { tick(); } }, 1000);
        poller = setInterval(check, 1500);
      })
      .catch(function () { end('The server could not be reached.'); });
  }

  function check() {
    if (busy) { return; }
    busy = true;
    fetch(box.getAttribute('data-check') + '?id=' + encodeURIComponent(id), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (s) {
        busy = false;
        if (s.state === 'opened') {
          clearInterval(timer);
          img.innerHTML = '';
          msg.textContent = 'Your phone has opened the code. Tap the button on your phone.';
        } else if (s.state === 'used') {
          stop();
          img.innerHTML = '';
          msg.textContent = 'Your phone is logged in. Updating the list…';
          setTimeout(function () { window.location.href = box.getAttribute('data-refresh'); }, 1200);
        } else if (s.state === 'expired') {
          end('The code has expired. Show a new one when you are ready.');
        }
      })
      .catch(function () { busy = false; });
  }

  show.addEventListener('click', function () {
    if (pw.value === '') { pw.focus(); return; }
    begin();
  });
  pw.addEventListener('keydown', function (e) {
    if (e.key === 'Enter') { e.preventDefault(); show.click(); }
  });
  again.addEventListener('click', function () {
    shown.hidden = true;
    ask.hidden = false;
    pw.focus();
  });
})();
