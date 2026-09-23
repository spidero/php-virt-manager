</div>
  </div>
</div>

<script>
  // forms with data-confirm ask before submitting (text stays HTML-escaped, no inline JS)
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
</script>
{if $logged_user}
<script src="assets/js/panel.js"></script>
{/if}
</body>
</html>
