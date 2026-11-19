</div>
  </div>
</div>
{if $logged_user}
<footer class="container small text-body-secondary text-end py-3">
  php-virt-manager <a href="{$app_version.version_url}" class="link-secondary" target="_blank" rel="noopener">{$app_version.version}</a>{if $app_version.revision}
  (<a href="{$app_version.revision_url}" class="link-secondary font-monospace" target="_blank" rel="noopener">{$app_version.revision}</a>){/if}
</footer>
{/if}

<script>
  // forms with data-confirm ask before submitting (text stays HTML-escaped, no inline JS)
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
</script>
{if $logged_user}
<script src="assets/js/panel.js"></script>
<script src="assets/js/sendkey.js"></script>
{/if}
</body>
</html>
