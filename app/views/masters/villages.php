<?php
/**
 * Village master.
 * @var array $rows, $states, $districts, $blocks, $gps, $editing
 * @var int|null $stateId, $districtId, $blockId, $gpId
 * @var string|null $search
 */
$pageSubtitle = 'Villages and urban wards, the lowest level of the geographic hierarchy';
$canManage = can('master.manage');

ob_start(); ?>
    <a href="<?= e(url('/masters')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>All Masters
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<form method="get" class="jk-filter-bar">
    <div class="row g-2">
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="state_id">State</label>
            <select class="form-select form-select-sm" id="state_id" name="state_id" data-auto-submit>
                <option value="">All States</option>
                <?php foreach ($states as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)$stateId === (int)$id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
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
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="block_id">Block</label>
            <select class="form-select form-select-sm" id="block_id" name="block_id"
                    data-depends-on="district_id" data-server-filled="1"
                    data-endpoint="/geo/blocks/{id}" data-placeholder="All Blocks" data-auto-submit>
                <option value="">All Blocks</option>
                <?php foreach ($blocks as $block): ?>
                    <option value="<?= (int)$block['id'] ?>" <?= (int)$blockId === (int)$block['id'] ? 'selected' : '' ?>>
                        <?= e($block['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-3">
            <label class="form-label" for="gram_panchayat_id">Gram Panchayat</label>
            <select class="form-select form-select-sm" id="gram_panchayat_id" name="gram_panchayat_id"
                    data-depends-on="block_id" data-server-filled="1"
                    data-endpoint="/geo/gram-panchayats/{id}" data-placeholder="All Gram Panchayats" data-auto-submit>
                <option value="">All Gram Panchayats</option>
                <?php foreach ($gps as $gp): ?>
                    <option value="<?= (int)$gp['id'] ?>" <?= (int)$gpId === (int)$gp['id'] ? 'selected' : '' ?>>
                        <?= e($gp['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-lg-3 d-flex align-items-end gap-2">
            <input type="search" class="form-control form-control-sm" name="search"
                   value="<?= e($search ?? '') ?>" placeholder="Village name">
            <button type="submit" class="btn btn-sm btn-primary">Go</button>
            <a href="<?= e(url('/masters/villages')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </div>
</form>

<div class="row g-3">
<?php if ($canManage): ?>
    <div class="col-lg-4">
        <div class="jk-card" style="position:sticky;top:76px">
            <div class="jk-card__head">
                <h3 class="jk-card__title">
                    <i class="bi bi-<?= $editing ? 'pencil' : 'plus-lg' ?>"></i><?= $editing ? 'Edit' : 'Add' ?> Village
                </h3>
                <?php if ($editing): ?><a href="<?= e(url('/masters/villages')) ?>" class="small">Cancel</a><?php endif; ?>
            </div>
            <div class="jk-card__body">
                <?php if (empty($blocks) && !$editing): ?>
                    <p class="text-muted small mb-0">Choose a District above first, so the Block list can be filled in.</p>
                <?php else: ?>
                    <form method="post" action="<?= e(url('/masters/villages')) ?>" data-guard>
                        <?= csrf_field() ?>
                        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

                        <div class="mb-2">
                            <label class="form-label" for="f_block_id">Block <span class="req">*</span></label>
                            <select class="form-select form-select-sm" id="f_block_id" name="block_id" required>
                                <option value="">Select Block</option>
                                <?php foreach ($blocks as $block): ?>
                                    <option value="<?= (int)$block['id'] ?>"
                                        <?= (int)($editing['block_id'] ?? $blockId ?? 0) === (int)$block['id'] ? 'selected' : '' ?>>
                                        <?= e($block['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="f_gp_id">Gram Panchayat</label>
                            <select class="form-select form-select-sm" id="f_gp_id" name="gram_panchayat_id"
                                    data-depends-on="f_block_id" data-server-filled="1"
                                    data-endpoint="/geo/gram-panchayats/{id}" data-placeholder="Not set">
                                <option value="">Not set</option>
                                <?php foreach ($gps as $gp): ?>
                                    <option value="<?= (int)$gp['id'] ?>"
                                        <?= (int)($editing['gram_panchayat_id'] ?? $gpId ?? 0) === (int)$gp['id'] ? 'selected' : '' ?>>
                                        <?= e($gp['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label" for="f_name">Village Name <span class="req">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="f_name" name="name"
                                   maxlength="150" required value="<?= e($editing['name'] ?? '') ?>">
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <label class="form-label" for="f_area">Area Type</label>
                                <select class="form-select form-select-sm" id="f_area" name="area_type">
                                    <option value="Rural" <?= ($editing['area_type'] ?? 'Rural') === 'Rural' ? 'selected' : '' ?>>Rural</option>
                                    <option value="Urban" <?= ($editing['area_type'] ?? '') === 'Urban' ? 'selected' : '' ?>>Urban</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label" for="f_pincode">PIN Code</label>
                                <input type="text" class="form-control form-control-sm" id="f_pincode" name="pincode"
                                       maxlength="6" value="<?= e($editing['pincode'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="f_active" name="is_active" value="1"
                                   <?= $editing === null || (int)($editing['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="f_active">Active</label>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save Changes' : 'Add Village' ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="col-lg-<?= $canManage ? 8 : 12 ?>">
    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-houses"></i>Villages
                <span class="badge bg-secondary"><?= count($rows) ?></span></h3>
            <?php if (count($rows) >= 500): ?>
                <span class="text-muted small">Showing the first 500 &mdash; narrow the filters to see more</span>
            <?php endif; ?>
        </div>
        <div class="jk-card__body jk-card__body--flush">
            <?php if (empty($rows)): ?>
                <div class="jk-empty"><i class="bi bi-houses"></i><h5>No villages found</h5>
                    <p>Narrow to a Block or Gram Panchayat above, or add one using the form.</p></div>
            <?php else: ?>
                <div class="jk-table-wrap" style="max-height:640px">
                    <table class="jk-table jk-table--compact">
                        <thead>
                            <tr><th>District</th><th>Block</th><th>Gram Panchayat</th><th>Village</th>
                                <th>Area</th><th>PIN</th><th class="num">Beneficiaries</th><th>Status</th>
                                <?php if ($canManage): ?><th class="no-print"></th><?php endif; ?></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr<?= (int)$row['is_active'] === 0 ? ' class="opacity-75"' : '' ?>>
                                    <td class="text-muted small"><?= e($row['district_name']) ?></td>
                                    <td class="small"><?= e($row['block_name']) ?></td>
                                    <td class="small text-muted"><?= e($row['gp_name'] ?? '-') ?></td>
                                    <td class="fw-semibold"><?= e($row['name']) ?></td>
                                    <td><span class="jk-chip"><?= e($row['area_type']) ?></span></td>
                                    <td class="small"><?= e($row['pincode'] ?? '-') ?></td>
                                    <td class="num"><?= num($row['beneficiary_count']) ?></td>
                                    <td><?= (int)$row['is_active'] === 1
                                            ? '<span class="badge bg-success">Active</span>'
                                            : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                    <?php if ($canManage): ?>
                                        <td class="jk-table__actions no-print">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= e(query_url(['edit' => (int)$row['id']])) ?>"
                                                   class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                                <form method="post" action="<?= e(url('/masters/villages/' . (int)$row['id'] . '/toggle')) ?>" class="d-inline">
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
