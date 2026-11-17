<?php /** @var ?array $student */ /** @var array $classes */ /** @var array $arms */
$v = static fn(string $k) => old($k) !== '' ? old($k) : (string) ($student[$k] ?? '');
$action = $student ? '/school/students/' . $student['id'] : '/school/students';
$classId = $v('class_id');
?>
<section class="card form-card">
  <form class="form compact" method="post" action="<?= e(url($action)) ?>">
    <?= csrf_field() ?>
    <div class="form-row">
      <?= field_html('Student ID', 'admission_no', $v('admission_no'), 'text', ['maxlength' => '40', 'placeholder' => $student ? 'Existing student ID' : 'Leave blank to generate automatically'], $student ? '(you can edit this ID)' : '(optional — auto-generated from your school prefix)') ?>
      <?= select_html('Gender', 'gender', ['M' => 'Male', 'F' => 'Female'], $v('gender'), [], 'Not specified') ?>
    </div>
    <div class="form-row">
      <?= field_html('First name', 'first_name', $v('first_name'), 'text', ['required' => true, 'maxlength' => '80']) ?>
      <?= field_html('Last name', 'last_name', $v('last_name'), 'text', ['required' => true, 'maxlength' => '80']) ?>
      <?= field_html('Other name', 'other_name', $v('other_name'), 'text', ['maxlength' => '80']) ?>
    </div>
    <div class="form-row">
      <?= select_html('Class', 'class_id', $classes, $classId, ['required' => true, 'id' => 'class_id'], 'Select class…') ?>
      <label class="field"><span class="field-label">Arm</span>
        <select name="arm_id" data-arms-for="#class_id">
          <option value="">None</option>
          <?php foreach ($arms as $cid => $list): foreach ($list as $a): ?>
            <option value="<?= (int) $a['id'] ?>" data-class="<?= (int) $cid ?>"<?= $v('arm_id') === (string) $a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?></option>
          <?php endforeach; endforeach; ?>
        </select>
      </label>
      <?= field_html('Date of birth', 'date_of_birth', $v('date_of_birth'), 'date') ?>
    </div>
    <?php if ($student): ?>
      <?= select_html('Status', 'status', ['active' => 'Active', 'graduated' => 'Graduated', 'withdrawn' => 'Withdrawn'], $v('status')) ?>
    <?php endif; ?>
    <div class="toolbar-left">
      <button class="btn btn-primary" type="submit">Save student</button>
      <a class="btn btn-ghost" href="<?= e(url('/school/students')) ?>">Cancel</a>
    </div>
  </form>
  <?php if ($student): ?>
    <form class="mt" method="post" action="<?= e(url('/school/students/' . $student['id'] . '/delete')) ?>" data-confirm="Delete this student permanently?">
      <?= csrf_field() ?><button class="btn btn-danger btn-sm" type="submit">Delete student</button>
    </form>
  <?php endif; ?>
</section>
