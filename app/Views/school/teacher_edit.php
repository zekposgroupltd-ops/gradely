<?php use App\Core\Permissions; /** @var array $t */ /** @var array $perms */ /** @var array $pairs */ /** @var array $assigned */
$byClass = [];
foreach ($pairs as $p) { $byClass[$p['class_name']][] = $p; }
?>
<form class="stack" method="post" action="<?= e(url('/school/teachers/' . $t['id'])) ?>" autocomplete="off">
  <?= csrf_field() ?>
  <section class="card">
    <div class="card-head"><h2>Account</h2><?= status_badge($t['status']) ?></div>
    <div class="form-row">
      <?= field_html('Full name', 'name', $t['name'], 'text', ['required' => true, 'maxlength' => '150']) ?>
      <label class="field"><span class="field-label">Email</span><input type="email" value="<?= e($t['email']) ?>" disabled></label>
      <?= select_html('Status', 'status', ['active' => 'Active', 'disabled' => 'Disabled (cannot sign in)'], $t['status']) ?>
      <?= field_html('Set a new password', 'new_password', '', 'password', ['autocomplete' => 'new-password', 'minlength' => '10'], '(optional)') ?>
    </div>
  </section>

  <section class="card">
    <div class="card-head"><h2>Access</h2></div>
    <p class="muted">This account is restricted to uploading subject scores. The school administrator manages class and subject assignments.</p>
  </section>

  <section class="card">
    <div class="card-head"><h2>Classes and subjects this teacher may score</h2></div>
    <?php if (!$byClass): ?>
      <p class="empty">Assign subjects to classes first (Setup → Subjects).</p>
    <?php else: foreach ($byClass as $class => $list): ?>
      <fieldset class="fieldset mb"><legend><?= e($class) ?></legend>
        <div class="check-grid">
          <?php foreach ($list as $p): $k = $p['class_id'] . ':' . $p['subject_id']; ?>
            <label class="check"><input type="checkbox" name="assign[<?= e($k) ?>]" value="1" <?= isset($assigned[$k]) ? 'checked' : '' ?>><span><?= e($p['subject_name']) ?></span></label>
          <?php endforeach; ?>
        </div>
      </fieldset>
    <?php endforeach; endif; ?>
  </section>
  <div class="toolbar-left"><button class="btn btn-primary" type="submit">Save changes</button><a class="btn btn-ghost" href="<?= e(url('/school/teachers')) ?>">Back</a></div>
</form>
