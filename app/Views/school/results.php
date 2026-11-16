<?php
use App\Core\Auth;
use App\Services\ResultEngine;
/** @var array $terms */ /** @var array $classes */ /** @var int $termId */ /** @var int $classId */ /** @var ?array $view */
$qs = http_build_query(['term_id' => $termId, 'class_id' => $classId]);
$hidden = '<input type="hidden" name="term_id" value="' . $termId . '"><input type="hidden" name="class_id" value="' . $classId . '">';
$post = static fn(string $path, string $label, string $cls, string $confirm = '') => '<form class="inline-form" method="post" action="' . e(url($path)) . '"' . ($confirm !== '' ? ' data-confirm="' . e($confirm) . '"' : '') . '>' . csrf_field() . $hidden . '<button class="btn ' . $cls . ' btn-sm" type="submit">' . e($label) . '</button></form>';
?>
<form class="filters" method="get" action="<?= e(url('/school/results')) ?>" data-autosubmit>
  <?= select_html('Term', 'term_id', term_options($terms), (string) $termId, [], 'Select term…') ?>
  <?= select_html('Class', 'class_id', array_column($classes, 'name', 'id'), (string) $classId, [], 'Select class…') ?>
  <a class="btn btn-ghost btn-sm" href="<?= e(url('/school/archive')) ?>">Archive</a>
</form>

<?php if ($view === null): ?>
  <section class="card"><p class="empty">Choose a term and class to process results.</p></section>
<?php else:
  $status = $view['status']; $c = $view['completeness']; $rows = $view['rows']; $cfg = $view['cfg'];
  $locked = in_array($status, ['approved', 'published', 'mixed'], true);
  $pct = $c['expected'] > 0 ? $c['entered'] / $c['expected'] * 100 : 0;
?>
  <section class="card mb">
    <div class="card-head"><h2>Status</h2><?= status_badge($status) ?></div>
    <div class="stat-row">
      <div class="stat-mini"><b><?= e($c['students']) ?></b><span>students</span></div>
      <div class="stat-mini"><b><?= e($c['subjects']) ?></b><span>subjects</span></div>
      <div class="stat-mini"><b><?= e(num($pct, 0)) ?>%</b><span>scores entered</span></div>
      <div class="stat-mini"><b><?= e(count($c['missing'])) ?></b><span>incomplete subject sets</span></div>
    </div>
    <div class="mt"><?= bar_html($pct, 'Scores entered') ?></div>

    <div class="toolbar-left mt">
      <?php if (!$locked && (Auth::can('scores.enter') || Auth::can('results.approve'))): ?><?= $post('/school/results/compute', 'Compute results', 'btn-ghost') ?><?php endif; ?>
      <?php if ($status === 'draft' && Auth::can('results.approve')): ?><?= $post('/school/results/approve', 'Approve results', 'btn-primary', 'Approve these results? Scores will be locked.') ?><?php endif; ?>
      <?php if ($status === 'approved' && Auth::can('results.publish')): ?><?= $post('/school/results/publish', 'Publish to portal', 'btn-primary', 'Publish now? Students with a valid scratch card will be able to see these results.') ?><?php endif; ?>
      <?php if ($status === 'approved' && Auth::can('results.approve')): ?><?= $post('/school/results/revert', 'Revert to draft', 'btn-ghost', 'Revert to draft so scores can be edited?') ?><?php endif; ?>
      <?php if ($status === 'published' && Auth::can('results.publish')): ?><?= $post('/school/results/unpublish', 'Unpublish', 'btn-danger', 'Withdraw these results from the public portal?') ?><?php endif; ?>
      <?php if ($rows): ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/school/reports/class?' . $qs)) ?>"><?= icon('print', 16) ?> Report cards (print / PDF)</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/school/results/export.csv?' . $qs)) ?>"><?= icon('download', 16) ?> Export CSV</a>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/school/results/comments?' . $qs)) ?>">Comments</a>
      <?php endif; ?>
    </div>
    <p class="muted small mt">Flow: enter scores → compute → approve (locks scores) → publish. Approving recomputes first, and requires every score to be entered.</p>
  </section>

  <?php if ($c['missing']): ?>
  <section class="card mb">
    <div class="card-head"><h2>Missing scores</h2><?= badge_html(count($c['missing']) . ' to complete', 'amber') ?></div>
    <ul class="missing-list">
      <?php foreach (array_slice($c['missing'], 0, 60) as $m): ?><li><strong><?= e($m['student']) ?></strong> · <?= e($m['subject']) ?>: <?= e(implode(', ', $m['components'])) ?></li><?php endforeach; ?>
      <?php if (count($c['missing']) > 60): ?><li class="muted">…and <?= count($c['missing']) - 60 ?> more</li><?php endif; ?>
    </ul>
  </section>
  <?php endif; ?>

  <section class="card">
    <div class="card-head"><h2>Class results</h2></div>
    <?php if (!$rows): ?><p class="empty">Nothing computed yet. Enter scores, then press “Compute results”.</p><?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Pos.</th><th>Student</th><th class="num">Subjects</th><th class="num">Total</th><th class="num">Average</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><strong><?= $r['position'] === null ? '—' : e(ResultEngine::ordinal((int) $r['position'])) ?></strong></td>
          <td><strong><?= e($r['last_name']) ?></strong> <?= e($r['first_name']) ?> <span class="muted small"><?= e($r['admission_no']) ?></span></td>
          <td class="num"><?= e($r['subjects_count']) ?></td><td class="num"><?= e(num($r['total'])) ?></td><td class="num"><?= e(num($r['average'])) ?>%</td>
          <td><?= status_badge($r['status']) ?></td>
          <td class="table-actions"><?php if ((int) $r['subjects_count'] > 0): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('/school/reports/student?term_id=' . $termId . '&student_id=' . $r['student_id'])) ?>">Report card</a><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="muted small mt">Report cards and the student portal show <?= $cfg['display_mode'] === 'both' ? 'grades and positions' : ($cfg['display_mode'] === 'grades' ? 'grades only' : 'positions only') ?> (change this under Setup → Grading &amp; scoring).</p>
    <?php endif; ?>
  </section>
<?php endif; ?>
