<?php /** @var string $content */ /** @var string $title */ ?>
<!doctype html>
<html lang="en">
<head>
  <?php require BASE_PATH . '/app/Views/partials/head.php'; ?>
</head>
<body class="auth-body" data-sw="<?= e(url('sw.js')) ?>">
  <main class="auth">
    <section class="auth-brand" aria-label="About GRADELY">
      <a class="brand-lockup" href="<?= e(url('/')) ?>">
        <img src="<?= e(asset('img/gradely-logo.png')) ?>" width="48" height="48" alt="">
        <span class="brand-word">GRADELY</span>
      </a>
      <div class="brand-copy">
        <h2>Academic Results. Simplified.</h2>
        <p>Scores in, report cards out. Automatic grading, positions and secure result checking for every term.</p>
        <ul class="brand-points">
          <li><?= icon('check', 18) ?> Automatic grading and class positions</li>
          <li><?= icon('check', 18) ?> Printable A4 report cards</li>
          <li><?= icon('check', 18) ?> Scratch-card result checking</li>
        </ul>
      </div>
      <p class="brand-foot">Powered by Techpreneur</p>
    </section>
    <section class="auth-panel">
      <div class="auth-card">
        <?= flash_html() ?>
        <?= $content ?>
        <?php require BASE_PATH . '/app/Views/partials/pwa_install.php'; ?>
      </div>
    </section>
  </main>
</body>
</html>
