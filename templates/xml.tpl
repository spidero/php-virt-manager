{include file="header.tpl"}
<h5 class="mb-3">{'XML definition of %s'|t:$node}
  <a href="edit.php?node={$node|escape:'url'}" class="btn btn-link btn-sm">&laquo; {'back'|t}</a></h5>
<div class="alert alert-warning small">{'For advanced changes not offered by the forms. The persistent definition is replaced; a running machine uses it after it is shut down and started again. A wrong change can make the machine unbootable - take a snapshot first.'|t}</div>
{foreach $errors as $e}
<div class="alert alert-danger"><pre class="mb-0 small text-wrap">{$e}</pre></div>
{/foreach}
<form method="post" action="xml.php">
  <input type="hidden" name="csrf" value="{$csrf_token}">
  <input type="hidden" name="node" value="{$node}">
  <textarea class="form-control font-monospace small mb-3" name="xml" rows="32" spellcheck="false" aria-label="XML">{$xml_text}</textarea>
  <button type="submit" class="btn btn-primary">{'Save'|t}</button>
  <a href="xml.php?node={$node|escape:'url'}" class="btn btn-link">{'Discard changes'|t}</a>
</form>
{include file="footer.tpl"}
