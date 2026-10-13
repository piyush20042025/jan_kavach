<?php
/**
 * Add / edit scheme (specification sections 6-14).
 *
 * @var array|null $scheme
 * @var array $options, $allStates, $selectedThemes, $selectedTypes,
 *            $selectedTargets, $selectedStates, $old, $errors
 */
$isEdit  = !empty($scheme);
$action  = $isEdit ? url('/schemes/' . (int)$scheme['id']) : url('/schemes');
$old     = $old ?? [];
$errors  = $errors ?? [];

/** Current value: old input first (after a failed save), then the record. */
$value = function (string $field, $default = '') use ($scheme, $old) {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }
    return $scheme[$field] ?? $default;
};
$invalid = fn(string $field) => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = function (string $field) use ($errors) {
    return isset($errors[$field])
        ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';
};

$selectedThemes  = array_map('intval', $old['theme_ids']            ?? $selectedThemes);
$selectedTypes   = array_map('intval', $old['beneficiary_type_ids'] ?? $selectedTypes);
$selectedTargets = array_map('intval', $old['target_group_ids']     ?? $selectedTargets);
$selectedStates  = array_map('intval', $old['state_ids']            ?? $selectedStates);

require VIEW_PATH . '/partials/page-head.php';
?>

<form method="post" action="<?= e($action) ?>" data-guard id="schemeForm" novalidate>
<?= csrf_field() ?>
<?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$scheme['id'] ?>"><?php endif; ?>

<div class="row g-3">
<div class="col-lg-8">

    <!-- ========== Basic information (section 6) ========== -->
    <fieldset class="jk-fieldset">
        <legend>Basic Scheme Information</legend>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="scheme_code">Scheme Code</label>
                <input type="text" class="form-control<?= $invalid('scheme_code') ?>" id="scheme_code"
                       name="scheme_code" maxlength="30"
                       value="<?= e($value('scheme_code', $suggestedCode ?? '')) ?>"
                       <?= $isEdit ? 'readonly' : '' ?>>
                <div class="form-text"><?= $isEdit ? 'The code cannot be changed.' : 'Leave as suggested unless your programme uses its own codes.' ?></div>
                <?= $feedback('scheme_code') ?>
            </div>

            <div class="col-md-8">
                <label class="form-label" for="name">Scheme Name <span class="req">*</span></label>
                <input type="text" class="form-control<?= $invalid('name') ?>" id="name" name="name"
                       maxlength="300" required value="<?= e($value('name')) ?>"
                       placeholder="e.g. Pradhan Mantri Matru Vandana Yojana (PMMVY)">
                <div id="duplicateWarning" class="mt-2"></div>
                <?= $feedback('name') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="short_name">Short Name / Acronym</label>
                <input type="text" class="form-control" id="short_name" name="short_name"
                       maxlength="80" value="<?= e($value('short_name')) ?>" placeholder="e.g. PMMVY">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="scheme_level">Scheme Type <span class="req">*</span></label>
                <select class="form-select<?= $invalid('scheme_level') ?>" id="scheme_level" name="scheme_level" required>
                    <?php foreach (['National' => 'National', 'State' => 'State Specific',
                                    'National+State' => 'National + State'] as $key => $label): ?>
                        <option value="<?= e($key) ?>" <?= $value('scheme_level', 'National') === $key ? 'selected' : '' ?>>
                            <?= e($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $feedback('scheme_level') ?>
            </div>

            <div class="col-md-4" id="stateField">
                <label class="form-label" for="state_id">State <span class="req">*</span></label>
                <select class="form-select<?= $invalid('state_id') ?>" id="state_id" name="state_id">
                    <option value="">Select State</option>
                    <?php foreach ($allStates as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)$value('state_id') === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Required for a State-specific scheme.</div>
                <?= $feedback('state_id') ?>
            </div>

            <div class="col-12">
                <label class="form-label" for="description">Scheme Description</label>
                <textarea class="form-control" id="description" name="description" rows="3"
                          placeholder="A short plain-language summary of what the scheme does."><?= e($value('description')) ?></textarea>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="ministry_id">Implementing Ministry</label>
                <select class="form-select" id="ministry_id" name="ministry_id">
                    <option value="">Not specified</option>
                    <?php foreach ($options['agencies'] as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)$value('ministry_id') === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" class="form-control mt-2" name="ministry_name" maxlength="200"
                       value="<?= e($value('ministry_name')) ?>"
                       placeholder="Or type a ministry not in the list">
            </div>

            <div class="col-md-6">
                <label class="form-label" for="department_id">Implementing Department</label>
                <select class="form-select" id="department_id" name="department_id">
                    <option value="">Not specified</option>
                    <?php foreach ($options['agencies'] as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)$value('department_id') === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" class="form-control mt-2" name="department_name" maxlength="200"
                       value="<?= e($value('department_name')) ?>"
                       placeholder="Or type a department not in the list">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="priority_id">Priority Ranking</label>
                <select class="form-select" id="priority_id" name="priority_id">
                    <option value="">Not ranked</option>
                    <?php foreach ($options['priorities'] as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)$value('priority_id') === (int)$id ? 'selected' : '' ?>>
                            <?= e(str_limit($name, 50)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="feasibility_id">Feasibility Ranking</label>
                <select class="form-select" id="feasibility_id" name="feasibility_id">
                    <option value="">Not ranked</option>
                    <?php foreach ($options['feasibility'] as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)$value('feasibility_id') === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">1 = difficult &hellip; 5 = easy.</div>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="launch_year">Launch Year</label>
                <input type="number" class="form-control" id="launch_year" name="launch_year"
                       min="1900" max="2100" value="<?= e($value('launch_year')) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="scheme_status">Scheme Status <span class="req">*</span></label>
                <select class="form-select" id="scheme_status" name="scheme_status" required>
                    <?php foreach (['Active', 'Dormant', 'Discontinued'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= $value('scheme_status', 'Active') === $status ? 'selected' : '' ?>>
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="active_enrollment" name="active_enrollment"
                           value="1" <?= (int)$value('active_enrollment', 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="active_enrollment">Active enrolment</label>
                </div>
            </div>

            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active"
                           value="1" <?= (int)$value('is_active', 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Record active (visible in listings)</label>
                </div>
            </div>
        </div>
    </fieldset>

    <!-- ========== Classification (sections 7, 8) ========== -->
    <fieldset class="jk-fieldset">
        <legend>Classification</legend>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="theme_ids">Themes</label>
                <select class="form-select" id="theme_ids" name="theme_ids[]" multiple size="7">
                    <?php foreach ($options['themes'] as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= in_array((int)$id, $selectedThemes, true) ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">The first selected theme becomes the primary one. Hold Ctrl / Cmd to select several.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="beneficiary_type_ids">Beneficiary Types</label>
                <select class="form-select" id="beneficiary_type_ids" name="beneficiary_type_ids[]" multiple size="7">
                    <?php foreach ($options['beneficiary_types'] as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= in_array((int)$id, $selectedTypes, true) ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="target_group_ids">Target Groups</label>
                <select class="form-select" id="target_group_ids" name="target_group_ids[]" multiple size="6">
                    <?php foreach ($options['target_groups'] as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= in_array((int)$id, $selectedTargets, true) ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6" id="statesField">
                <label class="form-label" for="state_ids">Applicable States</label>
                <select class="form-select" id="state_ids" name="state_ids[]" multiple size="6">
                    <?php foreach ($allStates as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= in_array((int)$id, $selectedStates, true) ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">For a scheme that runs in several States, or a national scheme with State variants.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="service_approach_id">Service Approach</label>
                <select class="form-select" id="service_approach_id" name="service_approach_id">
                    <option value="">Not specified</option>
                    <?php foreach ($options['service_approaches'] as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)$value('service_approach_id') === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="keywords">Search Keywords</label>
                <input type="text" class="form-control" id="keywords" name="keywords" maxlength="500"
                       value="<?= e($value('keywords')) ?>"
                       placeholder="Extra terms field staff might search for">
            </div>

            <div class="col-12">
                <label class="form-label" for="target_group_summary">Target Group (free text)</label>
                <textarea class="form-control" id="target_group_summary" name="target_group_summary" rows="2"
                          placeholder="e.g. 18-59 years old, pregnant and lactating women."><?= e($value('target_group_summary')) ?></textarea>
            </div>
        </div>
    </fieldset>

    <!-- ========== Eligibility (section 9) ========== -->
    <fieldset class="jk-fieldset">
        <legend>Eligibility</legend>
        <label class="form-label" for="eligibility_summary">Eligibility Criteria (as published)</label>
        <textarea class="form-control" id="eligibility_summary" name="eligibility_summary" rows="4"
                  placeholder="The eligibility text exactly as the department publishes it."><?= e($value('eligibility_summary')) ?></textarea>
        <div class="form-text">
            This is the narrative record. The <strong>structured rules</strong> that drive automatic matching are
            configured separately
            <?php if ($isEdit && can('scheme.rules')): ?>
                &mdash; <a href="<?= e(url('/schemes/' . (int)$scheme['id'] . '/rules')) ?>">open the rule builder</a>.
            <?php else: ?>
                &mdash; the rule builder opens once the scheme is saved.
            <?php endif; ?>
        </div>
    </fieldset>

    <!-- ========== Benefits (section 11) ========== -->
    <fieldset class="jk-fieldset">
        <legend>Benefits</legend>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="benefit_type">Type of Benefit</label>
                <input type="text" class="form-control" id="benefit_type" name="benefit_type" maxlength="120"
                       value="<?= e($value('benefit_type')) ?>" placeholder="e.g. Cash Benefit, Insurance Cover">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="benefit_amount">Financial Benefit Amount</label>
                <div class="input-group">
                    <span class="input-group-text"><?= e(config('app.currency_symbol')) ?></span>
                    <input type="number" step="0.01" min="0" class="form-control<?= $invalid('benefit_amount') ?>"
                           id="benefit_amount" name="benefit_amount" value="<?= e($value('benefit_amount')) ?>">
                </div>
                <?= $feedback('benefit_amount') ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="benefit_amount_text">Amount (when it is a range or slab)</label>
                <input type="text" class="form-control" id="benefit_amount_text" name="benefit_amount_text"
                       maxlength="255" value="<?= e($value('benefit_amount_text')) ?>"
                       placeholder="e.g. Up to 5 lakh per family per year">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="benefit_mode">Recurring / One-time</label>
                <select class="form-select" id="benefit_mode" name="benefit_mode">
                    <option value="">Not specified</option>
                    <?php foreach (['One-time', 'Recurring', 'Both', 'In-kind', 'Service'] as $mode): ?>
                        <option value="<?= e($mode) ?>" <?= $value('benefit_mode') === $mode ? 'selected' : '' ?>>
                            <?= e($mode) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="benefit_frequency">Benefit Frequency</label>
                <input type="text" class="form-control" id="benefit_frequency" name="benefit_frequency"
                       maxlength="80" value="<?= e($value('benefit_frequency')) ?>"
                       placeholder="e.g. Monthly, Annual, In instalments">
            </div>
            <div class="col-12">
                <label class="form-label" for="benefit_description">Benefit Description</label>
                <textarea class="form-control" id="benefit_description" name="benefit_description" rows="3"><?= e($value('benefit_description')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="benefit_conditions">Benefit Conditions</label>
                <textarea class="form-control" id="benefit_conditions" name="benefit_conditions" rows="2"
                          placeholder="Conditions a beneficiary must meet to keep receiving the benefit."><?= e($value('benefit_conditions')) ?></textarea>
            </div>
        </div>
    </fieldset>

    <!-- ========== How to apply (section 12) ========== -->
    <fieldset class="jk-fieldset">
        <legend>How to Apply</legend>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="application_mode">Application Mode</label>
                <select class="form-select" id="application_mode" name="application_mode">
                    <?php foreach (['Offline', 'Online', 'Both', 'Not Applicable'] as $mode): ?>
                        <option value="<?= e($mode) ?>" <?= $value('application_mode', 'Offline') === $mode ? 'selected' : '' ?>>
                            <?= e($mode) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label" for="portal_url">Official Portal URL</label>
                <input type="url" class="form-control<?= $invalid('portal_url') ?>" id="portal_url" name="portal_url"
                       maxlength="500" value="<?= e($value('portal_url')) ?>" placeholder="https://">
                <?= $feedback('portal_url') ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="physical_office">Physical Office</label>
                <input type="text" class="form-control" id="physical_office" name="physical_office" maxlength="255"
                       value="<?= e($value('physical_office')) ?>"
                       placeholder="e.g. Block Development Office, Anganwadi Centre">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="helpline_number">Helpline Number</label>
                <input type="text" class="form-control" id="helpline_number" name="helpline_number" maxlength="80"
                       value="<?= e($value('helpline_number')) ?>">
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="csc_available" name="csc_available" value="1"
                           <?= (int)$value('csc_available', 0) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="csc_available">CSC available</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="application_process">Application Process</label>
                <textarea class="form-control" id="application_process" name="application_process" rows="3"
                          placeholder="The steps a beneficiary follows to apply."><?= e($value('application_process')) ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label" for="application_instructions">Application Instructions for Field Teams</label>
                <textarea class="form-control" id="application_instructions" name="application_instructions" rows="2"><?= e($value('application_instructions')) ?></textarea>
            </div>
        </div>
    </fieldset>

    <!-- ========== Source (section 14) ========== -->
    <fieldset class="jk-fieldset">
        <legend>Source &amp; Verification</legend>
        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label" for="source_url">Official Source URL</label>
                <input type="url" class="form-control<?= $invalid('source_url') ?>" id="source_url" name="source_url"
                       maxlength="500" value="<?= e($value('source_url')) ?>" placeholder="https://">
                <?= $feedback('source_url') ?>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="last_verified_date">Last Verified Date</label>
                <input type="date" class="form-control" id="last_verified_date" name="last_verified_date"
                       value="<?= e($value('last_verified_date')) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="verified_by">Verified By</label>
                <input type="text" class="form-control" id="verified_by" name="verified_by" maxlength="150"
                       value="<?= e($value('verified_by', $authUser['full_name'] ?? '')) ?>">
            </div>
        </div>
    </fieldset>
</div>

<!-- ========== Side column ========== -->
<div class="col-lg-4">
    <div class="jk-card" style="position:sticky;top:76px">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-save"></i>Save</h3></div>
        <div class="jk-card__body">
            <?php if (!$isEdit): ?>
                <div class="form-check mb-3" id="confirmDuplicateWrap" style="display:none">
                    <input class="form-check-input" type="checkbox" id="confirm_duplicate" name="confirm_duplicate" value="1">
                    <label class="form-check-label text-danger fw-semibold" for="confirm_duplicate">
                        Save anyway, despite the possible duplicate
                    </label>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Changes' : 'Create Scheme' ?>
            </button>
            <a href="<?= e($isEdit ? url('/schemes/' . (int)$scheme['id']) : url('/schemes')) ?>"
               class="btn btn-outline-secondary w-100 mt-2">Cancel</a>

            <?php if (!$isEdit): ?>
                <p class="text-muted small mt-3 mb-0">
                    After saving you will be taken to the eligibility rule builder, so the scheme can take part
                    in automatic beneficiary matching.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($isEdit): ?>
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-link"></i>Related Setup</h3></div>
            <div class="jk-card__body d-grid gap-2">
                <?php if (can('scheme.rules')): ?>
                    <a href="<?= e(url('/schemes/' . (int)$scheme['id'] . '/rules')) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-sliders me-1"></i>Eligibility Rules
                        <span class="badge bg-secondary ms-1"><?= count($scheme['rules'] ?? []) ?></span>
                    </a>
                <?php endif; ?>
                <a href="<?= e(url('/schemes/' . (int)$scheme['id'] . '/documents')) ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-file-earmark-text me-1"></i>Required Documents
                    <span class="badge bg-secondary ms-1"><?= count($scheme['documents'] ?? []) ?></span>
                </a>
                <a href="<?= e(url('/schemes/' . (int)$scheme['id'])) ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-eye me-1"></i>View Scheme
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>
</form>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
(function () {
  const level      = document.getElementById('scheme_level');
  const stateField = document.getElementById('stateField');
  const stateInput = document.getElementById('state_id');

  // A National scheme has no single owning State.
  function syncStateField() {
    const needsState = level.value === 'State' || level.value === 'National+State';
    stateField.style.opacity = needsState ? '1' : '.5';
    stateInput.required = needsState;
    if (!needsState) stateInput.value = '';
    stateInput.disabled = !needsState;
  }
  level.addEventListener('change', syncStateField);
  syncStateField();

  // A disabled select is not submitted; re-enable it just before sending so
  // the server always receives the field it validates.
  document.getElementById('schemeForm').addEventListener('submit', function () {
    stateInput.disabled = false;
  });

  // Duplicate detection while the name is typed (section 38).
  const nameInput = document.getElementById('name');
  const warning   = document.getElementById('duplicateWarning');
  const confirmWrap = document.getElementById('confirmDuplicateWrap');

  const check = JK.debounce(function () {
    if (nameInput.value.trim().length < 5) { warning.innerHTML = ''; return; }

    JK.post('/schemes/check-duplicate', {
      name: nameInput.value,
      scheme_code: document.getElementById('scheme_code').value,
      scheme_level: level.value,
      state_id: stateInput.value,
      id: <?= $isEdit ? (int)$scheme['id'] : 0 ?>
    }).then(function (response) {
      const items = (response.data && response.data.duplicates) || [];
      if (items.length === 0) {
        warning.innerHTML = '<div class="text-success small"><i class="bi bi-check-circle me-1"></i>No similar scheme found.</div>';
        if (confirmWrap) confirmWrap.style.display = 'none';
        return;
      }
      let html = '<div class="alert alert-warning py-2 px-2 mb-0 small">' +
                 '<i class="bi bi-exclamation-triangle me-1"></i>' +
                 '<strong>' + items.length + ' similar scheme' + (items.length > 1 ? 's' : '') + ' already exist' +
                 (items.length > 1 ? '' : 's') + ':</strong><ul class="mb-0 mt-1 ps-3">';
      items.forEach(function (item) {
        html += '<li><a href="' + item.url + '" target="_blank">' + JK.escape(item.code) + ' - ' +
                JK.escape(item.name) + '</a>' +
                (item.state ? ' <span class="text-muted">(' + JK.escape(item.state) + ')</span>' : '') + '</li>';
      });
      html += '</ul></div>';
      warning.innerHTML = html;
      if (confirmWrap) confirmWrap.style.display = 'block';
    }).catch(function () { warning.innerHTML = ''; });
  }, 700);

  nameInput.addEventListener('input', check);
})();
</script>
<?php \JanKavach\Core\View::endSection(); ?>
