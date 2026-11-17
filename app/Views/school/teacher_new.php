<section class="card form-card">
  <p class="muted mb"><?= $teacherLimit === null ? 'Your school currently has no active-teacher limit.' : 'Your school can have up to ' . (int)$teacherLimit . ' active teacher accounts.' ?> Teacher accounts can only upload subject scores for the classes and subjects you assign.</p>
  <form class="form compact" method="post" action="<?= e(url('/school/teachers')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="form-row">
      <?= field_html('Full name', 'name', old('name'), 'text', ['required' => true, 'maxlength' => '150']) ?>
      <?= field_html('Email (used to sign in)', 'email', old('email'), 'email', ['required' => true, 'maxlength' => '190']) ?>
    </div>
    <?= field_html('Password', 'password', '', 'password', ['autocomplete' => 'new-password', 'minlength' => '10'], '(leave blank to generate one)') ?>
    <div class="alert alert-info">Permission: Upload subject results only. The school administrator controls each teacher’s class and subject assignments.</div>
    <div class="toolbar-left"><button class="btn btn-primary" type="submit">Create teacher</button><a class="btn btn-ghost" href="<?= e(url('/school/teachers')) ?>">Cancel</a></div>
  </form>
</section>
