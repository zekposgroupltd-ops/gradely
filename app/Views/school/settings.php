<?php /** @var array $cfg */ /** @var array $grades */ /** @var array $components */ /** @var array $usage */
$total = array_sum(array_map(static fn($c) => (float) $c['max_score'], $components));
$modes = [
    'grades' => ['Grades only', 'Report cards show grades and remarks. Positions are hidden everywhere.'],
    'positions' => ['Positions only', 'Report cards show positions. Grades and remarks are hidden everywhere.'],
    'both' => ['Grades and positions', 'Report cards show both.'],
];
?>
<form class="stack" method="post" action="<?= e(url('/school/settings')) ?>">
  <?= csrf_field() ?>
  <section class="card">
    <div class="card-head"><h2>What report cards display</h2></div>
    <p class="muted mb">Applies to the printed report, PDF/print view, exports and the online result portal. A hidden value is never sent to the student.</p>
    <div class="radio-cards" role="radiogroup" aria-label="Display mode">
      <?php foreach ($modes as $k => [$label, $desc]): ?>
        <label class="radio-card"><input type="radio" name="display_mode" value="<?= e($k) ?>" <?= $cfg['display_mode'] === $k ? 'checked' : '' ?>><strong><?= e($label) ?></strong><span><?= e($desc) ?></span></label>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h2>Positions & passing</h2></div>
    <div class="form-row">
      <?= select_html('Rank students by', 'position_by', ['average' => 'Average score', 'total' => 'Total score'], $cfg['position_by']) ?>
      <?= select_html('When students tie', 'tie_mode', ['competition' => 'Share a position, skip the next (1, 2, 2, 4)', 'dense' => 'Share a position, no skipping (1, 2, 2, 3)'], $cfg['tie_mode']) ?>
      <?= field_html('Pass mark (%)', 'pass_mark', num($cfg['pass_mark']), 'number', ['min' => '0', 'max' => '100', 'step' => '0.01']) ?>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h2>Scratch cards & report card</h2></div>
    <div class="form-row">
      <?= select_html('A card may be used by', 'card_binding', ['first_student' => 'Only the first student who uses it', 'none' => 'Any student of the school'], $cfg['card_binding']) ?>
      <?= field_html('Principal\'s name', 'principal_name', (string) $cfg['principal_name'], 'text', ['maxlength' => '150']) ?>
      <?= field_html('Report footer note', 'report_footer', (string) $cfg['report_footer'], 'text', ['maxlength' => '255', 'placeholder' => 'This report is computer-generated.']) ?>
    </div>
    <label class="check mt"><input type="checkbox" name="allow_download" value="1" <?= (int) $cfg['allow_download'] ? 'checked' : '' ?>><span>Allow students to print or save their result as PDF<small>When off, the portal hides the print button and the printed page is blank.</small></span></label>
    <p class="mt"><button class="btn btn-primary" type="submit">Save settings</button></p>
  </section>
</form>

<form class="card mt" method="post" action="<?= e(url('/school/settings/components')) ?>">
  <?= csrf_field() ?>
  <div class="card-head"><h2>Assessment components</h2><span class="badge badge-green">Total <?= e(num($total)) ?> marks</span></div>
  <p class="muted mb">Tests, assignments, projects and exams. A component that already has scores cannot be removed or have its maximum changed.</p>
  <div class="table-wrap"><table class="table table-tight">
    <thead><tr><th>Name</th><th>Max score</th><th class="c">Exam</th><th class="c">Remove</th></tr></thead>
    <tbody>
    <?php $i = 0; foreach ($components as $c): $locked = isset($usage[$c['id']]); ?>
      <tr>
        <td><input type="hidden" name="id[<?= $i ?>]" value="<?= (int) $c['id'] ?>"><input class="cell" name="name[<?= $i ?>]" value="<?= e($c['name']) ?>" maxlength="60" required aria-label="Component name"></td>
        <td><input class="cell" name="max[<?= $i ?>]" value="<?= e(num($c['max_score'])) ?>" type="number" step="0.01" min="0.01" max="999" required <?= $locked ? 'readonly title="Has scores"' : '' ?> aria-label="Maximum score"></td>
        <td class="c"><input class="tick-box" type="radio" name="exam" value="<?= $i ?>" <?= (int) $c['is_exam'] ? 'checked' : '' ?> aria-label="Is the exam"></td>
        <td class="c"><?= $locked ? '<span class="muted small">' . e($usage[$c['id']]) . ' scores</span>' : '<input class="tick-box" type="checkbox" name="remove[' . (int) $c['id'] . ']" value="1" aria-label="Remove">' ?></td>
      </tr>
    <?php $i++; endforeach; ?>
    <?php for ($k = 0; $k < 2; $k++, $i++): ?>
      <tr class="muted-row">
        <td><input type="hidden" name="id[<?= $i ?>]" value="0"><input class="cell" name="name[<?= $i ?>]" placeholder="New component (e.g. Project)" maxlength="60" aria-label="New component name"></td>
        <td><input class="cell" name="max[<?= $i ?>]" type="number" step="0.01" min="0.01" max="999" placeholder="10" aria-label="Maximum score"></td>
        <td class="c"><input class="tick-box" type="radio" name="exam" value="<?= $i ?>" aria-label="Is the exam"></td><td></td>
      </tr>
    <?php endfor; ?>
    </tbody>
  </table></div>
  <p class="mt"><button class="btn btn-primary" type="submit">Save components</button></p>
</form>

<form class="card mt" method="post" action="<?= e(url('/school/settings/grades')) ?>">
  <?= csrf_field() ?>
  <div class="card-head"><h2>Grading scale</h2></div>
  <p class="muted mb">Each grade starts at a minimum percentage and runs up to the next grade. The lowest grade must start at 0. Existing results keep their grades until you recompute them.</p>
  <div class="table-wrap"><table class="table table-tight">
    <thead><tr><th>Grade</th><th>Minimum %</th><th>Remark</th></tr></thead>
    <tbody>
    <?php $i = 0; foreach ($grades as $g): ?>
      <tr><td><input class="cell" name="grade[<?= $i ?>]" value="<?= e($g['grade']) ?>" maxlength="5" aria-label="Grade"></td>
          <td><input class="cell" name="min[<?= $i ?>]" value="<?= e(num($g['min_score'])) ?>" type="number" step="0.01" min="0" max="100" aria-label="Minimum percentage"></td>
          <td><input class="cell" name="remark[<?= $i ?>]" value="<?= e((string) $g['remark']) ?>" maxlength="60" aria-label="Remark"></td></tr>
    <?php $i++; endforeach; ?>
    <?php for ($k = 0; $k < 3; $k++, $i++): ?>
      <tr class="muted-row"><td><input class="cell" name="grade[<?= $i ?>]" maxlength="5" placeholder="New" aria-label="New grade"></td>
          <td><input class="cell" name="min[<?= $i ?>]" type="number" step="0.01" min="0" max="100" aria-label="Minimum percentage"></td>
          <td><input class="cell" name="remark[<?= $i ?>]" maxlength="60" aria-label="Remark"></td></tr>
    <?php endfor; ?>
    </tbody>
  </table></div>
  <p class="mt"><button class="btn btn-primary" type="submit">Save grading scale</button></p>
</form>
