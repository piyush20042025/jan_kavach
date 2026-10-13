<?php
/**
 * Field user performance (specification section 27).
 *
 * @var array $rows, $totals, $filters, $states, $districts, $users
 */
$pageSubtitle = 'Collection and conversion by field user';

ob_start(); ?>
    <div class="btn-group">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i>Export
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item jk-export" data-format="excel" href="#"><i class="bi bi-file-earmark-excel me-2"></i>Excel</a></li>
            <li><a class="dropdown-item jk-export" data-format="csv" href="#"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
            <li><a class="dropdown-item jk-export" data-format="pdf" target="_blank" href="#"><i class="bi bi-file-earmark-pdf me-2"></i>PDF / Print</a></li>
        </ul>
    </div>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<form method="get" class="jk-filter-bar">
    <div class="row g-2">
        <div class="col-6 col-md-3">
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
        <div class="col-6 col-md-3">
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
        <div class="col-6 col-md-3">
            <label class="form-label" for="user_id">Field User</label>
            <select class="form-select form-select-sm" id="user_id" name="user_id" data-auto-submit>
                <option value="">All users</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int)$user['id'] ?>" <?= (int)($filters['user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>>
                        <?= e($user['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary">Apply</button>
            <a href="<?= e(url('/field-data/performance')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </div>
</form>

<div class="jk-kpi-grid">
    <?php
    $tiles = [
        ['value' => num($totals['beneficiaries_registered']), 'label' => 'Registered',   'icon' => 'bi-person-plus',     'color' => '#0f4c81', 'soft' => '#e8f0f8'],
        ['value' => num($totals['assessments_completed']),    'label' => 'Assessed',     'icon' => 'bi-clipboard-check', 'color' => '#2a9d8f', 'soft' => '#e3f3f1'],
        ['value' => num($totals['schemes_identified']),       'label' => 'Schemes Identified', 'icon' => 'bi-check2-square', 'color' => '#198754', 'soft' => '#e6f4ec'],
        ['value' => num($totals['applications_initiated']),   'label' => 'Applications Initiated', 'icon' => 'bi-pencil-square', 'color' => '#1a6bb0', 'soft' => '#e8f0f8'],
        ['value' => num($totals['applications_submitted']),   'label' => 'Submitted',    'icon' => 'bi-send',            'color' => '#6610f2', 'soft' => '#eee6fd'],
        ['value' => num($totals['applications_approved']),    'label' => 'Approved',     'icon' => 'bi-check-circle',    'color' => '#198754', 'soft' => '#e6f4ec'],
        ['value' => num($totals['benefits_received']),        'label' => 'Benefits Received', 'icon' => 'bi-award',      'color' => '#146c43', 'soft' => '#e3f2e9'],
        ['value' => num($totals['field_visits']),             'label' => 'Field Visits', 'icon' => 'bi-geo',             'color' => '#6a4c93', 'soft' => '#efeaf6'],
    ];
    foreach ($tiles as $tile) {
        extract(['kpiValue' => $tile['value'], 'kpiLabel' => $tile['label'], 'kpiIcon' => $tile['icon'],
                 'kpiColor' => $tile['color'], 'kpiSoft' => $tile['soft'], 'kpiUrl' => null, 'kpiHint' => null]);
        include VIEW_PATH . '/partials/kpi.php';
    }
    ?>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-table"></i>Performance by Field User</h3>
                <span class="text-muted small"><?= count($rows) ?> user(s)</span>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <?php if (empty($rows)): ?>
                    <div class="jk-empty">
                        <i class="bi bi-person-workspace"></i>
                        <h5>No field users in scope</h5>
                        <p>Performance figures appear once Field Users and District Coordinators start collecting data.</p>
                    </div>
                <?php else: ?>
                    <div class="jk-table-wrap">
                        <table class="jk-table jk-table--compact">
                            <thead>
                                <tr>
                                    <th style="min-width:180px">Field User</th>
                                    <th>District</th>
                                    <th class="num">Registered</th>
                                    <th class="num">Assessed</th>
                                    <th class="num">Schemes</th>
                                    <th class="num">Initiated</th>
                                    <th class="num">Submitted</th>
                                    <th class="num">Approved</th>
                                    <th class="num">Benefited</th>
                                    <th class="num">Follow-ups</th>
                                    <th class="num">Visits</th>
                                    <th style="width:110px">Conversion</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $user): ?>
                                    <?php $conversion = percent_of($user['benefits_received'], $user['beneficiaries_registered']); ?>
                                    <tr<?= (int)$user['is_active'] === 0 ? ' class="opacity-75"' : '' ?>>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="jk-avatar jk-avatar--sm"><?= e(initials($user['full_name'])) ?></span>
                                                <div class="min-width-0">
                                                    <a href="<?= e(url('/users/' . (int)$user['user_id'])) ?>" class="fw-semibold">
                                                        <?= e(str_limit($user['full_name'], 22)) ?>
                                                    </a>
                                                    <div class="text-muted tiny">
                                                        <?= e($user['role_name']) ?>
                                                        <?php if ((int)$user['is_active'] === 0): ?>
                                                            &middot; <span class="text-danger">Inactive</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="small"><?= e($user['district_name'] ?? '-') ?></td>
                                        <td class="num fw-semibold"><?= num($user['beneficiaries_registered']) ?></td>
                                        <td class="num"><?= num($user['assessments_completed']) ?></td>
                                        <td class="num"><?= num($user['schemes_identified']) ?></td>
                                        <td class="num"><?= num($user['applications_initiated']) ?></td>
                                        <td class="num"><?= num($user['applications_submitted']) ?></td>
                                        <td class="num"><?= num($user['applications_approved']) ?></td>
                                        <td class="num"><?= num($user['benefits_received']) ?></td>
                                        <td class="num"><?= num($user['followups_done']) ?></td>
                                        <td class="num"><?= num($user['field_visits']) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="jk-progress flex-grow-1">
                                                    <span style="width:<?= min(100, $conversion) ?>%"></span>
                                                </div>
                                                <span class="tiny text-muted" style="min-width:32px"><?= pct($conversion, 0) ?></span>
                                            </div>
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

    <div class="col-xl-4">
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-bar-chart"></i>Registrations by User</h3></div>
            <div class="jk-card__body">
                <div class="jk-chart jk-chart--lg"><canvas id="chartPerformance"></canvas></div>
            </div>
        </div>

        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-info-circle"></i>Reading These Figures</h3></div>
            <div class="jk-card__body small text-muted">
                <p class="mb-2">
                    <strong>Conversion</strong> is the share of a user's registered beneficiaries who have
                    actually received a benefit. It is a programme outcome, not a measure of effort &mdash;
                    it depends heavily on which schemes are open in that district.
                </p>
                <p class="mb-0">
                    A high registration count with a low assessment count usually means households were
                    enumerated but the socio-economic questionnaire was not completed, which is what
                    stops scheme matching from working.
                </p>
            </div>
        </div>
    </div>
</div>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
  document.querySelectorAll('.jk-export').forEach(function (link) {
    link.addEventListener('click', function () {
      const params = new URLSearchParams(location.search);
      params.set('format', link.dataset.format);
      params.set('view', 'performance');
      link.href = JK.url('/field-data/export') + '?' + params.toString();
    });
  });

  JK.drawChart('chartPerformance', 'hbar', {
    labels: <?= ejs(array_map(fn($r) => str_limit($r['full_name'], 18), array_slice($rows, 0, 12))) ?>,
    values: <?= ejs(array_map(fn($r) => (int)$r['beneficiaries_registered'], array_slice($rows, 0, 12))) ?>,
    colors: []
  }, { label: 'Beneficiaries registered', emptyText: 'No registrations recorded yet' });
</script>
<?php \JanKavach\Core\View::endSection(); ?>
