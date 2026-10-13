<?php
/**
 * District drill-down (specification sections 4 and 29).
 *
 * @var array $district, $filters, $kpis, $funnel, $topSchemes,
 *            $followupDue, $recentBeneficiaries
 * @var bool  $stateReachable  false for a district/field user, who may not
 *                             open the State roll-up
 */
$pageSubtitle = $district['name'] . ', ' . $district['state_name'];

ob_start(); ?>
    <?php if ($stateReachable): ?>
        <a href="<?= e(url('/dashboard/state/' . (int)$district['state_id'])) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= e($district['state_name']) ?>
        </a>
    <?php else: ?>
        <a href="<?= e(url('/dashboard')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Dashboard
        </a>
    <?php endif; ?>
    <a href="<?= e(url('/beneficiaries?district_id=' . (int)$district['id'])) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-people me-1"></i>Beneficiaries
    </a>
    <a href="<?= e(url('/field-data?district_id=' . (int)$district['id'])) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-clipboard-data me-1"></i>Field Data
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<div class="jk-kpi-grid">
    <?php
    $districtId = (int)$district['id'];
    $tiles = [
        ['value' => num($kpis['total_beneficiaries']),     'label' => 'Registered Beneficiaries', 'icon' => 'bi-people',          'color' => '#0f4c81', 'soft' => '#e8f0f8',
         'url'   => '/beneficiaries?district_id=' . $districtId],
        ['value' => num($kpis['beneficiaries_assessed']),  'label' => 'Assessed',                 'icon' => 'bi-clipboard-check', 'color' => '#2a9d8f', 'soft' => '#e3f3f1',
         'url'   => '/beneficiaries?district_id=' . $districtId . '&assessment_status=Completed',
         'hint'  => pct(percent_of($kpis['beneficiaries_assessed'], $kpis['total_beneficiaries'])) . ' of registered'],
        ['value' => num($kpis['eligible_beneficiaries']),  'label' => 'Potentially Eligible',     'icon' => 'bi-check2-square',   'color' => '#198754', 'soft' => '#e6f4ec',
         'url'   => '/matching?district_id=' . $districtId],
        ['value' => num($kpis['total_applications']),      'label' => 'Applications',             'icon' => 'bi-pencil-square',   'color' => '#6610f2', 'soft' => '#eee6fd',
         'url'   => '/applications?district_id=' . $districtId],
        ['value' => num($kpis['applications_submitted']),  'label' => 'Submitted',                'icon' => 'bi-send',            'color' => '#1a6bb0', 'soft' => '#e8f0f8',
         'url'   => '/applications?district_id=' . $districtId . '&funnel_stage=Submitted'],
        ['value' => num($kpis['benefits_received_count']), 'label' => 'Benefits Received',        'icon' => 'bi-award',           'color' => '#146c43', 'soft' => '#e3f2e9',
         'hint'  => $kpis['benefit_amount_total'] > 0 ? short_money($kpis['benefit_amount_total']) . ' recorded' : null],
        ['value' => num($kpis['followups_pending']),       'label' => 'Pending Follow-ups',       'icon' => 'bi-telephone',       'color' => '#f59e0b', 'soft' => '#fdf3e0',
         'url'   => '/followups?district_id=' . $districtId,
         'hint'  => $kpis['followups_overdue'] > 0 ? num($kpis['followups_overdue']) . ' overdue' : 'nothing overdue'],
        ['value' => num($kpis['documents_pending']),       'label' => 'Documents Pending',        'icon' => 'bi-folder-x',        'color' => '#dc3545', 'soft' => '#fdecec',
         'url'   => '/reports/documents-pending?district_id=' . $districtId],
    ];
    foreach ($tiles as $tile) {
        extract(['kpiValue' => $tile['value'], 'kpiLabel' => $tile['label'], 'kpiIcon' => $tile['icon'],
                 'kpiColor' => $tile['color'], 'kpiSoft' => $tile['soft'],
                 'kpiUrl' => $tile['url'] ?? null, 'kpiHint' => $tile['hint'] ?? null]);
        include VIEW_PATH . '/partials/kpi.php';
    }
    ?>
</div>

<div class="row g-3">
    <div class="col-lg-7">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-funnel"></i>Application Funnel</h3>
                <a href="<?= e(url('/applications/funnel?district_id=' . $districtId)) ?>" class="small">Open funnel view</a>
            </div>
            <div class="jk-card__body">
                <?php require VIEW_PATH . '/partials/funnel.php'; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-calendar-check"></i>Follow-ups Due</h3>
                <a href="<?= e(url('/followups?district_id=' . $districtId)) ?>" class="small">View all</a>
            </div>
            <div class="jk-card__body jk-card__body--tight">
                <ul class="nav nav-tabs px-2" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#dfOverdue" type="button">
                            Overdue <span class="badge bg-danger"><?= num($kpis['followups_overdue']) ?></span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#dfToday" type="button">
                            Today <span class="badge bg-warning text-dark"><?= num($kpis['followups_today']) ?></span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#dfUpcoming" type="button">
                            Upcoming <span class="badge bg-secondary"><?= num($kpis['followups_upcoming']) ?></span>
                        </button>
                    </li>
                </ul>

                <div class="tab-content pt-2">
                    <?php
                    $buckets = [
                        'dfOverdue'  => ['list' => $followupDue['overdue'],  'empty' => 'Nothing is overdue in this district.', 'active' => true],
                        'dfToday'    => ['list' => $followupDue['today'],    'empty' => 'No follow-up is scheduled for today.',  'active' => false],
                        'dfUpcoming' => ['list' => $followupDue['upcoming'], 'empty' => 'Nothing is due in the next seven days.','active' => false],
                    ];
                    foreach ($buckets as $id => $bucket): ?>
                        <div class="tab-pane fade <?= $bucket['active'] ? 'show active' : '' ?>" id="<?= e($id) ?>">
                            <?php if (empty($bucket['list'])): ?>
                                <p class="text-muted text-center small py-4 mb-0">
                                    <i class="bi bi-check2-circle d-block fs-4 mb-1 text-success"></i>
                                    <?= e($bucket['empty']) ?>
                                </p>
                            <?php else: ?>
                                <div class="list-group list-group-flush jk-scroll-y" style="max-height:300px">
                                    <?php foreach ($bucket['list'] as $item): ?>
                                        <a href="<?= e(url('/applications/' . (int)$item['id'])) ?>"
                                           class="list-group-item list-group-item-action px-2 py-2">
                                            <div class="d-flex justify-content-between gap-2">
                                                <div class="min-width-0">
                                                    <div class="fw-semibold" style="font-size:.83rem">
                                                        <?= e(str_limit($item['beneficiary_name'], 28)) ?>
                                                    </div>
                                                    <div class="text-muted" style="font-size:.75rem">
                                                        <?= e(str_limit($item['scheme_name'], 36)) ?>
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

<div class="row g-3">
    <div class="col-lg-7">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-journal-text"></i>Most Applied Schemes</h3>
                <a href="<?= e(url('/reports/scheme-wise-application?district_id=' . $districtId)) ?>" class="small">Full report</a>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <?php if (empty($topSchemes)): ?>
                    <div class="jk-empty">
                        <i class="bi bi-journal-x"></i>
                        <h5>No applications recorded</h5>
                        <p>Once beneficiaries in <?= e($district['name']) ?> are matched and applications are
                           initiated, the leading schemes appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="jk-table-wrap">
                        <table class="jk-table jk-table--compact">
                            <thead>
                                <tr><th>Scheme</th><th>Level</th><th class="num">Applications</th>
                                    <th class="num">Submitted</th><th class="num">Approved</th>
                                    <th class="num">Benefited</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topSchemes as $row): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= e(url('/schemes/' . (int)$row['id'])) ?>" class="fw-semibold"
                                               title="<?= e($row['scheme_name']) ?>">
                                                <?= e(str_limit($row['label'], 46)) ?>
                                            </a>
                                            <?php if (!empty($row['theme_name'])): ?>
                                                <span class="d-block text-subtle tiny"><?= e($row['theme_name']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="jk-chip"><?= e($row['scheme_level']) ?></span></td>
                                        <td class="num"><?= num($row['applications']) ?></td>
                                        <td class="num"><?= num($row['submitted']) ?></td>
                                        <td class="num"><?= num($row['approved']) ?></td>
                                        <td class="num"><strong><?= num($row['benefited']) ?></strong></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-clock-history"></i>Recently Registered</h3>
                <a href="<?= e(url('/beneficiaries?district_id=' . $districtId)) ?>" class="small">View all</a>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <?php if (empty($recentBeneficiaries)): ?>
                    <div class="jk-empty">
                        <i class="bi bi-person-plus"></i>
                        <h5>Nothing registered yet</h5>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush jk-scroll-y" style="max-height:360px">
                        <?php foreach ($recentBeneficiaries as $row): ?>
                            <a href="<?= e(url('/beneficiaries/' . (int)$row['id'])) ?>"
                               class="list-group-item list-group-item-action px-3 py-2">
                                <div class="d-flex justify-content-between gap-2">
                                    <div class="min-width-0">
                                        <div class="fw-semibold" style="font-size:.84rem">
                                            <?= e(str_limit($row['name'], 30)) ?>
                                        </div>
                                        <div class="text-muted" style="font-size:.75rem">
                                            <code><?= e($row['beneficiary_code']) ?></code>
                                            <?php if (!empty($row['field_user_name'])): ?>
                                                &middot; <?= e(str_limit($row['field_user_name'], 22)) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end flex-shrink-0 tiny text-muted">
                                        <?= fmt_date($row['registration_date']) ?>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<p class="text-muted small">
    <i class="bi bi-shield-lock me-1"></i>
    <?php if (\JanKavach\Core\Auth::dataScope() === 'own'): ?>
        These figures cover <strong>the records you collected</strong> in <?= e($district['name']) ?>, not the
        whole district &mdash; your account is scoped to your own field data.
    <?php else: ?>
        Every figure on this page is restricted to records inside <?= e($district['name']) ?>, regardless of
        which filters are applied.
    <?php endif; ?>
</p>
