<?php /** @var string $content */ /** @var string $title */ /** @var array $reports */ /** @var bool $portal */
$blockPrint = !empty($portal) && !empty($reports) && (int) ($reports[0]['cfg']['allow_download'] ?? 1) === 0;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($title ?? 'Report') ?> · GRADELY</title>
  <meta name="theme-color" content="#0d2b22">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= e(asset('icons/favicon-32.png')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset('css/report.css')) ?>">
  <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="report-body<?= $blockPrint ? ' block-print' : '' ?>">
  <?= $content ?>
</body>
</html>
