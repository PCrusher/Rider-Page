(function () {
  'use strict';
  var box = document.getElementById('totp-qr');
  if (!box || typeof qrcode === 'undefined') { return; }
  var q = qrcode(0, 'M');
  q.addData(box.getAttribute('data-uri'));
  q.make();
  box.innerHTML = q.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
})();
