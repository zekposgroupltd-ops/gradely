<?php /** @var array $rows */ ?>
<section class="card">
  <div class="card-head"><h2>Results by session, term and class</h2></div>
  <?php if (!$rows): ?><p class="empty">No results have been computed yet.</p><?php else: ?>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Session</th><th>Term</th><th>Class</th><th class="num">Students</th><th class="num">Average</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): $st = $r['status_min'] === $r['status_max'] ? $r['status_min'] : 'mixed'; $qs = 'term_id=' . $r['term_id'] . '&class_id=' . $r['class_id']; ?>
      <tr>
        <td><?= e($r['session_name']) ?></td><td><?= e($r['term_name']) ?></td><td><?= e($r['class_name']) ?></td>
        <td class="num"><?= e($r['students']) ?></td><td class="num"><?= e(num($r['avg'], 1)) ?>%</td><td><?= status_badge($st) ?></td>
        <td class="table-actions">
          <a class="btn btn-ghost btn-sm" href="<?= e(url('/school/results?' . $qs)) ?>">Open</a>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('/school/reports/class?' . $qs)) ?>">Report cards</a>
          <a class="btn btn-ghost btn-sm" href="<?= e(url('/school/results/export.csv?' . $qs)) ?>">CSV</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
