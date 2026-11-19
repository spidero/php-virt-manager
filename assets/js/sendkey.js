// key combinations, "type text" and full screen ([data-send-keys="<machine>"] boxes):
// posts to sendkey.php without leaving the page, so an open console stays connected
(function () {
  'use strict';
  function send(box, fields, label) {
    var body = new URLSearchParams();
    body.set('csrf', box.getAttribute('data-csrf'));
    body.set('node', box.getAttribute('data-send-keys'));
    Object.keys(fields).forEach(function (k) { body.set(k, fields[k]); });
    var status = box.querySelector('[data-send-status]');
    return fetch('sendkey.php', { method: 'POST', body: body, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!status) return res;
        // plain text, the color tells success (green) from an error (red)
        status.textContent = res.ok ? label : (res.error || '');
        status.className = 'small ms-1 ' + (res.ok ? 'text-success' : 'text-danger');
        return res;
      })
      .catch(function () {});
  }
  function typeText(box) {
    var input = box.querySelector('[data-type-text-input]');
    if (!input || input.value === '') return;
    var text = input.value;
    send(box, { text: text }, box.getAttribute('data-text-typed').replace('%d', text.length)).then(function (res) {
      if (res && res.ok) input.value = '';
    });
  }
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-send-key], [data-type-text], [data-fullscreen]');
    if (!btn) return;
    e.preventDefault();
    var box = btn.closest('[data-send-keys]');
    if (btn.hasAttribute('data-fullscreen')) {
      if (document.fullscreenElement) document.exitFullscreen();
      else document.documentElement.requestFullscreen();
      return;
    }
    if (btn.hasAttribute('data-type-text')) {
      typeText(box);
      return;
    }
    var select = box.querySelector('[data-send-key-select]');
    send(box, { key: select.value }, select.options[select.selectedIndex].text);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && e.target.matches && e.target.matches('[data-type-text-input]')) {
      e.preventDefault();
      typeText(e.target.closest('[data-send-keys]'));
    }
  });
})();
