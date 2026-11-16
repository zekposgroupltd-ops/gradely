<?php /** @var array $rows */ ?>
<div class="toolbar"><span class="muted">Your latest notifications</span>
  <form class="inline-form" method="post" action="<?= e(url('/notifications/read')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm" type="submit">Mark all as read</button></form></div>
<section class="card">
  <?php if (!$rows): ?><p class="empty">You have no notifications.</p><?php else: foreach ($rows as $n): ?>
    <div class="notif<?= $n['read_at'] === null ? ' unread' : '' ?>">
      <strong><?= e($n['title']) ?></strong>
      <?php if ($n['body']): ?><span><?= e($n['body']) ?></span><?php endif; ?>
      <span class="muted small"><?= e($n['created_at']) ?></span>
    </div>
  <?php endforeach; endif; ?>
</section>
