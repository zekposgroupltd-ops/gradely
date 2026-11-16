<?php /** @var array $terms */ /** @var array $batches */ /** @var array $stats */ ?>
<section class="grid grid-4">
  <article class="card stat"><p class="stat-label"><?= icon('card', 18) ?> Total cards</p><p class="stat-value"><?= e($stats['total']) ?></p></article>
  <article class="card stat"><p class="stat-label">Unused</p><p class="stat-value"><?= e($stats['unused']) ?></p></article>
  <article class="card stat"><p class="stat-label">In use</p><p class="stat-value"><?= e($stats['in_use']) ?></p><p class="stat-sub">1 to 4 checks made</p></article>
  <article class="card stat"><p class="stat-label">Exhausted</p><p class="stat-value"><?= e($stats['exhausted']) ?></p><p class="stat-sub"><?= e($stats['revoked']) ?> revoked · <?= e($stats['expired']) ?> expired</p></article>
</section>

<section class="card mb">
  <div class="card-head"><h2>Generate a batch</h2></div>
  <?php if (!$terms): ?>
    <p class="empty">Add a session and term first (Setup → Sessions &amp; terms).</p>
  <?php else: ?>
  <p class="muted mb">Each card works for one term only, at your school only, for <?= (int) \App\Services\ScratchCardService::MAX_USES ?> successful result checks. PINs are shown once, right after generation.</p>
  <form class="filters" method="post" action="<?= e(url('/school/cards/generate')) ?>">
    <?= csrf_field() ?>
    <?= select_html('Term', 'term_id', term_options($terms), '', ['required' => true]) ?>
    <?= field_html('How many', 'quantity', '50', 'number', ['min' => '1', 'max' => '1000', 'required' => true]) ?>
    <?= field_html('Label', 'label', '', 'text', ['maxlength' => '100', 'placeholder' => 'e.g. JSS 1 parents'], '(optional)') ?>
    <?= field_html('Valid until', 'expires_on', '', 'date', [], '(optional)') ?>
    <button class="btn btn-primary" type="submit"><?= icon('plus', 18) ?> Generate cards</button>
  </form>
  <?php endif; ?>
</section>

<section class="card">
  <div class="card-head"><h2>Batches</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('/school/cards/export.csv')) ?>"><?= icon('download', 16) ?> Export inventory</a></div>
  <?php if (!$batches): ?><p class="empty">No batches yet.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Batch</th><th>Term</th><th class="num">Cards</th><th class="num">Unused</th><th class="num">Used</th><th class="num">Revoked</th><th>Created</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($batches as $b): ?>
      <tr>
        <td><strong>#<?= e($b['id']) ?></strong> <?= e($b['label'] ?? '') ?></td><td><?= e($b['session_name'] . ' · ' . $b['term_name']) ?></td>
        <td class="num"><?= e($b['quantity']) ?></td><td class="num"><?= e($b['unused']) ?></td><td class="num"><?= e($b['touched']) ?></td><td class="num"><?= e($b['revoked']) ?></td>
        <td class="nowrap muted"><?= e($b['created_at']) ?></td>
        <td class="table-actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('/school/cards/batches/' . $b['id'])) ?>">Open</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
