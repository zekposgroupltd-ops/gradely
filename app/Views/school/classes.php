<?php /** @var array $classes */ /** @var array $arms */ ?>
<section class="card mb">
  <div class="card-head"><h2>Add a class</h2></div>
  <form class="filters" method="post" action="<?= e(url('/school/classes')) ?>">
    <?= csrf_field() ?>
    <?= field_html('Class name', 'name', '', 'text', ['required' => true, 'maxlength' => '60', 'placeholder' => 'JSS 1']) ?>
    <button class="btn btn-primary" type="submit"><?= icon('plus', 18) ?> Add class</button>
  </form>
</section>

<?php if (!$classes): ?>
  <section class="card"><p class="empty">No classes yet. Add your first class above.</p></section>
<?php endif; ?>
<div class="grid grid-2">
<?php foreach ($classes as $c): ?>
  <section class="card">
    <div class="card-head">
      <h2><?= e($c['name']) ?> <span class="muted small"><?= e($c['students']) ?> student<?= (int) $c['students'] === 1 ? '' : 's' ?></span></h2>
      <form class="inline-form" method="post" action="<?= e(url('/school/classes/' . $c['id'] . '/delete')) ?>" data-confirm="Delete <?= e($c['name']) ?>? Its arms and subject assignments will be removed.">
        <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Delete</button>
      </form>
    </div>
    <p class="muted small mb">Arms</p>
    <?php if (empty($arms[$c['id']])): ?>
      <p class="muted">No arms (optional).</p>
    <?php else: ?>
      <div class="chips">
        <?php foreach ($arms[$c['id']] as $a): ?>
          <form class="chip" method="post" action="<?= e(url('/school/arms/' . $a['id'] . '/delete')) ?>" data-confirm="Delete arm <?= e($a['name']) ?>?">
            <?= csrf_field() ?><span><?= e($a['name']) ?></span><button type="submit" aria-label="Delete arm <?= e($a['name']) ?>">×</button>
          </form>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <form class="filters mt" method="post" action="<?= e(url('/school/classes/' . $c['id'] . '/arms')) ?>">
      <?= csrf_field() ?>
      <?= field_html('New arm', 'name', '', 'text', ['required' => true, 'maxlength' => '40', 'placeholder' => 'A']) ?>
      <button class="btn btn-ghost btn-sm" type="submit">Add arm</button>
    </form>
  </section>
<?php endforeach; ?>
</div>
