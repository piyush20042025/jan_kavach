<?php
/**
 * Block master.
 * @var array $rows, $states, $districts, $editing
 * @var int|null $stateId, $districtId
 * @var string|null $search
 */
$pageSubtitle = 'Blocks within each District';
$canManage = can('master.manage');

ob_start(); ?>
    <a href="<?= e(url('/masters')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>All Masters
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<form method="get" class="jk-filter-bar">
    <div class="row g-2">
        <div class="col-6 col-md-3">
            <label class="form-label" for="state_id">State</label>
            <select class="form-select form-select-sm" id="state_id" name="state_id" data-auto-submit>
                <option value="">All States</option>
                <?php foreach ($states as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)$stateId === (int)$id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="district_id">District</label>
            <select class="form-select form-select-sm" id="district_id" name="district_id"
                    data-depends-on="state_id" data-server-filled="1"
                    data-endpoint="/geo/districts/{id}" data-placeholder="All Districts" data-auto-submit>
                <option value="">All Districts</option>
                <?php foreach ($districts as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)$districtId === (int)$id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label" for="search">Search</label>
            <input type="search" class="form-control form-control-sm" id="search" name="search"
                   value="<?= e($search ?? '') ?>" placeholder="Block name">
        </div>
        <div class="col-6 col-md-3 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
            <a href="<?= e(url('/masters/blocks')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </div>
</form>

<div class="row g-3">
<?php if ($canManage): ?>
    <div class="col-lg-4">
        <div class="jk-card" style="position:sticky;top:76px">
            <div class="jk-card__head">
                <h3 class="jk-card__title">
                    <i class="bi bi-<?= $editing ? 'pencil' : 'plus-lg' ?>"></i><?= $editing ? 'Edit' : 'Add' ?> Block
                </h3>
                <?php if ($editing): ?><a href="<?= e(url('/masters/blocks')) ?>" class="small">Cancel</a><?php endif; ?>
            </div>
            <div class="jk-card__body">
                <form method="post" action="<?= e(url('/masters/blocks')) ?>" data-guard>
                    <?= csrf_field() ?>
                    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

                    <div class="mb-2">
                        <label class="form-label" for="f_state">State <span class="req">*</span></label>
                        <select class="form-select form-select-sm" id="f_state" name="form_state_id" required>
                            <option value="">Select State</option>
                            <?php foreach ($states as $id => $name): ?>
                                <option value="<?= (int)$id ?>" <?= (int)($stateId ?? 0) === (int)$id ? 'selected' : '' ?>>
                                    <?= e($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="f_district_id">District <span class="req">*</span></label>
                        <select class="form-select form-select-sm" id="f_district_id" name="district_id" required
                                data-depends-on="form_state_id" data-server-filled="1"
                                data-endpoint="/geo/districts/{id}" data-placeholder="Select District">
                            <option value="">Select District</option>
                            <?php foreach ($districts as $id => $name): ?>
                                <option value="<?= (int)$id ?>"
                                    <?= (int)($editing['district_id'] ?? $districtId ?? 0) === (int)$id ? 'selected' : '' ?>>
                                    <?= e($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="f_name">Block Name <span class="req">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="f_name" name="name"
                               maxlength="120" required value="<?= e($editing['name'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="f_type">Block Type</label>
                        <select class="form-select form-select-sm" id="f_type" name="block_type">
                            <?php foreach (['Rural', 'Urban', 'Mixed'] as $type): ?>
                                <option value="<?= e($type) ?>" <?= ($editing['block_type'] ?? 'Rural') === $type ? 'selected' : '' ?>>
                                    <?= e($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="f_code">Code</label>
                        <input type="text" class="form-control form-control-sm" id="f_code" name="code"
                               maxlength="15" value="<?= e($editing['code'] ?? '') ?>">
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="f_active" name="is_active" value="1"
                               <?= $editing === null || (int)($editing['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="f_active">Active</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save Changes' : 'Add Block' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="col-lg-<?= $canManage ? 8 : 12 ?>">
    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-grid"></i>Blocks
                <span class="badge bg-secondary"><?= count($rows) ?></span></h3>
        </div>
        <div class="jk-card__body jk-card__body--flush">
            <?php if (empty($rows)): ?>
                <div class="jk-empty">
                    <i class="bi bi-grid"></i>
                    <h5>No blocks found</h5>
                    <p>Blocks are seeded for sample districts in the programme States. Add more as the
                       programme expands, or pick a different District above.</p>
                </div>
            <?php else: ?>
                <div class="jk-table-wrap" style="max-height:640px">
                    <table class="jk-table jk-table--compact">
                        <thead>
                            <tr><th>State</th><th>District</th><th>Block</th><th>Type</th>
                                <th class="num">GPs</th><th class="num">Villages</th><th>Status</th>
                                <?php if ($canManage): ?><th class="no-print"></th><?php endif; ?></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr<?= (int)$row['is_active'] === 0 ? ' class="opacity-75"' : '' ?>>
                                    <td class="text-muted small"><?= e($row['state_name']) ?></td>
                                    <td class="small"><?= e($row['district_name']) ?></td>
                                    <td class="fw-semibold"><?= e($row['name']) ?></td>
                                    <td><span class="jk-chip"><?= e($row['block_type']) ?></span></td>
                                    <td class="num">
                                        <a href="<?= e(url('/masters/gram-panchayats?block_id=' . (int)$row['id'])) ?>">
                                            <?= num($row['gp_count']) ?>
                                        </a>
                                    </td>
                                    <td class="num">
                                        <a href="<?= e(url('/masters/villages?block_id=' . (int)$row['id'])) ?>">
                                            <?= num($row['village_count']) ?>
                                        </a>
                                    </td>
                                    <td><?= (int)$row['is_active'] === 1
                                            ? '<span class="badge bg-success">Active</span>'
                                            : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                    <?php if ($canManage): ?>
                                        <td class="jk-table__actions no-print">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= e(query_url(['edit' => (int)$row['id']])) ?>"
                                                   class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                                <form method="post" action="<?= e(url('/masters/blocks/' . (int)$row['id'] . '/toggle')) ?>" class="d-inline">
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
