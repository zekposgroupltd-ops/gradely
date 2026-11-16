<?php /** @var array $subjects */ /** @var array $classes */ /** @var array $assigned */ ?>
<section class="card mb">
  <div class="card-head"><h2>Add a subject</h2></div>
  <form class="filters" method="post" action="<?= e(url('/school/subjects')) ?>">
    <?= csrf_field() ?>
    <?= field_html('Subject name', 'name', '', 'text', ['required' => true, 'maxlength' => '100', 'placeholder' => 'Mathematics']) ?>
    <?= field_html('Short code', 'code', '', 'text', ['maxlength' => '20', 'placeholder' => 'MTH'], '(optional)') ?>
    <button class="btn btn-primary" type="submit"><?= icon('plus', 18) ?> Add subject</button>
  </form>
</section>

<section class="card">
  <div class="card-head"><h2>Assign subjects to classes</h2></div>
  <?php if (!$subjects || !$classes): ?>
    <p class="empty">Add at least one subject and one class to assign subjects.</p>
  <?php else: ?>
    <form method="post" action="<?= e(url('/school/subjects/assign')) ?>">
      <?= csrf_field() ?>
      <div class="table-wrap"><table class="table table-tight">
        <thead><tr><th class="sticky-col">Subject</th><?php foreach ($classes as $c): ?><th class="c"><?= e($c['name']) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach ($subjects as $s): ?>
          <tr>
            <td class="sticky-col"><strong><?= e($s['name']) ?></strong><?= $s['code'] ? ' <span class="muted small">' . e($s['code']) . '</span>' : '' ?></td>
            <?php foreach ($classes as $c): ?>
              <td class="c"><input class="tick-box" type="checkbox" name="assign[<?= (int) $s['id'] ?>][<?= (int) $c['id'] ?>]" value="1" <?= isset($assigned[$s['id'] . ':' . $c['id']]) ? 'checked' : '' ?> aria-label="<?= e($s['name'] . ' for ' . $c['name']) ?>"></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <p class="mt"><button class="btn btn-primary" type="submit">Save assignments</button></p>
    </form>
  <?php endif; ?>
</section>

<?php if ($subjects): ?>
<section class="card mt">
  <div class="card-head"><h2>All subjects</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Subject</th><th>Code</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($subjects as $s): ?>
      <tr>
        <td><?= e($s['name']) ?></td><td><?= e($s['code'] ?? '—') ?></td>
        <td class="table-actions">
          <form class="inline-form" method="post" action="<?= e(url('/school/subjects/' . $s['id'] . '/delete')) ?>" data-confirm="Delete <?= e($s['name']) ?>?">
            <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
<?php endif; ?>
