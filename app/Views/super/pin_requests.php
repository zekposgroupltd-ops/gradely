<?php /** @var array $rows */ /** @var float $price */ ?>
<div class="tabs" role="navigation" aria-label="Platform cards"><a class="tab" href="<?= e(url('/super/cards')) ?>">Card monitor</a><a class="tab is-active" href="<?= e(url('/super/pin-requests')) ?>">PIN purchase requests</a><a class="tab" href="<?= e(url('/super/settings')) ?>">Pricing settings</a></div>
<section class="card mb">
  <div class="card-head"><h2>PIN price</h2><strong>₦<?= e(number_format($price, 2)) ?> per student</strong></div>
  <p class="muted">Set the amount in Platform → Settings. Verify payment using your normal payment channel before approving a request. Approval releases the approved quantity for the school to generate.</p>
</section>
<section class="card">
  <div class="card-head"><h2>School requests</h2><span class="muted"><?= count($rows) ?> recent requests</span></div>
  <?php if (!$rows): ?><p class="empty">No PIN purchase requests yet.</p><?php else: ?>
  <div class="table-wrap"><table class="table"><thead><tr><th>School / Request</th><th>Term</th><th>Coverage</th><th class="num">Students</th><th class="num">Unit price</th><th class="num">Total</th><th>Status</th><th>Action</th></tr></thead><tbody>
  <?php foreach ($rows as $r): ?><tr>
    <td><strong><?= e($r['school_name']) ?></strong><br><small class="muted">#<?= e($r['id']) ?> · <?= e($r['requester_name'] ?? 'School admin') ?></small></td>
    <td><?= e($r['session_name'] . ' · ' . $r['term_name']) ?></td><td><?= e($r['class_name'] ?: 'Entire school') ?></td>
    <td class="num"><?= e($r['quantity']) ?></td><td class="num">₦<?= e(number_format((float)$r['unit_price'],2)) ?></td><td class="num"><strong>₦<?= e(number_format((float)$r['total_amount'],2)) ?></strong></td>
    <td><?= status_badge($r['status']) ?><?php if ($r['review_note']): ?><br><small class="muted"><?= e($r['review_note']) ?></small><?php endif; ?></td>
    <td><?php if ($r['status'] === 'pending'): ?><div class="stack" style="min-width:180px">
      <form method="post" action="<?= e(url('/super/pin-requests/' . $r['id'] . '/approve')) ?>" data-confirm="Confirm payment and approve this PIN request?"><?= csrf_field() ?><input type="text" name="review_note" maxlength="255" placeholder="Payment reference (optional)" class="cell"><button class="btn btn-primary btn-sm" type="submit">Approve</button></form>
      <form method="post" action="<?= e(url('/super/pin-requests/' . $r['id'] . '/reject')) ?>" data-confirm="Reject this PIN request?"><?= csrf_field() ?><input type="text" name="review_note" maxlength="255" placeholder="Reason (optional)" class="cell"><button class="btn btn-danger btn-sm" type="submit">Reject</button></form>
    </div><?php else: ?><span class="muted">—</span><?php endif; ?></td>
  </tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
