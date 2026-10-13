<?php
/**
 * Initiate / edit an application.
 *
 * @var array|null $application, $beneficiary
 * @var int|null $schemeId
 * @var array $statuses, $schemes, $matches, $assignees, $old, $errors
 */
$isEdit = !empty($application);
$action = $isEdit ? url('/applications/' . (int)$application['id']) : url('/applications');
$old    = $old ?? [];
$errors = $errors ?? [];

$value = function (string $field, $default = '') use ($application, $old) {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }
    return $application[$field] ?? $default;
};
$invalid  = fn(string $field) => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = fn(string $field) => isset($errors[$field])
    ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';

require VIEW_PATH . '/partials/page-head.php';
?>

<form method="post" action="<?= e($action) ?>" data-guard novalidate>
<?= csrf_field() ?>

<div class="row g-3">
<div class="col-lg-8">
    <fieldset class="jk-fieldset">
        <legend>Beneficiary &amp; Scheme</legend>
        <div class="row g-3">

            <?php if ($isEdit || $beneficiary): ?>
                <div class="col-12">
                    <label class="form-label">Beneficiary</label>
                    <div class="d-flex align-items-center gap-2 border rounded p-2 bg-light">
                        <span class="jk-avatar jk-avatar--sm">
                            <?= e(initials($beneficiary['name'] ?? $application['beneficiary_name'] ?? '')) ?>
                        </span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">
                                <?= e($beneficiary['name'] ?? $application['beneficiary_name'] ?? '') ?>
                            </div>
                            <div class="text-muted tiny">
                                <?= e($beneficiary['beneficiary_code'] ?? $application['beneficiary_code'] ?? '') ?>
                                <?php if (!empty($beneficiary['district_name'])): ?>
                                    &middot; <?= e($beneficiary['district_name']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <a href="<?= e(url('/beneficiaries/' . (int)($beneficiary['id'] ?? $application['beneficiary_id']))) ?>"
                           class="btn btn-sm btn-outline-secondary">Open</a>
                    </div>
                    <input type="hidden" name="beneficiary_id"
                           value="<?= (int)($beneficiary['id'] ?? $application['beneficiary_id'] ?? 0) ?>">
                </div>
            <?php else: ?>
                <div class="col-12">
                    <label class="form-label" for="beneficiary_id">Beneficiary <span class="req">*</span></label>
                    <input type="number" class="form-control<?= $invalid('beneficiary_id') ?>" id="beneficiary_id"
                           name="beneficiary_id" required value="<?= e($value('beneficiary_id')) ?>"
                           placeholder="Beneficiary record number">
                    <div class="form-text">
                        Applications are normally started from a beneficiary's
                        <a href="<?= e(url('/matching')) ?>">eligible-schemes list</a>, which fills this in for you.
                    </div>
                    <?= $feedback('beneficiary_id') ?>
                </div>
            <?php endif; ?>

            <div class="col-md-8">
                <label class="form-label" for="scheme_id">Scheme <span class="req">*</span></label>
                <?php if ($isEdit): ?>
                    <input type="text" class="form-control" readonly
                           value="<?= e($application['scheme_name'] ?? '') ?>">
                    <div class="form-text">The scheme cannot be changed once the application exists.</div>
                <?php else: ?>
                    <select class="form-select<?= $invalid('scheme_id') ?>" id="scheme_id" name="scheme_id" required>
                        <option value="">Select a scheme</option>
                        <?php if (!empty($matches)): ?>
                            <optgroup label="Schemes this beneficiary matched">
                                <?php foreach ($matches as $match): ?>
                                    <option value="<?= (int)$match['scheme_id'] ?>"
                                            data-eligibility="<?= e($match['eligibility_status']) ?>"
                                            <?= (int)$schemeId === (int)$match['scheme_id'] ? 'selected' : '' ?>>
                                        <?= e($match['short_name'] ?: str_limit($match['scheme_name'], 50)) ?>
                                        (<?= (int)$match['match_percent'] ?>% &ndash; <?= e($match['eligibility_status']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                        <optgroup label="All schemes">
                            <?php foreach ($schemes as $id => $name): ?>
                                <option value="<?= (int)$id ?>" <?= (int)$schemeId === (int)$id ? 'selected' : '' ?>>
                                    <?= e(str_limit($name, 60)) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                    <?= $feedback('scheme_id') ?>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="eligibility_status">Eligibility Grade</label>
                <select class="form-select" id="eligibility_status" name="eligibility_status">
                    <?php foreach (['Eligible', 'Potentially Eligible', 'Needs Verification', 'Not Eligible'] as $status): ?>
                        <option value="<?= e($status) ?>"
                                <?= $value('eligibility_status', 'Potentially Eligible') === $status ? 'selected' : '' ?>>
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </fieldset>

    <fieldset class="jk-fieldset">
        <legend>Progress</legend>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="status_id">Current Status <span class="req">*</span></label>
                <select class="form-select<?= $invalid('status_id') ?>" id="status_id" name="status_id" required>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= (int)$status['id'] ?>"
                                <?= (int)$value('status_id', $statuses[0]['id'] ?? 0) === (int)$status['id'] ? 'selected' : '' ?>>
                            <?= e($status['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $feedback('status_id') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="referral_date">Referral Date</label>
                <input type="date" class="form-control<?= $invalid('referral_date') ?>" id="referral_date"
                       name="referral_date" value="<?= e($value('referral_date', date('Y-m-d'))) ?>">
                <?= $feedback('referral_date') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="initiated_date">Initiated Date</label>
                <input type="date" class="form-control<?= $invalid('initiated_date') ?>" id="initiated_date"
                       name="initiated_date" value="<?= e($value('initiated_date')) ?>">
                <?= $feedback('initiated_date') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="application_number">Department Application Number</label>
                <input type="text" class="form-control" id="application_number" name="application_number"
                       maxlength="80" value="<?= e($value('application_number')) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="submission_date">Submission Date</label>
                <input type="date" class="form-control<?= $invalid('submission_date') ?>" id="submission_date"
                       name="submission_date" value="<?= e($value('submission_date')) ?>">
                <?= $feedback('submission_date') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="department">Department</label>
                <input type="text" class="form-control" id="department" name="department"
                       maxlength="200" value="<?= e($value('department')) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="verification_date">Verification Date</label>
                <input type="date" class="form-control" id="verification_date" name="verification_date"
                       value="<?= e($value('verification_date')) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="approval_date">Approval Date</label>
                <input type="date" class="form-control<?= $invalid('approval_date') ?>" id="approval_date"
                       name="approval_date" value="<?= e($value('approval_date')) ?>">
                <?= $feedback('approval_date') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="next_followup_date">Next Follow-up Date</label>
                <input type="date" class="form-control" id="next_followup_date" name="next_followup_date"
                       value="<?= e($value('next_followup_date')) ?>">
            </div>

            <div class="col-12">
                <label class="form-label" for="rejection_reason">Rejection Reason</label>
                <textarea class="form-control" id="rejection_reason" name="rejection_reason" rows="2"
                          maxlength="500"><?= e($value('rejection_reason')) ?></textarea>
            </div>
        </div>
    </fieldset>

    <fieldset class="jk-fieldset">
        <legend>Benefit</legend>
        <div class="row g-3">
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="benefit_received" name="benefit_received"
                           value="1" <?= (int)$value('benefit_received', 0) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="benefit_received">Benefit received</label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="benefit_amount">Benefit Amount</label>
                <div class="input-group">
                    <span class="input-group-text"><?= e(config('app.currency_symbol')) ?></span>
                    <input type="number" step="0.01" min="0" class="form-control<?= $invalid('benefit_amount') ?>"
                           id="benefit_amount" name="benefit_amount" value="<?= e($value('benefit_amount')) ?>">
                </div>
                <?= $feedback('benefit_amount') ?>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="benefit_date">Benefit Date</label>
                <input type="date" class="form-control" id="benefit_date" name="benefit_date"
                       value="<?= e($value('benefit_date')) ?>">
            </div>
        </div>
        <p class="text-muted small mb-0 mt-2">
            <i class="bi bi-info-circle me-1"></i>
            For a detailed benefit record with payment mode and reference number, use
            <strong>Record a Benefit</strong> on the application's detail page.
        </p>
    </fieldset>
</div>

<div class="col-lg-4">
    <div class="jk-card" style="position:sticky;top:76px">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-save"></i>Save</h3></div>
        <div class="jk-card__body">
            <div class="mb-3">
                <label class="form-label" for="assigned_to">Assigned To</label>
                <select class="form-select" id="assigned_to" name="assigned_to">
                    <option value="">Unassigned</option>
                    <?php foreach ($assignees as $user): ?>
                        <option value="<?= (int)$user['id'] ?>" <?= (int)$value('assigned_to') === (int)$user['id'] ? 'selected' : '' ?>>
                            <?= e($user['full_name']) ?>
                            <?= !empty($user['district_name']) ? ' - ' . e($user['district_name']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="remarks">Remarks</label>
                <textarea class="form-control" id="remarks" name="remarks" rows="3"><?= e($value('remarks')) ?></textarea>
            </div>

            <?php if ($isEdit): ?>
                <div class="mb-3">
                    <label class="form-label" for="status_remarks">Note for the status change</label>
                    <input type="text" class="form-control" id="status_remarks" name="status_remarks" maxlength="500">
                    <div class="form-text">Recorded in the status history if the status changes.</div>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Changes' : 'Create Application' ?>
            </button>
            <a href="<?= e($isEdit ? url('/applications/' . (int)$application['id']) : url('/applications')) ?>"
               class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
        </div>
    </div>

    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-info-circle"></i>Date Order</h3></div>
        <div class="jk-card__body small text-muted">
            The dates must run in order: referral, initiated, submitted, approved, then benefit.
            Leave any of them blank until the stage is actually reached &mdash; the status change
            fills the matching date in automatically.
        </div>
    </div>
</div>
</div>
</form>
