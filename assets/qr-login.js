(function () {
  'use strict';
  var box = document.getElementById('qr-login');
  if (!box || typeof qrcode === 'undefined') { return; }
  box.hidden = false;

  var startBtn = document.getElementById('qr-start');
  var qrBox = document.getElementById('qr-box');
  var img = document.getElementById('qr-img');
  var num = document.getElementById('qr-num');
  var msg = document.getElementById('qr-msg');
  var refresh = document.getElementById('qr-refresh');
  var timer = null, poller = null, poll = '', left = 0, busy = false;

  function stop() { clearInterval(timer); clearInterval(poller); busy = false; }
  function expired(text) {
    stop();
    img.innerHTML = '';
    num.parentNode.hidden = true;
    msg.textContent = text || 'This code has expired.';
    refresh.hidden = false;
  }
  function form(extra) {
    var fd = new FormData();
    fd.append('_csrf', box.getAttribute('data-csrf'));
    Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
    return fd;
  }
  function tick() { msg.textContent = 'Scan the code with your phone. It is valid for ' + Math.max(left, 0) + ' seconds.'; }

  function begin() {
    stop();
    refresh.hidden = true;
    startBtn.hidden = true;
    qrBox.hidden = false;
    img.innerHTML = '';
    num.parentNode.hidden = true;
    msg.textContent = 'Loading…';
    fetch(box.getAttribute('data-new'), { method: 'POST', body: form(), credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) { expired(j.error || 'A code could not be created.'); return; }
        poll = j.poll;
        left = j.ttl;
        var q = qrcode(0, 'M');
        q.addData(j.url);
        q.make();
        img.innerHTML = q.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
        num.textContent = j.code;
        num.parentNode.hidden = false;
        tick();
        timer = setInterval(function () { left -= 1; if (left > 0) { tick(); } }, 1000);
        poller = setInterval(check, 1500);
      })
      .catch(function () { expired('The server could not be reached.'); });
  }

  function check() {
    if (busy) { return; }
    busy = true;
    fetch(box.getAttribute('data-status') + '?p=' + encodeURIComponent(poll), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (s) {
        busy = false;
        if (s.state === 'pending') { left = s.left; }
        else if (s.state === 'scanned') {
          clearInterval(timer);
          img.innerHTML = '';
          msg.textContent = 'Now confirm on your phone: type the number shown above.';
        } else if (s.state === 'approved') {
          stop();
          msg.textContent = 'Approved. Logging you in…';
          claim();
        } else if (s.state === 'denied') { expired('The login was denied on the phone.'); }
        else if (s.state === 'failed') { expired('The number was typed wrong too often. Show a new code.'); }
        else { expired('This code has expired.'); }
      })
      .catch(function () { busy = false; });
  }

  function claim() {
    fetch(box.getAttribute('data-claim'), { method: 'POST', body: form({ p: poll }), credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.ok) { window.location.href = j.redirect; }
        else { expired(j.error || 'The login could not be completed.'); }
      })
      .catch(function () { expired('The server could not be reached.'); });
  }

  startBtn.addEventListener('click', begin);
  refresh.addEventListener('click', begin);
})();
