<?php /** @var array $s */ ?>
<div class="grid grid-2">
  <section class="card">
    <div class="card-head"><h2>School information</h2></div>
    <form class="form compact" method="post" action="<?= e(url('/school/profile')) ?>">
      <?= csrf_field() ?>
      <?= field_html('School name', 'name', $s['name'], 'text', ['required' => true, 'maxlength' => '190']) ?>
      <?= field_html('Email', 'email', (string) $s['email'], 'email', ['maxlength' => '190']) ?>
      <?= field_html('Phone', 'phone', (string) $s['phone'], 'tel', ['maxlength' => '40']) ?>
      <?= field_html('Address', 'address', (string) $s['address'], 'text', ['maxlength' => '255']) ?>
      <?= field_html('Website', 'website', (string) $s['website'], 'text', ['maxlength' => '190', 'placeholder' => 'https://']) ?>
      <button class="btn btn-primary" type="submit">Save changes</button>
    </form>
  </section>
  <section class="card">
    <div class="card-head"><h2>School logo</h2></div>
    <p class="muted mb">Shown on report cards and the result portal. PNG or JPG, up to 2 MB.</p>
    <?php if (!empty($s['logo_path'])): ?>
      <img class="logo-preview" src="<?= e(url('media/logo/' . $s['id']) . '?v=' . time()) ?>" alt="Current school logo" width="120" height="120">
    <?php else: ?>
      <p class="empty">No logo uploaded yet.</p>
    <?php endif; ?>
    <form class="form" method="post" action="<?= e(url('/school/profile/logo')) ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label class="field"><span class="field-label">Choose image</span><input type="file" name="logo" accept="image/png,image/jpeg" required></label>
      <button class="btn btn-ghost" type="submit"><?= icon('upload', 18) ?> Upload logo</button>
    </form>
  </section>
</div>
