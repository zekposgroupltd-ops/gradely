<?php /** @var array $terms */ /** @var array $classes */ /** @var int $termId */ /** @var int $classId */ /** @var ?array $stats */ /** @var array $compare */ ?>
<form class="filters" method="get" action="<?= e(url('/school/analytics')) ?>" data-autosubmit>
  <?= select_html('Term', 'term_id', term_options($terms), (string) $termId, [], 'Select term…') ?>
  <?= select_html('Class', 'class_id', array_column($classes, 'name', 'id'), (string) $classId, [], 'Select class…') ?>
</form>

<?php if ($compare): ?>
<section class="card mb">
  <div class="card-head"><h2>Class averages</h2></div>
  <?php foreach ($compare as $c): ?>
    <div class="bar-row"><span class="bar-label"><?= e($c['name']) ?> <span class="muted small">(<?= e($c['n']) ?>)</span></span><?= bar_html((float) $c['avg']) ?><span class="bar-val"><?= e(num($c['avg'], 1)) ?>%</span></div>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($stats === null): ?>
  <section class="card"><p class="empty">Choose a class to see subject performance.</p></section>
<?php elseif ($stats['ranked'] === 0): ?>
  <section class="card"><p class="empty">No computed results for this class and term yet.</p></section>
<?php else: ?>
  <section class="grid grid-4">
    <article class="card stat"><p class="stat-label">Class average</p><p class="stat-value"><?= e(num($stats['class_average'], 1)) ?>%</p></article>
    <article class="card stat"><p class="stat-label">Pass rate</p><p class="stat-value"><?= e(num($stats['pass_rate'], 1)) ?>%</p><p class="stat-sub"><?= e($stats['passed']) ?> of <?= e($stats['ranked']) ?> students</p></article>
    <article class="card stat"><p class="stat-label">Students ranked</p><p class="stat-value"><?= e($stats['ranked']) ?></p></article>
  </section>

  <section class="grid grid-2">
    <article class="card">
      <div class="card-head"><h2>Subject performance</h2></div>
      <?php foreach ($stats['subjects'] as $s): ?>
        <div class="bar-row"><span class="bar-label"><?= e($s['name']) ?></span><?= bar_html((float) $s['average']) ?><span class="bar-val"><?= e(num($s['average'], 1)) ?>%</span></div>
      <?php endforeach; ?>
    </article>
    <article class="card">
      <div class="card-head"><h2>Grade distribution</h2><span class="muted small">all subjects</span></div>
      <?php $maxN = max(1, max($stats['grades'] ?: [1])); foreach ($stats['grades'] as $g => $n): ?>
        <div class="bar-row"><span class="bar-label">Grade <?= e($g) ?></span><?= bar_html($n / $maxN * 100, $n . ' results') ?><span class="bar-val"><?= e($n) ?></span></div>
      <?php endforeach; ?>
    </article>
  </section>

  <section class="card mt">
    <div class="card-head"><h2>Subject detail</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Subject</th><th class="num">Students</th><th class="num">Average</th><th class="num">Highest</th><th class="num">Lowest</th><th class="num">Pass rate</th></tr></thead>
      <tbody><?php foreach ($stats['subjects'] as $s): ?>
        <tr><td><?= e($s['name']) ?></td><td class="num"><?= e($s['n']) ?></td><td class="num"><?= e(num($s['average'], 1)) ?>%</td><td class="num"><?= e(num($s['highest'])) ?></td><td class="num"><?= e(num($s['lowest'])) ?></td><td class="num"><?= e(num($s['pass_rate'], 1)) ?>%</td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </section>

  <section class="card mt">
    <div class="card-head"><h2>Top students</h2></div>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Position</th><th>Student</th><th class="num">Average</th></tr></thead>
      <tbody><?php foreach ($stats['top'] as $t): ?>
        <tr><td><?= e($t['position']) ?></td><td><?= e($t['last_name'] . ' ' . $t['first_name']) ?></td><td class="num"><?= e(num($t['average'])) ?>%</td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </section>
<?php endif; ?>
