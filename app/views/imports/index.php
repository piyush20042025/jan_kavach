<?php
/**
 * Excel scheme import - upload screen (specification section 37).
 * @var array $batches, $fields
 * @var int $maxSizeMb
 */
$pageSubtitle = 'Bulk-load schemes from an Excel workbook or CSV file';

ob_start(); ?>
    <a href="<?= e(url('/import/schemes/template')) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-download me-1"></i>Download Template
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<div class="row g-3">
<div class="col-lg-5">
    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-upload"></i>Upload a Workbook</h3></div>
        <div class="jk-card__body">
            <form method="post" action="<?= e(url('/import/schemes/upload')) ?>"
                  enctype="multipart/form-data" data-guard>
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="import_file">Excel or CSV File <span class="req">*</span></label>
                    <input type="file" class="form-control" id="import_file" name="import_file" required
                           accept=".xlsx,.xls,.csv">
                    <div class="form-text">
                        XLSX, XLS or CSV, up to <?= (int)$maxSizeMb ?> MB.
                        The first row must contain the column headings.
                    </div>
                    <div id="fileInfo" class="small mt-1"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="sheet_name">Sheet Name</label>
                    <input type="text" class="form-control" id="sheet_name" name="sheet_name"
                           maxlength="120" placeholder="Leave blank to use the first sheet">
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-upload me-1"></i>Upload &amp; Validate
                </button>
            </form>
        </div>
    </div>

    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-list-ol"></i>How the Import Works</h3></div>
        <div class="jk-card__body">
            <ol class="ps-3 mb-0 small text-muted">
                <li class="mb-2">
                    <strong class="text-dark">Upload.</strong>
                    The file is stored outside the web root and its real type is verified.
                </li>
                <li class="mb-2">
                    <strong class="text-dark">Map.</strong>
                    Columns are matched to fields automatically from their headings. You can correct
                    the mapping before anything is written.
                </li>
                <li class="mb-2">
                    <strong class="text-dark">Validate &amp; preview.</strong>
                    Every row is checked and flagged as ready, a possible duplicate, or an error.
                    Nothing has been saved at this point.
                </li>
                <li class="mb-2">
                    <strong class="text-dark">Choose what to do with duplicates.</strong>
                    Skip them, or update the existing scheme.
                </li>
                <li>
                    <strong class="text-dark">Import.</strong>
                    Each scheme is written in its own transaction, so one bad row cannot undo the rest.
                    Rejected rows stay available for review.
                </li>
            </ol>
        </div>
    </div>
</div>

<div class="col-lg-7">
    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-table"></i>Expected Columns</h3></div>
        <div class="jk-card__body">
            <p class="text-muted small">
                These are the fields the importer understands. Headings are matched loosely, so
                &ldquo;Priority Ranking (For FXBIS)&rdquo; still lands on <em>Priority Ranking</em>.
                Only <strong>Name of the Scheme</strong> and <strong>National / State-Specific</strong>
                are required.
            </p>
            <div class="d-flex flex-wrap gap-1">
                <?php foreach ($fields as $key => $label): ?>
                    <span class="jk-chip <?= in_array($key, ['name', 'natstate'], true) ? 'jk-chip--primary' : '' ?>">
                        <?= e($label) ?>
                        <?php if (in_array($key, ['name', 'natstate'], true)): ?>
                            <span class="text-danger">*</span>
                        <?php endif; ?>
                    </span>
                <?php endforeach; ?>
            </div>
            <p class="text-muted small mb-0 mt-3">
                <i class="bi bi-info-circle me-1"></i>
                Merged cells are handled: where a workbook merges a scheme's columns down several rows to
                list more than one Beneficiary Type or Theme, those rows are read as one scheme with several
                values.
            </p>
        </div>
    </div>

    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-clock-history"></i>Recent Imports</h3>
        </div>
        <div class="jk-card__body jk-card__body--flush">
            <?php if (empty($batches)): ?>
                <div class="jk-empty">
                    <i class="bi bi-inbox"></i>
                    <h5>No imports yet</h5>
                    <p>Upload a workbook to get started.</p>
                </div>
            <?php else: ?>
                <div class="jk-table-wrap">
                    <table class="jk-table jk-table--compact">
                        <thead>
                            <tr><th>Batch</th><th>File</th><th>Status</th><th class="num">Rows</th>
                                <th class="num">Inserted</th><th class="num">Updated</th><th class="num">Failed</th>
                                <th>By</th><th>When</th><th class="no-print"></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batches as $batch): ?>
                                <tr>
                                    <td><code class="small"><?= e($batch['batch_code']) ?></code></td>
                                    <td class="small"><?= e(str_limit($batch['original_name'], 26)) ?></td>
                                    <td>
                                        <?= badge($batch['status'], match ($batch['status']) {
                                            'Imported'  => 'success',
                                            'Validated' => 'info',
                                            'Failed'    => 'danger',
                                            'Cancelled' => 'secondary',
                                            default     => 'warning',
                                        }) ?>
                                    </td>
                                    <td class="num"><?= num($batch['total_rows']) ?></td>
                                    <td class="num"><?= num($batch['inserted_rows']) ?></td>
                                    <td class="num"><?= num($batch['updated_rows']) ?></td>
                                    <td class="num">
                                        <?= (int)$batch['failed_rows'] > 0
                                            ? '<span class="text-danger fw-semibold">' . num($batch['failed_rows']) . '</span>'
                                            : '0' ?>
                                    </td>
                                    <td class="small text-muted"><?= e(str_limit($batch['imported_by_name'] ?? '-', 16)) ?></td>
                                    <td class="small text-muted nowrap"><?= time_ago($batch['created_at']) ?></td>
                                    <td class="text-end no-print">
                                        <a href="<?= e(url('/import/schemes/' . (int)$batch['id'] . '/preview')) ?>"
                                           class="btn btn-sm btn-outline-secondary">Open</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
(function () {
  const input = document.getElementById('import_file');
  const info  = document.getElementById('fileInfo');
  const maxBytes = <?= (int)$maxSizeMb ?> * 1024 * 1024;

  input.addEventListener('change', function () {
    const file = input.files[0];
    if (!file) { info.innerHTML = ''; return; }

    const mb = file.size / 1048576;
    if (file.size > maxBytes) {
      info.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>' +
                       'This file is ' + mb.toFixed(1) + ' MB - the limit is <?= (int)$maxSizeMb ?> MB.</span>';
      input.value = '';
      return;
    }
    info.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>' +
                     JK.escape(file.name) + ' (' + mb.toFixed(2) + ' MB)</span>';
  });
})();
</script>
<?php \JanKavach\Core\View::endSection(); ?>
