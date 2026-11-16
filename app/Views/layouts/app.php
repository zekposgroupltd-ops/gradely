<?php
use App\Core\{Auth, Http, Nav};

/** @var string $content */ /** @var string $title */
$u = Auth::user();
$path = Http::path();
$crumb = $u['school_name'] ?? 'Platform administration';
$unread = unread_count();
$isActive = static function (array $item) use ($path): bool {
    if (in_array($item['path'], ['/school', '/super'], true)) {
        return $path === $item['path'];
    }
    foreach (array_merge([$item['path']], $item['also']) as $prefix) {
        if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
            return true;
        }
    }
    return false;
};
?>
<!doctype html>
<html lang="en">
<head>
  <?php require BASE_PATH . '/app/Views/partials/head.php'; ?>
</head>
<body data-sw="<?= e(url('sw.js')) ?>">
  <a class="skip" href="#main">Skip to content</a>
  <div class="shell">
    <aside class="side">
      <a class="logo-tile" href="<?= e(url(Auth::home())) ?>" aria-label="GRADELY home">
        <img src="<?= e(asset('img/gradely-logo.png')) ?>" width="40" height="40" alt="GRADELY">
      </a>
      <nav class="nav" aria-label="Main">
        <?php foreach (Nav::items($u) as $item): $active = $isActive($item); ?>
          <a class="nav-link<?= $active ? ' is-active' : '' ?>" href="<?= e(url($item['path'])) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
            <span class="nav-ico"><?= icon($item['icon'], 22) ?></span>
            <span class="nav-label"><?= e($item['label']) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
      <form class="side-foot" method="post" action="<?= e(url('/logout')) ?>">
        <?= csrf_field() ?>
        <button class="nav-link nav-btn" type="submit">
          <span class="nav-ico"><?= icon('logout', 22) ?></span>
          <span class="nav-label">Sign out</span>
        </button>
      </form>
    </aside>

    <div class="main">
      <header class="top">
        <div>
          <p class="crumb"><?= e($crumb) ?></p>
          <h1><?= e($title ?? '') ?></h1>
        </div>
        <div class="top-actions">
          <button class="btn btn-ghost btn-sm" type="button" data-pwa-install hidden><?= icon('download', 16) ?> Install app</button>
          <a class="icon-btn" href="<?= e(url('/notifications')) ?>" aria-label="Notifications<?= $unread ? ' (' . $unread . ' unread)' : '' ?>">
            <?= icon('bell', 20) ?>
            <?php if ($unread > 0): ?><span class="dot-count"><?= e($unread > 9 ? '9+' : $unread) ?></span><?php endif; ?>
          </a>
          <a class="user-chip" href="<?= e(url('/account/password')) ?>" title="My account">
            <span class="avatar" aria-hidden="true"><?= e(initials($u['name'] ?? '')) ?></span>
            <span class="user-meta">
              <span class="user-name"><?= e($u['name'] ?? '') ?></span>
              <span class="user-role"><?= e(role_label($u['role'] ?? null)) ?></span>
            </span>
          </a>
        </div>
      </header>

      <main id="main" class="content">
        <?= flash_html() ?>
        <?php require BASE_PATH . '/app/Views/partials/ios_hint.php'; ?>
        <?= $content ?>
      </main>

      <footer class="foot">GRADELY · Academic Results. Simplified. · Powered by Techpreneur</footer>
    </div>
  </div>
</body>
</html>
