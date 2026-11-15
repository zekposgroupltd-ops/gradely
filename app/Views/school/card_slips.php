<?php use App\Services\ScratchCardService as S; /** @var array $b */ /** @var array $pins */ /** @var array $school */ ?>
<div class="report-toolbar no-print">
  <a class="btn btn-ghost btn-sm" href="<?= e(url('/school/cards/batches/' . $b['id'])) ?>">← Back</a>
  <span class="report-toolbar-note"><?= count($pins) ?> scratch cards · cut along the dashed lines</span>
  <button class="btn btn-primary btn-sm" type="button" data-print><?= icon('print', 16) ?> Print</button>
</div>
<div class="slips">
  <?php foreach ($pins as $c): ?>
    <div class="slip">
      <div class="slip-school"><?= e($school['name']) ?></div>
      <div class="slip-term"><?= e($b['session_name'] . ' · ' . $b['term_name']) ?> result checker</div>
      <div class="slip-pin"><?= e(S::formatPin($c['pin'])) ?></div>
      <div class="slip-meta">Serial <?= e($c['serial']) ?> · valid for <?= (int) S::MAX_USES ?> checks · GRADELY</div>
    </div>
  <?php endforeach; ?>
</div>
