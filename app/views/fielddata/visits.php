<?php
/**
 * Field visit log.
 * @var array $visits, $filters, $states, $districts, $fieldUsers
 */
$pageSubtitle = 'Visits recorded by field teams';

ob_start(); ?>
    <a href="<?= e(url('/field-data')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Field Data
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
                    <option value="<?= (int)$id ?>" <?= (int)($filters['state_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
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
                    <option value="<?= (int)$id ?>" <?= (int)($filters['district_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="field_user_id">Field User</label>
            <select class="form-select form-select-sm" id="field_user_id" name="field_user_id" data-auto-submit>
                <option value="">All</option>
                <?php foreach ($fieldUsers as $user): ?>
                    <option value="<?= (int)$user['id'] ?>" <?= (int)($filters['field_user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>>
                        <?= e($user['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="date_from">From</label>
            <input type="date" class="form-control form-control-sm" id="date_from" name="date_from"
                   value="<?= e($filters['date_from'] ?? '') ?>" data-auto-submit>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="date_to">To</label>
            <input type="date" class="form-control form-control-sm" id="date_to" name="date_to"
                   value="<?= e($filters['date_to'] ?? '') ?>" data-date-min-from="date_from" data-auto-submit>
        </div>
        <div class="col-6 col-lg-2 d-flex align-items-end">
            <a href="<?= e(url('/field-data/visits')) ?>" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
        </div>
    </div>
</form>

<div class="jk-card">
    <div class="jk-card__head">
        <h3 class="jk-card__title"><i class="bi bi-geo"></i>Field Visits</h3>
        <span class="text-muted small"><?= count($visits) ?> visit(s), most recent first</span>
    </div>
    <div class="jk-card__body jk-card__body--flush">
        <?php if (empty($visits)): ?>
            <div class="jk-empty">
                <i class="bi bi-geo"></i>
                <h5>No field visits recorded</h5>
                <p>Visits are logged when a beneficiary is registered, and can be added from a beneficiary's profile.</p>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap">
                <table class="jk-table">
                    <thead>
                        <tr><th>Date</th><th>Type</th><th>Beneficiary</th><th>Village</th><th>Block</th>
                            <th>District</th><th>Field User</th><th>Purpose</th><th>Observations</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($visits as $visit): ?>
                            <tr>
                                <td class="nowrap"><?= fmt_date($visit['visit_date']) ?></td>
                                <td><span class="jk-chip"><?= e($visit['visit_type']) ?></span></td>
                                <td>
                                    <?php if (!empty($visit['beneficiary_id'])): ?>
                                        <a href="<?= e(url('/beneficiaries/' . (int)$visit['beneficiary_id'])) ?>">
                                            <?= e(str_limit($visit['beneficiary_name'] ?? '-', 22)) ?>
                                        </a>
                                        <div class="text-muted tiny"><?= e($visit['beneficiary_code'] ?? '') ?></div>
                                    <?php else: ?>
                                        <span class="text-muted">Area visit</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= e(str_limit($visit['village_display'] ?? '-', 20)) ?></td>
                                <td class="small"><?= e($visit['block_name'] ?? '-') ?></td>
                                <td class="small"><?= e($visit['district_name'] ?? '-') ?></td>
                                <td class="small"><?= e(str_limit($visit['field_user_name'] ?? '-', 20)) ?></td>
                                <td class="text-muted small"><?= e(str_limit($visit['purpose'] ?? '-', 50)) ?></td>
                                <td class="text-muted small"><?= e(str_limit($visit['observations'] ?? '-', 70)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
