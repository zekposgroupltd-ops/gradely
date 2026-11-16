<?php /** @var array $schools */ /** @var string $q */ /** @var string $status */ ?>
<div class="toolbar">
  <form class="filters" method="get" action="<?= e(url('/super/schools')) ?>" data-autosubmit>
    <label class="field"><span class="field-label">Search</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="School name or email"></label>
    <?= select_html('Status', 'status', ['active' => 'Active', 'suspended' => 'Suspended', 'inactive' => 'Inactive'], $status, [], 'All') ?>
    <button class="btn btn-ghost btn-sm" type="submit">Search</button>
  </form>
  <a class="btn btn-primary btn-sm" href="<?= e(url('/super/schools/new')) ?>"><?= icon('plus', 16) ?> Register school</a>
</div>
<section class="card">
  <?php if (!$schools): ?><p class="empty">No schools found.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>School</th><th>Portal link</th><th class="num">Students</th><th class="num">Staff</th><th class="num">Cards</th><th>Licence</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($schools as $s): ?>
      <tr>
        <td><strong><?= e($s['name']) ?></strong><br><span class="muted small"><?= e($s['email'] ?? '') ?></span></td>
        <td><code>/s/<?= e($s['slug']) ?></code></td>
        <td class="num"><?= e($s['students']) ?></td><td class="num"><?= e($s['staff']) ?></td><td class="num"><?= e($s['cards']) ?></td>
        <td class="nowrap"><?= e($s['license_expires_on'] ?? 'No expiry') ?></td>
        <td><?= status_badge($s['status']) ?></td>
        <td class="table-actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('/super/schools/' . $s['id'])) ?>">Manage</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
