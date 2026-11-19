{* key combination sender for $node; handled by assets/js/sendkey.js *}
<div class="d-inline-flex align-items-center gap-1" data-send-keys="{$node}" data-csrf="{$csrf_token}">
  <select class="form-select form-select-sm w-auto" data-send-key-select aria-label="{'Send key'|t}">
{foreach $key_combos as $key => $combo}
    <option value="{$key}">{$combo[0]}</option>
{/foreach}
  </select>
  <button type="button" class="btn btn-sm {$btn_class|default:'btn-outline-secondary'} text-nowrap" data-send-key>{'Send key'|t}</button>
  <span data-send-status class="small ms-1" role="status"></span>
</div>
