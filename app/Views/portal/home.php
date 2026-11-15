<?php /** @var ?array $school */ /** @var array $schools */ /** @var array $terms */ /** @var bool $notFound */ ?>
<section class="portal-card">
  <h1 class="portal-title">Check your result</h1>

<?php if ($school === null): ?>
  <p class="muted">Choose your school to continue. You will need your student ID and the PIN on your scratch card.</p>
  <?php if ($notFound): ?><div class="alert alert-error" role="alert">That school was not found or is not active.</div><?php endif; ?>
  <?php if (!$schools): ?>
    <p class="empty">No schools are available yet.</p>
  <?php else: ?>
    <form class="form" method="get" action="<?= e(url('/')) ?>">
      <?= select_html('School', 'school', array_column($schools, 'name', 'slug'), '', ['required' => true], 'Select your school…') ?>
      <button class="btn btn-primary btn-block" type="submit">Continue</button>
    </form>
  <?php endif; ?>

<?php else: ?>
  <div class="portal-school">
    <?php if (!empty($school['logo_path'])): ?>
      <img src="<?= e(url('media/logo/' . $school['id'])) ?>" width="56" height="56" alt="">
    <?php endif; ?>
    <div>
      <p class="muted">Result portal for</p>
      <p class="portal-school-name"><?= e($school['name']) ?></p>
    </div>
  </div>
  <?php if (!$terms): ?>
    <p class="empty">This school has no terms set up yet.</p>
  <?php else: ?>
    <form class="form" method="post" action="<?= e(url('/portal/check')) ?>" autocomplete="off" novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="school" value="<?= e($school['slug']) ?>">
      <?php
        $opts = [];
        foreach ($terms as $t) { $opts[$t['id']] = $t['session_name'] . ' · ' . $t['name']; }
      ?>
      <?= select_html('Session and term', 'term_id', $opts, old('term_id'), ['required' => true], 'Select session and term…') ?>
      <?= field_html('Student ID', 'admission_no', old('admission_no'), 'text', ['required' => true, 'autocapitalize' => 'characters', 'autocomplete' => 'off', 'maxlength' => '40']) ?>
      <?= field_html('Scratch-card PIN', 'pin', '', 'password', ['required' => true, 'inputmode' => 'numeric', 'autocomplete' => 'off', 'maxlength' => '20', 'placeholder' => '12 digits'], '(from your scratch card)') ?>
      <button class="btn btn-primary btn-block" type="submit">View result</button>
    </form>
    <p class="muted small">Each card is valid for one term at one school and can be used for <?= (int) \App\Services\ScratchCardService::MAX_USES ?> successful result checks.</p>
  <?php endif; ?>
  <p class="small"><a href="<?= e(url('/')) ?>">Not your school?</a></p>
<?php endif; ?>
</section>
