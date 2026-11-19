<!doctype html>
<html lang="{$lang}" data-bs-theme="dark">
  <head>
    <meta charset="utf-8">
    <title>{$node} - {'console'|t}</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <style>
      html, body { margin: 0; height: 100%; background: #282828; }
      body { display: flex; flex-direction: column; }
      .console-bar { flex: 0 0 auto; padding: .3rem .6rem; background: #1e1e1e; border-bottom: 1px solid #3a3a3a; }
      iframe { border: 0; width: 100%; flex: 1 1 auto; display: block; }
    </style>
  </head>
  <body>
    <div class="console-bar d-flex align-items-center gap-3">
      <span class="small text-body-secondary">{$node}</span>
      {include file="sendkeys.tpl" btn_class='btn-outline-light'}
    </div>
    <iframe src="{$console_url}" title="{'console of %s'|t:$node}"></iframe>
    <script src="assets/js/sendkey.js"></script>
  </body>
</html>
