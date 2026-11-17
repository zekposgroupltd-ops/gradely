<?php /** @var bool $verified */ /** @var ?array $details */ /** @var string $message */ ?>
<section class="portal-card verify-card">
  <div class="verify-status <?= $verified ? 'is-valid' : 'is-invalid' ?>">
    <strong><?= $verified ? 'ORIGINAL RESULT — VERIFIED' : 'VERIFICATION FAILED' ?></strong>
    <p><?= e($message) ?></p>
  </div>
  <?php if ($verified && $details): ?>
    <dl class="verify-details">
      <div><dt>School</dt><dd><?= e($details['school']) ?></dd></div>
      <div><dt>Student ID</dt><dd><?= e($details['student_id']) ?></dd></div>
      <div><dt>Student name</dt><dd><?= e($details['student_name']) ?></dd></div>
      <div><dt>Academic session</dt><dd><?= e($details['session']) ?></dd></div>
      <div><dt>Term</dt><dd><?= e($details['term']) ?></dd></div>
    </dl>
  <?php endif; ?>
  <p class="small muted">GRADELY verifies authenticity against the school's current published record. This page does not provide a report download.</p>
  <a class="btn btn-ghost btn-block" href="<?= e(url('/')) ?>">Return to GRADELY</a>
</section>
