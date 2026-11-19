<?php
$siteName = defined('YIIMP_SITE_NAME') ? htmlspecialchars(YIIMP_SITE_NAME, ENT_QUOTES) : 'YiiMP';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $siteName ?> — Maintenance</title>
<style>
  body { margin: 0; font-family: sans-serif; background: #1a1a2e; color: #eee;
         display: flex; align-items: center; justify-content: center; min-height: 100vh; }
  .box { text-align: center; padding: 2rem 3rem; background: #16213e;
         border-radius: 8px; border: 1px solid #0f3460; max-width: 480px; }
  h1  { font-size: 1.6rem; color: #e94560; margin: 0 0 .75rem; }
  p   { margin: .5rem 0; color: #aaa; line-height: 1.6; }
  small { font-size: .8rem; color: #555; }
</style>
</head>
<body>
<div class="box">
  <h1>&#9881; Under Maintenance</h1>
  <p><strong><?= $siteName ?></strong> is temporarily offline for scheduled maintenance.</p>
  <p>The pool server is still running normally.<br>Please check back soon.</p>
  <small>HTTP 503 — Service Unavailable</small>
</div>
</body>
</html>
