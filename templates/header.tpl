<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">

    <title>PHP virt-manager</title>
  </head>
  <body>
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
