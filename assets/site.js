(function () {
  'use strict';
  var me = document.currentScript;
  var copiedText = (me && me.dataset.copied) || 'Copied';

  /* Count clicks on sponsor, social and video links (only when statistics are on). No cookie, no identifier. */
  var beacon = me && me.dataset.beacon;
  function track(el) {
    if (!beacon || !el || !navigator.sendBeacon || navigator.doNotTrack === '1') { return; }
    try {
      var fd = new FormData();
      fd.append('t', el.getAttribute('data-t'));
      fd.append('p', window.location.pathname);
      navigator.sendBeacon(beacon, fd);
    } catch (err) { /* statistics must never break the page */ }
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest('[data-t]');
    if (t) { track(t); }
  });

  /* Contact form: an invisible puzzle solved by the browser (no third party, no cookie). Bots that post the form directly
     do not solve it, and their messages go to the Spam tab. Without it the form still works. */
  (function () {
    var form = document.querySelector('form.c-form[data-pow]');
    if (!form || !window.crypto || !crypto.subtle || !window.TextEncoder) { return; }
    var zeros = parseInt(form.getAttribute('data-pow'), 10) || 4;
    var token = form.querySelector('input[name="s"]');
    var out = form.querySelector('input[name="pw"]');
    if (!token || !out) { return; }
    var enc = new TextEncoder();
    var prefix = new Array(zeros + 1).join('0');
    var solved = null;
    var waiting = false;
    function solve() {
      var n = 0;
      function step() {
        var tries = [];
        for (var i = 0; i < 500; i++, n++) {
          tries.push(crypto.subtle.digest('SHA-256', enc.encode(token.value + ':' + n)).then((function (k) {
            return function (buf) {
              var b = new Uint8Array(buf), hex = '';
              for (var j = 0; j < 3; j++) { hex += ('0' + b[j].toString(16)).slice(-2); }
              return hex.indexOf(prefix) === 0 ? k : -1;
            };
          })(n)));
        }
        return Promise.all(tries).then(function (r) {
          for (var i = 0; i < r.length; i++) { if (r[i] >= 0) { return r[i]; } }
          return step();
        });
      }
      return step();
    }
    solved = solve().then(function (n) { out.value = String(n); return true; }, function () { return false; });
    form.addEventListener('submit', function (e) {
      if (out.value !== '' || waiting) { return; }
      e.preventDefault();
      waiting = true;
      solved.then(function () { form.submit(); });
    });
  })();

  /* Click-to-load YouTube: nothing is requested from YouTube until the visitor presses play. */
  document.addEventListener('click', function (e) {
    var play = e.target.closest('.video .play');
    if (play) {
      var box = play.closest('.video');
      var id = box.getAttribute('data-yt');
      if (!/^[A-Za-z0-9_-]{11}$/.test(id)) { return; }
      var f = document.createElement('iframe');
      f.src = 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0';
      f.title = 'YouTube video';
      f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
      f.setAttribute('allowfullscreen', '');
      box.innerHTML = '';
      box.appendChild(f);
      return;
    }
    var copy = e.target.closest('[data-copy]');
    if (copy) {
      var text = copy.getAttribute('data-copy');
      var original = copy.textContent;
      var done = function () {
        copy.textContent = copiedText;
        setTimeout(function () { copy.textContent = original; }, 1600);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, function () {});
      }
    }
  });

  /* The cookie notice: shown until it was dismissed once. The choice is kept in this browser only (no cookie). */
  var note = document.getElementById('notice');
  if (note) {
    var key = 'rp-notice';
    var seen = false;
    try { seen = window.localStorage.getItem(key) === note.getAttribute('data-id'); } catch (err) { /* private mode */ }
    if (!seen) { note.hidden = false; }
    note.addEventListener('click', function (e) {
      if (e.target.closest('[data-notice-ok]')) {
        note.hidden = true;
        try { window.localStorage.setItem(key, note.getAttribute('data-id')); } catch (err) { /* ignore */ }
      }
    });
  }

  /* Highlight the menu item of the section in view. */
  var links = Array.prototype.slice.call(document.querySelectorAll('.snav a[data-sec]'));
  var sections = links.map(function (a) { return document.getElementById(a.getAttribute('data-sec')); }).filter(Boolean);
  if (sections.length && 'IntersectionObserver' in window && document.getElementById('top')) {
    var visible = {};
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) { visible[en.target.id] = en.isIntersecting; });
      var hit = sections.filter(function (s) { return visible[s.id]; })[0];
      links.forEach(function (a) {
        a.setAttribute('aria-current', String(!!hit && a.getAttribute('data-sec') === hit.id));
      });
    }, { rootMargin: '-30% 0px -60% 0px', threshold: 0 });
    sections.forEach(function (s) { io.observe(s); });
  }
})();
