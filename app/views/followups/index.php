<?php
/**
 * Follow-up register (specification section 22).
 *
 * @var JanKavach\Core\Paginator $paginator
 * @var array $filters, $due, $dueLists, $types, $outcomes, $states, $districts, $users
 */
$pageSubtitle = 'Follow-up interactions and what is due next in ' . ($scopeLabel ?: 'the programme');
$activeFilters = array_filter($filters, fn($v) => $v !== null && $v !== '');

ob_start(); ?>
    <?php if (can('followup.create')): ?>
        <a href="<?= e(url('/followups/create')) ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Record Follow-up
        </a>
    <?php endif; ?>
    <a href="<?= e(url('/followups/calendar')) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-calendar3 me-1"></i>What's Due
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

<!-- Due buckets (section 22) -->
<div class="jk-kpi-grid">
    <?php
    $tiles = [
        ['value' => num($due['overdue']),  'label' => 'Overdue',          'icon' => 'bi-calendar-x',     'color' => '#dc3545', 'soft' => '#fdecec', 'url' => '/applications?followup_due=overdue'],
        ['value' => num($due['today']),    'label' => 'Due Today',        'icon' => 'bi-calendar-event', 'color' => '#f59e0b', 'soft' => '#fdf3e0', 'url' => '/applications?followup_due=today'],
        ['value' => num($due['upcoming']), 'label' => 'Next 7 Days',      'icon' => 'bi-calendar-week',  'color' => '#0f4c81', 'soft' => '#e8f0f8', 'url' => '/applications?followup_due=upcoming'],
        ['value' => num($due['total']),    'label' => 'Open Follow-ups',  'icon' => 'bi-list-task',      'color' => '#6c757d', 'soft' => '#eef0f2'],
    ];
    foreach ($tiles as $tile) {
        extract(['kpiValue' => $tile['value'], 'kpiLabel' => $tile['label'], 'kpiIcon' => $tile['icon'],
                 'kpiColor' => $tile['color'], 'kpiSoft' => $tile['soft'],
                 'kpiUrl' => $tile['url'] ?? null, 'kpiHint' => null]);
        include VIEW_PATH . '/partials/kpi.php';
    }
    ?>
</div>

<form method="get" class="jk-filter-bar">
    <div class="row g-2">
        <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label" for="search">Search</label>
            <input type="search" class="form-control form-control-sm" id="search" name="search"
                   value="<?= e($filters['search'] ?? '') ?>" placeholder="Beneficiary, scheme or discussion">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="followup_type">Type</label>
            <select class="form-select form-select-sm" id="followup_type" name="followup_type" data-auto-submit>
                <option value="">All types</option>
                <?php foreach ($types as $type): ?>
                    <option value="<?= e($type) ?>" <?= ($filters['followup_type'] ?? '') === $type ? 'selected' : '' ?>>
                        <?= e($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="outcome">Outcome</label>
            <select class="form-select form-select-sm" id="outcome" name="outcome" data-auto-submit>
                <option value="">All outcomes</option>
                <?php foreach ($outcomes as $outcome): ?>
                    <option value="<?= e($outcome) ?>" <?= ($filters['outcome'] ?? '') === $outcome ? 'selected' : '' ?>>
                        <?= e($outcome) ?>
                    </option>
                <?php endforeach; ?>
            </select>
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
            <label class="form-label" for="responsible_user_id">Responsible</label>
            <select class="form-select form-select-sm" id="responsible_user_id" name="responsible_user_id" data-auto-submit>
                <option value="">Anyone</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int)$user['id'] ?>" <?= (int)($filters['responsible_user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>>
                        <?= e($user['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="date_from">From</label>
            <input type="date" class="form-control form-control-sm" id="date_from" name="date_from"
                   value="<?= e($filters['date_from'] ?? '') ?>" data-auto-submit>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="date_to">To</label>
            <input type="date" class="form-control form-control-sm" id="date_to" name="date_to"
                   value="<?= e($filters['date_to'] ?? '') ?>" data-date-min-from="date_from" data-auto-submit>
        </div>
        <div class="col-12 col-lg-4 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            <?php if ($activeFilters): ?>
                <a href="<?= e(url('/followups')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="jk-card">
    <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-journal-check"></i>Follow-up Register</h3></div>
    <div class="jk-card__body jk-card__body--flush">
        <?php if ($paginator->isEmpty()): ?>
            <div class="jk-empty">
                <i class="bi bi-telephone-x"></i>
                <h5>No follow-ups recorded</h5>
                <p><?= $activeFilters ? 'Nothing matched the filters.' : 'Follow-up calls and visits appear here once field teams start logging them.' ?></p>
                <?php if (can('followup.create')): ?>
                    <a href="<?= e(url('/followups/create')) ?>" class="btn btn-sm btn-primary">Record a follow-up</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap">
                <table class="jk-table">
                    <thead>
                        <tr>
                            <th><?= sort_link('Date', 'date', $sortKey, $sortDir) ?></th>
                            <th style="min-width:160px"><?= sort_link('Beneficiary', 'beneficiary', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Scheme', 'scheme', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Type', 'type', $sortKey, $sortDir) ?></th>
                            <th style="min-width:200px">Discussion</th>
                            <th><?= sort_link('Outcome', 'outcome', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Next', 'next', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('By', 'user', $sortKey, $sortDir) ?></th>
                            <th class="jk-table__actions no-print"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginator->items as $followup): ?>
                            <tr>
                                <td class="nowrap"><?= fmt_date($followup['followup_date']) ?></td>
                                <td>
                                    <a href="<?= e(url('/beneficiaries/' . (int)$followup['beneficiary_id'])) ?>" class="fw-semibold">
                                        <?= e(str_limit($followup['beneficiary_name'], 22)) ?>
                                    </a>
                                    <div class="text-muted tiny"><?= e($followup['beneficiary_code']) ?></div>
                                </td>
                                <td class="small"><?= e(str_limit($followup['scheme_short_name'] ?: ($followup['scheme_name'] ?? '-'), 24)) ?></td>
                                <td><span class="jk-chip"><?= e($followup['followup_type']) ?></span></td>
                                <td class="text-muted small"><?= e(str_limit($followup['discussion'] ?? '-', 100)) ?></td>
                                <td><?= badge($followup['outcome'], status_variant($followup['outcome'])) ?></td>
                                <td class="nowrap small <?= !empty($followup['next_followup_date']) && strtotime($followup['next_followup_date']) < strtotime('today') ? 'text-danger fw-semibold' : '' ?>">
                                    <?= fmt_date($followup['next_followup_date']) ?>
                                </td>
                                <td class="small text-muted"><?= e(str_limit($followup['responsible_name'] ?? $followup['created_by_name'] ?? '-', 16)) ?></td>
                                <td class="jk-table__actions no-print">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= e(url('/followups/' . (int)$followup['id'])) ?>"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (can('followup.edit')): ?>
                                            <a href="<?= e(url('/followups/' . (int)$followup['id'] . '/edit')) ?>"
                                               class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
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
            <?php $recordNoun = 'follow-up'; require VIEW_PATH . '/partials/pagination.php'; ?>
        </div>
    <?php endif; ?>
</div>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
  document.querySelectorAll('.jk-export').forEach(function (link) {
    link.addEventListener('click', function () {
      const params = new URLSearchParams(location.search);
      params.set('format', link.dataset.format);
      link.href = JK.url('/followups/export') + '?' + params.toString();
    });
  });
</script>
<?php \JanKavach\Core\View::endSection(); ?>
