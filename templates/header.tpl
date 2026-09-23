{* POST form with a single button; $extra adds hidden fields, $confirm asks first *}
{function name=post_button url='' action='' label='' class='btn-outline-secondary btn-sm' confirm='' extra=[]}
<form method="post" action="{$url}" class="d-inline-block me-1 mb-1"{if $confirm} data-confirm="{$confirm}"{/if}>
  <input type="hidden" name="csrf" value="{$csrf_token}">
  <input type="hidden" name="action" value="{$action}">
{foreach $extra as $k => $v}
  <input type="hidden" name="{$k}" value="{$v}">
{/foreach}
  <button type="submit" class="btn {$class}">{$label}</button>
</form>
{/function}
<!doctype html>
<html lang="{$lang}">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS, copied from vendor/ by composer (see composer.json scripts) -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <script>
      // follow the system light/dark preference
      document.documentElement.setAttribute('data-bs-theme',
        window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    </script>
    <style>
      /* let the content column shrink (long table cells must not push it below the menu) */
      .row > .col { min-width: 0; }
      /* tables placed directly in cards: align cell padding with the card body */
      .card > .table > :not(caption) > * > :first-child,
      .card > .table-responsive > .table > :not(caption) > * > :first-child { padding-left: 1rem; }
      .card > .table > :not(caption) > * > :last-child,
      .card > .table-responsive > .table > :not(caption) > * > :last-child { padding-right: 1rem; }
    </style>

    <title>PHP virt-manager</title>
  </head>
  <body{if isset($node) && isset($state)} data-node="{$node}" data-state="{$state.id}"{/if}>
  <br>
<div class="container">
  <div class="row">
{if $logged_user}
    <div class="col-12 col-lg-3">
    {include file="menu.tpl"}
    </div>
{/if}
    <div class="col">
{if $flash}
    <div class="alert alert-{$flash.type}" role="alert">{$flash.message}</div>
{/if}
