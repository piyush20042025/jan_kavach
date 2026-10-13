<?php
/**
 * Main dashboard (specification sections 4, 5, 39).
 *
 * @var array $kpis, $funnel, $filters, $followupDue, $recentBeneficiaries,
 *            $topSchemes, $bottlenecks, $topFieldUsers, $stateCoverage,
 *            $districtCoverage, $states, $districts, $themes, $schemes, $statuses
 */
use JanKavach\Core\Auth;
use JanKavach\Core\View;

$pageSubtitle = 'Programme overview for ' . ($scopeLabel ?: 'the whole programme');
$hasFilters = (bool)array_filter([
    $filters['date_from'] ?? null, $filters['date_to'] ?? null,
    $filters['state_id'] ?? null, $filters['district_id'] ?? null,
    $filters['scheme_id'] ?? null, $filters['theme_id'] ?? null,
    $filters['status_id'] ?? null, $filters['gender'] ?? null,
]);

ob_start(); ?>
    <a href="<?= e(url('/reports')) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-file-bar-graph me-1"></i>Reports
    </a>
    <button class="btn btn-sm btn-outline-secondary" type="button"
            data-bs-toggle="collapse" data-bs-target="#dashFilters">
        <i class="bi bi-funnel me-1"></i>Filters<?= $hasFilters ? ' <span class="badge bg-primary ms-1">on</span>' : '' ?>
    </button>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<!-- =================================================================
     Quick actions (section 39)
     ================================================================= -->
<div class="jk-quick-actions mb-3">
    <?php if (can('beneficiary.create')): ?>
        <a class="jk-quick-action" href="<?= e(url('/beneficiaries/create')) ?>">
            <i class="bi bi-person-plus"></i>Add Beneficiary
        </a>
    <?php endif; ?>
    <?php if (can('scheme.create')): ?>
        <a class="jk-quick-action" href="<?= e(url('/schemes/create')) ?>">
            <i class="bi bi-plus-square"></i>Add Scheme
        </a>
    <?php endif; ?>
    <?php if (can('beneficiary.match')): ?>
        <a class="jk-quick-action" href="<?= e(url('/matching/eligibility-check')) ?>">
            <i class="bi bi-check2-square"></i>Find Eligible Scheme
        </a>
    <?php endif; ?>
    <?php if (can('fielddata.view')): ?>
        <a class="jk-quick-action" href="<?= e(url('/field-data')) ?>">
            <i class="bi bi-clipboard-data"></i>View Field Data
        </a>
    <?php endif; ?>
    <?php if (can('followup.view')): ?>
        <a class="jk-quick-action" href="<?= e(url('/followups?due=overdue')) ?>">
            <i class="bi bi-telephone"></i>Pending Follow-ups
            <?php if (!empty($kpis['followups_overdue'])): ?>
                <span class="badge bg-danger"><?= num($kpis['followups_overdue']) ?></span>
            <?php endif; ?>
        </a>
    <?php endif; ?>
    <?php if (can('report.view')): ?>
        <a class="jk-quick-action" href="<?= e(url('/reports')) ?>">
            <i class="bi bi-file-earmark-bar-graph"></i>Generate Report
        </a>
    <?php endif; ?>
</div>

<!-- =================================================================
     Filters
     ================================================================= -->
<div class="collapse <?= $hasFilters ? 'show' : '' ?>" id="dashFilters">
    <form method="get" class="jk-filter-bar" action="<?= e(url('/dashboard')) ?>">
        <div class="row g-2">
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="f_date_from">From Date</label>
                <input type="date" class="form-control form-control-sm" id="f_date_from"
                       name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="f_date_to">To Date</label>
                <input type="date" class="form-control form-control-sm" id="f_date_to"
                       name="date_to" value="<?= e($filters['date_to'] ?? '') ?>"
                       data-date-min-from="date_from">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="f_state">State</label>
                <select class="form-select form-select-sm" id="f_state" name="state_id">
                    <option value="">All States</option>
                    <?php foreach ($states as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)($filters['state_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="f_district">District</label>
                <select class="form-select form-select-sm" id="f_district" name="district_id"
                        data-depends-on="state_id" data-server-filled="1"
                        data-endpoint="/geo/districts/{id}" data-placeholder="All Districts"
                        data-selected="<?= (int)($filters['district_id'] ?? 0) ?>">
                    <option value="">All Districts</option>
                    <?php foreach ($districts as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)($filters['district_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="f_theme">Theme</label>
                <select class="form-select form-select-sm" id="f_theme" name="theme_id">
                    <option value="">All Themes</option>
                    <?php foreach ($themes as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)($filters['theme_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="f_scheme">Scheme</label>
                <select class="form-select form-select-sm" id="f_scheme" name="scheme_id">
                    <option value="">All Schemes</option>
                    <?php foreach ($schemes as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)($filters['scheme_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                            <?= e(str_limit($name, 42)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="f_level">Scheme Type</label>
                <select class="form-select form-select-sm" id="f_level" name="scheme_level">
                    <option value="">All</option>
                    <?php foreach (['National', 'State', 'National+State'] as $level): ?>
                        <option value="<?= e($level) ?>" <?= ($filters['scheme_level'] ?? '') === $level ? 'selected' : '' ?>>
                            <?= e($level) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="f_btype">Beneficiary Type</label>
                <select class="form-select form-select-sm" id="f_btype" name="beneficiary_type_id">
                    <option value="">All</option>
                    <?php foreach ($beneficiaryTypes as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)($filters['beneficiary_type_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="f_status">Application Status</label>
                <select class="form-select form-select-sm" id="f_status" name="status_id">
                    <option value="">All Statuses</option>
                    <?php foreach ($statuses as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)($filters['status_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="f_gender">Gender</label>
                <select class="form-select form-select-sm" id="f_gender" name="gender">
                    <option value="">All</option>
                    <?php foreach (['Male', 'Female', 'Other'] as $gender): ?>
                        <option value="<?= e($gender) ?>" <?= ($filters['gender'] ?? '') === $gender ? 'selected' : '' ?>>
                            <?= e($gender) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-lg-4 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="bi bi-funnel me-1"></i>Apply Filters
                </button>
                <?php if ($hasFilters): ?>
                    <a href="<?= e(url('/dashboard')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- =================================================================
     KPI cards (section 4)
     ================================================================= -->
<h2 class="h6 text-muted text-uppercase mb-2" style="font-size:.72rem;letter-spacing:.06em">Scheme Coverage</h2>
<div class="jk-kpi-grid">
    <?php
    $tiles = [
        ['value' => num($kpis['total_schemes']),    'label' => 'Total Schemes',    'icon' => 'bi-journal-text',   'color' => '#0f4c81', 'soft' => '#e8f0f8', 'url' => '/schemes',
         'hint' => num($kpis['active_schemes']) . ' active'],
        ['value' => num($kpis['national_schemes']), 'label' => 'National Schemes', 'icon' => 'bi-flag',           'color' => '#1a6bb0', 'soft' => '#e8f0f8', 'url' => '/schemes?scheme_level=National'],
        ['value' => num($kpis['state_schemes']),    'label' => 'State Schemes',    'icon' => 'bi-map',            'color' => '#2a9d8f', 'soft' => '#e3f3f1', 'url' => '/schemes?scheme_level=State'],
        ['value' => num($kpis['total_states']),     'label' => 'States / UTs',     'icon' => 'bi-geo',            'color' => '#6a4c93', 'soft' => '#efeaf6'],
        ['value' => num($kpis['total_districts']),  'label' => 'Districts',        'icon' => 'bi-geo-alt',        'color' => '#457b9d', 'soft' => '#e9f0f5'],
    ];
    foreach ($tiles as $tile) {
        extract(['kpiValue' => $tile['value'], 'kpiLabel' => $tile['label'], 'kpiIcon' => $tile['icon'],
                 'kpiColor' => $tile['color'], 'kpiSoft' => $tile['soft'],
                 'kpiUrl' => $tile['url'] ?? null, 'kpiHint' => $tile['hint'] ?? null]);
        include VIEW_PATH . '/partials/kpi.php';
    }
    ?>
</div>

<h2 class="h6 text-muted text-uppercase mb-2 mt-3" style="font-size:.72rem;letter-spacing:.06em">Beneficiaries &amp; Delivery</h2>
<div class="jk-kpi-grid">
    <?php
    $tiles = [
        ['value' => num($kpis['total_beneficiaries']),     'label' => 'Registered Beneficiaries', 'icon' => 'bi-people',        'color' => '#0f4c81', 'soft' => '#e8f0f8', 'url' => '/beneficiaries'],
        ['value' => num($kpis['beneficiaries_assessed']),  'label' => 'Assessed',                 'icon' => 'bi-clipboard-check','color' => '#2a9d8f', 'soft' => '#e3f3f1', 'url' => '/beneficiaries?assessment_status=Completed',
         'hint' => pct(percent_of($kpis['beneficiaries_assessed'], $kpis['total_beneficiaries'])) . ' of registered'],
        ['value' => num($kpis['eligible_beneficiaries']),  'label' => 'Potentially Eligible',     'icon' => 'bi-check2-square', 'color' => '#198754', 'soft' => '#e6f4ec', 'url' => '/matching'],
        ['value' => num($kpis['applications_initiated']),  'label' => 'Applications Initiated',   'icon' => 'bi-pencil-square', 'color' => '#1a6bb0', 'soft' => '#e8f0f8', 'url' => '/applications'],
        ['value' => num($kpis['applications_submitted']),  'label' => 'Applications Submitted',   'icon' => 'bi-send',          'color' => '#6610f2', 'soft' => '#eee6fd', 'url' => '/applications?funnel_stage=Submitted'],
        ['value' => num($kpis['applications_approved']),   'label' => 'Approved',                 'icon' => 'bi-check-circle',  'color' => '#198754', 'soft' => '#e6f4ec', 'url' => '/applications?funnel_stage=Approved'],
        ['value' => num($kpis['benefits_received_count']), 'label' => 'Benefits Received',        'icon' => 'bi-award',         'color' => '#146c43', 'soft' => '#e3f2e9', 'url' => '/applications?benefit_received=1',
         'hint' => $kpis['benefit_amount_total'] > 0 ? short_money($kpis['benefit_amount_total']) . ' recorded' : null],
        ['value' => num($kpis['followups_pending']),       'label' => 'Pending Follow-ups',       'icon' => 'bi-telephone',     'color' => '#f59e0b', 'soft' => '#fdf3e0', 'url' => '/followups',
         'hint' => $kpis['followups_overdue'] > 0 ? num($kpis['followups_overdue']) . ' overdue' : 'nothing overdue'],
        ['value' => num($kpis['documents_pending']),       'label' => 'Documents Pending',        'icon' => 'bi-folder-x',      'color' => '#dc3545', 'soft' => '#fdecec', 'url' => '/reports/documents-pending'],
    ];
    foreach ($tiles as $tile) {
        extract(['kpiValue' => $tile['value'], 'kpiLabel' => $tile['label'], 'kpiIcon' => $tile['icon'],
                 'kpiColor' => $tile['color'], 'kpiSoft' => $tile['soft'],
                 'kpiUrl' => $tile['url'] ?? null, 'kpiHint' => $tile['hint'] ?? null]);
        include VIEW_PATH . '/partials/kpi.php';
    }
    ?>
</div>

<!-- =================================================================
     Funnel + follow-ups
     ================================================================= -->
<div class="row g-3">
    <div class="col-lg-7">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-funnel"></i>Application Funnel</h3>
                <a href="<?= e(url('/applications/funnel')) ?>" class="small">Open funnel view</a>
            </div>
            <div class="jk-card__body">
                <?php require VIEW_PATH . '/partials/funnel.php'; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-calendar-check"></i>Follow-ups</h3>
                <a href="<?= e(url('/followups')) ?>" class="small">View all</a>
            </div>
            <div class="jk-card__body jk-card__body--tight">
                <ul class="nav nav-tabs px-2" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#fuOverdue" type="button">
                            Overdue <span class="badge bg-danger"><?= num($kpis['followups_overdue']) ?></span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#fuToday" type="button">
                            Today <span class="badge bg-warning text-dark"><?= num($kpis['followups_today']) ?></span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#fuUpcoming" type="button">
                            Upcoming <span class="badge bg-secondary"><?= num($kpis['followups_upcoming']) ?></span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content pt-2">
                    <?php
                    $buckets = [
                        'fuOverdue'  => ['list' => $followupDue['overdue'],  'empty' => 'Nothing is overdue.', 'active' => true],
                        'fuToday'    => ['list' => $followupDue['today'],    'empty' => 'No follow-up is scheduled for today.', 'active' => false],
                        'fuUpcoming' => ['list' => $followupDue['upcoming'], 'empty' => 'Nothing is due in the next seven days.', 'active' => false],
                    ];
                    foreach ($buckets as $id => $bucket): ?>
                        <div class="tab-pane fade <?= $bucket['active'] ? 'show active' : '' ?>" id="<?= e($id) ?>">
                            <?php if (empty($bucket['list'])): ?>
                                <p class="text-muted text-center small py-4 mb-0">
                                    <i class="bi bi-check2-circle d-block fs-4 mb-1 text-success"></i>
                                    <?= e($bucket['empty']) ?>
                                </p>
                            <?php else: ?>
                                <div class="list-group list-group-flush jk-scroll-y" style="max-height:268px">
                                    <?php foreach ($bucket['list'] as $item): ?>
                                        <a href="<?= e(url('/applications/' . (int)$item['id'])) ?>"
                                           class="list-group-item list-group-item-action px-2 py-2">
                                            <div class="d-flex justify-content-between gap-2">
                                                <div class="min-width-0">
                                                    <div class="fw-semibold" style="font-size:.83rem">
                                                        <?= e(str_limit($item['beneficiary_name'], 28)) ?>
                                                    </div>
                                                    <div class="text-muted" style="font-size:.75rem">
                                                        <?= e(str_limit($item['scheme_name'], 34)) ?>
                                                        <?php if (!empty($item['district_name'])): ?>
                                                            &middot; <?= e($item['district_name']) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="text-end flex-shrink-0">
                                                    <div style="font-size:.74rem"><?= fmt_date($item['next_followup_date']) ?></div>
                                                    <?php if ((int)$item['days_overdue'] > 0): ?>
                                                        <span class="badge bg-danger"><?= (int)$item['days_overdue'] ?>d late</span>
                                                    <?php else: ?>
                                                        <span class="badge" style="background:<?= e($item['status_color'] ?: '#6c757d') ?>">
                                                            <?= e(str_limit($item['status_name'], 16)) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- =================================================================
     Charts (section 5)
     ================================================================= -->
<div class="row g-3">
    <div class="col-lg-4">
        <div class="jk-card h-100">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-pie-chart"></i>National vs State Schemes</h3></div>
            <div class="jk-card__body"><div class="jk-chart jk-chart--sm"><canvas id="chartSchemeLevel"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="jk-card h-100">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-tags"></i>Schemes by Theme</h3></div>
            <div class="jk-card__body"><div class="jk-chart jk-chart--sm"><canvas id="chartSchemeTheme"></canvas></div></div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="jk-card h-100">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-people"></i>Schemes by Beneficiary Type</h3></div>
            <div class="jk-card__body"><div class="jk-chart jk-chart--md"><canvas id="chartSchemeBeneficiary"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-star"></i>Schemes by Priority</h3>
            </div>
            <div class="jk-card__body">
                <div class="jk-chart jk-chart--md"><canvas id="chartSchemePriority"></canvas></div>
                <p class="text-muted mb-0 mt-2" style="font-size:.73rem">
                    <i class="bi bi-info-circle me-1"></i>The imported workbook left the priority column blank,
                    so schemes show as <em>Not Ranked</em> until they are ranked in Scheme &rsaquo; Edit.
                </p>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="jk-card h-100">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-diagram-3"></i>Service Approach</h3></div>
            <div class="jk-card__body"><div class="jk-chart jk-chart--md"><canvas id="chartSchemeApproach"></canvas></div></div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="jk-card h-100">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-geo-alt"></i>Beneficiaries by District</h3></div>
            <div class="jk-card__body"><div class="jk-chart jk-chart--md"><canvas id="chartBeneficiaryDistrict"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="jk-card h-100">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-gender-ambiguous"></i>Gender</h3></div>
            <div class="jk-card__body"><div class="jk-chart jk-chart--md"><canvas id="chartBeneficiaryGender"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="jk-card h-100">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-house"></i>Rural / Urban</h3></div>
            <div class="jk-card__body"><div class="jk-chart jk-chart--md"><canvas id="chartBeneficiaryArea"></canvas></div></div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="jk-card h-100">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-bar-chart-steps"></i>Age Groups</h3></div>
            <div class="jk-card__body"><div class="jk-chart jk-chart--sm"><canvas id="chartBeneficiaryAge"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="jk-card h-100">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-person-badge"></i>Social Category</h3></div>
            <div class="jk-card__body"><div class="jk-chart jk-chart--sm"><canvas id="chartBeneficiaryCategory"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="jk-card h-100">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-check2-square"></i>Eligibility Grades</h3></div>
            <div class="jk-card__body"><div class="jk-chart jk-chart--sm"><canvas id="chartEligibility"></canvas></div></div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="jk-card h-100">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-graph-up"></i>Registrations Over Time</h3></div>
            <div class="jk-card__body"><div class="jk-chart jk-chart--md"><canvas id="chartRegistrationTrend"></canvas></div></div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="jk-card h-100">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-activity"></i>Application Progress Over Time</h3></div>
            <div class="jk-card__body"><div class="jk-chart jk-chart--md"><canvas id="chartApplicationTrend"></canvas></div></div>
        </div>
    </div>
</div>

<!-- =================================================================
     Coverage tables
     ================================================================= -->
<div class="row g-3">
    <div class="col-xl-7">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-diagram-2"></i>Scheme Coverage</h3>
                <a href="<?= e(url('/reports/scheme-wise-beneficiary')) ?>" class="small">Full report</a>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <?php if (empty($topSchemes)): ?>
                    <div class="jk-empty">
                        <i class="bi bi-diagram-2"></i>
                        <h5>No applications recorded yet</h5>
                        <p>Once beneficiaries are linked to schemes, the most-used schemes and their
                           progress to benefit appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="jk-table-wrap">
                        <table class="jk-table jk-table--compact">
                            <thead>
                                <tr>
                                    <th>Scheme</th>
                                    <th class="num">Applications</th>
                                    <th class="num">Submitted</th>
                                    <th class="num">Approved</th>
                                    <th class="num">Benefited</th>
                                    <th class="num">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topSchemes as $scheme): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= e(url('/schemes/' . (int)$scheme['id'])) ?>" class="fw-semibold">
                                                <?= e(str_limit($scheme['label'], 34)) ?>
                                            </a>
                                            <div class="text-muted tiny">
                                                <?= e($scheme['scheme_level']) ?>
                                                <?= !empty($scheme['theme_name']) ? ' &middot; ' . e($scheme['theme_name']) : '' ?>
                                            </div>
                                        </td>
                                        <td class="num"><?= num($scheme['applications']) ?></td>
                                        <td class="num"><?= num($scheme['submitted']) ?></td>
                                        <td class="num"><?= num($scheme['approved']) ?></td>
                                        <td class="num"><strong><?= num($scheme['benefited']) ?></strong></td>
                                        <td class="num"><?= (float)$scheme['benefit_amount'] > 0 ? short_money($scheme['benefit_amount']) : '-' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-exclamation-triangle"></i>Where Applications Are Waiting</h3>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <?php if (empty($bottlenecks)): ?>
                    <div class="jk-empty">
                        <i class="bi bi-check2-circle"></i>
                        <h5>Nothing is stuck</h5>
                        <p>No open applications are waiting in a non-terminal status.</p>
                    </div>
                <?php else: ?>
                    <div class="jk-table-wrap">
                        <table class="jk-table jk-table--compact">
                            <thead>
                                <tr><th>Status</th><th class="num">Waiting</th><th class="num">Avg. Days</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bottlenecks as $row): ?>
                                    <tr>
                                        <td>
                                            <span class="jk-theme-dot" style="background:<?= e($row['color'] ?: '#6c757d') ?>"></span>
                                            <?= e($row['label']) ?>
                                        </td>
                                        <td class="num"><?= num($row['value']) ?></td>
                                        <td class="num">
                                            <?php $days = (int)$row['avg_days_waiting']; ?>
                                            <span class="badge bg-<?= $days > 60 ? 'danger' : ($days > 30 ? 'warning text-dark' : 'secondary') ?>">
                                                <?= $days ?>
                                            </span>
                                        </td>
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

<?php if (!empty($stateCoverage)): ?>
<div class="jk-card">
    <div class="jk-card__head">
        <h3 class="jk-card__title"><i class="bi bi-map"></i>State-wise Coverage</h3>
        <a href="<?= e(url('/reports/state-wise-beneficiary')) ?>" class="small">Full report</a>
    </div>
    <div class="jk-card__body jk-card__body--flush">
        <div class="jk-table-wrap">
            <table class="jk-table jk-table--compact">
                <thead>
                    <tr>
                        <th>State</th><th class="num">Beneficiaries</th><th class="num">Assessed</th>
                        <th class="num">Applications</th><th class="num">Benefited</th>
                        <th class="num">Schemes Available</th><th style="width:130px">Conversion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stateCoverage as $row): ?>
                        <?php $conv = percent_of($row['benefited'], $row['beneficiaries']); ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/dashboard/state/' . (int)$row['id'])) ?>" class="fw-semibold">
                                    <?= e($row['state_name']) ?>
                                </a>
                            </td>
                            <td class="num"><?= num($row['beneficiaries']) ?></td>
                            <td class="num"><?= num($row['assessed']) ?></td>
                            <td class="num"><?= num($row['applications']) ?></td>
                            <td class="num"><?= num($row['benefited']) ?></td>
                            <td class="num"><?= num($row['schemes_available']) ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="jk-progress flex-grow-1">
                                        <span style="width:<?= min(100, $conv) ?>%"></span>
                                    </div>
                                    <span class="tiny text-muted" style="min-width:34px"><?= pct($conv, 0) ?></span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($districtCoverage)): ?>
<div class="jk-card">
    <div class="jk-card__head">
        <h3 class="jk-card__title"><i class="bi bi-geo-alt"></i>District-wise Coverage</h3>
        <a href="<?= e(url('/reports/district-wise-beneficiary')) ?>" class="small">Full report</a>
    </div>
    <div class="jk-card__body jk-card__body--flush">
        <div class="jk-table-wrap">
            <table class="jk-table jk-table--compact">
                <thead>
                    <tr>
                        <th>District</th><th>State</th><th class="num">Beneficiaries</th>
                        <th class="num">Assessed</th><th class="num">Eligible</th>
                        <th class="num">Applications</th><th class="num">Submitted</th><th class="num">Benefited</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($districtCoverage as $row): ?>
                        <tr>
                            <td>
                                <a href="<?= e(url('/dashboard/district/' . (int)$row['id'])) ?>" class="fw-semibold">
                                    <?= e($row['district_name']) ?>
                                </a>
                            </td>
                            <td class="text-muted"><?= e($row['state_name']) ?></td>
                            <td class="num"><?= num($row['beneficiaries']) ?></td>
                            <td class="num"><?= num($row['assessed']) ?></td>
                            <td class="num"><?= num($row['eligible']) ?></td>
                            <td class="num"><?= num($row['applications']) ?></td>
                            <td class="num"><?= num($row['submitted']) ?></td>
                            <td class="num"><strong><?= num($row['benefited']) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- =================================================================
     Recent activity and field performance
     ================================================================= -->
<div class="row g-3">
    <div class="col-lg-6">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-clock-history"></i>Recently Registered</h3>
                <a href="<?= e(url('/beneficiaries')) ?>" class="small">View all</a>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <?php if (empty($recentBeneficiaries)): ?>
                    <div class="jk-empty">
                        <i class="bi bi-person-plus"></i>
                        <h5>No beneficiaries registered yet</h5>
                        <?php if (can('beneficiary.create')): ?>
                            <a href="<?= e(url('/beneficiaries/create')) ?>" class="btn btn-sm btn-primary">
                                Register the first beneficiary
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentBeneficiaries as $beneficiary): ?>
                            <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'])) ?>"
                               class="list-group-item list-group-item-action d-flex align-items-center gap-2 py-2">
                                <span class="jk-avatar jk-avatar--sm"><?= e(initials($beneficiary['name'])) ?></span>
                                <span class="flex-grow-1 min-width-0">
                                    <span class="d-block fw-semibold" style="font-size:.84rem"><?= e($beneficiary['name']) ?></span>
                                    <span class="d-block text-muted tiny">
                                        <?= e($beneficiary['beneficiary_code']) ?>
                                        <?php if (!empty($beneficiary['district_name'])): ?>
                                            &middot; <?= e($beneficiary['district_name']) ?>
                                        <?php endif; ?>
                                        <?php if (!empty($beneficiary['field_user_name'])): ?>
                                            &middot; by <?= e($beneficiary['field_user_name']) ?>
                                        <?php endif; ?>
                                    </span>
                                </span>
                                <span class="text-end flex-shrink-0">
                                    <?= badge($beneficiary['record_status'], status_variant($beneficiary['record_status'])) ?>
                                    <span class="d-block text-subtle tiny"><?= time_ago($beneficiary['created_at']) ?></span>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-trophy"></i>Field User Performance</h3>
                <?php if (can('fielddata.performance')): ?>
                    <a href="<?= e(url('/field-data/performance')) ?>" class="small">Full comparison</a>
                <?php endif; ?>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <?php if (empty($topFieldUsers)): ?>
                    <div class="jk-empty">
                        <i class="bi bi-person-workspace"></i>
                        <h5>No field activity recorded</h5>
                        <p>Registration and assessment figures appear here as field users start collecting data.</p>
                    </div>
                <?php else: ?>
                    <div class="jk-table-wrap">
                        <table class="jk-table jk-table--compact">
                            <thead>
                                <tr>
                                    <th>Field User</th><th>District</th>
                                    <th class="num">Registered</th><th class="num">Assessed</th><th class="num">Applications</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topFieldUsers as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="jk-avatar jk-avatar--sm"><?= e(initials($user['full_name'])) ?></span>
                                                <span>
                                                    <span class="d-block fw-semibold" style="font-size:.82rem">
                                                        <?= e(str_limit($user['full_name'], 22)) ?>
                                                    </span>
                                                    <span class="d-block text-muted tiny"><?= e($user['role_name']) ?></span>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-muted"><?= e($user['district_name'] ?? '-') ?></td>
                                        <td class="num"><strong><?= num($user['beneficiaries_registered']) ?></strong></td>
                                        <td class="num"><?= num($user['assessments_completed']) ?></td>
                                        <td class="num"><?= num($user['applications_initiated']) ?></td>
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

<?php View::startSection('scripts'); ?>
<script>
(function () {
  // The dashboard renders immediately; the charts arrive over AJAX so a
  // slow aggregate query never holds up the page.
  const query = window.location.search || '';

  JK.get('/dashboard/charts' + query).then(function (response) {
    const charts = response.charts || {};

    JK.drawChart('chartSchemeLevel',       'doughnut', charts.scheme_level,       { legendPosition: 'bottom' });
    JK.drawChart('chartSchemeTheme',       'bar',      charts.scheme_theme,       { label: 'Schemes' });
    JK.drawChart('chartSchemeBeneficiary', 'hbar',     charts.scheme_beneficiary, { label: 'Schemes' });
    JK.drawChart('chartSchemePriority',    'doughnut', charts.scheme_priority,    { legendPosition: 'bottom' });
    JK.drawChart('chartSchemeApproach',    'doughnut', charts.scheme_approach,    { legendPosition: 'bottom' });

    JK.drawChart('chartBeneficiaryDistrict', 'hbar',     charts.beneficiary_district, { label: 'Beneficiaries' });
    JK.drawChart('chartBeneficiaryGender',   'doughnut', charts.beneficiary_gender,   { legendPosition: 'bottom' });
    JK.drawChart('chartBeneficiaryArea',     'doughnut', charts.beneficiary_area,     { legendPosition: 'bottom' });
    JK.drawChart('chartBeneficiaryAge',      'bar',      charts.beneficiary_age,      { label: 'Beneficiaries', solid: true });
    JK.drawChart('chartBeneficiaryCategory', 'bar',      charts.beneficiary_category, { label: 'Beneficiaries' });
    JK.drawChart('chartEligibility',         'doughnut', charts.eligibility,          { legendPosition: 'bottom' });

    const reg = charts.registration_trend || { labels: [], values: [] };
    JK.drawMultiLine('chartRegistrationTrend', reg.labels, [
      { label: 'Beneficiaries registered', data: reg.values, color: '#0f4c81' }
    ]);

    const app = charts.application_trend || { labels: [], created: [], submitted: [], benefited: [] };
    JK.drawMultiLine('chartApplicationTrend', app.labels, [
      { label: 'Initiated', data: app.created,   color: '#1a6bb0' },
      { label: 'Submitted', data: app.submitted, color: '#6a4c93' },
      { label: 'Benefited', data: app.benefited, color: '#198754' }
    ]);
  }).catch(function () {
    document.querySelectorAll('.jk-chart').forEach(function (wrap) {
      wrap.innerHTML = '<div class="jk-chart-empty text-danger">' +
        '<div><i class="bi bi-exclamation-triangle d-block fs-4 mb-1"></i>Charts could not be loaded</div></div>';
    });
  });
})();
</script>
<?php View::endSection(); ?>
