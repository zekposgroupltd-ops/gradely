<?php /** @var array $c */ /** @var array $usages */
$eff = $c['status'] === 'active' && $c['expires_at'] !== null && $c['expires_at'] <= date('Y-m-d H:i:s') ? 'expired' : $c['status'];
?>
<div class="toolbar"><a class="btn btn-ghost btn-sm" href="<?= e(url('/school/cards/batches/' . $c['batch_id'])) ?>">← Batch #<?= e($c['batch_id']) ?></a>
  <?php if (in_array($eff, ['active', 'expired'], true)): ?>
    <form class="inline-form" method="post" action="<?= e(url('/school/cards/' . $c['id'] . '/revoke')) ?>" data-confirm="Revoke this card? It will stop working immediately.">
      <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Revoke card</button></form>
  <?php endif; ?></div>
<section class="card mb">
  <div class="card-head"><h2><code><?= e($c['serial']) ?></code></h2><?= status_badge($eff) ?></div>
  <dl class="kv kv-wide">
    <dt>Term</dt><dd><?= e($c['session_name'] . ' · ' . $c['term_name']) ?></dd>
    <dt>Checks used</dt><dd><?= e($c['use_count']) ?> of <?= e($c['max_uses']) ?></dd>
    <dt>Used by</dt><dd><?= $c['admission_no'] ? e($c['last_name'] . ' ' . $c['first_name'] . ' (' . $c['admission_no'] . ')') : '—' ?></dd>
    <dt>First used</dt><dd><?= e($c['first_used_at'] ?? '—') ?></dd>
    <dt>Expires</dt><dd><?= e($c['expires_at'] ?? 'No expiry') ?></dd>
    <dt>Created</dt><dd><?= e($c['created_at']) ?></dd>
  </dl>
</section>
<section class="card">
  <div class="card-head"><h2>Usage history</h2></div>
  <?php if (!$usages): ?><p class="empty">This card has not been used yet.</p><?php else: ?>
  <div class="table-wrap"><table class="table table-tight">
    <thead><tr><th>#</th><th>When</th><th>Student</th><th>IP address</th></tr></thead>
    <tbody><?php foreach ($usages as $i => $u): ?>
      <tr><td><?= $i + 1 ?></td><td class="nowrap"><?= e($u['used_at']) ?></td><td><?= e($u['last_name'] . ' ' . $u['first_name'] . ' (' . $u['admission_no'] . ')') ?></td><td><?= e($u['ip']) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
