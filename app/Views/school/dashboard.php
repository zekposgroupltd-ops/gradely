<?php
/** @var array $school */ /** @var array $counts */ /** @var ?array $current */ /** @var array $checklist */ /** @var string $announcement */
use App\Core\Auth;
$done = count(array_filter($checklist, static fn($c) => $c[1]));
?>
<?php if ($announcement !== ''): ?><div class="banner" role="note"><?= e($announcement) ?></div><?php endif; ?>

<section class="grid grid-4" aria-label="Key figures">
  <article class="card stat"><p class="stat-label"><?= icon('users', 18) ?> Active students</p><p class="stat-value"><?= e($counts['students']) ?></p></article>
  <article class="card stat"><p class="stat-label"><?= icon('layers', 18) ?> Classes</p><p class="stat-value"><?= e($counts['classes']) ?></p></article>
  <article class="card stat"><p class="stat-label"><?= icon('book', 18) ?> Subjects</p><p class="stat-value"><?= e($counts['subjects']) ?></p></article>
  <article class="card stat"><p class="stat-label"><?= icon('shield', 18) ?> Assessment components</p><p class="stat-value"><?= e($counts['components']) ?></p></article>
</section>

<section class="grid grid-2">
  <article class="card">
    <div class="card-head"><h2>Current term</h2><?php if (Auth::role() === 'school_admin'): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/school/academics')) ?>">Manage</a><?php endif; ?></div>
    <?php if ($current): ?>
      <p class="big"><?= e($current['term_name']) ?></p>
      <p class="muted"><?= e($current['session_name']) ?> academic session</p>
    <?php else: ?>
      <p class="empty">No current term has been set yet.</p>
    <?php endif; ?>
  </article>

  <?php if (Auth::role() === 'school_admin'): ?>
  <article class="card">
    <div class="card-head"><h2>Getting started</h2><span class="badge badge-green"><?= $done ?> of <?= count($checklist) ?></span></div>
    <?= bar_html(count($checklist) ? $done / count($checklist) * 100 : 0, 'Setup progress') ?>
    <ul class="checklist mt">
      <?php foreach ($checklist as [$label, $ok, $href]): ?>
        <li class="<?= $ok ? 'done' : '' ?>"><span class="tick"><?= $ok ? icon('check', 14) : '' ?></span><a href="<?= e(url($href)) ?>"><?= e($label) ?></a></li>
      <?php endforeach; ?>
    </ul>
  </article>
  <?php else: ?>
  <article class="card">
    <div class="card-head"><h2>School</h2><?= status_badge($school['status'] ?? 'active') ?></div>
    <dl class="kv">
      <dt>Name</dt><dd><?= e($school['name'] ?? '—') ?></dd>
      <dt>Email</dt><dd><?= e($school['email'] ?? '—') ?></dd>
      <dt>Phone</dt><dd><?= e($school['phone'] ?? '—') ?></dd>
    </dl>
  </article>
  <?php endif; ?>
</section>
