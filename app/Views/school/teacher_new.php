<?php use App\Core\Permissions; ?>
<section class="card form-card">
  <form class="form compact" method="post" action="<?= e(url('/school/teachers')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-row">
      <?= field_html('Full name', 'name', old('name'), 'text', ['required' => true, 'maxlength' => '150']) ?>
      <?= field_html('Email (used to sign in)', 'email', old('email'), 'email', ['required' => true, 'maxlength' => '190']) ?>
    </div>
    <?= field_html('Password', 'password', '', 'password', ['autocomplete' => 'new-password', 'minlength' => '10'], '(leave blank to generate one)') ?>
    <fieldset class="fieldset">
      <legend>Permissions</legend>
      <div class="check-grid">
        <?php foreach (Permissions::LIST as $key => $label): ?>
          <label class="check"><input type="checkbox" name="perm[<?= e($key) ?>]" value="1" <?= $key === 'scores.enter' ? 'checked' : '' ?>><span><?= e($label) ?></span></label>
        <?php endforeach; ?>
      </div>
    </fieldset>
    <p class="muted small">After saving you can assign the classes and subjects this teacher may enter scores for.</p>
    <div class="toolbar-left"><button class="btn btn-primary" type="submit">Create teacher</button><a class="btn btn-ghost" href="<?= e(url('/school/teachers')) ?>">Cancel</a></div>
  </form>
</section>
