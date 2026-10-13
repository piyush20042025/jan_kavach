<?php
/**
 * Add / edit user (specification section 16).
 *
 * @var array|null $user
 * @var array $roles, $states, $districts, $blocks, $old, $errors
 * @var string|null $generatedPassword
 */
$isEdit = !empty($user);
$action = $isEdit ? url('/users/' . (int)$user['id']) : url('/users');
$old    = $old ?? [];
$errors = $errors ?? [];
$isSelf = $isEdit && (int)$user['id'] === (int)($authUser['id'] ?? 0);

$value = function (string $field, $default = '') use ($user, $old) {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }
    return $user[$field] ?? $default;
};
$invalid  = fn(string $field) => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = fn(string $field) => isset($errors[$field])
    ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';

// Role metadata drives which geography fields become compulsory.
$roleMeta = [];
foreach ($roles as $role) {
    $roleMeta[(int)$role['id']] = [
        'code'     => $role['code'],
        'state'    => (int)$role['requires_state'] === 1,
        'district' => (int)$role['requires_district'] === 1,
        'scope'    => $role['data_scope'],
    ];
}

require VIEW_PATH . '/partials/page-head.php';
?>

<form method="post" action="<?= e($action) ?>" data-guard novalidate>
<?= csrf_field() ?>

<div class="row g-3">
<div class="col-lg-8">
    <fieldset class="jk-fieldset">
        <legend>User Details</legend>
        <div class="row g-3">
            <?php if ($isEdit): ?>
                <div class="col-md-4">
                    <label class="form-label">User ID</label>
                    <input type="text" class="form-control" value="<?= e($user['user_code']) ?>" readonly>
                </div>
            <?php endif; ?>

            <div class="col-md-<?= $isEdit ? 8 : 12 ?>">
                <label class="form-label" for="full_name">Full Name <span class="req">*</span></label>
                <input type="text" class="form-control<?= $invalid('full_name') ?>" id="full_name" name="full_name"
                       maxlength="150" required value="<?= e($value('full_name')) ?>">
                <?= $feedback('full_name') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="username">Username <span class="req">*</span></label>
                <input type="text" class="form-control<?= $invalid('username') ?>" id="username" name="username"
                       maxlength="60" required autocomplete="off" value="<?= e($value('username')) ?>">
                <div class="form-text">3-60 characters: letters, numbers, dots, dashes or underscores.</div>
                <?= $feedback('username') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="email">Email <span class="req">*</span></label>
                <input type="email" class="form-control<?= $invalid('email') ?>" id="email" name="email"
                       maxlength="150" required value="<?= e($value('email')) ?>">
                <?= $feedback('email') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="mobile">Mobile Number</label>
                <input type="tel" class="form-control<?= $invalid('mobile') ?>" id="mobile" name="mobile"
                       maxlength="15" inputmode="numeric" value="<?= e($value('mobile')) ?>">
                <?= $feedback('mobile') ?>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="designation">Designation</label>
                <input type="text" class="form-control" id="designation" name="designation"
                       maxlength="120" value="<?= e($value('designation')) ?>"
                       placeholder="e.g. District Coordinator">
            </div>

            <div class="col-md-6">
                <label class="form-label" for="organization">Organization</label>
                <input type="text" class="form-control" id="organization" name="organization"
                       maxlength="150" value="<?= e($value('organization')) ?>">
            </div>
        </div>
    </fieldset>

    <fieldset class="jk-fieldset">
        <legend>Role &amp; Area of Work</legend>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label" for="role_id">Role <span class="req">*</span></label>
                <select class="form-select<?= $invalid('role_id') ?>" id="role_id" name="role_id" required
                        <?= $isSelf ? 'disabled' : '' ?>>
                    <option value="">Select a role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int)$role['id'] ?>" <?= (int)$value('role_id') === (int)$role['id'] ? 'selected' : '' ?>>
                            <?= e($role['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text" id="roleHelp"></div>
                <?php if ($isSelf): ?>
                    <div class="form-text text-warning">You cannot change your own role.</div>
                    <input type="hidden" name="role_id" value="<?= (int)$value('role_id') ?>">
                <?php endif; ?>
                <?= $feedback('role_id') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="state_id">State <span class="req" id="stateReq" style="display:none">*</span></label>
                <select class="form-select<?= $invalid('state_id') ?>" id="state_id" name="state_id">
                    <option value="">Not assigned</option>
                    <?php foreach ($states as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)$value('state_id') === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $feedback('state_id') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="district_id">District <span class="req" id="districtReq" style="display:none">*</span></label>
                <select class="form-select<?= $invalid('district_id') ?>" id="district_id" name="district_id"
                        data-depends-on="state_id" data-server-filled="1"
                        data-endpoint="/geo/districts/{id}" data-placeholder="Not assigned">
                    <option value="">Not assigned</option>
                    <?php foreach ($districts as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)$value('district_id') === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?= $feedback('district_id') ?>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="block_id">Block (optional)</label>
                <select class="form-select" id="block_id" name="block_id"
                        data-depends-on="district_id" data-server-filled="1"
                        data-endpoint="/geo/blocks/{id}" data-placeholder="Not assigned">
                    <option value="">Not assigned</option>
                    <?php foreach ($blocks as $block): ?>
                        <option value="<?= (int)$block['id'] ?>" <?= (int)$value('block_id') === (int)$block['id'] ? 'selected' : '' ?>>
                            <?= e($block['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="alert alert-info mt-3 mb-0 py-2 px-3 small" id="scopeNote">
            <i class="bi bi-shield-lock me-1"></i>
            <span id="scopeNoteText">Select a role to see what data this user will be able to see.</span>
        </div>
    </fieldset>

    <?php if (!$isEdit): ?>
        <fieldset class="jk-fieldset">
            <legend>Password</legend>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="password">Password</label>
                    <div class="jk-password-wrap">
                        <input type="text" class="form-control<?= $invalid('password') ?>" id="password"
                               name="password" autocomplete="new-password"
                               value="<?= e($generatedPassword ?? '') ?>">
                    </div>
                    <div class="form-text">
                        A password has been generated for you. Note it down &mdash; it is shown once.
                        At least 8 characters with letters and numbers.
                    </div>
                    <?= $feedback('password') ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="password_confirmation">Confirm Password</label>
                    <input type="text" class="form-control" id="password_confirmation"
                           name="password_confirmation" value="<?= e($generatedPassword ?? '') ?>">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="must_change_password"
                               name="must_change_password" value="1" checked>
                        <label class="form-check-label" for="must_change_password">
                            Require a password change at first sign-in
                        </label>
                    </div>
                </div>
            </div>
        </fieldset>
    <?php endif; ?>
</div>

<div class="col-lg-4">
    <div class="jk-card" style="position:sticky;top:76px">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-save"></i>Save</h3></div>
        <div class="jk-card__body">
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                       <?= $isEdit === false || (int)$value('is_active', 1) === 1 ? 'checked' : '' ?>
                       <?= $isSelf ? 'disabled' : '' ?>>
                <label class="form-check-label" for="is_active">Account active</label>
                <?php if ($isSelf): ?>
                    <div class="form-text text-warning">You cannot deactivate your own account.</div>
                <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Changes' : 'Create User' ?>
            </button>
            <a href="<?= e($isEdit ? url('/users/' . (int)$user['id']) : url('/users')) ?>"
               class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
        </div>
    </div>

    <?php if ($isEdit && can('user.reset_password')): ?>
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-key"></i>Password</h3></div>
            <div class="jk-card__body">
                <p class="text-muted small">
                    Resetting issues a new password and forces a change at next sign-in.
                    The new password is shown once, so note it before leaving the page.
                </p>
                <form method="post" action="<?= e(url('/users/' . (int)$user['id'] . '/reset-password')) ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-warning w-100"
                            data-confirm="Issue a new password for this user?">
                        <i class="bi bi-arrow-repeat me-1"></i>Reset Password
                    </button>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>
</form>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
(function () {
  const META  = <?= ejs($roleMeta) ?>;
  const role  = document.getElementById('role_id');
  const state = document.getElementById('state_id');
  const district = document.getElementById('district_id');

  const scopeText = {
    all:      'This role sees data for every State and District.',
    state:    'This role sees only the assigned State, and can manage users within it.',
    district: 'This role sees only the assigned District.',
    own:      'This role sees only the records it collected, within the assigned District.'
  };

  function sync() {
    const meta = META[role.value];
    document.getElementById('stateReq').style.display    = meta && meta.state ? '' : 'none';
    document.getElementById('districtReq').style.display = meta && meta.district ? '' : 'none';
    state.required = !!(meta && meta.state);
    district.required = !!(meta && meta.district);

    document.getElementById('roleHelp').textContent = meta
      ? 'Data scope: ' + meta.scope
      : '';
    document.getElementById('scopeNoteText').textContent = meta
      ? (scopeText[meta.scope] || '')
      : 'Select a role to see what data this user will be able to see.';

    // A Super Administrator is national by definition.
    const isSuper = meta && meta.code === 'SUPER_ADMIN';
    state.disabled = isSuper;
    district.disabled = isSuper;
    if (isSuper) { state.value = ''; district.value = ''; }
  }

  role.addEventListener('change', sync);
  sync();

  // Re-enable before submitting so the server receives the fields.
  role.form.addEventListener('submit', function () {
    state.disabled = false;
    district.disabled = false;
  });
})();
</script>
<?php \JanKavach\Core\View::endSection(); ?>
