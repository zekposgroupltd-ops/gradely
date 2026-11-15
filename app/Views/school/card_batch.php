<?php use App\Services\ScratchCardService as S;
/** @var array $b */ /** @var array $cards */ /** @var array $stats */ /** @var string $status */ /** @var int $page */ /** @var int $pages */ /** @var int $total */ /** @var ?array $pins */ ?>
<div class="toolbar">
  <a class="btn btn-ghost btn-sm" href="<?= e(url('/school/cards')) ?>">← All batches</a>
  <div class="toolbar-right">
    <form class="inline-form" method="post" action="<?= e(url('/school/cards/batches/' . $b['id'] . '/revoke')) ?>" data-confirm="Revoke every UNUSED card in this batch?">
      <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Revoke unused cards</button>
    </form>
  </div>
</div>

<?php if ($pins !== null): ?>
<section class="pin-box mb" aria-labelledby="pins-h">
  <h2 id="pins-h">Save these PINs now</h2>
  <p>PINs are stored only as a one-way hash, so this is the only time they can be shown. Download or print them before leaving this page.</p>
  <div class="toolbar-left mt">
    <a class="btn btn-primary btn-sm" href="<?= e(url('/school/cards/batches/' . $b['id'] . '/pins.csv')) ?>"><?= icon('download', 16) ?> Download CSV (once)</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/school/cards/batches/' . $b['id'] . '/slips')) ?>" target="_blank" rel="noopener"><?= icon('print', 16) ?> Print slips</a>
    <form class="inline-form" method="post" action="<?= e(url('/school/cards/batches/' . $b['id'] . '/discard')) ?>" data-confirm="Clear the PIN list? You will not be able to see these PINs again.">
      <?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">I've saved them, clear the list</button>
    </form>
  </div>
  <ul class="pin-list">
    <?php foreach (array_slice($pins, 0, 12) as $c): ?><li><span class="muted small"><?= e($c['serial']) ?></span><code><?= e(S::formatPin($c['pin'])) ?></code></li><?php endforeach; ?>
  </ul>
  <?php if (count($pins) > 12): ?><p class="muted small mt">Showing 12 of <?= count($pins) ?>. The CSV and print view contain all of them.</p><?php endif; ?>
</section>
<?php endif; ?>

<section class="card mb">
  <div class="card-head"><h2>Batch #<?= e($b['id']) ?> <?= e($b['label'] ?? '') ?></h2><span class="muted"><?= e($b['session_name'] . ' · ' . $b['term_name']) ?></span></div>
  <div class="stat-row">
    <div class="stat-mini"><b><?= e($stats['total']) ?></b><span>cards</span></div>
    <div class="stat-mini"><b><?= e($stats['unused']) ?></b><span>unused</span></div>
    <div class="stat-mini"><b><?= e($stats['in_use']) ?></b><span>in use</span></div>
    <div class="stat-mini"><b><?= e($stats['exhausted']) ?></b><span>exhausted</span></div>
    <div class="stat-mini"><b><?= e($stats['revoked']) ?></b><span>revoked</span></div>
  </div>
</section>

<form class="filters" method="get" action="<?= e(url('/school/cards/batches/' . $b['id'])) ?>" data-autosubmit>
  <?= select_html('Show', 'status', ['unused' => 'Unused', 'active' => 'Active', 'exhausted' => 'Exhausted', 'revoked' => 'Revoked', 'expired' => 'Expired'], $status, [], 'All cards') ?>
</form>
<section class="card">
  <?php if (!$cards): ?><p class="empty">No cards match.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Serial</th><th>Status</th><th class="num">Checks used</th><th>Used by</th><th>Last used</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($cards as $c): ?>
      <tr>
        <td><code><?= e($c['serial']) ?></code></td><td><?= status_badge($c['eff_status']) ?></td>
        <td class="num"><?= e($c['use_count']) ?> / <?= e($c['max_uses']) ?></td>
        <td><?= $c['admission_no'] ? e($c['last_name'] . ' ' . $c['first_name'] . ' (' . $c['admission_no'] . ')') : '<span class="muted">—</span>' ?></td>
        <td class="muted nowrap"><?= e($c['last_used_at'] ?? '—') ?></td>
        <td class="table-actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('/school/cards/' . $c['id'])) ?>">Details</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="muted small mt"><?= e($total) ?> card<?= $total === 1 ? '' : 's' ?></p>
  <?= pager_html($page, $pages, ['status' => $status]) ?>
  <?php endif; ?>
</section>
