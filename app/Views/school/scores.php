<?php /** @var array $terms */ /** @var array $classes */ /** @var array $subjects */ /** @var int $termId */ /** @var int $classId */ /** @var int $subjectId */ /** @var ?array $grid */
$qs = http_build_query(['term_id' => $termId, 'class_id' => $classId, 'subject_id' => $subjectId]);
?>
<form class="filters" method="get" action="<?= e(url('/school/scores')) ?>" data-autosubmit>
  <?= select_html('Term', 'term_id', term_options($terms), (string) $termId, [], 'Select term…') ?>
  <?= select_html('Class', 'class_id', array_column($classes, 'name', 'id'), (string) $classId, [], 'Select class…') ?>
  <?= select_html('Subject', 'subject_id', array_column($subjects, 'name', 'id'), (string) $subjectId, $classId ? [] : ['disabled' => true], 'Select subject…') ?>
  <noscript><button class="btn btn-ghost btn-sm" type="submit">Load</button></noscript>
</form>

<?php if (!$classes): ?>
  <section class="card"><p class="empty">You have not been assigned any classes yet. Ask your school administrator to assign subjects to you.</p></section>
<?php elseif ($grid === null): ?>
  <section class="card"><p class="empty">Choose a term, class and subject to enter scores.</p></section>
<?php else: $comps = $grid['components']; ?>
  <?php if ($grid['locked']): ?>
    <div class="alert alert-info lock-note"><?= icon('shield', 18) ?> Results for this class are approved or published, so scores are locked. An administrator can revert them to draft to allow corrections.</div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('/school/scores')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="term_id" value="<?= $termId ?>"><input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="subject_id" value="<?= $subjectId ?>">
    <section class="card">
      <div class="card-head"><h2>Scores</h2><span class="muted small"><?= count($grid['students']) ?> student<?= count($grid['students']) === 1 ? '' : 's' ?> · Enter moves down a column</span></div>
      <?php if (!$grid['students']): ?>
        <p class="empty">There are no active students in this class.</p>
      <?php else: ?>
      <div class="table-wrap"><table class="table table-tight" data-score-grid>
        <thead><tr><th class="sticky-col">Student</th>
          <?php foreach ($comps as $c): ?><th class="c"><?= e($c['name']) ?><br><span class="muted small">/ <?= e(num($c['max_score'])) ?></span></th><?php endforeach; ?>
          <th class="c">Total</th></tr></thead>
        <tbody>
        <?php foreach ($grid['students'] as $s): ?>
          <tr>
            <td class="sticky-col"><strong><?= e($s['last_name']) ?></strong> <?= e($s['first_name']) ?><br><span class="muted small"><?= e($s['admission_no']) ?></span></td>
            <?php foreach ($comps as $c): ?>
              <td class="c"><input class="score-input" type="text" inputmode="decimal" autocomplete="off"
                   name="scores[<?= (int) $s['id'] ?>][<?= (int) $c['id'] ?>]" value="<?= e($grid['scores'][$s['id']][$c['id']] ?? '') ?>"
                   data-max="<?= e(num($c['max_score'])) ?>" data-col="<?= (int) $c['id'] ?>" <?= $grid['locked'] ? 'readonly' : '' ?>
                   aria-label="<?= e($s['last_name'] . ' ' . $s['first_name'] . ' ' . $c['name']) ?>"></td>
            <?php endforeach; ?>
            <td class="c b" data-row-total>—</td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php if (!$grid['locked']): ?>
        <div class="filters mt">
          <?= field_html('Reason for corrections', 'reason', '', 'text', ['maxlength' => '255', 'placeholder' => 'Optional, kept in the history'], '(optional)') ?>
          <button class="btn btn-primary" type="submit">Save scores</button>
        </div>
        <p class="muted small">An asterisk (*) means some components are still empty. Clearing a box and saving removes that score; every change is kept in the history below.</p>
      <?php endif; ?>
      <?php endif; ?>
    </section>
  </form>

  <?php if (!$grid['locked'] && $grid['students']): ?>
  <section class="card mt">
    <div class="card-head"><h2>Bulk import</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('/school/scores/template.csv?' . $qs)) ?>"><?= icon('download', 16) ?> Download sheet</a></div>
    <p class="muted mb">Download the sheet (it lists this class and any existing scores), fill it in Excel, then upload the CSV or .xlsx. If any cell is invalid, nothing is saved. Blank cells are left unchanged.</p>
    <form class="filters" method="post" action="<?= e(url('/school/scores/import')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="term_id" value="<?= $termId ?>"><input type="hidden" name="class_id" value="<?= $classId ?>"><input type="hidden" name="subject_id" value="<?= $subjectId ?>">
      <label class="field"><span class="field-label">File</span><input type="file" name="file" accept=".csv,.xlsx,text/csv" required></label>
      <button class="btn btn-ghost" type="submit"><?= icon('upload', 18) ?> Import scores</button>
    </form>
  </section>
  <?php endif; ?>

  <?php if ($grid['history']): ?>
  <section class="card mt">
    <div class="card-head"><h2>Recent changes</h2></div>
    <div class="table-wrap"><table class="table table-tight">
      <thead><tr><th>When</th><th>Student</th><th>Component</th><th class="num">From</th><th class="num">To</th><th>By</th><th>Reason</th></tr></thead>
      <tbody>
      <?php foreach ($grid['history'] as $h): ?>
        <tr><td class="nowrap"><?= e($h['changed_at']) ?></td><td><?= e($h['last_name'] . ' ' . $h['first_name']) ?></td><td><?= e($h['component']) ?></td>
            <td class="num"><?= e(num($h['old_score'])) ?></td><td class="num"><?= $h['action'] === 'delete' ? 'cleared' : e(num($h['new_score'])) ?></td>
            <td><?= e($h['by_name'] ?? '—') ?></td><td><?= e($h['reason'] ?? ($h['action'] === 'import' ? 'Import' : '')) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <?php endif; ?>
<?php endif; ?>
