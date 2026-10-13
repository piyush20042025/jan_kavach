<?php
/**
 * Beneficiary registration / edit (specification sections 17, 18).
 *
 * @var array|null $beneficiary
 * @var array $states, $districts, $blocks, $gps, $villages, $fieldUsers,
 *            $old, $errors
 * @var array $questionSections  assessment questions grouped by section
 * @var array $answers           answers already recorded, keyed by question
 */
$isEdit = !empty($beneficiary);
$action = $isEdit ? url('/beneficiaries/' . (int)$beneficiary['id']) : url('/beneficiaries');
$old    = $old ?? [];
$errors = $errors ?? [];

$value = function (string $field, $default = '') use ($beneficiary, $old) {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }
    return $beneficiary[$field] ?? $default;
};
// After a failed validation the questionnaire repopulates from the submitted
// answers rather than from what was last stored.
$questionSections = $questionSections ?? [];
$answers = array_merge($answers ?? [], is_array($old['answers'] ?? null) ? $old['answers'] : []);

$invalid  = fn(string $field) => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = fn(string $field) => isset($errors[$field])
    ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';

require VIEW_PATH . '/partials/page-head.php';
?>

<form method="post" action="<?= e($action) ?>" data-guard id="beneficiaryForm" novalidate>
<?= csrf_field() ?>
<?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$beneficiary['id'] ?>"><?php endif; ?>

<div class="row g-3">
<div class="col-lg-8">

    <!-- ===== Beneficiary details (section 17) ===== -->
    <fieldset class="jk-fieldset">
        <legend>Beneficiary Details</legend>
        <div class="row g-3">
            <?php if ($isEdit): ?>
                <div class="col-md-4">
                    <label class="form-label">Beneficiary ID</label>
                    <input type="text" class="form-control" value="<?= e($beneficiary['beneficiary_code']) ?>" readonly>
                    <div class="form-text">Generated automatically; it cannot be changed.</div>
                </div>
            <?php endif; ?>

            <div class="col-md-4">
                <label class="form-label" for="registration_date">Registration Date <span class="req">*</span></label>
                <input type="date" class="form-control<?= $invalid('registration_date') ?>" id="registration_date"
                       name="registration_date" required max="<?= date('Y-m-d') ?>"
                       value="<?= e($value('registration_date', date('Y-m-d'))) ?>">
                <?= $feedback('registration_date') ?>
            </div>

            <div class="col-md-<?= $isEdit ? 4 : 8 ?>">
                <label class="form-label" for="name">Beneficiary Name <span class="req">*</span></label>
                <input type="text" class="form-control<?= $invalid('name') ?>" id="name" name="name"
                       required maxlength="150" value="<?= e($value('name')) ?>">
                <div id="duplicateWarning" class="mt-2"></div>
                <?= $feedback('name') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="father_name">Father's Name</label>
                <input type="text" class="form-control" id="father_name" name="father_name"
                       maxlength="150" value="<?= e($value('father_name')) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="mother_name">Mother's Name</label>
                <input type="text" class="form-control" id="mother_name" name="mother_name"
                       maxlength="150" value="<?= e($value('mother_name')) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="spouse_name">Spouse Name</label>
                <input type="text" class="form-control" id="spouse_name" name="spouse_name"
                       maxlength="150" value="<?= e($value('spouse_name')) ?>">
                <div class="form-text">Recorded when the beneficiary is married.</div>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="relation_type">Care Of (Relation)</label>
                <select class="form-select" id="relation_type" name="relation_type">
                    <?php foreach (['Father', 'Husband', 'Mother', 'Guardian', 'Other'] as $relation): ?>
                        <option value="<?= e($relation) ?>" <?= $value('relation_type', 'Father') === $relation ? 'selected' : '' ?>>
                            <?= e($relation) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="relation_name">Care Of (Name)</label>
                <input type="text" class="form-control" id="relation_name" name="relation_name"
                       maxlength="150" value="<?= e($value('relation_name')) ?>">
                <div class="form-text">The name that appears on records as "c/o".</div>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="gender">Gender <span class="req">*</span></label>
                <select class="form-select<?= $invalid('gender') ?>" id="gender" name="gender" required>
                    <option value="">Select</option>
                    <?php foreach (['Female', 'Male', 'Other'] as $gender): ?>
                        <option value="<?= e($gender) ?>" <?= $value('gender') === $gender ? 'selected' : '' ?>>
                            <?= e($gender) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $feedback('gender') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="dob">Date of Birth</label>
                <input type="date" class="form-control<?= $invalid('dob') ?>" id="dob" name="dob"
                       max="<?= date('Y-m-d') ?>" value="<?= e($value('dob')) ?>">
                <div class="form-text">Age is calculated from this when supplied.</div>
                <?= $feedback('dob') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="age">Age <span class="req">*</span></label>
                <input type="number" class="form-control<?= $invalid('age') ?>" id="age" name="age"
                       min="0" max="120" value="<?= e($value('age')) ?>">
                <div class="form-text">Enter the age when the date of birth is not known.</div>
                <?= $feedback('age') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="mobile">Mobile Number</label>
                <input type="tel" class="form-control<?= $invalid('mobile') ?>" id="mobile" name="mobile"
                       maxlength="15" inputmode="numeric" value="<?= e($value('mobile')) ?>"
                       placeholder="10-digit number">
                <?= $feedback('mobile') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="alt_mobile">Alternate Mobile</label>
                <input type="tel" class="form-control<?= $invalid('alt_mobile') ?>" id="alt_mobile" name="alt_mobile"
                       maxlength="15" inputmode="numeric" value="<?= e($value('alt_mobile')) ?>">
                <?= $feedback('alt_mobile') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="aadhaar_number">Aadhaar Number</label>
                <input type="text" class="form-control<?= $invalid('aadhaar_number') ?>" id="aadhaar_number"
                       name="aadhaar_number" maxlength="14" inputmode="numeric" autocomplete="off"
                       placeholder="<?= $isEdit && !empty($beneficiary['aadhaar_last4'])
                            ? 'On file: XXXX XXXX ' . e($beneficiary['aadhaar_last4']) : '12 digits' ?>">
                <div class="form-text">
                    <i class="bi bi-shield-lock me-1"></i>Only the last four digits are stored,
                    plus a one-way hash used to spot duplicate registrations.
                    <?= $isEdit ? ' Leave blank to keep what is already held.' : '' ?>
                </div>
                <?= $feedback('aadhaar_number') ?>
            </div>
        </div>
    </fieldset>

    <!-- ===== Address (section 25) ===== -->
    <fieldset class="jk-fieldset">
        <legend>Address</legend>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="state_id">State <span class="req">*</span></label>
                <select class="form-select<?= $invalid('state_id') ?>" id="state_id" name="state_id" required>
                    <option value="">Select State</option>
                    <?php foreach ($states as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)$value('state_id') === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $feedback('state_id') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="district_id">District <span class="req">*</span></label>
                <select class="form-select<?= $invalid('district_id') ?>" id="district_id" name="district_id" required
                        data-depends-on="state_id" data-server-filled="1"
                        data-endpoint="/geo/districts/{id}" data-placeholder="Select District">
                    <option value="">Select District</option>
                    <?php foreach ($districts as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)$value('district_id') === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $feedback('district_id') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="block_id">Block</label>
                <select class="form-select<?= $invalid('block_id') ?>" id="block_id" name="block_id"
                        data-depends-on="district_id" data-server-filled="1"
                        data-endpoint="/geo/blocks/{id}" data-placeholder="Select Block">
                    <option value="">Select Block</option>
                    <?php foreach ($blocks as $block): ?>
                        <option value="<?= (int)$block['id'] ?>" <?= (int)$value('block_id') === (int)$block['id'] ? 'selected' : '' ?>>
                            <?= e($block['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $feedback('block_id') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="gram_panchayat_id">Gram Panchayat</label>
                <select class="form-select" id="gram_panchayat_id" name="gram_panchayat_id"
                        data-depends-on="block_id" data-server-filled="1"
                        data-endpoint="/geo/gram-panchayats/{id}" data-placeholder="Select Gram Panchayat">
                    <option value="">Select Gram Panchayat</option>
                    <?php foreach ($gps as $gp): ?>
                        <option value="<?= (int)$gp['id'] ?>" <?= (int)$value('gram_panchayat_id') === (int)$gp['id'] ? 'selected' : '' ?>>
                            <?= e($gp['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="village_id">Village</label>
                <select class="form-select" id="village_id" name="village_id"
                        data-depends-on="gram_panchayat_id" data-server-filled="1"
                        data-endpoint="/geo/villages/{id}" data-placeholder="Select Village">
                    <option value="">Select Village</option>
                    <?php foreach ($villages as $village): ?>
                        <option value="<?= (int)$village['id'] ?>" <?= (int)$value('village_id') === (int)$village['id'] ? 'selected' : '' ?>>
                            <?= e($village['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="village_name">Village (if not listed)</label>
                <input type="text" class="form-control" id="village_name" name="village_name"
                       maxlength="150" value="<?= e($value('village_name')) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="post_office">Post Office</label>
                <input type="text" class="form-control" id="post_office" name="post_office"
                       maxlength="120" value="<?= e($value('post_office')) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label" for="address_line">Address</label>
                <input type="text" class="form-control" id="address_line" name="address_line"
                       maxlength="255" value="<?= e($value('address_line')) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label" for="pincode">PIN Code</label>
                <input type="text" class="form-control<?= $invalid('pincode') ?>" id="pincode" name="pincode"
                       maxlength="6" inputmode="numeric" value="<?= e($value('pincode')) ?>">
                <?= $feedback('pincode') ?>
            </div>

            <div class="col-md-3">
                <label class="form-label" for="area_type">Rural / Urban <span class="req">*</span></label>
                <select class="form-select" id="area_type" name="area_type" required>
                    <option value="Rural" <?= $value('area_type', 'Rural') === 'Rural' ? 'selected' : '' ?>>Rural</option>
                    <option value="Urban" <?= $value('area_type') === 'Urban' ? 'selected' : '' ?>>Urban</option>
                </select>
            </div>
        </div>
    </fieldset>

    <!-- ===== Beneficiary Profile Questionnaire =====
         Rendered from assessment_questions, so a question added under
         Settings appears here with no change to this view. -->
    <?php foreach ($questionSections as $section => $questions): ?>
        <fieldset class="jk-fieldset">
            <legend>
                <?= e($section) ?>
                <span class="badge bg-secondary ms-1"><?= count($questions) ?></span>
            </legend>
            <div class="row g-3">
                <?php foreach ($questions as $question): ?>
                    <?php
                    $current   = $answers[$question['question_key']] ?? '';
                    $fieldBase = 'answers';
                    $colWidth  = null;
                    include VIEW_PATH . '/partials/question-field.php';
                    ?>
                <?php endforeach; ?>
            </div>
        </fieldset>
    <?php endforeach; ?>

    <?php if (empty($questionSections)): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            No questionnaire is configured, so only the details above can be recorded.
            <?php if (can('settings.manage')): ?>
                <a href="<?= e(url('/settings/assessment-questions')) ?>">Configure questions</a>.
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ===== Side column ===== -->
<div class="col-lg-4">
    <div class="jk-card" style="position:sticky;top:76px">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-save"></i>Save</h3></div>
        <div class="jk-card__body">
            <div class="mb-3">
                <label class="form-label" for="field_user_id">Collected By</label>
                <select class="form-select" id="field_user_id" name="field_user_id">
                    <option value="">Me (<?= e($authUser['full_name'] ?? '') ?>)</option>
                    <?php foreach ($fieldUsers as $user): ?>
                        <option value="<?= (int)$user['id'] ?>" <?= (int)$value('field_user_id') === (int)$user['id'] ? 'selected' : '' ?>>
                            <?= e($user['full_name']) ?>
                            <?= !empty($user['district_name']) ? ' - ' . e($user['district_name']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="remarks">Remarks</label>
                <textarea class="form-control" id="remarks" name="remarks" rows="2"><?= e($value('remarks')) ?></textarea>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="consent_given" name="consent_given" value="1"
                       <?= (int)$value('consent_given', 0) === 1 ? 'checked' : '' ?>>
                <label class="form-check-label" for="consent_given">
                    Consent obtained to record and process this information
                </label>
            </div>

            <?php if (!$isEdit): ?>
                <div class="form-check mb-3" id="confirmDuplicateWrap" style="display:none">
                    <input class="form-check-input" type="checkbox" id="confirm_duplicate" name="confirm_duplicate" value="1">
                    <label class="form-check-label text-danger fw-semibold" for="confirm_duplicate">
                        Register anyway &mdash; this is a different person
                    </label>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Changes' : 'Register Beneficiary' ?>
            </button>
            <a href="<?= e($isEdit ? url('/beneficiaries/' . (int)$beneficiary['id']) : url('/beneficiaries')) ?>"
               class="btn btn-outline-secondary w-100 mt-2">Cancel</a>

            <?php if (!$isEdit): ?>
                <p class="text-muted small mt-3 mb-0">
                    The questionnaire below is saved with the registration. Answer what is known now
                    &mdash; anything left blank can be completed later on the Assessment screen, and the
                    matching engine reads whatever has been recorded.
                </p>
            <?php endif; ?>
        </div>
    </div>

    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-shield-lock"></i>Data Protection</h3></div>
        <div class="jk-card__body small text-muted">
            <ul class="mb-0 ps-3">
                <li>Aadhaar is stored as the last four digits plus a one-way hash. The full number is never kept.</li>
                <li>Mobile numbers are masked in listings and exports.</li>
                <li>Every change to this record is written to the audit trail.</li>
                <li>Only users assigned to this District or State can open the record.</li>
            </ul>
        </div>
    </div>
</div>
</div>
</form>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
(function () {
  const dob = document.getElementById('dob');
  const age = document.getElementById('age');

  // Date of birth wins when both are supplied.
  dob.addEventListener('change', function () {
    if (!dob.value) return;
    const birth = new Date(dob.value), today = new Date();
    let years = today.getFullYear() - birth.getFullYear();
    const m = today.getMonth() - birth.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) years--;
    if (years >= 0 && years <= 120) { age.value = years; age.readOnly = true; }
  });
  if (dob.value) age.readOnly = true;

  // Duplicate registration check (section 38 applied to beneficiaries).
  const nameInput = document.getElementById('name');
  const district  = document.getElementById('district_id');
  const warning   = document.getElementById('duplicateWarning');
  const confirmWrap = document.getElementById('confirmDuplicateWrap');

  const check = JK.debounce(function () {
    if (nameInput.value.trim().length < 4 || !district.value) { warning.innerHTML = ''; return; }

    JK.post('/beneficiaries/check-duplicate', {
      name: nameInput.value,
      mobile: document.getElementById('mobile').value,
      dob: dob.value,
      district_id: district.value,
      aadhaar_number: document.getElementById('aadhaar_number').value,
      id: <?= $isEdit ? (int)$beneficiary['id'] : 0 ?>
    }).then(function (response) {
      const items = (response.data && response.data.duplicates) || [];
      if (items.length === 0) { warning.innerHTML = ''; if (confirmWrap) confirmWrap.style.display = 'none'; return; }

      let html = '<div class="alert alert-warning py-2 px-2 mb-0 small">' +
                 '<i class="bi bi-exclamation-triangle me-1"></i><strong>Possible duplicate registration:</strong>' +
                 '<ul class="mb-0 mt-1 ps-3">';
      items.forEach(function (item) {
        html += '<li><a href="' + item.url + '" target="_blank">' + JK.escape(item.code) + ' - ' +
                JK.escape(item.name) + '</a> (' + JK.escape(item.gender || '') + ', ' +
                JK.escape(String(item.age || '-')) + ', ' + JK.escape(item.village || '') + ')</li>';
      });
      warning.innerHTML = html + '</ul></div>';
      if (confirmWrap) confirmWrap.style.display = 'block';
    }).catch(function () { warning.innerHTML = ''; });
  }, 800);

  ['input', 'change'].forEach(function (event) {
    nameInput.addEventListener(event, check);
    district.addEventListener(event, check);
    document.getElementById('mobile').addEventListener(event, check);
  });
})();
</script>
<?php \JanKavach\Core\View::endSection(); ?>
