<?php
/**
 * Field Data > Beneficiary Records (specification section 23).
 *
 * @var JanKavach\Core\Paginator $paginator
 * @var array $filters, $states, $districts, $fieldUsers, $summary
 */
$pageSubtitle = 'Records collected by field teams in ' . ($scopeLabel ?: 'the programme');

ob_start(); ?>
    <?php if (can('fielddata.performance')): ?>
        <a href="<?= e(url('/field-data/performance')) ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-speedometer2 me-1"></i>Performance
        </a>
    <?php endif; ?>
    <a href="<?= e(url('/field-data/visits')) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-geo me-1"></i>Field Visits
    </a>
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

<div class="jk-kpi-grid">
    <?php
    $tiles = [
        ['value' => num($summary['total']),      'label' => 'Records Collected',  'icon' => 'bi-clipboard-data',  'color' => '#0f4c81', 'soft' => '#e8f0f8'],
        ['value' => num($summary['assessed']),   'label' => 'Assessments Done',   'icon' => 'bi-clipboard-check', 'color' => '#2a9d8f', 'soft' => '#e3f3f1',
         'hint' => pct(percent_of($summary['assessed'], $summary['total'])) . ' of records'],
        ['value' => num($summary['collectors']), 'label' => 'Field Users Active', 'icon' => 'bi-people',          'color' => '#6a4c93', 'soft' => '#efeaf6'],
        ['value' => num($summary['villages']),   'label' => 'Villages Covered',   'icon' => 'bi-geo-alt',         'color' => '#457b9d', 'soft' => '#e9f0f5'],
    ];
    foreach ($tiles as $tile) {
        extract(['kpiValue' => $tile['value'], 'kpiLabel' => $tile['label'], 'kpiIcon' => $tile['icon'],
                 'kpiColor' => $tile['color'], 'kpiSoft' => $tile['soft'],
                 'kpiUrl' => null, 'kpiHint' => $tile['hint'] ?? null]);
        include VIEW_PATH . '/partials/kpi.php';
    }
    ?>
</div>

<?php if ($summary['first'] && $summary['last']): ?>
    <p class="text-muted small">
        <i class="bi bi-calendar-range me-1"></i>
        Collection runs from <strong><?= fmt_date($summary['first']) ?></strong>
        to <strong><?= fmt_date($summary['last']) ?></strong>.
    </p>
<?php endif; ?>

<form method="get" class="jk-filter-bar">
    <div class="row g-2">
        <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label" for="search">Search</label>
            <input type="search" class="form-control form-control-sm" id="search" name="search"
                   value="<?= e($filters['search'] ?? '') ?>" placeholder="Name, ID or mobile">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
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
        <div class="col-6 col-md-4 col-lg-2">
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
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="field_user_id">Field User</label>
            <select class="form-select form-select-sm" id="field_user_id" name="field_user_id" data-auto-submit>
                <option value="">All field users</option>
                <?php foreach ($fieldUsers as $user): ?>
                    <option value="<?= (int)$user['id'] ?>" <?= (int)($filters['field_user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>>
                        <?= e($user['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="record_status">Status</label>
            <select class="form-select form-select-sm" id="record_status" name="record_status" data-auto-submit>
                <option value="">All</option>
                <?php foreach (['Registered', 'Assessed', 'Matched', 'In Process', 'Benefited'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['record_status'] ?? '') === $status ? 'selected' : '' ?>>
                        <?= e($status) ?>
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
        <div class="col-12 col-lg-3 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            <a href="<?= e(url('/field-data')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </div>
</form>

<div class="jk-card">
    <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-clipboard-data"></i>Beneficiary Records</h3></div>
    <div class="jk-card__body jk-card__body--flush">
        <?php if ($paginator->isEmpty()): ?>
            <div class="jk-empty">
                <i class="bi bi-clipboard-x"></i>
                <h5>No field records found</h5>
                <p>Nothing matched the current filters.</p>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap">
                <table class="jk-table">
                    <thead>
                        <tr>
                            <th><?= sort_link('Beneficiary ID', 'code', $sortKey, $sortDir) ?></th>
                            <th style="min-width:160px"><?= sort_link('Name', 'name', $sortKey, $sortDir) ?></th>
                            <th>Mobile</th>
                            <th><?= sort_link('State', 'state', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('District', 'district', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Village', 'village', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Field User', 'field_user', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Registered', 'registered', $sortKey, $sortDir) ?></th>
                            <th class="num"><?= sort_link('Schemes Identified', 'schemes', $sortKey, $sortDir) ?></th>
                            <th class="num">Applications</th>
                            <th><?= sort_link('Current Status', 'status', $sortKey, $sortDir) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginator->items as $record): ?>
                            <tr>
                                <td class="nowrap"><code class="small"><?= e($record['beneficiary_code']) ?></code></td>
                                <td>
                                    <a href="<?= e(url('/beneficiaries/' . (int)$record['id'])) ?>" class="fw-semibold">
                                        <?= e($record['name']) ?>
                                    </a>
                                    <div class="text-muted tiny">
                                        <?= e($record['gender']) ?><?= $record['age'] !== null ? ', ' . (int)$record['age'] : '' ?>
                                    </div>
                                </td>
                                <td class="jk-masked nowrap"><?= e(mask_mobile($record['mobile'] ?? null)) ?></td>
                                <td class="small"><?= e($record['state_name'] ?? '-') ?></td>
                                <td class="small"><?= e($record['district_name'] ?? '-') ?></td>
                                <td class="small text-muted"><?= e(str_limit($record['village_name'] ?? '-', 20)) ?></td>
                                <td class="small"><?= e(str_limit($record['field_user_name'] ?? '-', 20)) ?></td>
                                <td class="nowrap small"><?= fmt_date($record['registration_date']) ?></td>
                                <td class="num">
                                    <?= (int)$record['schemes_identified'] > 0
                                        ? '<span class="badge bg-success-subtle text-success">' . (int)$record['schemes_identified'] . '</span>'
                                        : '<span class="text-subtle">-</span>' ?>
                                </td>
                                <td class="num">
                                    <?= (int)$record['applications_count'] > 0
                                        ? (int)$record['applications_count']
                                        : '<span class="text-subtle">-</span>' ?>
                                </td>
                                <td><?= badge($record['record_status'], status_variant($record['record_status'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if (!$paginator->isEmpty()): ?>
        <div class="jk-card__foot">
            <?php $recordNoun = 'record'; require VIEW_PATH . '/partials/pagination.php'; ?>
        </div>
    <?php endif; ?>
</div>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
  document.querySelectorAll('.jk-export').forEach(function (link) {
    link.addEventListener('click', function () {
      const params = new URLSearchParams(location.search);
      params.set('format', link.dataset.format);
      params.set('view', 'records');
      link.href = JK.url('/field-data/export') + '?' + params.toString();
    });
  });
</script>
<?php \JanKavach\Core\View::endSection(); ?>
