<h1 class="auth-title">Welcome back</h1>
<p class="muted">Sign in to your GRADELY account.</p>

<form class="form" method="post" action="<?= e(url('/login')) ?>" novalidate>
  <?= csrf_field() ?>
  <label class="field">
    <span class="field-label">Email address</span>
    <input type="email" name="email" value="<?= e(old('email')) ?>" autocomplete="username" required autofocus>
  </label>
  <label class="field">
    <span class="field-label">Password</span>
    <input type="password" name="password" autocomplete="current-password" required>
  </label>
  <button class="btn btn-primary btn-block" type="submit">Sign in</button>
</form>
<p class="muted small mt">Student or parent? <a href="<?= e(url('/')) ?>">Check your result</a></p>
