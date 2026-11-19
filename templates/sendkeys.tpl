{* key combinations and "type text" for $node; handled by assets/js/sendkey.js; $fullscreen adds a full screen button *}
<div class="d-inline-flex flex-wrap align-items-center gap-1" data-send-keys="{$node}" data-csrf="{$csrf_token}" data-text-typed="{'Typed: %d characters'|t}">
  <select class="form-select form-select-sm w-auto" data-send-key-select aria-label="{'Send key'|t}">
{foreach $key_combos as $key => $combo}
    <option value="{$key}">{$combo[0]}</option>
{/foreach}
  </select>
  <button type="button" class="btn btn-sm {$btn_class|default:'btn-outline-secondary'} text-nowrap" data-send-key>{'Send key'|t}</button>
  <input type="text" class="form-control form-control-sm" style="width: 12rem" data-type-text-input autocomplete="off"
         placeholder="{'text to type'|t}" aria-label="{'Text to type into the machine'|t}" title="{'Typed as key presses (US layout); Enter in the field types it'|t}">
  <button type="button" class="btn btn-sm {$btn_class|default:'btn-outline-secondary'} text-nowrap" data-type-text>{'Type'|t}</button>
{if $fullscreen|default:false}
  <button type="button" class="btn btn-sm {$btn_class|default:'btn-outline-secondary'} text-nowrap" data-fullscreen>{'Full screen'|t}</button>
{/if}
  <span data-send-status class="small ms-1" role="status"></span>
</div>
