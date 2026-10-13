<?php
/**
 * Bulk scheme matching workbench.
 *
 * @var JanKavach\Core\Paginator $paginator
 * @var array $filters, $states, $districts, $stats
 */
$pageSubtitle = 'Run scheme matching across beneficiary records in ' . ($scopeLabel ?: 'the programme');

ob_start(); ?>
    <a href="<?= e(url('/matching/eligibility-check')) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-lightning me-1"></i>Quick Eligibility Check
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<div class="jk-kpi-grid">
    <?php
    $tiles = [
        ['value' => num($stats['total']),    'label' => 'Beneficiaries in scope', 'icon' => 'bi-people',           'color' => '#0f4c81', 'soft' => '#e8f0f8'],
        ['value' => num($stats['assessed']), 'label' => 'Assessment complete',    'icon' => 'bi-clipboard-check',  'color' => '#2a9d8f', 'soft' => '#e3f3f1'],
        ['value' => num($stats['matched']),  'label' => 'Already matched',        'icon' => 'bi-check2-square',    'color' => '#198754', 'soft' => '#e6f4ec'],
        ['value' => num($stats['awaiting']), 'label' => 'Assessed, not matched',  'icon' => 'bi-hourglass-split',  'color' => '#f59e0b', 'soft' => '#fdf3e0'],
        ['value' => num($stats['by_status']['Eligible'] ?? 0),             'label' => 'Eligible matches',  'icon' => 'bi-check-circle',    'color' => '#146c43', 'soft' => '#e3f2e9'],
        ['value' => num($stats['by_status']['Potentially Eligible'] ?? 0), 'label' => 'Potential matches', 'icon' => 'bi-question-circle', 'color' => '#fd7e14', 'soft' => '#fdeee0'],
    ];
    foreach ($tiles as $tile) {
        extract(['kpiValue' => $tile['value'], 'kpiLabel' => $tile['label'], 'kpiIcon' => $tile['icon'],
                 'kpiColor' => $tile['color'], 'kpiSoft' => $tile['soft'], 'kpiUrl' => null, 'kpiHint' => null]);
        include VIEW_PATH . '/partials/kpi.php';
    }
    ?>
</div>

<div class="row g-3">
<div class="col-lg-8">
    <form method="get" class="jk-filter-bar">
        <div class="row g-2">
            <div class="col-12 col-md-4">
                <label class="form-label" for="search">Search</label>
                <input type="search" class="form-control form-control-sm" id="search" name="search"
                       value="<?= e($filters['search'] ?? '') ?>" placeholder="Name or beneficiary ID">
            </div>
            <div class="col-6 col-md-4">
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
            <div class="col-6 col-md-4">
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
            <div class="col-6 col-md-4">
                <label class="form-label" for="assessment_status">Assessment</label>
                <select class="form-select form-select-sm" id="assessment_status" name="assessment_status" data-auto-submit>
                    <option value="">All</option>
                    <?php foreach (['Completed', 'In Progress', 'Pending'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($filters['assessment_status'] ?? '') === $status ? 'selected' : '' ?>>
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label" for="record_status">Record Status</label>
                <select class="form-select form-select-sm" id="record_status" name="record_status" data-auto-submit>
                    <option value="">All</option>
                    <?php foreach (['Registered', 'Assessed', 'Matched', 'In Process', 'Benefited'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($filters['record_status'] ?? '') === $status ? 'selected' : '' ?>>
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-sm btn-primary">Search</button>
                <a href="<?= e(url('/matching')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
            </div>
        </div>
    </form>

    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-people"></i>Beneficiaries</h3>
        </div>
        <div class="jk-card__body jk-card__body--flush">
            <?php if ($paginator->isEmpty()): ?>
                <div class="jk-empty">
                    <i class="bi bi-person-x"></i>
                    <h5>No beneficiaries matched the filters</h5>
                </div>
            <?php else: ?>
                <div class="jk-table-wrap">
                    <table class="jk-table jk-table--compact">
                        <thead>
                            <tr><th>Beneficiary</th><th>District</th><th>Assessment</th>
                                <th class="num">Schemes</th><th>Status</th><th class="no-print"></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paginator->items as $beneficiary): ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'])) ?>" class="fw-semibold">
                                            <?= e($beneficiary['name']) ?>
                                        </a>
                                        <div class="text-muted tiny"><?= e($beneficiary['beneficiary_code']) ?></div>
                                    </td>
                                    <td class="small"><?= e($beneficiary['district_name'] ?? '-') ?></td>
                                    <td>
                                        <?= badge($beneficiary['assessment_status'],
                                                  $beneficiary['assessment_status'] === 'Completed' ? 'success' : 'secondary') ?>
                                    </td>
                                    <td class="num">
                                        <?php if ((int)$beneficiary['schemes_identified'] > 0): ?>
                                            <span class="badge bg-success-subtle text-success">
                                                <?= (int)$beneficiary['schemes_identified'] ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-subtle">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= badge($beneficiary['record_status'], status_variant($beneficiary['record_status'])) ?></td>
                                    <td class="text-end no-print">
                                        <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/matching')) ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-check2-square me-1"></i>Match
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php if (!$paginator->isEmpty()): ?>
            <div class="jk-card__foot">
                <?php $recordNoun = 'beneficiary'; require VIEW_PATH . '/partials/pagination.php'; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bulk run -->
<div class="col-lg-4">
    <div class="jk-card" style="position:sticky;top:76px">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-lightning-charge"></i>Run Matching in Bulk</h3>
        </div>
        <div class="jk-card__body">
            <p class="text-muted small">
                Matching is re-run for every beneficiary matching the filters on the left, against every
                scheme available in their State. Existing results are replaced, so a rule change takes effect
                everywhere at once.
            </p>

            <form method="post" action="<?= e(url('/matching/run-bulk')) ?>" data-guard>
                <?= csrf_field() ?>
                <?php
                // Carry the current filters into the bulk run.
                foreach (($_GET ?? []) as $key => $value) {
                    if (is_array($value) || in_array($key, ['page', 'per_page'], true)) continue;
                    echo '<input type="hidden" name="' . e($key) . '" value="' . e($value) . '">';
                }
                ?>

                <label class="form-label" for="limit">How many records at most</label>
                <select class="form-select form-select-sm mb-3" id="limit" name="limit">
                    <option value="50">50 records</option>
                    <option value="100">100 records</option>
                    <option value="200" selected>200 records</option>
                    <option value="500">500 records (slowest)</option>
                </select>

                <button type="submit" class="btn btn-primary w-100"
                        data-confirm="Run scheme matching for the filtered beneficiaries? Existing match results will be replaced.">
                    <i class="bi bi-play-fill me-1"></i>Run Matching
                </button>
            </form>

            <hr>

            <h4 class="h6">When to re-run</h4>
            <ul class="text-muted small ps-3 mb-0">
                <li>After eligibility rules are edited on a scheme.</li>
                <li>After a batch of assessments is completed.</li>
                <li>After new schemes are imported for a State.</li>
            </ul>
        </div>
    </div>

    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-pie-chart"></i>Match Grades</h3></div>
        <div class="jk-card__body">
            <?php
            $grades = [
                'Eligible'             => ['success', $stats['by_status']['Eligible'] ?? 0],
                'Potentially Eligible' => ['warning', $stats['by_status']['Potentially Eligible'] ?? 0],
                'Needs Verification'   => ['info',    $stats['by_status']['Needs Verification'] ?? 0],
                'Not Eligible'         => ['secondary', $stats['by_status']['Not Eligible'] ?? 0],
            ];
            $totalGrades = array_sum(array_map(fn($g) => $g[1], $grades));
            ?>
            <?php if ($totalGrades === 0): ?>
                <p class="text-muted small mb-0">No matching has been run for this selection yet.</p>
            <?php else: ?>
                <?php foreach ($grades as $label => [$variant, $count]): ?>
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small">
                            <span><?= e($label) ?></span>
                            <span class="fw-semibold"><?= num($count) ?></span>
                        </div>
                        <div class="jk-progress">
                            <span class="bg-<?= e($variant) ?>"
                                  style="width:<?= percent_of($count, $totalGrades) ?>%;background:var(--bs-<?= e($variant) ?>)"></span>
                        </div>
                    </div>
                <?php endforeach; ?>
                <p class="text-subtle tiny mb-0 mt-2">
                    Counts are beneficiary-scheme pairs, not beneficiaries.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
