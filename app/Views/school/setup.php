<?php
$cards = [
    ['/school/academics', 'calendar', 'Sessions & terms', 'Add academic sessions, terms and set the current term.'],
    ['/school/classes', 'layers', 'Classes & arms', 'Create classes such as JSS 1 and their arms (A, B, C).'],
    ['/school/subjects', 'book', 'Subjects', 'Create subjects and assign them to classes.'],
    ['/school/settings', 'edit', 'Grading & scoring', 'Assessment components, grading scale, positions and what report cards display.'],
    ['/school/teachers', 'user', 'Teachers', 'Teacher accounts, permissions and class/subject assignments.'],
    ['/school/profile', 'school', 'School profile & logo', 'Name, contact details and logo shown on report cards.'],
];
?>
<div class="link-grid">
  <?php foreach ($cards as [$href, $ico, $label, $desc]): ?>
    <a class="link-card" href="<?= e(url($href)) ?>">
      <span class="ico"><?= icon($ico, 22) ?></span>
      <span><strong><?= e($label) ?></strong><span><?= e($desc) ?></span></span>
    </a>
  <?php endforeach; ?>
</div>
