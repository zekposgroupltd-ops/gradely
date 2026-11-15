<?php /** @var string $state */ ?>
<h1 class="auth-title">First-time setup</h1>

<?php if ($state === 'no_db'): ?>
  <div class="alert alert-error" role="alert">The database tables were not found. Import <code>database/schema.sql</code> (phpMyAdmin → Import) and check the DB settings in <code>.env</code>, then reload this page.</div>
<?php elseif ($state === 'no_token'): ?>
  <div class="alert alert-info">Add a long random <code>SETUP_TOKEN=</code> value to your <code>.env</code> file, then reload this page. This keeps strangers from creating the platform owner account.</div>
<?php else: ?>
  <p class="muted">Create the Super Administrator account. This page disables itself as soon as one exists.</p>
  <form class="form" method="post" action="<?= e(url('/setup')) ?>" novalidate>
    <?= csrf_field() ?>
    <label class="field">
      <span class="field-label">Setup token (from .env)</span>
      <input type="password" name="setup_token" autocomplete="off" required>
    </label>
    <label class="field">
      <span class="field-label">Full name</span>
      <input type="text" name="name" value="<?= e(old('name')) ?>" autocomplete="name" required>
    </label>
    <label class="field">
      <span class="field-label">Email address</span>
      <input type="email" name="email" value="<?= e(old('email')) ?>" autocomplete="username" required>
    </label>
    <label class="field">
      <span class="field-label">Password <span class="hint">(at least 10 characters)</span></span>
      <input type="password" name="password" autocomplete="new-password" minlength="10" required>
    </label>
    <label class="field">
      <span class="field-label">Confirm password</span>
      <input type="password" name="password_confirm" autocomplete="new-password" minlength="10" required>
    </label>
    <button class="btn btn-primary btn-block" type="submit">Create Super Administrator</button>
  </form>
<?php endif; ?>
