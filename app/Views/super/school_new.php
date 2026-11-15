<section class="card form-card">
  <form class="form compact" method="post" action="<?= e(url('/super/schools')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <h2>School</h2>
    <div class="form-row">
      <?= field_html('School name', 'name', old('name'), 'text', ['required' => true, 'maxlength' => '190']) ?>
      <?= field_html('School email', 'email', old('email'), 'email', ['maxlength' => '190']) ?>
      <?= field_html('Phone', 'phone', old('phone'), 'tel', ['maxlength' => '40']) ?>
      <?= field_html('Address', 'address', old('address'), 'text', ['maxlength' => '255']) ?>
    </div>
    <h2>School administrator account</h2>
    <div class="form-row">
      <?= field_html('Administrator name', 'admin_name', old('admin_name'), 'text', ['required' => true, 'maxlength' => '150']) ?>
      <?= field_html('Administrator email (sign-in)', 'admin_email', old('admin_email'), 'email', ['required' => true, 'maxlength' => '190']) ?>
      <?= field_html('Password', 'admin_password', '', 'password', ['autocomplete' => 'new-password', 'minlength' => '10'], '(leave blank to generate one)') ?>
    </div>
    <p class="muted small">The school starts with a default grading scale (A–F) and three assessment components (1st Test 20, 2nd Test 20, Exam 60). The school administrator can change both.</p>
    <div class="toolbar-left"><button class="btn btn-primary" type="submit">Register school</button><a class="btn btn-ghost" href="<?= e(url('/super/schools')) ?>">Cancel</a></div>
  </form>
</section>
