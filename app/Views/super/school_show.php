<?php /** @var array $s */ /** @var array $admins */ /** @var array $counts */ /** @var array $activity */ ?>
<div class="toolbar"><a class="btn btn-ghost btn-sm" href="<?= e(url('/super/schools')) ?>">← All schools</a>
  <div class="toolbar-right"><?= status_badge($s['status']) ?><code>/s/<?= e($s['slug']) ?></code></div></div>

<section class="stat-row mb">
  <div class="stat-mini"><b><?= e($counts['students']) ?></b><span>students</span></div>
  <div class="stat-mini"><b><?= e($counts['classes']) ?></b><span>classes</span></div>
  <div class="stat-mini"><b><?= e($counts['subjects']) ?></b><span>subjects</span></div>
  <div class="stat-mini"><b><?= e($counts['published']) ?></b><span>published results</span></div>
  <div class="stat-mini"><b><?= e($counts['cards']) ?></b><span>cards (<?= e($counts['cards_used']) ?> used)</span></div>
</section>

<div class="grid grid-2">
  <section class="card">
    <div class="card-head"><h2>Details & licence</h2></div>
    <form class="form compact" method="post" action="<?= e(url('/super/schools/' . $s['id'] . '/update')) ?>">
      <?= csrf_field() ?>
      <?= field_html('School name', 'name', $s['name'], 'text', ['required' => true, 'maxlength' => '190']) ?>
      <div class="form-row">
        <?= field_html('Email', 'email', (string) $s['email'], 'email') ?>
        <?= field_html('Phone', 'phone', (string) $s['phone'], 'tel') ?>
      </div>
      <?= field_html('Address', 'address', (string) $s['address']) ?>
      <div class="form-row">
        <?= field_html('Plan', 'plan', $s['plan'], 'text', ['maxlength' => '40']) ?>
        <?= field_html('Licence expires on', 'license_expires_on', (string) $s['license_expires_on'], 'date', [], '(informational)') ?>
      </div>
      <button class="btn btn-primary" type="submit">Save</button>
    </form>
  </section>

  <section class="card">
    <div class="card-head"><h2>Access</h2></div>
    <p class="muted mb">Suspended and inactive schools cannot sign in, and their published results stop appearing on the portal.</p>
    <div class="toolbar-left">
      <?php foreach (['active' => ['Activate', 'btn-primary'], 'suspended' => ['Suspend', 'btn-ghost'], 'inactive' => ['Deactivate', 'btn-danger']] as $st => [$label, $cls]): if ($s['status'] === $st) { continue; } ?>
        <form class="inline-form" method="post" action="<?= e(url('/super/schools/' . $s['id'] . '/status')) ?>" data-confirm="<?= e($label) ?> <?= e($s['name']) ?>?">
          <?= csrf_field() ?><input type="hidden" name="status" value="<?= e($st) ?>"><button class="btn <?= $cls ?> btn-sm" type="submit"><?= e($label) ?></button>
        </form>
      <?php endforeach; ?>
    </div>
    <h3 class="mt">Recent activity</h3>
    <?php if (!$activity): ?><p class="muted">Nothing yet.</p><?php else: ?>
      <ul class="plain-list"><?php foreach ($activity as $a): ?><li><code><?= e($a['action']) ?></code> <span class="muted small"><?= e($a['user_name'] ?? 'system') ?> · <?= e($a['created_at']) ?></span></li><?php endforeach; ?></ul>
    <?php endif; ?>
  </section>
</div>

<section class="card mt">
  <div class="card-head"><h2>Accounts</h2></div>
  <div class="table-wrap"><table class="table">
    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($admins as $a): ?>
      <tr>
        <td><?= e($a['name']) ?></td><td><?= e($a['email']) ?></td><td><?= e(role_label($a['role'])) ?></td><td><?= status_badge($a['status']) ?></td>
        <td class="muted"><?= e($a['last_login_at'] ?? 'Never') ?></td>
        <td class="table-actions">
          <form class="inline-form" method="post" action="<?= e(url('/super/schools/' . $s['id'] . '/users/' . $a['id'] . '/reset')) ?>" data-confirm="Reset the password for <?= e($a['email']) ?>? A new temporary password will be shown once.">
            <?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Reset password</button></form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>
