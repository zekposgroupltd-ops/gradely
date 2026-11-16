<?php
/** @var array $schools */ /** @var int $staffCount */ /** @var array $cards */ /** @var array $recent */
$totalSchools = array_sum($schools);
?>
<section class="grid grid-4" aria-label="Key figures">
  <article class="card stat">
    <p class="stat-label"><?= icon('school', 18) ?> Registered schools</p>
    <p class="stat-value"><?= e($totalSchools) ?></p>
    <p class="stat-sub">
      <span class="dot dot-green"></span><?= e($schools['active']) ?> active
      <span class="dot dot-amber"></span><?= e($schools['suspended']) ?> suspended
      <span class="dot dot-gray"></span><?= e($schools['inactive']) ?> inactive
    </p>
  </article>
  <article class="card stat">
    <p class="stat-label"><?= icon('users', 18) ?> School staff accounts</p>
    <p class="stat-value"><?= e($staffCount) ?></p>
    <p class="stat-sub">Administrators and teachers</p>
  </article>
  <article class="card stat">
    <p class="stat-label"><?= icon('card', 18) ?> Scratch cards issued</p>
    <p class="stat-value"><?= e($cards['total']) ?></p>
    <p class="stat-sub"><?= e($cards['used']) ?> used · <?= e($cards['exhausted']) ?> exhausted</p>
  </article>
</section>

<section class="card" aria-labelledby="recent-h">
  <div class="card-head">
    <h2 id="recent-h">Recent activity</h2>
  </div>
  <?php if (!$recent): ?>
    <p class="empty">No activity recorded yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="table">
        <thead><tr><th>When</th><th>Action</th><th>User</th><th>School</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $row): ?>
          <tr>
            <td><?= e($row['created_at']) ?></td>
            <td><code><?= e($row['action']) ?></code></td>
            <td><?= e($row['user_name'] ?? '—') ?></td>
            <td><?= e($row['school_name'] ?? '—') ?></td>
            <td><?= e($row['ip'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
