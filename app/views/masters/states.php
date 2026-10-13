<?php
/**
 * State / UT master.
 * @var array $rows, $editing
 * @var string|null $search
 */
$pageSubtitle = 'States and Union Territories, and how much of the programme sits in each';
$canManage = can('master.manage') && \JanKavach\Core\Auth::isSuperAdmin();

ob_start(); ?>
    <a href="<?= e(url('/masters')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>All Masters
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<div class="row g-3">
<?php if ($canManage): ?>
    <div class="col-lg-4">
        <div class="jk-card" style="position:sticky;top:76px">
            <div class="jk-card__head">
                <h3 class="jk-card__title">
                    <i class="bi bi-<?= $editing ? 'pencil' : 'plus-lg' ?>"></i>
                    <?= $editing ? 'Edit' : 'Add' ?> State
                </h3>
                <?php if ($editing): ?>
                    <a href="<?= e(url('/masters/states')) ?>" class="small">Cancel</a>
                <?php endif; ?>
            </div>
            <div class="jk-card__body">
                <form method="post" action="<?= e(url('/masters/states')) ?>" data-guard>
                    <?= csrf_field() ?>
                    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

                    <div class="mb-2">
                        <label class="form-label" for="state_code">State Code <span class="req">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="state_code" name="state_code"
                               maxlength="10" required value="<?= e($editing['state_code'] ?? '') ?>"
                               style="text-transform:uppercase">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="name">State Name <span class="req">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="name" name="name"
                               maxlength="120" required value="<?= e($editing['name'] ?? '') ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="type">Type <span class="req">*</span></label>
                        <select class="form-select form-select-sm" id="type" name="type" required>
                            <option value="State" <?= ($editing['type'] ?? 'State') === 'State' ? 'selected' : '' ?>>State</option>
                            <option value="UT" <?= ($editing['type'] ?? '') === 'UT' ? 'selected' : '' ?>>Union Territory</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label" for="region">Region</label>
                        <select class="form-select form-select-sm" id="region" name="region">
                            <option value="">Not set</option>
                            <?php foreach (['North', 'South', 'East', 'West', 'Central', 'North-East', 'Islands'] as $region): ?>
                                <option value="<?= e($region) ?>" <?= ($editing['region'] ?? '') === $region ? 'selected' : '' ?>>
                                    <?= e($region) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="is_programme_state"
                               name="is_programme_state" value="1"
                               <?= (int)($editing['is_programme_state'] ?? 0) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_programme_state">Programme is operational here</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                               <?= $editing === null || (int)($editing['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save Changes' : 'Add State' ?>
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
                <i class="bi bi-map"></i>States &amp; Union Territories
                <span class="badge bg-secondary"><?= count($rows) ?></span>
            </h3>
            <form method="get" class="d-flex gap-2">
                <input type="search" class="form-control form-control-sm" name="search"
                       value="<?= e($search ?? '') ?>" placeholder="Filter&hellip;" style="width:190px">
                <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            </form>
        </div>
        <div class="jk-card__body jk-card__body--flush">
            <div class="jk-table-wrap">
                <table class="jk-table jk-table--compact">
                    <thead>
                        <tr>
                            <th>Code</th><th>State / UT</th><th>Type</th><th>Region</th>
                            <th class="num">Districts</th><th class="num">Schemes</th><th class="num">Beneficiaries</th>
                            <th>Programme</th><th>Status</th>
                            <?php if ($canManage): ?><th class="no-print"></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr<?= (int)$row['is_active'] === 0 ? ' class="opacity-75"' : '' ?>>
                                <td><code><?= e($row['state_code']) ?></code></td>
                                <td class="fw-semibold"><?= e($row['name']) ?></td>
                                <td><?= e($row['type']) ?></td>
                                <td class="text-muted"><?= e($row['region'] ?? '-') ?></td>
                                <td class="num">
                                    <a href="<?= e(url('/masters/districts?state_id=' . (int)$row['id'])) ?>">
                                        <?= num($row['district_count']) ?>
                                    </a>
                                </td>
                                <td class="num"><?= num($row['scheme_count']) ?></td>
                                <td class="num"><?= num($row['beneficiary_count']) ?></td>
                                <td>
                                    <?= (int)$row['is_programme_state'] === 1
                                        ? '<span class="badge bg-success-subtle text-success">Operational</span>'
                                        : '<span class="text-subtle tiny">-</span>' ?>
                                </td>
                                <td><?= (int)$row['is_active'] === 1
                                        ? '<span class="badge bg-success">Active</span>'
                                        : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                <?php if ($canManage): ?>
                                    <td class="jk-table__actions no-print">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= e(query_url(['edit' => (int)$row['id']])) ?>"
                                               class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                            <form method="post" action="<?= e(url('/masters/states/' . (int)$row['id'] . '/toggle')) ?>" class="d-inline">
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
        </div>
    </div>
</div>
</div>
