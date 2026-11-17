<?php /** @var ?array $report */ ?>
<?php if ($report): ?>
  <section class="card mb">
    <div class="card-head"><h2>Import finished</h2></div>
    <div class="stat-row">
      <div class="stat-mini"><b><?= e($report['imported']) ?></b><span>students imported</span></div>
      <div class="stat-mini"><b><?= e(count($report['errors'])) ?></b><span>rows skipped</span></div>
      <div class="stat-mini"><b><?= e($report['total']) ?></b><span>rows in file</span></div>
    </div>
    <?php if ($report['errors']): ?>
      <h3 class="mt">Rows that were skipped</h3>
      <div class="table-wrap"><table class="table table-tight">
        <thead><tr><th>Row</th><th>Student ID</th><th>Problem</th></tr></thead>
        <tbody><?php foreach ($report['errors'] as [$line, $adm, $msg]): ?>
          <tr><td><?= e($line) ?></td><td><?= e($adm) ?></td><td><?= e($msg) ?></td></tr>
        <?php endforeach; ?></tbody>
      </table></div>
      <p class="muted small mt">Fix these rows in your file and import it again. Rows that were already imported are skipped as duplicates.</p>
    <?php endif; ?>
    <p class="mt"><a class="btn btn-primary" href="<?= e(url('/school/students')) ?>">View students</a></p>
  </section>
<?php endif; ?>

<section class="card">
  <div class="card-head"><h2>Import students from Excel or CSV</h2></div>
  <ol class="steps">
    <li>Download the template and fill one student per row. Required: <code>first_name</code>, <code>last_name</code>, <code>class</code>. Student ID is optional; leave it blank to generate one automatically.</li>
    <li>The <code>class</code> (and optional <code>arm</code>) must already exist under Classes.</li>
    <li>Upload the file (.xlsx or .csv, up to 2 MB and 2,000 rows).</li>
  </ol>
  <p class="muted small">For example, with the school prefix SGW, new records are assigned SGW0001, SGW0002 and so on. You can still enter your own student IDs.</p>
  <p><a class="btn btn-ghost btn-sm" href="<?= e(url('/school/students/template.csv')) ?>"><?= icon('download', 16) ?> Download template</a></p>
  <form class="form" method="post" action="<?= e(url('/school/students/import')) ?>" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <label class="field"><span class="field-label">File</span><input type="file" name="file" accept=".csv,.xlsx,text/csv" required></label>
    <div class="toolbar-left"><button class="btn btn-primary" type="submit"><?= icon('upload', 18) ?> Import students</button></div>
  </form>
</section>
