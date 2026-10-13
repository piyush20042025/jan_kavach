<?php
/**
 * Document verification queue.
 *
 * @var JanKavach\Core\Paginator $paginator
 * @var array $filters, $documentTypes, $states, $districts, $expiring
 */
$pageSubtitle = 'Uploaded documents awaiting verification in ' . ($scopeLabel ?: 'the programme');
require VIEW_PATH . '/partials/page-head.php';
?>

<form method="get" class="jk-filter-bar">
    <div class="row g-2">
        <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label" for="search">Search</label>
            <input type="search" class="form-control form-control-sm" id="search" name="search"
                   value="<?= e($filters['search'] ?? '') ?>" placeholder="Beneficiary name or ID">
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <label class="form-label" for="document_type_id">Document Type</label>
            <select class="form-select form-select-sm" id="document_type_id" name="document_type_id" data-auto-submit>
                <option value="">All types</option>
                <?php foreach ($documentTypes as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['document_type_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="state_id">State</label>
            <select class="form-select form-select-sm" id="state_id" name="state_id" data-auto-submit>
                <option value="">All States</option>
                <?php foreach ($states as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['state_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="district_id">District</label>
            <select class="form-select form-select-sm" id="district_id" name="district_id"
                    data-depends-on="state_id" data-server-filled="1"
                    data-endpoint="/geo/districts/{id}" data-placeholder="All Districts" data-auto-submit>
                <option value="">All Districts</option>
                <?php foreach ($districts as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['district_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-lg-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            <a href="<?= e(url('/documents/pending')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </div>
</form>

<div class="jk-card">
    <div class="jk-card__head">
        <h3 class="jk-card__title"><i class="bi bi-hourglass-split"></i>Awaiting Verification</h3>
    </div>
    <div class="jk-card__body jk-card__body--flush">
        <?php if ($paginator->isEmpty()): ?>
            <div class="jk-empty">
                <i class="bi bi-check2-circle"></i>
                <h5>Nothing is waiting</h5>
                <p>Every uploaded document in your area has been verified or rejected.</p>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap">
                <table class="jk-table">
                    <thead>
                        <tr><th>Beneficiary</th><th>Document</th><th>Number</th><th>District</th>
                            <th>Uploaded</th><th class="jk-table__actions no-print">Actions</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginator->items as $document): ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url('/beneficiaries/' . (int)$document['beneficiary_id'])) ?>" class="fw-semibold">
                                        <?= e($document['beneficiary_name']) ?>
                                    </a>
                                    <div class="text-muted tiny"><?= e($document['beneficiary_code']) ?></div>
                                </td>
                                <td class="fw-semibold"><?= e($document['document_name']) ?></td>
                                <td class="jk-masked">
                                    <?= mask_document_number($document['document_number'], (bool)$document['is_sensitive']) ?>
                                </td>
                                <td><?= e($document['district_name'] ?? '-') ?></td>
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
                                           data-bs-toggle="tooltip" title="View file"><i class="bi bi-eye"></i></a>
                                        <?php if (can('beneficiary.verify')): ?>
                                            <form method="post" action="<?= e(url('/documents/' . (int)$document['id'] . '/verify')) ?>" class="d-inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="status" value="Verified">
                                                <button class="btn btn-outline-success" type="submit"
                                                        data-bs-toggle="tooltip" title="Mark verified">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            </form>
                                            <button class="btn btn-outline-danger jk-reject" type="button"
                                                    data-id="<?= (int)$document['id'] ?>"
                                                    data-name="<?= e($document['document_name']) ?>"
                                                    data-bs-toggle="tooltip" title="Reject">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
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

    <?php if (!$paginator->isEmpty()): ?>
        <div class="jk-card__foot">
            <?php $recordNoun = 'document'; require VIEW_PATH . '/partials/pagination.php'; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($expiring)): ?>
    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-calendar-x"></i>Documents Expiring or Expired</h3>
            <span class="badge bg-warning text-dark"><?= count($expiring) ?></span>
        </div>
        <div class="jk-card__body jk-card__body--flush">
            <div class="jk-table-wrap">
                <table class="jk-table jk-table--compact">
                    <thead><tr><th>Beneficiary</th><th>Document</th><th>District</th><th>Expires</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($expiring as $document): ?>
                            <?php $days = (int)$document['days_left']; ?>
                            <tr class="<?= $days < 0 ? 'jk-row-danger' : 'jk-row-warning' ?>">
                                <td>
                                    <a href="<?= e(url('/beneficiaries/' . (int)$document['beneficiary_id'])) ?>">
                                        <?= e($document['beneficiary_name']) ?>
                                    </a>
                                    <div class="text-muted tiny"><?= e($document['beneficiary_code']) ?></div>
                                </td>
                                <td><?= e($document['document_name']) ?></td>
                                <td><?= e($document['district_name'] ?? '-') ?></td>
                                <td><?= fmt_date($document['expiry_date']) ?></td>
                                <td>
                                    <?php if ($days < 0): ?>
                                        <span class="badge bg-danger">Expired <?= abs($days) ?> days ago</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Expires in <?= $days ?> days</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

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
                <label class="form-label" for="rejection_reason">Reason <span class="req">*</span></label>
                <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="3"
                          required maxlength="255"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Reject</button>
            </div>
        </form>
    </div>
</div>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
  document.querySelectorAll('.jk-reject').forEach(function (button) {
    button.addEventListener('click', function () {
      document.getElementById('rejectDocName').textContent = button.dataset.name;
      document.getElementById('rejectForm').action = JK.url('/documents/' + button.dataset.id + '/verify');
      new bootstrap.Modal(document.getElementById('rejectModal')).show();
    });
  });
</script>
<?php \JanKavach\Core\View::endSection(); ?>
<?php endif; ?>
