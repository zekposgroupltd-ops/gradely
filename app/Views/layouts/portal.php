<?php
use App\Services\Platform;

/** @var string $content */ /** @var string $title */ /** @var ?array $school */
$email = Platform::get('support_email');
$phone = Platform::get('support_phone');
?>
<!doctype html>
<html lang="en">
<head>
  <?php require BASE_PATH . '/app/Views/partials/head.php'; ?>
</head>
<body class="portal-body" data-sw="<?= e(url('sw.js')) ?>">
  <header class="portal-top">
    <a class="brand-lockup brand-dark" href="<?= e(url('/')) ?>">
      <img src="<?= e(asset('img/gradely-logo.png')) ?>" width="36" height="36" alt="">
      <span class="brand-word">GRADELY</span>
    </a>
    <a class="portal-staff" href="<?= e(url('/login')) ?>">Staff sign in</a>
  </header>
  <main class="<?= $school === null ? 'portal-main-wide' : 'portal-main' ?>">
    <?= flash_html() ?>
    <?= $content ?>
    <div class="portal-install">
      <button class="btn btn-ghost btn-block" type="button" data-pwa-install hidden><?= icon('download', 18) ?> Install GRADELY App</button>
      <?php require BASE_PATH . '/app/Views/partials/ios_hint.php'; ?>
    </div>
  </main>
  <script src="<?= e(asset('js/verify-scanner.js')) ?>" defer></script>
  <footer class="portal-foot">
    <p>GRADELY · Academic Results. Simplified. · Powered by Techpreneur</p>
    <?php if ($email !== '' || $phone !== ''): ?>
      <p>Need help? <?= e(trim($email . ($email !== '' && $phone !== '' ? ' · ' : '') . $phone)) ?></p>
    <?php endif; ?>
  </footer>
</body>
</html>
