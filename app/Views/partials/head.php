<?php /** Shared <head> tags: icons, PWA manifest, theme, stylesheet, scripts. Expects $title. */ ?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title ?? 'GRADELY') ?> · GRADELY</title>
<meta name="theme-color" content="#0d2b22">
<link rel="manifest" href="<?= e(url('manifest.webmanifest')) ?>">
<link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('icons/favicon-32.png')) ?>">
<link rel="apple-touch-icon" href="<?= e(asset('icons/apple-touch-icon.png')) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="GRADELY">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/pwa.js')) ?>" defer></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
