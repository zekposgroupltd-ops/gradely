<?php /** @var array $sessions */ /** @var array $terms */ ?>
<div class="grid grid-2">
  <section class="card">
    <div class="card-head"><h2>Add a session</h2></div>
    <form class="form compact" method="post" action="<?= e(url('/school/sessions')) ?>">
      <?= csrf_field() ?>
      <?= field_html('Session name', 'name', '', 'text', ['required' => true, 'maxlength' => '40', 'placeholder' => '2026/2027']) ?>
      <button class="btn btn-primary" type="submit"><?= icon('plus', 18) ?> Add session</button>
    </form>
  </section>
  <section class="card">
    <div class="card-head"><h2>Add a term</h2></div>
    <?php if (!$sessions): ?>
      <p class="empty">Add a session first.</p>
    <?php else: ?>
      <form class="form compact" method="post" action="<?= e(url('/school/terms')) ?>">
        <?= csrf_field() ?>
        <?= select_html('Session', 'session_id', array_column($sessions, 'name', 'id'), (string) ($sessions[0]['id'] ?? ''), ['required' => true]) ?>
        <label class="field"><span class="field-label">Term name</span>
          <input type="text" name="name" list="term-names" required maxlength="40" placeholder="First Term">
          <datalist id="term-names"><option value="First Term"><option value="Second Term"><option value="Third Term"></datalist>
        </label>
        <?= field_html('Next term begins', 'next_term_begins', '', 'date', [], '(optional, shown on report cards)') ?>
        <button class="btn btn-primary" type="submit"><?= icon('plus', 18) ?> Add term</button>
      </form>
    <?php endif; ?>
  </section>
</div>

<?php foreach ($sessions as $s): ?>
  <section class="card mt">
    <div class="card-head">
      <h2><?= e($s['name']) ?> <?= (int) $s['is_current'] ? badge_html('Current', 'green') : '' ?></h2>
      <form class="inline-form" method="post" action="<?= e(url('/school/sessions/' . $s['id'] . '/delete')) ?>" data-confirm="Delete session <?= e($s['name']) ?>?">
        <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Delete session</button>
      </form>
    </div>
    <?php if (empty($terms[$s['id']])): ?>
      <p class="empty">No terms yet.</p>
    <?php else: ?>
      <div class="table-wrap"><table class="table">
        <thead><tr><th>Term</th><th>Next term begins</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($terms[$s['id']] as $t): ?>
          <tr>
            <td><strong><?= e($t['name']) ?></strong></td>
            <td>
              <form class="inline-form" method="post" action="<?= e(url('/school/terms/' . $t['id'] . '/update')) ?>">
                <?= csrf_field() ?>
                <input class="cell" type="date" name="next_term_begins" value="<?= e((string) $t['next_term_begins']) ?>" aria-label="Next term begins for <?= e($t['name']) ?>">
                <button class="btn btn-ghost btn-sm" type="submit">Save</button>
              </form>
            </td>
            <td><?= (int) $t['is_current'] ? badge_html('Current', 'green') : badge_html('—') ?></td>
            <td class="table-actions">
              <?php if (!(int) $t['is_current']): ?>
                <form class="inline-form" method="post" action="<?= e(url('/school/terms/' . $t['id'] . '/current')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Make current</button></form>
              <?php endif; ?>
              <form class="inline-form" method="post" action="<?= e(url('/school/terms/' . $t['id'] . '/delete')) ?>" data-confirm="Delete <?= e($t['name']) ?>?"><?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Delete</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
