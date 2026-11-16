<?php
/** @var array $reports */ /** @var bool $portal */ /** @var string $back */ /** @var ?int $remaining */
$allowPrint = !$portal || (int) ($reports[0]['cfg']['allow_download'] ?? 1) === 1;
?>
<div class="report-toolbar no-print">
  <?php if ($portal): ?>
    <form method="post" action="<?= e(url('/portal/finish')) ?>">
      <?= csrf_field() ?>
      <button class="btn btn-ghost btn-sm" type="submit">← Done</button>
    </form>
  <?php else: ?>
    <a class="btn btn-ghost btn-sm" href="<?= e(url($back)) ?>">← Back</a>
  <?php endif; ?>
  <span class="report-toolbar-note">
    <?php if ($portal): ?>
      This card has <strong><?= e((int) ($remaining ?? 0)) ?></strong> result check<?= ($remaining ?? 0) === 1 ? '' : 's' ?> left.
    <?php else: ?>
      <?= count($reports) ?> report card<?= count($reports) === 1 ? '' : 's' ?> · <?= e(ucfirst($reports[0]['status'])) ?><?= count($reports) > 1 ? ' and others' : '' ?>
    <?php endif; ?>
  </span>
  <?php if ($allowPrint): ?>
    <button class="btn btn-primary btn-sm" type="button" data-print><?= icon('print', 16) ?> Print / Save as PDF</button>
  <?php endif; ?>
</div>
<?php if (!$portal && count($reports) > 0): ?>
  <p class="report-hint no-print">Tip: in the print dialog choose <strong>Save as PDF</strong>, paper <strong>A4</strong>, margins <strong>None</strong>. Each student starts on a new page.</p>
<?php endif; ?>

<?php foreach ($reports as $r): ?>
  <?php require BASE_PATH . '/app/Views/report/card.php'; ?>
<?php endforeach; ?>
