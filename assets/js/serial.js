// text console: xterm.js <-> websocket (websockify, binary frames) <-> serial port
(function () {
  'use strict';
  var el = document.getElementById('terminal');
  var status = document.querySelector('[data-serial-status]');
  var term = new Terminal({ cursorBlink: true, fontSize: 14, scrollback: 5000, convertEol: false });
  term.open(el);

  // fit the terminal to the window: character cell size from the rendered row
  function fit() {
    var cell = el.querySelector('.xterm-rows > div');
    var h = cell ? cell.getBoundingClientRect().height : 17;
    var w = h * 0.6;
    var cols = Math.max(20, Math.floor((el.clientWidth - 8) / w));
    var rows = Math.max(5, Math.floor((el.clientHeight - 8) / h));
    term.resize(cols, rows);
  }
  window.addEventListener('resize', fit);
  setTimeout(fit, 50);

  var url = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.host + el.getAttribute('data-ws-path');
  var ws = new WebSocket(url, ['binary']);
  ws.binaryType = 'arraybuffer';
  var encoder = new TextEncoder();
  ws.onopen = function () {
    status.textContent = status.getAttribute('data-text-connected');
    status.className = 'small text-success';
    term.focus();
  };
  ws.onmessage = function (e) {
    term.write(new Uint8Array(e.data));
  };
  ws.onclose = function () {
    status.textContent = status.getAttribute('data-text-closed');
    status.className = 'small text-danger';
  };
  term.onData(function (data) {
    if (ws.readyState === WebSocket.OPEN) ws.send(encoder.encode(data));
  });
})();
