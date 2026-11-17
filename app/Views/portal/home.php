<?php /** @var ?array $school */ /** @var array $schools */ /** @var array $terms */ /** @var bool $notFound */ ?>
<?php if ($school === null): ?>
<?php if ($notFound): ?><div class="alert alert-error" role="alert">That school portal was not found or is not active. You can still check a result using your student ID and PIN below.</div><?php endif; ?>
<section class="landing-hero">
  <div>
    <span class="landing-kicker">Academic Results. Simplified.</span>
    <h1>School results, organised and accessible.</h1>
    <p>GRADELY helps schools manage assessments, calculate academic performance, publish verified results and provide students with a secure way to check their report sheets.</p>
    <div class="landing-actions"><a class="btn btn-primary" href="#check-result">Check result <?= icon('check',16) ?></a><a class="btn btn-ghost" href="<?= e(url('/login')) ?>">School staff sign in</a></div>
    <div class="landing-trust"><span>Secure result checking</span><span>School-branded reports</span><span>Academic performance tools</span></div>
  </div>
  <div class="landing-preview" aria-label="Preview of the GRADELY school dashboard">
    <div class="preview-window">
      <div class="preview-bar"><span>GRADELY · DASHBOARD PREVIEW</span><span class="preview-dots"><i></i><i></i><i></i></span></div>
      <div class="preview-stat-grid"><div class="preview-stat"><b>428</b><span>STUDENTS</span></div><div class="preview-stat"><b>18</b><span>SUBJECTS</span></div><div class="preview-stat"><b>92%</b><span>RESULTS READY</span></div></div>
      <div class="preview-lines"><div class="preview-line"></div><div class="preview-line"></div><div class="preview-line"></div></div>
    </div>
    <div class="preview-result"><strong>TERM RESULT SHEET</strong><span></span><span></span><span style="width:65%"></span><small style="color:#658073;font-size:10px">Student performance summary</small></div>
  </div>
</section>

<section class="landing-section">
  <h2>One platform for the results process</h2>
  <p class="landing-lead">From setting up a school term to entering scores and issuing result sheets, GRADELY brings the key academic workflow into one connected system.</p>
  <div class="landing-features">
    <article class="landing-feature"><div class="feature-icon">▦</div><h3>Academic management</h3><p>Organise sessions, terms, classes, arms, subjects, students and teacher assignments in one place.</p></article>
    <article class="landing-feature"><div class="feature-icon">✓</div><h3>Results processing</h3><p>Enter assessment scores, calculate totals and grades, review class positions and control publication.</p></article>
    <article class="landing-feature"><div class="feature-icon">▤</div><h3>Branded report sheets</h3><p>Prepare student report sheets with the school's identity, comments, grading details and performance summaries.</p></article>
    <article class="landing-feature"><div class="feature-icon">⌁</div><h3>Teacher collaboration</h3><p>Give authorised teachers limited access to upload scores for the subjects and classes assigned to them.</p></article>
    <article class="landing-feature"><div class="feature-icon">⌕</div><h3>Secure result checking</h3><p>Students use their student ID and a valid result-checking PIN. The PIN identifies the correct school and term.</p></article>
    <article class="landing-feature"><div class="feature-icon">▥</div><h3>School insights</h3><p>Review class and subject performance, compare outcomes and keep a record of published academic results.</p></article>
  </div>
</section>

<section class="landing-section">
  <h2>A closer look at the workspace</h2>
  <p class="landing-lead">The school workspace is designed around the real tasks administrators and teachers perform during a result cycle.</p>
  <div class="landing-shots">
    <article class="landing-shot">
      <div class="shot-top"><strong>Students · Class management</strong><span>GRADELY</span></div>
      <div class="shot-metrics"><b>JSS 2</b><b>42 students</b><b>Active</b></div>
      <div class="shot-table"><div class="shot-row shot-head"><span>Student</span><span>Student ID</span><span>Status</span></div><div class="shot-row"><span>Amara James</span><span>SGW0001</span><span>Active</span></div><div class="shot-row"><span>David Okoro</span><span>SGW0002</span><span>Active</span></div><div class="shot-row"><span>Mary John</span><span>SGW0003</span><span>Active</span></div></div>
      <p class="shot-caption">Organise students, classes and student IDs.</p>
    </article>
    <article class="landing-shot">
      <div class="shot-top"><strong>Term result sheet</strong><span>REPORT PREVIEW</span></div>
      <div class="shot-report-head"><span class="shot-school-mark">SGW</span><span><b>SUCCESS GATEWAY</b><small>Student academic report</small></span></div>
      <div class="shot-table"><div class="shot-row shot-head"><span>Subject</span><span>Total</span><span>Grade</span></div><div class="shot-row"><span>Mathematics</span><span>86</span><span>A</span></div><div class="shot-row"><span>English</span><span>78</span><span>B</span></div><div class="shot-row"><span>Basic Science</span><span>91</span><span>A</span></div></div>
      <p class="shot-caption">Prepare school-branded reports for students.</p>
    </article>
  </div>
</section>

<section class="landing-section">
  <h2>How result checking works</h2>
  <p class="landing-lead">You do not need to search for or select your school. Your result-checking PIN identifies the school and term linked to your result.</p>
  <div class="landing-how">
    <article class="landing-step"><b>01 · Get your details</b><p>Obtain your student ID and result-checking PIN from your school.</p></article>
    <article class="landing-step"><b>02 · Enter and verify</b><p>Enter both details in the result checker. GRADELY validates the PIN and student record.</p></article>
    <article class="landing-step"><b>03 · View your result</b><p>When the school has published the result, view your report sheet securely.</p></article>
  </div>
</section>

<section class="landing-check" id="check-result">
  <div><span class="landing-kicker">Student / Parent portal</span><h2>Check your result</h2><p>Enter the student ID and result-checking PIN issued by your school. The portal will automatically identify the school and the correct term.</p><p class="small">Need help? Contact your school administrator for your student ID or PIN.</p></div>
  <form class="form" method="post" action="<?= e(url('/portal/check')) ?>" autocomplete="off" novalidate>
    <?= csrf_field() ?>
    <?= field_html('Student ID', 'admission_no', old('admission_no'), 'text', ['required' => true, 'autocapitalize' => 'characters', 'autocomplete' => 'off', 'maxlength' => '40', 'placeholder' => 'e.g. SGW0001']) ?>
    <?= field_html('Result-checking PIN', 'pin', '', 'password', ['required' => true, 'inputmode' => 'numeric', 'autocomplete' => 'off', 'maxlength' => '20', 'placeholder' => 'Enter your PIN']) ?>
    <button class="btn btn-primary btn-block" type="submit">Check result</button>
    <p class="small" style="color:#c6d9cf">Your school is identified automatically. No school selection is required.</p>
  </form>
</section>

<section class="landing-section verify-lookup">
  <div>
    <span class="landing-kicker">Official result verification</span>
    <h2>Verify a published result</h2>
    <p class="landing-lead">Scan the Code 128 barcode printed on a report, or enter its verification code manually. Verification only confirms whether the published record matches.</p>
  </div>
  <div class="verify-tools">
    <button class="btn btn-ghost" type="button" id="start-barcode-scan">Scan result barcode with camera</button>
    <button class="btn btn-ghost" type="button" id="stop-barcode-scan" hidden>Stop camera</button>
    <video id="barcode-camera" playsinline muted hidden></video>
    <p class="small muted" id="barcode-scan-status" aria-live="polite">Camera scanning requires a supported browser and HTTPS. Manual entry is always available.</p>
    <form class="form" method="get" action="<?= e(url('/verify')) ?>">
      <?= field_html('Verification code or verification URL', 'code', '', 'text', ['maxlength' => '255', 'autocomplete' => 'off', 'placeholder' => 'Enter the 32-character code']) ?>
      <button class="btn btn-primary btn-block" type="submit">Verify result</button>
    </form>
  </div>
</section>

<section class="landing-section" style="text-align:center;padding-bottom:18px">
  <h2>Built for schools. Simple for families.</h2>
  <p class="landing-lead" style="margin:12px auto">GRADELY supports school administrators, teachers, students and parents through a more organised result management process.</p>
  <a class="btn btn-ghost" href="<?= e(url('/login')) ?>">Access school administration</a>
</section>
<?php else: ?>
<section class="portal-card">
  <h1 class="portal-title">Check your result</h1>
  <div class="portal-school">
    <?php if (!empty($school['logo_path'])): ?><img src="<?= e(url('media/logo/' . $school['id'])) ?>" width="56" height="56" alt=""><?php endif; ?>
    <div><p class="muted">Result portal for</p><p class="portal-school-name"><?= e($school['name']) ?></p></div>
  </div>
  <?php if (!$terms): ?><p class="empty">This school has no terms set up yet.</p><?php else: ?>
    <form class="form" method="post" action="<?= e(url('/portal/check')) ?>" autocomplete="off" novalidate>
      <?= csrf_field() ?><input type="hidden" name="school" value="<?= e($school['slug']) ?>">
      <?php $opts = []; foreach ($terms as $t) { $opts[$t['id']] = $t['session_name'] . ' · ' . $t['name']; } ?>
      <?= select_html('Session and term', 'term_id', $opts, old('term_id'), ['required' => true], 'Select session and term…') ?>
      <?= field_html('Student ID', 'admission_no', old('admission_no'), 'text', ['required' => true, 'autocapitalize' => 'characters', 'autocomplete' => 'off', 'maxlength' => '40']) ?>
      <?= field_html('Scratch-card PIN', 'pin', '', 'password', ['required' => true, 'inputmode' => 'numeric', 'autocomplete' => 'off', 'maxlength' => '20']) ?>
      <button class="btn btn-primary btn-block" type="submit">View result</button>
    </form>
  <?php endif; ?>
  <p class="small"><a href="<?= e(url('/')) ?>">Back to GRADELY</a></p>
</section>
<?php endif; ?>
