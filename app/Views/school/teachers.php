<?php /** @var array $teachers */ ?>
<div class="toolbar"><span class="muted">Teachers can only upload subject scores. Active teacher accounts: <?= (int)$teacherCount ?> / <?= $teacherLimit === null ? 'Unlimited' : (int)$teacherLimit ?>.</span>
  <?php if ($teacherLimit === null || $teacherCount < $teacherLimit): ?><a class="btn btn-primary btn-sm" href="<?= e(url('/school/teachers/new')) ?>"><?= icon('plus', 16) ?> Add teacher</a><?php endif; ?></div>
<section class="card">
  <?php if (!$teachers): ?><p class="empty">No teachers yet.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Assignments</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($teachers as $t): ?>
      <tr>
        <td><strong><?= e($t['name']) ?></strong></td><td><?= e($t['email']) ?></td>
        <td><?= e($t['assignments']) ?></td><td><?= status_badge($t['status']) ?></td>
        <td class="muted"><?= e($t['last_login_at'] ?? 'Never') ?></td>
        <td class="table-actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('/school/teachers/' . $t['id'] . '/edit')) ?>">Manage</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
