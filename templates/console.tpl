<!doctype html>
<html lang="{$lang}">
  <head>
    <meta charset="utf-8">
    <title>{$node} - {'console'|t}</title>
    <style>
      html, body { margin: 0; height: 100%; background: #282828; }
      iframe { border: 0; width: 100%; height: 100%; display: block; }
    </style>
  </head>
  <body>
    <iframe src="{$console_url}" title="{'console of %s'|t:$node}"></iframe>
  </body>
</html>
