// key combination sender ([data-send-keys="<machine>"] with a select and a
// [data-send-key] button): posts to sendkey.php without leaving the page, so an
// open console stays connected
(function () {
  'use strict';
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-send-key]');
    if (!btn) return;
    e.preventDefault();
    var box = btn.closest('[data-send-keys]');
    var select = box.querySelector('[data-send-key-select]');
    var label = select.options[select.selectedIndex].text;
    var body = new URLSearchParams();
    body.set('csrf', box.getAttribute('data-csrf'));
    body.set('node', box.getAttribute('data-send-keys'));
    body.set('key', select.value);
    var status = box.querySelector('[data-send-status]');
    fetch('sendkey.php', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!status) return;
        // plain text, the color tells success (green) from an error (red)
        status.textContent = res.ok ? label : (res.error || '');
        status.className = 'small ms-1 ' + (res.ok ? 'text-success' : 'text-danger');
      })
      .catch(function () {});
  });
})();
