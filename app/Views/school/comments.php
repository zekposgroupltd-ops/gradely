<?php /** @var array $term */ /** @var array $class */ /** @var array $rows */
use App\Core\Auth;
$admin = Auth::role() === 'school_admin' || Auth::can('results.approve');
?>
<div class="toolbar"><span class="muted"><?= e($class['name']) ?> · <?= e($term['name']) ?></span>
  <a class="btn btn-ghost btn-sm" href="<?= e(url('/school/results?term_id=' . $term['id'] . '&class_id=' . $class['id'])) ?>">← Back to results</a></div>
<form method="post" action="<?= e(url('/school/results/comments')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="term_id" value="<?= (int) $term['id'] ?>"><input type="hidden" name="class_id" value="<?= (int) $class['id'] ?>">
  <section class="card">
    <?php if (!$rows): ?><p class="empty">Compute results first; comments attach to computed report cards.</p><?php else: ?>
    <div class="table-wrap"><table class="table table-tight">
      <thead><tr><th>Student</th><th>Class teacher's comment</th><th>Principal's comment</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): $id = (int) $r['student_id']; ?>
        <tr>
          <td class="nowrap"><strong><?= e($r['last_name']) ?></strong> <?= e($r['first_name']) ?></td>
          <td><input class="cell" name="teacher[<?= $id ?>]" maxlength="500" value="<?= e((string) $r['teacher_comment']) ?>" aria-label="Teacher comment for <?= e($r['first_name']) ?>"></td>
          <td><input class="cell" name="principal[<?= $id ?>]" maxlength="500" value="<?= e((string) $r['principal_comment']) ?>" <?= $admin ? '' : 'disabled' ?> aria-label="Principal comment for <?= e($r['first_name']) ?>"></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <p class="mt"><button class="btn btn-primary" type="submit">Save comments</button></p>
    <?php endif; ?>
  </section>
</form>
