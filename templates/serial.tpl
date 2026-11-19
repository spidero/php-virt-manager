<!doctype html>
<html lang="{$lang}" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <title>{$node} - {'text console'|t}</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/vendor/xterm/xterm.css">
    <style>
      html, body { margin: 0; height: 100%; background: #000; }
      body { display: flex; flex-direction: column; }
      .console-bar { flex: 0 0 auto; padding: .3rem .6rem; background: #1e1e1e; border-bottom: 1px solid #3a3a3a; }
      #terminal { flex: 1 1 auto; padding: 4px; min-height: 0; }
    </style>
  </head>
  <body>
    <div class="console-bar d-flex align-items-center gap-3">
      <span class="small text-body-secondary">{$node} - {'text console'|t}</span>
      <span class="small" data-serial-status data-text-connected="{'connected, press Enter for a prompt'|t}" data-text-closed="{'disconnected'|t}"></span>
    </div>
    <div id="terminal" data-ws-path="{$ws_path}"></div>
    <script src="assets/vendor/xterm/xterm.js"></script>
    <script src="assets/js/serial.js"></script>
  </body>
</html>
