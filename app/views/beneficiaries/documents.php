<?php
/**
 * Beneficiary document management (specification section 19).
 *
 * @var array $beneficiary, $documents, $outstanding, $documentTypes
 * @var int $maxSizeMb
 * @var string $allowedTypes
 */
$pageSubtitle = $beneficiary['name'] . ' (' . $beneficiary['beneficiary_code'] . ')';
$heldTypeIds = array_column($documents, 'document_type_id');

ob_start(); ?>
    <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'])) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Profile
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<?php if (!empty($outstanding)): ?>
    <div class="alert alert-warning">
        <div class="d-flex gap-2 align-items-start">
            <i class="bi bi-exclamation-triangle mt-1"></i>
            <div class="flex-grow-1">
                <strong><?= count($outstanding) ?> mandatory document(s) still needed</strong>
                for the schemes this beneficiary matched.
                <div class="jk-table-wrap mt-2">
                    <table class="jk-table jk-table--compact" style="background:transparent">
                        <thead><tr><th>Document</th><th>Required by</th><th class="no-print"></th></tr></thead>
                        <tbody>
                            <?php foreach ($outstanding as $document): ?>
                                <tr>
                                    <td class="fw-semibold"><?= e($document['name']) ?></td>
                                    <td class="small"><?= e(str_limit($document['required_by'], 90)) ?></td>
                                    <td class="text-end no-print">
                                        <button class="btn btn-sm btn-primary jk-upload-for"
                                                data-type-id="<?= (int)$document['id'] ?>"
                                                data-type-name="<?= e($document['name']) ?>">
                                            <i class="bi bi-upload me-1"></i>Upload
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3">
<div class="col-lg-5">
    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-upload"></i>Upload a Document</h3></div>
        <div class="jk-card__body">
            <form method="post" action="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/documents')) ?>"
                  enctype="multipart/form-data" data-guard id="uploadForm">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label" for="document_type_id">Document Type <span class="req">*</span></label>
                    <select class="form-select" id="document_type_id" name="document_type_id" required>
                        <option value="">Select a document type</option>
                        <?php
                        $byCategory = [];
                        foreach ($documentTypes as $type) {
                            $byCategory[$type['category']][] = $type;
                        }
                        foreach ($byCategory as $category => $types): ?>
                            <optgroup label="<?= e($category) ?>">
                                <?php foreach ($types as $type): ?>
                                    <option value="<?= (int)$type['id'] ?>"
                                            data-requires-number="<?= (int)$type['requires_number'] ?>"
                                            data-validity="<?= (int)$type['validity_required'] ?>"
                                            <?= in_array($type['id'], $heldTypeIds) ? 'data-held="1"' : '' ?>>
                                        <?= e($type['name']) ?><?= in_array($type['id'], $heldTypeIds) ? ' (already on file)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3" id="replaceWrap" style="display:none">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="replace_existing" name="replace_existing" value="1">
                        <label class="form-check-label" for="replace_existing">
                            Replace the copy already on file
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="document_number">Document Number</label>
                    <input type="text" class="form-control" id="document_number" name="document_number"
                           maxlength="80" autocomplete="off">
                    <div class="form-text">Sensitive numbers are shown masked wherever they appear.</div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label" for="issue_date">Issue Date</label>
                        <input type="date" class="form-control" id="issue_date" name="issue_date" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="expiry_date">Expiry Date</label>
                        <input type="date" class="form-control" id="expiry_date" name="expiry_date"
                               data-date-min-from="issue_date">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="document_file">File <span class="req">*</span></label>
                    <input type="file" class="form-control" id="document_file" name="document_file" required
                           accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                    <div class="form-text">
                        <?= e(strtoupper(str_replace(',', ', ', $allowedTypes))) ?> only,
                        up to <?= (int)$maxSizeMb ?> MB.
                    </div>
                    <div id="fileInfo" class="small mt-1"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="remarks">Remarks</label>
                    <textarea class="form-control" id="remarks" name="remarks" rows="2" maxlength="500"></textarea>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-upload me-1"></i>Upload Document
                </button>
            </form>
        </div>
    </div>

    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-shield-check"></i>How Files Are Handled</h3></div>
        <div class="jk-card__body small text-muted">
            <ul class="mb-0 ps-3">
                <li>Files are stored outside the web root and have no public URL.</li>
                <li>The real file type is read from the file contents, not the browser's claim.</li>
                <li>Each file is renamed on upload, so the original name never reaches the disk.</li>
                <li>Every view and download is written to the audit trail.</li>
            </ul>
        </div>
    </div>
</div>

<div class="col-lg-7">
    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title">
                <i class="bi bi-folder"></i>Documents on File
                <span class="badge bg-secondary"><?= count($documents) ?></span>
            </h3>
        </div>
        <div class="jk-card__body jk-card__body--flush">
            <?php if (empty($documents)): ?>
                <div class="jk-empty">
                    <i class="bi bi-folder-x"></i>
                    <h5>No documents uploaded yet</h5>
                    <p>Use the form alongside to add the beneficiary's supporting documents.</p>
                </div>
            <?php else: ?>
                <div class="jk-table-wrap">
                    <table class="jk-table jk-table--compact">
                        <thead>
                            <tr><th>Document</th><th>Number</th><th>Validity</th><th>Status</th>
                                <th>Uploaded</th><th class="no-print">Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($documents as $document): ?>
                                <?php
                                $expired = !empty($document['expiry_date']) && strtotime($document['expiry_date']) < time();
                                $rowClass = $document['verification_status'] === 'Rejected' ? 'jk-row-danger'
                                          : ($expired ? 'jk-row-warning' : '');
                                ?>
                                <tr class="<?= $rowClass ?>">
                                    <td>
                                        <span class="fw-semibold"><?= e($document['document_name']) ?></span>
                                        <div class="text-muted tiny">
                                            <?= e($document['original_name'] ?? '') ?>
                                            <?php if (!empty($document['file_size'])): ?>
                                                &middot; <?= (int)$document['file_size'] >= 1048576
                                                    ? round((int)$document['file_size'] / 1048576, 1) . ' MB'
                                                    : round((int)$document['file_size'] / 1024) . ' KB' ?>
                                            <?php endif; ?>
                                            <?php if ((int)$document['used_by_schemes'] > 0): ?>
                                                &middot; needed by <?= (int)$document['used_by_schemes'] ?> scheme(s)
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="jk-masked">
                                        <?= mask_document_number($document['document_number'], (bool)$document['is_sensitive']) ?>
                                    </td>
                                    <td class="small">
                                        <?php if (!empty($document['issue_date']) || !empty($document['expiry_date'])): ?>
                                            <?= fmt_date($document['issue_date'], '?') ?> &ndash; <?= fmt_date($document['expiry_date'], '?') ?>
                                            <?php if ($expired): ?>
                                                <span class="badge bg-warning text-dark d-block mt-1">Expired</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-subtle">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= badge($document['verification_status'], status_variant($document['verification_status'])) ?>
                                        <?php if ($document['verification_status'] === 'Rejected' && !empty($document['rejection_reason'])): ?>
                                            <div class="text-danger tiny mt-1"><?= e($document['rejection_reason']) ?></div>
                                        <?php elseif ($document['verification_status'] === 'Verified' && !empty($document['verified_by_name'])): ?>
                                            <div class="text-subtle tiny mt-1">by <?= e($document['verified_by_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= fmt_date($document['created_at']) ?>
                                        <?php if (!empty($document['uploaded_by_name'])): ?>
                                            <div class="tiny">by <?= e($document['uploaded_by_name']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="jk-table__actions no-print">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= e(url('/documents/' . (int)$document['id'] . '/view')) ?>"
                                               target="_blank" class="btn btn-outline-secondary"
                                               data-bs-toggle="tooltip" title="View"><i class="bi bi-eye"></i></a>
                                            <a href="<?= e(url('/documents/' . (int)$document['id'] . '/download')) ?>"
                                               class="btn btn-outline-secondary"
                                               data-bs-toggle="tooltip" title="Download"><i class="bi bi-download"></i></a>
                                            <?php if (can('beneficiary.verify')): ?>
                                                <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split"
                                                        data-bs-toggle="dropdown" aria-label="Verify"></button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <form method="post" action="<?= e(url('/documents/' . (int)$document['id'] . '/verify')) ?>" class="m-0">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="status" value="Verified">
                                                            <button class="dropdown-item text-success" type="submit">
                                                                <i class="bi bi-check-circle me-2"></i>Mark verified
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <li>
                                                        <button class="dropdown-item text-danger jk-reject"
                                                                data-id="<?= (int)$document['id'] ?>"
                                                                data-name="<?= e($document['document_name']) ?>">
                                                            <i class="bi bi-x-circle me-2"></i>Reject
                                                        </button>
                                                    </li>
                                                    <?php if (can('beneficiary.document')): ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="post" action="<?= e(url('/documents/' . (int)$document['id'] . '/delete')) ?>" class="m-0">
                                                                <?= csrf_field() ?>
                                                                <button class="dropdown-item text-danger" type="submit"
                                                                        data-confirm="Remove this document and its file permanently?">
                                                                    <i class="bi bi-trash me-2"></i>Delete
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
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

<!-- Reject modal -->
<?php if (can('beneficiary.verify')): ?>
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post" id="rejectForm" data-guard>
            <?= csrf_field() ?>
            <input type="hidden" name="status" value="Rejected">
            <div class="modal-header">
                <h5 class="modal-title">Reject Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2">Rejecting <strong id="rejectDocName"></strong>.</p>
                <label class="form-label" for="rejection_reason">
                    Reason <span class="req">*</span>
                </label>
                <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3" required
                          maxlength="255" placeholder="What is wrong with it, so the field team can correct it"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject Document</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
(function () {
  const typeSelect  = document.getElementById('document_type_id');
  const replaceWrap = document.getElementById('replaceWrap');
  const numberInput = document.getElementById('document_number');
  const expiry      = document.getElementById('expiry_date');

  typeSelect.addEventListener('change', function () {
    const option = typeSelect.options[typeSelect.selectedIndex];
    if (!option || !option.value) { replaceWrap.style.display = 'none'; return; }

    replaceWrap.style.display = option.dataset.held === '1' ? 'block' : 'none';
    numberInput.required = option.dataset.requiresNumber === '1';
    expiry.closest('.col-6').style.opacity = option.dataset.validity === '1' ? '1' : '.6';
  });

  // Client-side size check, so a large file is caught before it is sent.
  const fileInput = document.getElementById('document_file');
  const maxBytes = <?= (int)$maxSizeMb ?> * 1024 * 1024;

  fileInput.addEventListener('change', function () {
    const info = document.getElementById('fileInfo');
    const file = fileInput.files[0];
    if (!file) { info.innerHTML = ''; return; }

    const mb = file.size / 1048576;
    if (file.size > maxBytes) {
      info.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>' +
                       'This file is ' + mb.toFixed(1) + ' MB - the limit is <?= (int)$maxSizeMb ?> MB.</span>';
      fileInput.value = '';
      return;
    }
    info.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>' +
                     JK.escape(file.name) + ' (' + mb.toFixed(2) + ' MB)</span>';
  });

  // Upload buttons in the "still needed" list preselect their document type.
  document.querySelectorAll('.jk-upload-for').forEach(function (button) {
    button.addEventListener('click', function () {
      typeSelect.value = button.dataset.typeId;
      typeSelect.dispatchEvent(new Event('change'));
      document.getElementById('uploadForm').scrollIntoView({ behavior: 'smooth', block: 'center' });
      fileInput.focus();
    });
  });

  // Reject modal
  document.querySelectorAll('.jk-reject').forEach(function (button) {
    button.addEventListener('click', function () {
      document.getElementById('rejectDocName').textContent = button.dataset.name;
      document.getElementById('rejectForm').action = JK.url('/documents/' + button.dataset.id + '/verify');
      new bootstrap.Modal(document.getElementById('rejectModal')).show();
    });
  });
})();
</script>
<?php \JanKavach\Core\View::endSection(); ?>
