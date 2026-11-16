<?php /** @var array $rows */ /** @var int $page */ /** @var int $pages */ /** @var int $total */ /** @var array $schools */ /** @var int $schoolId */ /** @var string $action */ ?>
<form class="filters" method="get" action="<?= e(url('/super/logs')) ?>" data-autosubmit>
  <?= select_html('School', 'school_id', array_column($schools, 'name', 'id'), (string) $schoolId, [], 'All schools') ?>
  <label class="field"><span class="field-label">Action starts with</span><input type="search" name="action" value="<?= e($action) ?>" placeholder="login, results, cards…"></label>
  <button class="btn btn-ghost btn-sm" type="submit">Filter</button>
</form>
<section class="card">
  <?php if (!$rows): ?><p class="empty">No activity matches.</p><?php else: ?>
  <div class="table-wrap"><table class="table table-tight">
    <thead><tr><th>When</th><th>Action</th><th>User</th><th>School</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr><td class="nowrap"><?= e($r['created_at']) ?></td><td><code><?= e($r['action']) ?></code></td><td><?= e($r['user_name'] ?? '—') ?></td><td><?= e($r['school_name'] ?? '—') ?></td>
          <td class="small muted"><?= e(($r['entity'] ? $r['entity'] . ' ' . $r['entity_id'] . ' ' : '') . ($r['meta'] ?? '')) ?></td><td><?= e($r['ip'] ?? '') ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="muted small mt"><?= e($total) ?> event<?= $total === 1 ? '' : 's' ?></p>
  <?= pager_html($page, $pages, ['school_id' => $schoolId, 'action' => $action]) ?>
  <?php endif; ?>
</section>
