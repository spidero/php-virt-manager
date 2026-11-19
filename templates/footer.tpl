</div>
  </div>
</div>
{if $logged_user}
<footer class="container small text-body-secondary text-end py-3">
  php-virt-manager <a href="{$app_version.version_url}" class="link-secondary" target="_blank" rel="noopener">{$app_version.version}</a>{if $app_version.revision}
  (<a href="{$app_version.revision_url}" class="link-secondary font-monospace" target="_blank" rel="noopener">{$app_version.revision}</a>){/if}
{if $update_info}
  - <a href="{$update_info.url}" class="link-warning fw-semibold" target="_blank" rel="noopener">{'version %s available'|t:$update_info.version}</a>
{/if}
{if $can.admin}
  <form method="post" action="update.php" class="d-inline">
    <input type="hidden" name="csrf" value="{$csrf_token}">
    <input type="hidden" name="back" value="{$page}.php">
    <button type="submit" class="btn btn-link btn-sm p-0 align-baseline link-secondary">{'check for updates'|t}</button>
  </form>
{/if}
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
