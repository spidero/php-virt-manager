{include file="header.tpl"}
<div class="card">
  <div class="card-body">

{if $storage==""}
Storage pools:<br><br>
{foreach from=$storagepools item=sp}
<b><a href="storage.php?storage={$sp}">{$sp}</a></b><br>
{/foreach}
{else}
Storage <b>{$storage}</b> details:
<hr>
{$xml}
<hr>
<p>Files</p>

{foreach from=$list item=l}
{$l}<br>
{/foreach}
{/if}

  </div>
</div>
{include file="footer.tpl"}
