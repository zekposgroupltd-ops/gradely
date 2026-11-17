<?php
use App\Services\ResultEngine;

/** @var array $r  one report built by ReportService (display settings already applied) */
$s = $r['school']; $st = $r['student']; $t = $r['term']; $sum = $r['summary'];
$cols = count($r['components']);
$contact = array_filter([$s['address'], $s['phone'] ? 'Tel: ' . $s['phone'] : '', $s['email'], $s['website']]);
$fullName = mb_strtoupper($st['last_name']) . ' ' . $st['first_name'] . ($st['other_name'] ? ' ' . $st['other_name'] : '');
$infoCols = 2 + $cols + 1 + ($r['show_grades'] ? 1 : 0) + ($r['show_positions'] ? 2 : 0) + ($r['show_grades'] ? 1 : 0);
?>
<section class="sheet">
  <div class="sheet-frame">
    <?php if (!empty($s['logo_path'])): ?><div class="rc-watermark" aria-hidden="true"><img src="<?= e(url('media/logo/' . $s['id'])) ?>" alt=""></div><?php endif; ?>
    <header class="rc-head">
      <?php if (!empty($s['logo_path'])): ?>
        <img class="rc-logo" src="<?= e(url('media/logo/' . $s['id'])) ?>" alt="">
      <?php else: ?>
        <span class="rc-logo rc-logo-placeholder" aria-label="School logo not uploaded"><?= e(initials($s['name'])) ?></span>
      <?php endif; ?>
      <div class="rc-head-text">
        <h1 class="rc-school"><?= e($s['name']) ?></h1>
        <?php if ($contact): ?><p class="rc-addr"><?= e(implode(' · ', $contact)) ?></p><?php endif; ?>
      </div>
    </header>

    <h2 class="rc-title">Term Result Sheet · <?= e($t['term_name']) ?> · <?= e($t['session_name']) ?></h2>

    <dl class="rc-info">
      <div><dt>Student's name</dt><dd><?= e($fullName) ?></dd></div>
      <div><dt>Student ID</dt><dd><?= e($st['admission_no']) ?></dd></div>
      <div><dt>Class</dt><dd><?= e($st['class_name'] . ($st['arm_name'] ? ' ' . $st['arm_name'] : '')) ?></dd></div>
      <?php if ($sum['class_size']): ?><div><dt>Number in class</dt><dd><?= e($sum['class_size']) ?></dd></div><?php endif; ?>
    </dl>

    <div class="rc-table-wrap">
      <table class="rc-table">
        <thead>
          <tr>
            <th rowspan="2" class="c">S/N</th>
            <th rowspan="2" class="l">Subject</th>
            <?php foreach ($r['components'] as $c): ?><th class="c"><?= e($c['name']) ?></th><?php endforeach; ?>
            <th class="c">Total</th>
            <th class="c">Class avg</th>
            <?php if ($r['show_grades']): ?><th class="c">Grade</th><?php endif; ?>
            <?php if ($r['show_positions']): ?><th class="c">Position</th><?php endif; ?>
            <?php if ($r['show_grades']): ?><th class="l">Remark</th><?php endif; ?>
          </tr>
          <tr class="rc-max">
            <?php foreach ($r['components'] as $c): ?><th class="c"><?= e(num($c['max_score'])) ?></th><?php endforeach; ?>
            <th class="c"><?= e(num($r['max_total'])) ?></th>
            <th class="c"></th>
            <?php if ($r['show_grades']): ?><th></th><?php endif; ?>
            <?php if ($r['show_positions']): ?><th></th><?php endif; ?>
            <?php if ($r['show_grades']): ?><th></th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($r['rows'] as $i => $row): ?>
            <tr>
              <td class="c"><?= $i + 1 ?></td>
              <td class="l"><?= e($row['subject']) ?></td>
              <?php foreach ($row['scores'] as $sc): ?><td class="c"><?= e($sc === null ? '—' : num($sc)) ?></td><?php endforeach; ?>
              <td class="c b"><?= e($row['total'] === null ? '—' : num($row['total'])) ?></td>
              <td class="c"><?= e($row['class_average'] === null ? '—' : num($row['class_average'], 1)) ?></td>
              <?php if ($r['show_grades']): ?><td class="c b"><?= e($row['grade'] ?? '—') ?></td><?php endif; ?>
              <?php if ($r['show_positions']): ?><td class="c"><?= $row['position'] === null ? '—' : e(ResultEngine::ordinal($row['position'])) ?></td><?php endif; ?>
              <?php if ($r['show_grades']): ?><td class="l"><?= e($row['remark'] ?? '') ?></td><?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="rc-summary">
      <div><span>Subjects scored</span><strong><?= e($sum['subjects']) ?></strong></div>
      <div><span>Grand total</span><strong><?= e(num($sum['total'])) ?> / <?= e(num($sum['out_of'])) ?></strong></div>
      <div><span>Average</span><strong><?= e(num($sum['average'])) ?>%</strong></div>
      <?php if ($r['show_grades'] && $sum['grade'] !== null): ?><div><span>Overall grade</span><strong><?= e($sum['grade']) ?></strong></div><?php endif; ?>
      <?php if ($r['show_positions'] && $sum['position'] !== null): ?>
        <div><span>Position in class</span><strong><?= e(ResultEngine::ordinal($sum['position'])) ?> of <?= e($sum['class_size']) ?></strong></div>
      <?php endif; ?>
    </div>

    <div class="rc-comments">
      <p><span class="rc-label">Class teacher's comment:</span> <?= e($r['teacher_comment'] ?: '………………………………………………………………') ?></p>
      <p><span class="rc-label">Principal's comment:</span> <?= e($r['principal_comment'] ?: '………………………………………………………………') ?></p>
      <?php if (!empty($t['next_term_begins'])): ?><p><span class="rc-label">Next term begins:</span> <?= e(date('j F Y', strtotime($t['next_term_begins']))) ?></p><?php endif; ?>
    </div>

    <?php if ($r['show_grades'] && $r['grade_key']): ?>
      <p class="rc-key"><span class="rc-label">Grade key:</span>
        <?php foreach ($r['grade_key'] as $g): ?>
          <span class="rc-key-item"><?= e($g['grade']) ?> = <?= e(num($g['min_score'])) ?>–<?= e(num($g['max_score'])) ?><?= $g['remark'] ? ' (' . e($g['remark']) . ')' : '' ?></span>
        <?php endforeach; ?>
      </p>
    <?php endif; ?>

    <div class="rc-sign">
      <div><span class="rc-line"></span><p>Class teacher</p></div>
      <div><span class="rc-line"></span><p>Principal<?= !empty($r['cfg']['principal_name']) ? ': ' . e($r['cfg']['principal_name']) : '' ?></p></div>
    </div>

    <?php if (!empty($r['verification']['barcode_svg'])): ?>
      <div class="rc-verification">
        <div><strong>Result authenticity</strong><small>Scan to verify this published report</small></div>
        <div class="rc-barcode"><?= $r['verification']['barcode_svg'] ?><small><?= e($r['verification']['token']) ?></small></div>
      </div>
    <?php endif; ?>
    <footer class="rc-foot">
      <?= e($r['cfg']['report_footer'] ?: 'This report is computer-generated.') ?>
      <span class="rc-powered">GRADELY · Powered by Techpreneur</span>
    </footer>
  </div>
</section>
