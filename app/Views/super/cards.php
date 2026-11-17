<?php /** @var array $perSchool */ /** @var array $totals */ /** @var array $recent */ ?>
<div class="tabs" role="navigation" aria-label="Platform cards"><a class="tab is-active" href="<?= e(url('/super/cards')) ?>">Card monitor</a><a class="tab" href="<?= e(url('/super/pin-requests')) ?>">PIN purchase requests</a><a class="tab" href="<?= e(url('/super/settings')) ?>">Pricing settings</a></div>
<section class="stat-row mb">
  <div class="stat-mini"><b><?= e($totals['total']) ?></b><span>cards issued</span></div>
  <div class="stat-mini"><b><?= e($totals['unused']) ?></b><span>unused</span></div>
  <div class="stat-mini"><b><?= e($totals['in_use']) ?></b><span>in use</span></div>
  <div class="stat-mini"><b><?= e($totals['exhausted']) ?></b><span>exhausted</span></div>
  <div class="stat-mini"><b><?= e($totals['revoked'] + $totals['expired']) ?></b><span>revoked / expired</span></div>
</section>
<section class="card mb">
  <div class="card-head"><h2>By school</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>School</th><th class="num">Total</th><th class="num">Unused</th><th class="num">In use</th><th class="num">Exhausted</th><th class="num">Revoked</th></tr></thead>
    <tbody><?php foreach ($perSchool as $r): $s = $r['stats']; ?>
      <tr><td><a href="<?= e(url('/super/schools/' . $r['id'])) ?>"><?= e($r['school']) ?></a></td><td class="num"><?= e($s['total']) ?></td><td class="num"><?= e($s['unused']) ?></td><td class="num"><?= e($s['in_use']) ?></td><td class="num"><?= e($s['exhausted']) ?></td><td class="num"><?= e($s['revoked']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<section class="card">
  <div class="card-head"><h2>Latest successful result checks</h2></div>
  <?php if (!$recent): ?><p class="empty">No result checks yet.</p><?php else: ?>
  <div class="table-wrap"><table class="table table-tight">
    <thead><tr><th>When</th><th>School</th><th>Term</th><th>Student ID</th><th>Card</th><th>IP</th></tr></thead>
    <tbody><?php foreach ($recent as $r): ?>
      <tr><td class="nowrap"><?= e($r['used_at']) ?></td><td><?= e($r['school_name']) ?></td><td><?= e($r['term_name']) ?></td><td><?= e($r['admission_no']) ?></td><td><code><?= e($r['serial']) ?></code></td><td><?= e($r['ip']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
