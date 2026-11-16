<?php /** @var array $rows */ /** @var string $q */ /** @var int $classId */ /** @var array $classes */ /** @var int $page */ /** @var int $pages */ /** @var int $total */ ?>
<div class="toolbar">
  <form class="filters" method="get" action="<?= e(url('/school/students')) ?>" data-autosubmit>
    <label class="field"><span class="field-label">Search</span><input type="search" name="q" value="<?= e($q) ?>" placeholder="Name or student ID"></label>
    <?= select_html('Class', 'class_id', $classes, (string) $classId, [], 'All classes') ?>
    <button class="btn btn-ghost btn-sm" type="submit">Search</button>
  </form>
  <div class="toolbar-right">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/school/students/export.csv')) ?>"><?= icon('download', 16) ?> Export</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('/school/students/import')) ?>"><?= icon('upload', 16) ?> Import</a>
    <a class="btn btn-primary btn-sm" href="<?= e(url('/school/students/new')) ?>"><?= icon('plus', 16) ?> Add student</a>
  </div>
</div>

<section class="card">
  <?php if (!$rows): ?>
    <p class="empty"><?= $q !== '' || $classId ? 'No students match your search.' : 'No students yet. Add one, or import a spreadsheet.' ?></p>
  <?php else: ?>
    <div class="table-wrap"><table class="table">
      <thead><tr><th>Student ID</th><th>Name</th><th>Class</th><th>Gender</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $s): ?>
        <tr>
          <td class="nowrap"><?= e($s['admission_no']) ?></td>
          <td><strong><?= e($s['last_name']) ?></strong> <?= e($s['first_name'] . ($s['other_name'] ? ' ' . $s['other_name'] : '')) ?></td>
          <td><?= e($s['class_name'] . ($s['arm_name'] ? ' ' . $s['arm_name'] : '')) ?></td>
          <td><?= e($s['gender'] ?? '—') ?></td>
          <td><?= status_badge($s['status']) ?></td>
          <td class="table-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/school/students/' . $s['id'] . '/edit')) ?>">Edit</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="muted small mt"><?= e($total) ?> student<?= $total === 1 ? '' : 's' ?></p>
    <?= pager_html($page, $pages, ['q' => $q, 'class_id' => $classId]) ?>
  <?php endif; ?>
</section>
