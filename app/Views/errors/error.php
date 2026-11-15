<?php /** @var int $code */ /** @var string $title */ /** @var string $message */ ?>
<p class="error-code"><?= e($code) ?></p>
<h1 class="auth-title"><?= e($title) ?></h1>
<?php if (($message ?? '') !== ''): ?>
  <p class="muted"><?= e($message) ?></p>
<?php endif; ?>
<p><a class="btn btn-ghost" href="<?= e(url('/')) ?>">Back to home</a></p>
