<?php
/**
 * State drill-down (specification section 4 - "State-wise scheme distribution"
 * and section 29 - geography-restricted views).
 *
 * @var array $state, $filters, $kpis, $districtCoverage, $funnel, $states
 */
$pageSubtitle = 'Coverage across the districts of ' . $state['name'];

ob_start(); ?>
    <a href="<?= e(url('/dashboard')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Programme Dashboard
    </a>
    <a href="<?= e(url('/schemes?state_id=' . (int)$state['id'])) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-journal-text me-1"></i>Schemes
    </a>
    <a href="<?= e(url('/beneficiaries?state_id=' . (int)$state['id'])) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-people me-1"></i>Beneficiaries
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<?php if (count($states) > 1): ?>
    <form method="get" class="jk-filter-bar" id="stateSwitch">
        <div class="row g-2 align-items-end">
            <div class="col-sm-6 col-md-4 col-lg-3">
                <label class="form-label" for="sw_state">Switch State / UT</label>
                <select class="form-select form-select-sm" id="sw_state" name="state_id" data-switch-state>
                    <?php foreach ($states as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)$id === (int)$state['id'] ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <label class="form-label" for="sw_from">From Date</label>
                <input type="date" class="form-control form-control-sm" id="sw_from"
                       name="date_from" value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <label class="form-label" for="sw_to">To Date</label>
                <input type="date" class="form-control form-control-sm" id="sw_to"
                       name="date_to" value="<?= e($filters['date_to'] ?? '') ?>">
            </div>
            <div class="col-sm-6 col-md-4 col-lg-3">
                <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Apply</button>
                <a href="<?= e(url('/dashboard/state/' . (int)$state['id'])) ?>"
                   class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>
<?php endif; ?>

<h2 class="h6 text-muted text-uppercase mb-2" style="font-size:.72rem;letter-spacing:.06em">
    <?= e($state['name']) ?> at a Glance
</h2>
<div class="jk-kpi-grid">
    <?php
    $tiles = [
        ['value' => num($kpis['total_schemes']),           'label' => 'Schemes Available',        'icon' => 'bi-journal-text',   'color' => '#0f4c81', 'soft' => '#e8f0f8',
         'url'   => '/schemes?state_id=' . (int)$state['id'],
         'hint'  => num($kpis['national_schemes']) . ' national + ' . num($kpis['state_schemes']) . ' state'],
        ['value' => num($kpis['total_districts']),         'label' => 'Districts',                'icon' => 'bi-geo-alt',        'color' => '#457b9d', 'soft' => '#e9f0f5',
         'url'   => '/masters/districts?state_id=' . (int)$state['id']],
        ['value' => num($kpis['total_beneficiaries']),     'label' => 'Registered Beneficiaries', 'icon' => 'bi-people',         'color' => '#1a6bb0', 'soft' => '#e8f0f8',
         'url'   => '/beneficiaries?state_id=' . (int)$state['id']],
        ['value' => num($kpis['beneficiaries_assessed']),  'label' => 'Assessed',                 'icon' => 'bi-clipboard-check','color' => '#2a9d8f', 'soft' => '#e3f3f1',
         'hint'  => pct(percent_of($kpis['beneficiaries_assessed'], $kpis['total_beneficiaries'])) . ' of registered'],
        ['value' => num($kpis['eligible_beneficiaries']),  'label' => 'Potentially Eligible',     'icon' => 'bi-check2-square',  'color' => '#198754', 'soft' => '#e6f4ec',
         'url'   => '/matching?state_id=' . (int)$state['id']],
        ['value' => num($kpis['total_applications']),      'label' => 'Applications',             'icon' => 'bi-pencil-square',  'color' => '#6610f2', 'soft' => '#eee6fd',
         'url'   => '/applications?state_id=' . (int)$state['id']],
        ['value' => num($kpis['benefits_received_count']), 'label' => 'Benefits Received',        'icon' => 'bi-award',          'color' => '#146c43', 'soft' => '#e3f2e9',
         'hint'  => $kpis['benefit_amount_total'] > 0 ? short_money($kpis['benefit_amount_total']) . ' recorded' : null],
        ['value' => num($kpis['followups_pending']),       'label' => 'Pending Follow-ups',       'icon' => 'bi-telephone',      'color' => '#f59e0b', 'soft' => '#fdf3e0',
         'url'   => '/followups?state_id=' . (int)$state['id'],
         'hint'  => $kpis['followups_overdue'] > 0 ? num($kpis['followups_overdue']) . ' overdue' : 'nothing overdue'],
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
                <a href="<?= e(url('/applications/funnel?state_id=' . (int)$state['id'])) ?>" class="small">Open funnel view</a>
            </div>
            <div class="jk-card__body">
                <?php require VIEW_PATH . '/partials/funnel.php'; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-people"></i>Who Is Registered</h3>
            </div>
            <div class="jk-card__body">
                <?php
                $splits = [
                    ['label' => 'Female',    'value' => (int)$kpis['female'],       'total' => (int)$kpis['total_beneficiaries'], 'color' => '#c9184a'],
                    ['label' => 'Male',      'value' => (int)$kpis['male'],         'total' => (int)$kpis['total_beneficiaries'], 'color' => '#1a6bb0'],
                    ['label' => 'Other',     'value' => (int)$kpis['other_gender'], 'total' => (int)$kpis['total_beneficiaries'], 'color' => '#6a4c93'],
                    ['label' => 'Rural',     'value' => (int)$kpis['rural'],        'total' => (int)$kpis['total_beneficiaries'], 'color' => '#2a9d8f'],
                    ['label' => 'Urban',     'value' => (int)$kpis['urban'],        'total' => (int)$kpis['total_beneficiaries'], 'color' => '#e9c46a'],
                ];
                ?>
                <?php if ((int)$kpis['total_beneficiaries'] === 0): ?>
                    <div class="jk-empty py-4">
                        <i class="bi bi-person-plus"></i>
                        <h5>No beneficiaries in <?= e($state['name']) ?> yet</h5>
                        <p>Registration and assessment figures will appear here once field teams begin recording.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($splits as $split): ?>
                        <?php $share = percent_of($split['value'], $split['total']); ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span><?= e($split['label']) ?></span>
                                <span class="text-muted"><?= num($split['value']) ?> &middot; <?= pct($share, 0) ?></span>
                            </div>
                            <div class="jk-progress">
                                <span style="width:<?= min(100, $share) ?>%;background:<?= e($split['color']) ?>"></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <p class="text-subtle tiny mb-0 mt-3">
                        Rural / urban totals may not add up to the registered count where the location type
                        was not recorded.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="jk-card">
    <div class="jk-card__head">
        <h3 class="jk-card__title"><i class="bi bi-geo-alt"></i>District-wise Coverage</h3>
        <a href="<?= e(url('/reports/district-wise-beneficiary?state_id=' . (int)$state['id'])) ?>" class="small">Full report</a>
    </div>
    <div class="jk-card__body jk-card__body--flush">
        <?php if (empty($districtCoverage)): ?>
            <div class="jk-empty">
                <i class="bi bi-geo"></i>
                <h5>No district activity recorded</h5>
                <p>No beneficiary has yet been registered against a district in <?= e($state['name']) ?>.</p>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap">
                <table class="jk-table jk-table--compact" data-sortable>
                    <thead>
                        <tr>
                            <th>District</th><th class="num">Beneficiaries</th><th class="num">Assessed</th>
                            <th class="num">Eligible</th><th class="num">Applications</th>
                            <th class="num">Submitted</th><th class="num">Benefited</th>
                            <th style="width:130px">Conversion</th><th class="no-print"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($districtCoverage as $row): ?>
                            <?php $conv = percent_of($row['benefited'], $row['beneficiaries']); ?>
                            <tr>
                                <td>
                                    <a href="<?= e(url('/dashboard/district/' . (int)$row['id'])) ?>" class="fw-semibold">
                                        <?= e($row['district_name']) ?>
                                    </a>
                                </td>
                                <td class="num"><?= num($row['beneficiaries']) ?></td>
                                <td class="num"><?= num($row['assessed']) ?></td>
                                <td class="num"><?= num($row['eligible']) ?></td>
                                <td class="num"><?= num($row['applications']) ?></td>
                                <td class="num"><?= num($row['submitted']) ?></td>
                                <td class="num"><strong><?= num($row['benefited']) ?></strong></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="jk-progress flex-grow-1">
                                            <span style="width:<?= min(100, $conv) ?>%"></span>
                                        </div>
                                        <span class="tiny text-muted" style="min-width:34px"><?= pct($conv, 0) ?></span>
                                    </div>
                                </td>
                                <td class="text-end no-print">
                                    <a href="<?= e(url('/dashboard/district/' . (int)$row['id'])) ?>"
                                       class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-right"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
(function () {
  // Switching the State navigates to that State's dashboard rather than
  // appending a filter, so the breadcrumb and title stay truthful.
  const form = document.getElementById('stateSwitch');
  if (!form) return;
  form.addEventListener('submit', function (event) {
    event.preventDefault();
    const stateId = form.querySelector('[data-switch-state]').value;
    const params  = new URLSearchParams();
    ['date_from', 'date_to'].forEach(function (name) {
      const field = form.querySelector('[name="' + name + '"]');
      if (field && field.value) params.set(name, field.value);
    });
    const query = params.toString();
    window.location.href = JK.url('/dashboard/state/' + stateId) + (query ? '?' + query : '');
  });
})();
</script>
<?php \JanKavach\Core\View::endSection(); ?>
