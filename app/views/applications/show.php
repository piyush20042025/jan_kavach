<?php
/**
 * Application detail with the status pipeline.
 *
 * @var array $application, $statuses, $assignees
 */
$pageSubtitle = $application['beneficiary_name'] . ' &rarr; ' . ($application['scheme_short_name'] ?: $application['scheme_name']);
$currentOrder = (int)$application['stage_order'];

ob_start(); ?>
    <a href="<?= e(url('/beneficiaries/' . (int)$application['beneficiary_id'])) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-person me-1"></i>Beneficiary
    </a>
    <?php if (can('followup.create')): ?>
        <a href="<?= e(url('/followups/create?application_id=' . (int)$application['id'])) ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-telephone me-1"></i>Record Follow-up
        </a>
    <?php endif; ?>
    <?php if (can('application.edit')): ?>
        <a href="<?= e(url('/applications/' . (int)$application['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
    <?php endif; ?>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<!-- Pipeline -->
<div class="jk-card">
    <div class="jk-card__body">
        <div class="jk-steps">
            <?php foreach ($statuses as $status): ?>
                <?php
                if (in_array($status['code'], ['REJECTED', 'CLOSED'], true)
                    && $application['status_code'] !== $status['code']) {
                    continue;
                }
                $order = (int)$status['stage_order'];
                $class = $order < $currentOrder ? 'done' : ($order === $currentOrder ? 'active' : '');
                ?>
                <div class="jk-step <?= $class ?>">
                    <span class="jk-step__num">
                        <?= $order < $currentOrder ? '<i class="bi bi-check"></i>' : $order ?>
                    </span>
                    <span class="flex-grow-1"><?= e($status['name']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<div class="row g-3">
<div class="col-lg-8">

    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-file-earmark-text"></i>Application Details</h3>
            <span class="badge" style="background:<?= e($application['status_color'] ?: '#6c757d') ?>">
                <?= e($application['status_name']) ?>
            </span>
        </div>
        <div class="jk-card__body">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="jk-dl">
                        <div class="jk-dl__row"><div class="jk-dl__key">Application Code</div>
                            <div class="jk-dl__val"><code><?= e($application['application_code']) ?></code></div></div>
                        <div class="jk-dl__row"><div class="jk-dl__key">Department Number</div>
                            <div class="jk-dl__val"><?= e($application['application_number'] ?: '-') ?></div></div>
                        <div class="jk-dl__row"><div class="jk-dl__key">Department</div>
                            <div class="jk-dl__val"><?= e($application['department'] ?: '-') ?></div></div>
                        <div class="jk-dl__row"><div class="jk-dl__key">Eligibility Grade</div>
                            <div class="jk-dl__val"><?= badge($application['eligibility_status'], eligibility_variant($application['eligibility_status'])) ?></div></div>
                        <div class="jk-dl__row"><div class="jk-dl__key">Assigned To</div>
                            <div class="jk-dl__val"><?= e($application['assigned_to_name'] ?: 'Unassigned') ?></div></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="jk-dl">
                        <div class="jk-dl__row"><div class="jk-dl__key">Referral Date</div>
                            <div class="jk-dl__val"><?= fmt_date($application['referral_date']) ?></div></div>
                        <div class="jk-dl__row"><div class="jk-dl__key">Initiated</div>
                            <div class="jk-dl__val"><?= fmt_date($application['initiated_date']) ?></div></div>
                        <div class="jk-dl__row"><div class="jk-dl__key">Submitted</div>
                            <div class="jk-dl__val"><?= fmt_date($application['submission_date']) ?></div></div>
                        <div class="jk-dl__row"><div class="jk-dl__key">Approved</div>
                            <div class="jk-dl__val"><?= fmt_date($application['approval_date']) ?></div></div>
                        <div class="jk-dl__row"><div class="jk-dl__key">Next Follow-up</div>
                            <div class="jk-dl__val <?= !empty($application['next_followup_date']) && strtotime($application['next_followup_date']) < strtotime('today') ? 'text-danger fw-semibold' : '' ?>">
                                <?= fmt_date($application['next_followup_date']) ?>
                            </div></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($application['rejection_reason'])): ?>
                <div class="alert alert-danger mt-3 mb-0">
                    <strong>Rejection reason:</strong> <?= e($application['rejection_reason']) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($application['remarks'])): ?>
                <div class="mt-3">
                    <div class="text-muted small fw-semibold mb-1">Remarks</div>
                    <div class="jk-prose"><?= nl2br_e($application['remarks']) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Status history -->
    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-clock-history"></i>Status History</h3></div>
        <div class="jk-card__body">
            <?php if (empty($application['history'])): ?>
                <p class="text-muted mb-0">No status changes recorded.</p>
            <?php else: ?>
                <div class="jk-timeline">
                    <?php foreach ($application['history'] as $entry): ?>
                        <div class="jk-timeline__item done">
                            <span class="jk-timeline__dot" style="background:<?= e($entry['to_color'] ?: '#6c757d') ?>;border-color:<?= e($entry['to_color'] ?: '#6c757d') ?>">
                                <i class="bi bi-arrow-right"></i>
                            </span>
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <div>
                                    <p class="jk-timeline__title">
                                        <?= !empty($entry['from_status']) ? e($entry['from_status']) . ' &rarr; ' : '' ?>
                                        <?= e($entry['to_status']) ?>
                                    </p>
                                    <div class="jk-timeline__meta">
                                        <?= e($entry['remarks'] ?: 'No remarks') ?>
                                        <?php if (!empty($entry['changed_by_name'])): ?>
                                            &middot; by <?= e($entry['changed_by_name']) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="text-muted small nowrap"><?= fmt_datetime($entry['changed_at']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Follow-ups -->
    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-telephone"></i>Follow-ups</h3>
            <?php if (can('followup.create')): ?>
                <a href="<?= e(url('/followups/create?application_id=' . (int)$application['id'])) ?>" class="small">Record one</a>
            <?php endif; ?>
        </div>
        <div class="jk-card__body jk-card__body--flush">
            <?php if (empty($application['followups'])): ?>
                <p class="text-muted p-3 mb-0">No follow-up has been recorded against this application.</p>
            <?php else: ?>
                <div class="jk-table-wrap">
                    <table class="jk-table jk-table--compact">
                        <thead><tr><th>Date</th><th>Type</th><th>Discussion</th><th>Outcome</th><th>Next</th><th>By</th></tr></thead>
                        <tbody>
                            <?php foreach ($application['followups'] as $followup): ?>
                                <tr>
                                    <td class="nowrap"><?= fmt_date($followup['followup_date']) ?></td>
                                    <td><?= e($followup['followup_type']) ?></td>
                                    <td class="text-muted small"><?= e(str_limit($followup['discussion'] ?? '-', 110)) ?></td>
                                    <td><?= badge($followup['outcome'], status_variant($followup['outcome'])) ?></td>
                                    <td class="nowrap"><?= fmt_date($followup['next_followup_date']) ?></td>
                                    <td class="small text-muted"><?= e($followup['responsible_name'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Benefits -->
    <?php if (!empty($application['benefits'])): ?>
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-award"></i>Benefits Received</h3></div>
            <div class="jk-card__body jk-card__body--flush">
                <div class="jk-table-wrap">
                    <table class="jk-table jk-table--compact">
                        <thead><tr><th>Date</th><th>Type</th><th class="num">Amount</th><th>Mode</th>
                            <th>Instalment</th><th>Reference</th></tr></thead>
                        <tbody>
                            <?php foreach ($application['benefits'] as $benefit): ?>
                                <tr>
                                    <td><?= fmt_date($benefit['received_date']) ?></td>
                                    <td><?= e($benefit['benefit_type'] ?? '-') ?></td>
                                    <td class="num fw-semibold"><?= money($benefit['amount']) ?></td>
                                    <td><?= e($benefit['payment_mode']) ?></td>
                                    <td><?= $benefit['instalment_no'] !== null ? (int)$benefit['instalment_no'] : '-' ?></td>
                                    <td class="text-muted small"><?= e($benefit['reference_no'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Side -->
<div class="col-lg-4">
    <?php if (can('application.edit')): ?>
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-arrow-right-circle"></i>Advance the Application</h3></div>
            <div class="jk-card__body">
                <form method="post" action="<?= e(url('/applications/' . (int)$application['id'] . '/status')) ?>" data-guard id="statusForm">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label" for="status_id">New Status <span class="req">*</span></label>
                        <select class="form-select" id="status_id" name="status_id" required>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= (int)$status['id'] ?>"
                                        data-code="<?= e($status['code']) ?>"
                                        <?= (int)$application['status_id'] === (int)$status['id'] ? 'selected' : '' ?>>
                                    <?= e($status['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3" id="applicationNumberWrap" style="display:none">
                        <label class="form-label" for="application_number">Department Application Number</label>
                        <input type="text" class="form-control" id="application_number" name="application_number"
                               maxlength="80" value="<?= e($application['application_number'] ?? '') ?>">
                    </div>

                    <div class="mb-3" id="rejectionWrap" style="display:none">
                        <label class="form-label" for="rejection_reason">Rejection Reason <span class="req">*</span></label>
                        <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="2"
                                  maxlength="500"><?= e($application['rejection_reason'] ?? '') ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="next_followup_date">Next Follow-up Date</label>
                        <input type="date" class="form-control" id="next_followup_date" name="next_followup_date"
                               value="<?= e($application['next_followup_date'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="remarks">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2" maxlength="500"></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i>Update Status
                    </button>
                </form>
            </div>
        </div>

        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-award"></i>Record a Benefit</h3></div>
            <div class="jk-card__body">
                <form method="post" action="<?= e(url('/applications/' . (int)$application['id'] . '/benefit')) ?>" data-guard>
                    <?= csrf_field() ?>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label" for="received_date">Date <span class="req">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="received_date"
                                   name="received_date" required max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="amount">Amount</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><?= e(config('app.currency_symbol')) ?></span>
                                <input type="number" step="0.01" min="0" class="form-control" id="amount" name="amount">
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="payment_mode">Mode <span class="req">*</span></label>
                            <select class="form-select form-select-sm" id="payment_mode" name="payment_mode" required>
                                <?php foreach (['DBT', 'Cheque', 'Cash', 'In-kind', 'Service', 'Insurance Cover', 'Other'] as $mode): ?>
                                    <option value="<?= e($mode) ?>"><?= e($mode) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="instalment_no">Instalment</label>
                            <input type="number" min="1" max="60" class="form-control form-control-sm"
                                   id="instalment_no" name="instalment_no">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="reference_no">Reference / Transaction No.</label>
                            <input type="text" class="form-control form-control-sm" id="reference_no"
                                   name="reference_no" maxlength="80">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-accent w-100 mt-3">
                        <i class="bi bi-plus-lg me-1"></i>Record Benefit
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-person"></i>Beneficiary</h3></div>
        <div class="jk-card__body">
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="jk-avatar"><?= e(initials($application['beneficiary_name'])) ?></span>
                <div class="min-width-0">
                    <a href="<?= e(url('/beneficiaries/' . (int)$application['beneficiary_id'])) ?>" class="fw-semibold">
                        <?= e($application['beneficiary_name']) ?>
                    </a>
                    <div class="text-muted tiny"><?= e($application['beneficiary_code']) ?></div>
                </div>
            </div>
            <div class="jk-dl">
                <div class="jk-dl__row"><div class="jk-dl__key">Gender / Age</div>
                    <div class="jk-dl__val"><?= e($application['gender']) ?><?= $application['age'] !== null ? ', ' . (int)$application['age'] : '' ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Mobile</div>
                    <div class="jk-dl__val"><?= e($application['beneficiary_mobile'] ?: '-') ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Aadhaar</div>
                    <div class="jk-dl__val jk-masked"><?= mask_aadhaar($application['aadhaar_last4']) ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">District</div>
                    <div class="jk-dl__val"><?= e($application['district_name'] ?? '-') ?></div></div>
            </div>
        </div>
    </div>

    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-journal-text"></i>Scheme</h3></div>
        <div class="jk-card__body">
            <a href="<?= e(url('/schemes/' . (int)$application['scheme_id'])) ?>" class="fw-semibold d-block mb-1">
                <?= e($application['scheme_name']) ?>
            </a>
            <div class="d-flex flex-wrap gap-1 mb-2">
                <span class="jk-chip"><?= e($application['scheme_level']) ?></span>
                <?php if (!empty($application['theme_name'])): ?>
                    <?= badge_color($application['theme_name'], $application['theme_color'] ?: '#6c757d') ?>
                <?php endif; ?>
            </div>
            <?php if (!empty($application['benefit_description'])): ?>
                <p class="small text-muted mb-2"><?= e(str_limit($application['benefit_description'], 170)) ?></p>
            <?php endif; ?>
            <?php if (!empty($application['portal_url'])): ?>
                <div class="small mb-1"><?= link_external($application['portal_url'], 'Official portal') ?></div>
            <?php endif; ?>
            <?php if (!empty($application['helpline_number'])): ?>
                <div class="small">
                    <i class="bi bi-telephone me-1"></i>
                    <a href="tel:<?= e(preg_replace('/\s+/', '', $application['helpline_number'])) ?>">
                        <?= e($application['helpline_number']) ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
(function () {
  const statusSelect = document.getElementById('status_id');
  if (!statusSelect) return;

  const rejectionWrap = document.getElementById('rejectionWrap');
  const numberWrap    = document.getElementById('applicationNumberWrap');
  const reason        = document.getElementById('rejection_reason');

  function sync() {
    const code = statusSelect.options[statusSelect.selectedIndex].dataset.code;
    const isRejected = code === 'REJECTED';
    const needsNumber = ['SUBMITTED', 'UNDER_VERIFICATION', 'APPROVED'].indexOf(code) >= 0;

    rejectionWrap.style.display = isRejected ? 'block' : 'none';
    reason.required = isRejected;
    numberWrap.style.display = needsNumber ? 'block' : 'none';
  }

  statusSelect.addEventListener('change', sync);
  sync();
})();
</script>
<?php \JanKavach\Core\View::endSection(); ?>
