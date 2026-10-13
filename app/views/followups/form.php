<?php
/**
 * Record / edit a follow-up (specification section 22).
 *
 * @var array|null $followup, $application, $beneficiary
 * @var array $applications, $types, $outcomes, $statuses, $users, $old, $errors
 * @var string|null $defaultNextDate
 */
$isEdit = !empty($followup);
$action = $isEdit ? url('/followups/' . (int)$followup['id']) : url('/followups');
$old    = $old ?? [];
$errors = $errors ?? [];

$value = function (string $field, $default = '') use ($followup, $old) {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }
    return $followup[$field] ?? $default;
};
$invalid  = fn(string $field) => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = fn(string $field) => isset($errors[$field])
    ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';

$beneficiaryId = (int)($beneficiary['id'] ?? $followup['beneficiary_id'] ?? 0);

require VIEW_PATH . '/partials/page-head.php';
?>

<form method="post" action="<?= e($action) ?>" data-guard novalidate>
<?= csrf_field() ?>

<div class="row g-3">
<div class="col-lg-8">
    <fieldset class="jk-fieldset">
        <legend>Follow-up</legend>
        <div class="row g-3">

            <div class="col-12">
                <label class="form-label">Beneficiary <span class="req">*</span></label>
                <?php if ($beneficiary): ?>
                    <div class="d-flex align-items-center gap-2 border rounded p-2 bg-light">
                        <span class="jk-avatar jk-avatar--sm"><?= e(initials($beneficiary['name'])) ?></span>
                        <div class="flex-grow-1">
                            <div class="fw-semibold"><?= e($beneficiary['name']) ?></div>
                            <div class="text-muted tiny">
                                <?= e($beneficiary['beneficiary_code']) ?>
                                <?php if (!empty($beneficiary['mobile'])): ?>
                                    &middot; <?= e($beneficiary['mobile']) ?>
                                <?php endif; ?>
                                <?php if (!empty($beneficiary['district_name'])): ?>
                                    &middot; <?= e($beneficiary['district_name']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($beneficiary['mobile'])): ?>
                            <a href="tel:<?= e($beneficiary['mobile']) ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-telephone"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <input type="hidden" name="beneficiary_id" value="<?= $beneficiaryId ?>">
                <?php else: ?>
                    <input type="number" class="form-control<?= $invalid('beneficiary_id') ?>"
                           name="beneficiary_id" required value="<?= e($value('beneficiary_id')) ?>"
                           placeholder="Beneficiary record number">
                    <div class="form-text">
                        Follow-ups are normally recorded from an application or a beneficiary's profile,
                        which fills this in automatically.
                    </div>
                    <?= $feedback('beneficiary_id') ?>
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="application_id">Application</label>
                <select class="form-select" id="application_id" name="application_id">
                    <option value="">Not linked to a specific application</option>
                    <?php foreach ($applications as $app): ?>
                        <option value="<?= (int)$app['id'] ?>"
                                <?= (int)$value('application_id', $application['id'] ?? 0) === (int)$app['id'] ? 'selected' : '' ?>>
                            <?= e($app['application_code']) ?> &ndash;
                            <?= e(str_limit($app['scheme_short_name'] ?: $app['scheme_name'], 34)) ?>
                            (<?= e($app['status_name']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Linking it keeps the application's status and next follow-up date in step.</div>
            </div>

            <div class="col-md-3">
                <label class="form-label" for="followup_date">Follow-up Date <span class="req">*</span></label>
                <input type="date" class="form-control<?= $invalid('followup_date') ?>" id="followup_date"
                       name="followup_date" required max="<?= date('Y-m-d') ?>"
                       value="<?= e($value('followup_date', date('Y-m-d'))) ?>">
                <?= $feedback('followup_date') ?>
            </div>

            <div class="col-md-3">
                <label class="form-label" for="followup_type">Type <span class="req">*</span></label>
                <select class="form-select<?= $invalid('followup_type') ?>" id="followup_type" name="followup_type" required>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= e($type) ?>" <?= $value('followup_type', 'Phone Call') === $type ? 'selected' : '' ?>>
                            <?= e($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $feedback('followup_type') ?>
            </div>

            <div class="col-12">
                <label class="form-label" for="discussion">Discussion</label>
                <textarea class="form-control" id="discussion" name="discussion" rows="4" maxlength="5000"
                          placeholder="What was discussed, what the beneficiary said, and what was agreed."><?= e($value('discussion')) ?></textarea>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="outcome">Outcome <span class="req">*</span></label>
                <select class="form-select<?= $invalid('outcome') ?>" id="outcome" name="outcome" required>
                    <?php foreach ($outcomes as $outcome): ?>
                        <option value="<?= e($outcome) ?>" <?= $value('outcome', 'Pending') === $outcome ? 'selected' : '' ?>>
                            <?= e($outcome) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $feedback('outcome') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="status_id">Application Status After This</label>
                <select class="form-select" id="status_id" name="status_id">
                    <option value="">Leave unchanged</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= (int)$status['id'] ?>" <?= (int)$value('status_id') === (int)$status['id'] ? 'selected' : '' ?>>
                            <?= e($status['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="next_followup_date">Next Follow-up Date</label>
                <input type="date" class="form-control<?= $invalid('next_followup_date') ?>" id="next_followup_date"
                       name="next_followup_date" data-date-min-from="followup_date"
                       value="<?= e($value('next_followup_date', $defaultNextDate ?? '')) ?>">
                <?= $feedback('next_followup_date') ?>
            </div>
        </div>
    </fieldset>
</div>

<div class="col-lg-4">
    <div class="jk-card" style="position:sticky;top:76px">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-save"></i>Save</h3></div>
        <div class="jk-card__body">
            <div class="mb-3">
                <label class="form-label" for="responsible_user_id">Responsible</label>
                <select class="form-select" id="responsible_user_id" name="responsible_user_id">
                    <option value="">Me (<?= e($authUser['full_name'] ?? '') ?>)</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?= (int)$user['id'] ?>" <?= (int)$value('responsible_user_id') === (int)$user['id'] ? 'selected' : '' ?>>
                            <?= e($user['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Changes' : 'Record Follow-up' ?>
            </button>
            <a href="<?= e($beneficiaryId ? url('/beneficiaries/' . $beneficiaryId) : url('/followups')) ?>"
               class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
        </div>
    </div>

    <?php if ($application): ?>
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-file-earmark-text"></i>Application</h3></div>
            <div class="jk-card__body">
                <div class="jk-dl">
                    <div class="jk-dl__row"><div class="jk-dl__key">Code</div>
                        <div class="jk-dl__val"><a href="<?= e(url('/applications/' . (int)$application['id'])) ?>">
                            <?= e($application['application_code']) ?></a></div></div>
                    <div class="jk-dl__row"><div class="jk-dl__key">Scheme</div>
                        <div class="jk-dl__val"><?= e($application['scheme_short_name'] ?: $application['scheme_name']) ?></div></div>
                    <div class="jk-dl__row"><div class="jk-dl__key">Status</div>
                        <div class="jk-dl__val"><span class="badge" style="background:<?= e($application['status_color'] ?: '#6c757d') ?>">
                            <?= e($application['status_name']) ?></span></div></div>
                    <div class="jk-dl__row"><div class="jk-dl__key">Submitted</div>
                        <div class="jk-dl__val"><?= fmt_date($application['submission_date']) ?></div></div>
                    <div class="jk-dl__row"><div class="jk-dl__key">Department</div>
                        <div class="jk-dl__val"><?= e($application['department'] ?: '-') ?></div></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>
</form>
