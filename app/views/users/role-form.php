<?php
/**
 * Role and permission editor.
 *
 * @var array|null $role
 * @var array $permissions (grouped by module), $granted, $old, $errors
 * @var int|null $userCount
 */
$isEdit = !empty($role);
$action = $isEdit ? url('/roles/' . (int)$role['id']) : url('/roles');
$old    = $old ?? [];
$errors = $errors ?? [];
$isSuper = $isEdit && $role['code'] === 'SUPER_ADMIN';

$value = function (string $field, $default = '') use ($role, $old) {
    if (array_key_exists($field, $old)) {
        return $old[$field];
    }
    return $role[$field] ?? $default;
};
$invalid  = fn(string $field) => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = fn(string $field) => isset($errors[$field])
    ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';

ob_start(); ?>
    <a href="<?= e(url('/roles')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>All Roles
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<?php if ($isSuper): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <i class="bi bi-shield-exclamation mt-1"></i>
        <div>
            The <strong>Super Administrator</strong> role always keeps full access and national scope, so that
            the platform can never be locked out. Its name and description can be changed; its permissions and
            scope cannot.
        </div>
    </div>
<?php endif; ?>

<form method="post" action="<?= e($action) ?>" data-guard novalidate>
<?= csrf_field() ?>

<div class="row g-3">
<div class="col-lg-4">
    <div class="jk-card" style="position:sticky;top:76px">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-shield-lock"></i>Role</h3></div>
        <div class="jk-card__body">
            <div class="mb-2">
                <label class="form-label" for="name">Role Name <span class="req">*</span></label>
                <input type="text" class="form-control<?= $invalid('name') ?>" id="name" name="name"
                       maxlength="100" required value="<?= e($value('name')) ?>">
                <?= $feedback('name') ?>
            </div>

            <div class="mb-2">
                <label class="form-label" for="code">Role Code <span class="req">*</span></label>
                <input type="text" class="form-control<?= $invalid('code') ?>" id="code" name="code"
                       maxlength="50" required value="<?= e($value('code')) ?>"
                       style="text-transform:uppercase" <?= $isSuper ? 'readonly' : '' ?>>
                <div class="form-text">Machine key, e.g. BLOCK_COORDINATOR.</div>
                <?= $feedback('code') ?>
            </div>

            <div class="mb-2">
                <label class="form-label" for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="2"
                          maxlength="255"><?= e($value('description')) ?></textarea>
            </div>

            <div class="mb-2">
                <label class="form-label" for="data_scope">Data Scope <span class="req">*</span></label>
                <select class="form-select<?= $invalid('data_scope') ?>" id="data_scope" name="data_scope" required
                        <?= $isSuper ? 'disabled' : '' ?>>
                    <option value="all"      <?= $value('data_scope') === 'all' ? 'selected' : '' ?>>All &mdash; every State and District</option>
                    <option value="state"    <?= $value('data_scope') === 'state' ? 'selected' : '' ?>>State &mdash; the assigned State only</option>
                    <option value="district" <?= $value('data_scope', 'district') === 'district' ? 'selected' : '' ?>>District &mdash; the assigned District only</option>
                    <option value="own"      <?= $value('data_scope') === 'own' ? 'selected' : '' ?>>Own &mdash; only records they collected</option>
                </select>
                <?php if ($isSuper): ?><input type="hidden" name="data_scope" value="all"><?php endif; ?>
                <div class="form-text">
                    This is applied to every query, so it restricts data, not just the menu.
                </div>
                <?= $feedback('data_scope') ?>
            </div>

            <div class="mb-2">
                <label class="form-label" for="sort_order">Menu Order</label>
                <input type="number" class="form-control" id="sort_order" name="sort_order"
                       min="0" max="999" value="<?= e($value('sort_order', 50)) ?>">
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                       <?= $isEdit === false || (int)$value('is_active', 1) === 1 ? 'checked' : '' ?>
                       <?= $isSuper ? 'disabled' : '' ?>>
                <label class="form-check-label" for="is_active">Active</label>
            </div>

            <?php if ($isEdit && !empty($userCount)): ?>
                <div class="alert alert-info py-2 px-2 small mb-3">
                    <i class="bi bi-people me-1"></i>
                    <?= num($userCount) ?> user(s) currently hold this role. Permission changes take effect
                    for them immediately.
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Save Role' : 'Create Role' ?>
            </button>
            <a href="<?= e(url('/roles')) ?>" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
        </div>
    </div>
</div>

<div class="col-lg-8">
    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title">
                <i class="bi bi-key"></i>Permissions
                <span class="badge bg-secondary" id="permCount"><?= count($granted) ?></span>
            </h3>
            <?php if (!$isSuper): ?>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAll">Select all</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAll">Clear all</button>
                </div>
            <?php endif; ?>
        </div>
        <div class="jk-card__body">
            <?php if ($isSuper): ?>
                <p class="text-muted">
                    The Super Administrator holds every permission in the system by definition.
                    Permission checks short-circuit for this role.
                </p>
            <?php endif; ?>

            <?php foreach ($permissions as $module => $items): ?>
                <div class="mb-3 permission-module">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <h4 class="h6 mb-0 text-primary"><?= e($module) ?></h4>
                        <span class="flex-grow-1 border-bottom"></span>
                        <?php if (!$isSuper): ?>
                            <button type="button" class="btn btn-sm btn-link p-0 module-toggle"
                                    style="font-size:.74rem">toggle</button>
                        <?php endif; ?>
                    </div>
                    <div class="row g-2">
                        <?php foreach ($items as $permission): ?>
                            <?php $isGranted = $isSuper || in_array((int)$permission['id'], $granted, true); ?>
                            <div class="col-md-6">
                                <div class="form-check border rounded p-2 ps-4 h-100 <?= $isGranted ? 'border-primary bg-light' : '' ?>">
                                    <input class="form-check-input perm-check" type="checkbox"
                                           name="permissions[]" value="<?= (int)$permission['id'] ?>"
                                           id="perm<?= (int)$permission['id'] ?>"
                                           <?= $isGranted ? 'checked' : '' ?>
                                           <?= $isSuper ? 'disabled' : '' ?>>
                                    <label class="form-check-label" for="perm<?= (int)$permission['id'] ?>">
                                        <span class="fw-semibold d-block" style="font-size:.84rem">
                                            <?= e($permission['name']) ?>
                                        </span>
                                        <span class="text-muted d-block" style="font-size:.74rem">
                                            <?= e($permission['description'] ?? '') ?>
                                        </span>
                                        <code class="tiny text-subtle"><?= e($permission['code']) ?></code>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$isSuper): ?>
            <div class="jk-card__foot text-muted small">
                <i class="bi bi-shield-check me-1"></i>
                You can only grant permissions you hold yourself, so a role cannot be used to gain
                more access than you already have.
            </div>
        <?php endif; ?>
    </div>
</div>
</div>
</form>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
(function () {
  const checks  = document.querySelectorAll('.perm-check:not([disabled])');
  const counter = document.getElementById('permCount');
  if (checks.length === 0) return;

  function paint(check) {
    const box = check.closest('.form-check');
    box.classList.toggle('border-primary', check.checked);
    box.classList.toggle('bg-light', check.checked);
  }

  function refresh() {
    counter.textContent = document.querySelectorAll('.perm-check:checked').length;
  }

  checks.forEach(function (check) {
    check.addEventListener('change', function () { paint(check); refresh(); });
  });

  const selectAll = document.getElementById('selectAll');
  const clearAll  = document.getElementById('clearAll');

  if (selectAll) selectAll.addEventListener('click', function () {
    checks.forEach(function (c) { c.checked = true; paint(c); });
    refresh();
  });
  if (clearAll) clearAll.addEventListener('click', function () {
    checks.forEach(function (c) { c.checked = false; paint(c); });
    refresh();
  });

  document.querySelectorAll('.module-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      const module = button.closest('.permission-module');
      const inputs = module.querySelectorAll('.perm-check:not([disabled])');
      const allOn = Array.prototype.every.call(inputs, function (c) { return c.checked; });
      inputs.forEach(function (c) { c.checked = !allOn; paint(c); });
      refresh();
    });
  });
})();
</script>
<?php \JanKavach\Core\View::endSection(); ?>
