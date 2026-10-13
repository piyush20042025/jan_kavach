<?php
/**
 * Application listing (specification section 21).
 *
 * @var JanKavach\Core\Paginator $paginator
 * @var array $filters, $statuses, $states, $districts, $schemes, $funnel
 */
$pageSubtitle = 'Beneficiary-scheme applications in ' . ($scopeLabel ?: 'the programme');
$activeFilters = array_filter($filters, fn($v) => $v !== null && $v !== '' && $v !== false);

ob_start(); ?>
    <?php if (can('application.create')): ?>
        <a href="<?= e(url('/applications/create')) ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Initiate Application
        </a>
    <?php endif; ?>
    <a href="<?= e(url('/applications/funnel')) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-funnel me-1"></i>Funnel
    </a>
    <?php if (can('application.export')): ?>
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
    <?php endif; ?>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<!-- Quick funnel strip -->
<div class="jk-card">
    <div class="jk-card__body jk-card__body--tight">
        <div class="d-flex flex-wrap gap-2">
            <?php foreach ($funnel as $stage): ?>
                <?php if (!empty($stage['aside'])) continue; ?>
                <a href="<?= e(query_url(['funnel_stage' => $stage['stage'], 'page' => null])) ?>"
                   class="jk-chip <?= ($filters['funnel_stage'] ?? '') === $stage['stage'] ? 'jk-chip--primary' : '' ?>">
                    <?= e($stage['label']) ?>
                    <strong class="ms-1"><?= num($stage['count']) ?></strong>
                </a>
            <?php endforeach; ?>
            <?php foreach ($funnel as $stage): ?>
                <?php if (empty($stage['aside']) || (int)$stage['count'] === 0) continue; ?>
                <a href="<?= e(query_url(['funnel_stage' => $stage['stage'], 'page' => null])) ?>"
                   class="jk-chip <?= ($filters['funnel_stage'] ?? '') === $stage['stage'] ? 'jk-chip--primary' : '' ?>">
                    <i class="bi <?= $stage['stage'] === 'Rejected' ? 'bi-x-circle text-danger' : 'bi-archive' ?>"></i>
                    <?= e($stage['label']) ?> <strong class="ms-1"><?= num($stage['count']) ?></strong>
                </a>
            <?php endforeach; ?>
            <?php if (!empty($filters['funnel_stage'])): ?>
                <a href="<?= e(query_url(['funnel_stage' => null, 'page' => null])) ?>" class="jk-chip text-danger">
                    <i class="bi bi-x"></i>Clear stage filter
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<form method="get" class="jk-filter-bar">
    <?php if (!empty($filters['funnel_stage'])): ?>
        <input type="hidden" name="funnel_stage" value="<?= e($filters['funnel_stage']) ?>">
    <?php endif; ?>
    <div class="row g-2">
        <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label" for="search">Search</label>
            <input type="search" class="form-control form-control-sm" id="search" name="search"
                   value="<?= e($filters['search'] ?? '') ?>"
                   placeholder="Beneficiary, application or department number">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="status_id">Status</label>
            <select class="form-select form-select-sm" id="status_id" name="status_id" data-auto-submit>
                <option value="">All statuses</option>
                <?php foreach ($statuses as $status): ?>
                    <option value="<?= (int)$status['id'] ?>" <?= (int)($filters['status_id'] ?? 0) === (int)$status['id'] ? 'selected' : '' ?>>
                        <?= e($status['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="scheme_id">Scheme</label>
            <select class="form-select form-select-sm" id="scheme_id" name="scheme_id" data-auto-submit>
                <option value="">All schemes</option>
                <?php foreach ($schemes as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['scheme_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e(str_limit($name, 36)) ?>
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
            <label class="form-label" for="followup_due">Follow-up</label>
            <select class="form-select form-select-sm" id="followup_due" name="followup_due" data-auto-submit>
                <option value="">Any</option>
                <option value="overdue"  <?= ($filters['followup_due'] ?? '') === 'overdue' ? 'selected' : '' ?>>Overdue</option>
                <option value="today"    <?= ($filters['followup_due'] ?? '') === 'today' ? 'selected' : '' ?>>Due today</option>
                <option value="upcoming" <?= ($filters['followup_due'] ?? '') === 'upcoming' ? 'selected' : '' ?>>Next 7 days</option>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="date_from">Created From</label>
            <input type="date" class="form-control form-control-sm" id="date_from" name="date_from"
                   value="<?= e($filters['date_from'] ?? '') ?>" data-auto-submit>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="date_to">Created To</label>
            <input type="date" class="form-control form-control-sm" id="date_to" name="date_to"
                   value="<?= e($filters['date_to'] ?? '') ?>" data-date-min-from="date_from" data-auto-submit>
        </div>
        <div class="col-12 col-lg-4 d-flex align-items-end gap-2 flex-wrap">
            <div class="form-check me-2">
                <input class="form-check-input" type="checkbox" id="pending_only" name="pending_only" value="1"
                       data-auto-submit <?= !empty($filters['pending_only']) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="pending_only">In progress only</label>
            </div>
            <div class="form-check me-2">
                <input class="form-check-input" type="checkbox" id="benefit_received" name="benefit_received" value="1"
                       data-auto-submit <?= !empty($filters['benefit_received']) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="benefit_received">Benefit received</label>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            <?php if ($activeFilters): ?>
                <a href="<?= e(url('/applications')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="jk-card">
    <div class="jk-card__body jk-card__body--flush">
        <?php if ($paginator->isEmpty()): ?>
            <div class="jk-empty">
                <i class="bi bi-file-earmark-x"></i>
                <h5>No applications found</h5>
                <p><?= $activeFilters
                        ? 'Nothing matched the current filters.'
                        : 'No beneficiary has been linked to a scheme yet. Start from a beneficiary\'s eligible-schemes list.' ?></p>
                <?php if (can('application.create')): ?>
                    <a href="<?= e(url('/applications/create')) ?>" class="btn btn-sm btn-primary">Initiate an application</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap">
                <table class="jk-table">
                    <thead>
                        <tr>
                            <th><?= sort_link('Application', 'code', $sortKey, $sortDir) ?></th>
                            <th style="min-width:170px"><?= sort_link('Beneficiary', 'beneficiary', $sortKey, $sortDir) ?></th>
                            <th style="min-width:180px"><?= sort_link('Scheme', 'scheme', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('District', 'district', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Status', 'status', $sortKey, $sortDir) ?></th>
                            <th>Eligibility</th>
                            <th><?= sort_link('Submitted', 'submitted', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Approved', 'approved', $sortKey, $sortDir) ?></th>
                            <th>Benefit</th>
                            <th><?= sort_link('Next Follow-up', 'followup', $sortKey, $sortDir) ?></th>
                            <th class="jk-table__actions no-print"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginator->items as $application): ?>
                            <?php
                            $overdue = !empty($application['next_followup_date'])
                                    && strtotime($application['next_followup_date']) < strtotime('today')
                                    && (int)$application['benefit_received'] === 0;
                            ?>
                            <tr class="<?= $overdue ? 'jk-row-warning' : '' ?>">
                                <td class="nowrap">
                                    <a href="<?= e(url('/applications/' . (int)$application['id'])) ?>" class="fw-semibold">
                                        <?= e($application['application_code']) ?>
                                    </a>
                                    <?php if (!empty($application['application_number'])): ?>
                                        <div class="text-muted tiny"><?= e(str_limit($application['application_number'], 20)) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= e(url('/beneficiaries/' . (int)$application['beneficiary_id'])) ?>">
                                        <?= e(str_limit($application['beneficiary_name'], 24)) ?>
                                    </a>
                                    <div class="text-muted tiny">
                                        <?= e($application['gender']) ?><?= $application['age'] !== null ? ', ' . (int)$application['age'] : '' ?>
                                    </div>
                                </td>
                                <td>
                                    <a href="<?= e(url('/schemes/' . (int)$application['scheme_id'])) ?>">
                                        <?= e(str_limit($application['scheme_short_name'] ?: $application['scheme_name'], 30)) ?>
                                    </a>
                                    <div class="text-muted tiny"><?= e($application['scheme_level']) ?></div>
                                </td>
                                <td class="small"><?= e($application['district_name'] ?? '-') ?></td>
                                <td>
                                    <span class="badge" style="background:<?= e($application['status_color'] ?: '#6c757d') ?>">
                                        <?= e($application['status_name']) ?>
                                    </span>
                                </td>
                                <td><?= badge($application['eligibility_status'], eligibility_variant($application['eligibility_status'])) ?></td>
                                <td class="nowrap small"><?= fmt_date($application['submission_date']) ?></td>
                                <td class="nowrap small"><?= fmt_date($application['approval_date']) ?></td>
                                <td>
                                    <?php if ((int)$application['benefit_received'] === 1): ?>
                                        <span class="badge bg-success">
                                            <?= $application['benefit_amount'] !== null
                                                ? short_money($application['benefit_amount']) : 'Received' ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-subtle">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="nowrap small <?= $overdue ? 'text-danger fw-semibold' : '' ?>">
                                    <?= fmt_date($application['next_followup_date']) ?>
                                    <?php if ($overdue): ?>
                                        <i class="bi bi-exclamation-triangle ms-1" data-bs-toggle="tooltip" title="Overdue"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="jk-table__actions no-print">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= e(url('/applications/' . (int)$application['id'])) ?>"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Open">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (can('followup.create')): ?>
                                            <a href="<?= e(url('/followups/create?application_id=' . (int)$application['id'])) ?>"
                                               class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Record follow-up">
                                                <i class="bi bi-telephone"></i>
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
            <?php $recordNoun = 'application'; require VIEW_PATH . '/partials/pagination.php'; ?>
        </div>
    <?php endif; ?>
</div>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
  document.querySelectorAll('.jk-export').forEach(function (link) {
    link.addEventListener('click', function () {
      const params = new URLSearchParams(location.search);
      params.set('format', link.dataset.format);
      link.href = JK.url('/applications/export') + '?' + params.toString();
    });
  });
</script>
<?php \JanKavach\Core\View::endSection(); ?>
