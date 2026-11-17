<?php /** @var array $terms */ /** @var array $batches */ /** @var array $stats */ /** @var array $requests */ /** @var array $approvedRequests */ /** @var array $classes */ /** @var float $pinPrice */ ?>
<section class="grid grid-4">
  <article class="card stat"><p class="stat-label"><?= icon('card', 18) ?> Total cards</p><p class="stat-value"><?= e($stats['total']) ?></p></article>
  <article class="card stat"><p class="stat-label">Unused</p><p class="stat-value"><?= e($stats['unused']) ?></p></article>
  <article class="card stat"><p class="stat-label">In use</p><p class="stat-value"><?= e($stats['in_use']) ?></p><p class="stat-sub">Successful checks already made</p></article>
  <article class="card stat"><p class="stat-label">Exhausted</p><p class="stat-value"><?= e($stats['exhausted']) ?></p><p class="stat-sub"><?= e($stats['revoked']) ?> revoked · <?= e($stats['expired']) ?> expired</p></article>
</section>

<section class="card mb">
  <div class="card-head"><h2>Request result-checking PINs</h2><strong>₦<?= e(number_format($pinPrice, 2)) ?> / student</strong></div>
  <p class="muted mb">Choose a class or the entire school. GRADELY calculates the quantity from active enrolment. The platform administrator reviews payment and approves the request. You can generate PINs only after approval. If term results already exist, GRADELY uses that term’s saved class roster; otherwise it estimates from the current active student list.</p>
  <?php if (!$terms): ?><p class="empty">Add an academic session and term first.</p>
  <?php elseif ($pinPrice <= 0): ?><p class="empty">The platform administrator has not configured the PIN price yet.</p>
  <?php else: ?>
  <form class="filters" method="post" action="<?= e(url('/school/cards/request')) ?>">
    <?= csrf_field() ?>
    <?= select_html('Session and term', 'term_id', term_options($terms), '', ['required' => true]) ?>
    <?php $classOpts = [0 => 'Entire school · ' . $schoolStudentCount . ' students · ₦' . number_format($pinPrice * $schoolStudentCount, 2)]; foreach ($classes as $c) { $classOpts[$c['id']] = $c['name'] . ' · ' . $c['student_count'] . ' students · ₦' . number_format($pinPrice * (int)$c['student_count'], 2); } ?>
    <?= select_html('Coverage', 'class_id', $classOpts, '0', ['required' => true]) ?>
    <button class="btn btn-primary" type="submit">Submit PIN request</button>
  </form>
  <?php endif; ?>
</section>

<?php if ($approvedRequests): ?><section class="card mb">
  <div class="card-head"><h2>Approved requests — generate PINs</h2></div>
  <p class="muted">Generate exactly the approved quantity. PINs are displayed once; download or print them immediately and keep them secure.</p>
  <?php foreach ($approvedRequests as $r): ?>
    <form class="filters" method="post" action="<?= e(url('/school/cards/generate')) ?>">
      <?= csrf_field() ?><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
      <div class="field"><span class="field-label">Request #<?= e($r['id']) ?></span><strong><?= e($r['quantity']) ?> PINs · <?= e($r['class_name'] ?: 'Entire school') ?></strong><small class="hint"><?= e($r['session_name'] . ' · ' . $r['term_name']) ?></small></div>
      <?= field_html('Valid until', 'expires_on', '', 'date', [], '(optional)') ?>
      <button class="btn btn-primary" type="submit">Generate approved PINs</button>
    </form>
  <?php endforeach; ?>
</section><?php endif; ?>

<section class="card mb">
  <div class="card-head"><h2>PIN purchase requests</h2></div>
  <?php if (!$requests): ?><p class="empty">You have not requested PINs yet.</p><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>Request</th><th>Term</th><th>Coverage</th><th class="num">Quantity</th><th class="num">Amount</th><th>Status</th><th>Submitted</th></tr></thead><tbody>
  <?php foreach ($requests as $r): ?><tr><td>#<?= e($r['id']) ?></td><td><?= e($r['session_name'] . ' · ' . $r['term_name']) ?></td><td><?= e($r['class_name'] ?: 'Entire school') ?></td><td class="num"><?= e($r['quantity']) ?></td><td class="num">₦<?= e(number_format((float)$r['total_amount'],2)) ?></td><td><?= status_badge($r['status']) ?></td><td class="muted"><?= e($r['created_at']) ?></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>

<section class="card">
  <div class="card-head"><h2>Generated PIN batches</h2><a class="btn btn-ghost btn-sm" href="<?= e(url('/school/cards/export.csv')) ?>"><?= icon('download', 16) ?> Export inventory</a></div>
  <?php if (!$batches): ?><p class="empty">No PIN batches generated yet.</p><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>Batch</th><th>Term</th><th>Coverage</th><th class="num">Cards</th><th class="num">Unused</th><th class="num">Used</th><th class="num">Revoked</th><th>Created</th><th></th></tr></thead><tbody>
  <?php foreach ($batches as $b): ?><tr><td><strong>#<?= e($b['id']) ?></strong> <?= e($b['label'] ?? '') ?></td><td><?= e($b['session_name'] . ' · ' . $b['term_name']) ?></td><td><?= e($b['class_name'] ?: 'Entire school') ?></td><td class="num"><?= e($b['quantity']) ?></td><td class="num"><?= e($b['unused']) ?></td><td class="num"><?= e($b['touched']) ?></td><td class="num"><?= e($b['revoked']) ?></td><td class="nowrap muted"><?= e($b['created_at']) ?></td><td class="table-actions"><a class="btn btn-ghost btn-sm" href="<?= e(url('/school/cards/batches/' . $b['id'])) ?>">Open</a></td></tr><?php endforeach; ?>
  </tbody></table></div><?php endif; ?>
</section>
