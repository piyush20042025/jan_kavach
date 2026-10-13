<?php
/**
 * District master.
 * @var array $rows, $states, $editing
 * @var int|null $stateId
 * @var string|null $search
 */
$pageSubtitle = 'Districts within each State, and the field activity recorded in them';
$canManage = can('master.manage');

ob_start(); ?>
    <a href="<?= e(url('/masters')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>All Masters
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<form method="get" class="jk-filter-bar">
    <div class="row g-2">
        <div class="col-6 col-md-4 col-lg-3">
            <label class="form-label" for="state_id">State</label>
            <select class="form-select form-select-sm" id="state_id" name="state_id" data-auto-submit>
                <option value="">All States</option>
                <?php foreach ($states as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)$stateId === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-3">
            <label class="form-label" for="search">Search</label>
            <input type="search" class="form-control form-control-sm" id="search" name="search"
                   value="<?= e($search ?? '') ?>" placeholder="District name">
        </div>
        <div class="col-6 col-lg-2 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            <a href="<?= e(url('/masters/districts')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </div>
</form>

<div class="row g-3">
<?php if ($canManage): ?>
    <div class="col-lg-4">
        <div class="jk-card" style="position:sticky;top:76px">
            <div class="jk-card__head">
                <h3 class="jk-card__title">
                    <i class="bi bi-<?= $editing ? 'pencil' : 'plus-lg' ?>"></i>
                    <?= $editing ? 'Edit' : 'Add' ?> District
                </h3>
                <?php if ($editing): ?>
                    <a href="<?= e(url('/masters/districts')) ?>" class="small">Cancel</a>
                <?php endif; ?>
            </div>
            <div class="jk-card__body">
                <form method="post" action="<?= e(url('/masters/districts')) ?>" data-guard>
                    <?= csrf_field() ?>
                    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

                    <div class="mb-2">
                        <label class="form-label" for="f_state_id">State <span class="req">*</span></label>
                        <select class="form-select form-select-sm" id="f_state_id" name="state_id" required>
                            <option value="">Select State</option>
                            <?php foreach ($states as $id => $name): ?>
                                <option value="<?= (int)$id ?>"
                                    <?= (int)($editing['state_id'] ?? $stateId ?? 0) === (int)$id ? 'selected' : '' ?>>
                                    <?= e($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="f_name">District Name <span class="req">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="f_name" name="name"
                               maxlength="120" required value="<?= e($editing['name'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="f_code">District Code</label>
                        <input type="text" class="form-control form-control-sm" id="f_code" name="district_code"
                               maxlength="15" value="<?= e($editing['district_code'] ?? '') ?>">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="f_active" name="is_active" value="1"
                               <?= $editing === null || (int)($editing['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="f_active">Active</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save Changes' : 'Add District' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="col-lg-<?= $canManage ? 8 : 12 ?>">
    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title">
                <i class="bi bi-geo-alt"></i>Districts
                <span class="badge bg-secondary"><?= count($rows) ?></span>
            </h3>
        </div>
        <div class="jk-card__body jk-card__body--flush">
            <?php if (empty($rows)): ?>
                <div class="jk-empty"><i class="bi bi-geo-alt"></i><h5>No districts found</h5></div>
            <?php else: ?>
                <div class="jk-table-wrap" style="max-height:640px">
                    <table class="jk-table jk-table--compact">
                        <thead>
                            <tr><th>State</th><th>District</th><th>Code</th>
                                <th class="num">Blocks</th><th class="num">Beneficiaries</th>
                                <th>Status</th><?php if ($canManage): ?><th class="no-print"></th><?php endif; ?></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr<?= (int)$row['is_active'] === 0 ? ' class="opacity-75"' : '' ?>>
                                    <td class="text-muted"><?= e($row['state_name']) ?></td>
                                    <td class="fw-semibold"><?= e($row['name']) ?></td>
                                    <td><code class="small"><?= e($row['district_code'] ?? '-') ?></code></td>
                                    <td class="num">
                                        <a href="<?= e(url('/masters/blocks?district_id=' . (int)$row['id'])) ?>">
                                            <?= num($row['block_count']) ?>
                                        </a>
                                    </td>
                                    <td class="num"><?= num($row['beneficiary_count']) ?></td>
                                    <td><?= (int)$row['is_active'] === 1
                                            ? '<span class="badge bg-success">Active</span>'
                                            : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                    <?php if ($canManage): ?>
                                        <td class="jk-table__actions no-print">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= e(query_url(['edit' => (int)$row['id']])) ?>"
                                                   class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                                <form method="post" action="<?= e(url('/masters/districts/' . (int)$row['id'] . '/toggle')) ?>" class="d-inline">
                                                    <?= csrf_field() ?>
                                                    <button class="btn btn-outline-secondary" type="submit">
                                                        <i class="bi bi-<?= (int)$row['is_active'] === 1 ? 'pause' : 'play' ?>-circle"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    <?php endif; ?>
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
