<?php
/**
 * Beneficiary register listing.
 *
 * Sensitive identifiers are masked here by design (specification section 30):
 * Aadhaar is never shown in a list, and the mobile number is partially hidden.
 *
 * @var JanKavach\Core\Paginator $paginator
 * @var array $filters, $states, $districts, $fieldUsers, $schemes
 */
$pageSubtitle = 'Beneficiary records for ' . ($scopeLabel ?: 'the whole programme');
$activeFilters = array_filter($filters, fn($v) => $v !== null && $v !== '' && $v !== false);
$maskMobile = (bool)(setting('mask_mobile_in_lists') ?? true);

ob_start(); ?>
    <?php if (can('beneficiary.create')): ?>
        <a href="<?= e(url('/beneficiaries/create')) ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-person-plus me-1"></i>Register Beneficiary
        </a>
    <?php endif; ?>
    <?php if (can('beneficiary.match')): ?>
        <a href="<?= e(url('/matching')) ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-check2-square me-1"></i>Scheme Matching
        </a>
    <?php endif; ?>
    <?php if (can('beneficiary.export')): ?>
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

<form method="get" class="jk-filter-bar">
    <div class="row g-2">
        <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label" for="search">Search</label>
            <input type="search" class="form-control form-control-sm" id="search" name="search"
                   value="<?= e($filters['search'] ?? '') ?>" placeholder="Name, beneficiary ID or mobile">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="state_id">State</label>
            <select class="form-select form-select-sm" id="state_id" name="state_id" data-auto-submit>
                <option value="">All States</option>
                <?php foreach ($states as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['state_id'] ?? 0) === (int)$id ? 'selected' : '' ?>><?= e($name) ?></option>
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
                    <option value="<?= (int)$id ?>" <?= (int)($filters['district_id'] ?? 0) === (int)$id ? 'selected' : '' ?>><?= e($name) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-1">
            <label class="form-label" for="gender">Gender</label>
            <select class="form-select form-select-sm" id="gender" name="gender" data-auto-submit>
                <option value="">All</option>
                <?php foreach (['Male', 'Female', 'Other'] as $gender): ?>
                    <option value="<?= e($gender) ?>" <?= ($filters['gender'] ?? '') === $gender ? 'selected' : '' ?>><?= e($gender) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-1">
            <label class="form-label" for="age_group">Age</label>
            <select class="form-select form-select-sm" id="age_group" name="age_group" data-auto-submit>
                <option value="">All</option>
                <?php foreach (['0-5', '6-14', '15-17', '18-35', '36-59', '60+'] as $group): ?>
                    <option value="<?= e($group) ?>" <?= ($filters['age_group'] ?? '') === $group ? 'selected' : '' ?>><?= e($group) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-1">
            <label class="form-label" for="area_type">Area</label>
            <select class="form-select form-select-sm" id="area_type" name="area_type" data-auto-submit>
                <option value="">All</option>
                <option value="Rural" <?= ($filters['area_type'] ?? '') === 'Rural' ? 'selected' : '' ?>>Rural</option>
                <option value="Urban" <?= ($filters['area_type'] ?? '') === 'Urban' ? 'selected' : '' ?>>Urban</option>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="record_status">Record Status</label>
            <select class="form-select form-select-sm" id="record_status" name="record_status" data-auto-submit>
                <option value="">All</option>
                <?php foreach (['Registered', 'Assessed', 'Matched', 'In Process', 'Benefited', 'Closed', 'Rejected'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['record_status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="assessment_status">Assessment</label>
            <select class="form-select form-select-sm" id="assessment_status" name="assessment_status" data-auto-submit>
                <option value="">All</option>
                <?php foreach (['Pending', 'In Progress', 'Completed'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['assessment_status'] ?? '') === $status ? 'selected' : '' ?>><?= e($status) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
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
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="scheme_id">Matched to Scheme</label>
            <select class="form-select form-select-sm" id="scheme_id" name="scheme_id" data-auto-submit>
                <option value="">Any scheme</option>
                <?php foreach ($schemes as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['scheme_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e(str_limit($name, 36)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="date_from">Registered From</label>
            <input type="date" class="form-control form-control-sm" id="date_from" name="date_from"
                   value="<?= e($filters['date_from'] ?? '') ?>" data-auto-submit>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="date_to">Registered To</label>
            <input type="date" class="form-control form-control-sm" id="date_to" name="date_to"
                   value="<?= e($filters['date_to'] ?? '') ?>" data-date-min-from="date_from" data-auto-submit>
        </div>
        <div class="col-12 col-lg-4 d-flex align-items-end gap-2">
            <div class="form-check me-2">
                <input class="form-check-input" type="checkbox" id="has_documents_pending"
                       name="has_documents_pending" value="1" data-auto-submit
                       <?= !empty($filters['has_documents_pending']) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="has_documents_pending">Documents pending only</label>
            </div>
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Search</button>
            <?php if ($activeFilters): ?>
                <a href="<?= e(url('/beneficiaries')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="jk-card">
    <div class="jk-card__body jk-card__body--flush">
        <?php if ($paginator->isEmpty()): ?>
            <div class="jk-empty">
                <i class="bi bi-person-x"></i>
                <h5>No beneficiaries found</h5>
                <p><?= $activeFilters
                        ? 'No record matched the filters. Try widening them.'
                        : 'No beneficiary has been registered in your area yet.' ?></p>
                <?php if (can('beneficiary.create')): ?>
                    <a href="<?= e(url('/beneficiaries/create')) ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-person-plus me-1"></i>Register a beneficiary
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap">
                <table class="jk-table">
                    <thead>
                        <tr>
                            <th><?= sort_link('Beneficiary ID', 'code', $sortKey, $sortDir) ?></th>
                            <th style="min-width:180px"><?= sort_link('Name', 'name', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Gender', 'gender', $sortKey, $sortDir) ?></th>
                            <th class="num"><?= sort_link('Age', 'age', $sortKey, $sortDir) ?></th>
                            <th>Mobile</th>
                            <th><?= sort_link('District', 'district', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Village', 'village', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Registered', 'registered', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Assessment', 'assessment', $sortKey, $sortDir) ?></th>
                            <th class="num"><?= sort_link('Schemes', 'schemes', $sortKey, $sortDir) ?></th>
                            <th class="num">Apps</th>
                            <th><?= sort_link('Status', 'status', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Field User', 'field_user', $sortKey, $sortDir) ?></th>
                            <th class="jk-table__actions no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginator->items as $beneficiary): ?>
                            <tr>
                                <td class="nowrap"><code class="small"><?= e($beneficiary['beneficiary_code']) ?></code></td>
                                <td>
                                    <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'])) ?>" class="fw-semibold">
                                        <?= e($beneficiary['name']) ?>
                                    </a>
                                </td>
                                <td><?= e($beneficiary['gender']) ?></td>
                                <td class="num"><?= $beneficiary['age'] !== null ? (int)$beneficiary['age'] : '-' ?></td>
                                <td class="nowrap">
                                    <?php if (!empty($beneficiary['mobile'])): ?>
                                        <span class="jk-masked"><?= e($maskMobile ? mask_mobile($beneficiary['mobile']) : $beneficiary['mobile']) ?></span>
                                    <?php else: ?>
                                        <span class="text-subtle">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($beneficiary['district_name'] ?? '-') ?></td>
                                <td class="text-muted"><?= e(str_limit($beneficiary['village_name'] ?? '-', 22)) ?></td>
                                <td class="nowrap"><?= fmt_date($beneficiary['registration_date']) ?></td>
                                <td>
                                    <?= badge($beneficiary['assessment_status'],
                                              $beneficiary['assessment_status'] === 'Completed' ? 'success'
                                              : ($beneficiary['assessment_status'] === 'In Progress' ? 'warning' : 'secondary')) ?>
                                </td>
                                <td class="num">
                                    <?php if ((int)$beneficiary['schemes_identified'] > 0): ?>
                                        <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/matching')) ?>"
                                           class="badge bg-success-subtle text-success text-decoration-none">
                                            <?= (int)$beneficiary['schemes_identified'] ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-subtle">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="num">
                                    <?= (int)$beneficiary['applications_count'] > 0
                                        ? (int)$beneficiary['applications_count']
                                        : '<span class="text-subtle">-</span>' ?>
                                </td>
                                <td><?= badge($beneficiary['record_status'], status_variant($beneficiary['record_status'])) ?></td>
                                <td class="text-muted small"><?= e(str_limit($beneficiary['field_user_name'] ?? '-', 18)) ?></td>
                                <td class="jk-table__actions no-print">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'])) ?>"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="360&deg; profile">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (can('beneficiary.assess')): ?>
                                            <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/assessment')) ?>"
                                               class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Assessment">
                                                <i class="bi bi-clipboard-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (can('beneficiary.match')): ?>
                                            <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/matching')) ?>"
                                               class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Eligible schemes">
                                                <i class="bi bi-check2-square"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split"
                                                data-bs-toggle="dropdown" aria-label="More"></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <?php if (can('beneficiary.edit')): ?>
                                                <li><a class="dropdown-item" href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/edit')) ?>">
                                                    <i class="bi bi-pencil me-2"></i>Edit details</a></li>
                                            <?php endif; ?>
                                            <?php if (can('beneficiary.document')): ?>
                                                <li><a class="dropdown-item" href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/documents')) ?>">
                                                    <i class="bi bi-folder me-2"></i>Documents
                                                    <?php if ((int)$beneficiary['document_count'] > 0): ?>
                                                        <span class="badge bg-secondary"><?= (int)$beneficiary['document_count'] ?></span>
                                                    <?php endif; ?>
                                                </a></li>
                                            <?php endif; ?>
                                            <?php if (can('application.create')): ?>
                                                <li><a class="dropdown-item" href="<?= e(url('/applications/create?beneficiary_id=' . (int)$beneficiary['id'])) ?>">
                                                    <i class="bi bi-file-earmark-plus me-2"></i>Initiate application</a></li>
                                            <?php endif; ?>
                                            <li><a class="dropdown-item" href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/print')) ?>" target="_blank">
                                                <i class="bi bi-printer me-2"></i>Print profile</a></li>
                                            <?php if (can('beneficiary.delete')): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="post" action="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/delete')) ?>" class="m-0">
                                                        <?= csrf_field() ?>
                                                        <button class="dropdown-item text-danger" type="submit"
                                                                data-confirm="Delete this beneficiary record? This cannot be undone.">
                                                            <i class="bi bi-trash me-2"></i>Delete
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
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
            <?php $recordNoun = 'beneficiary'; require VIEW_PATH . '/partials/pagination.php'; ?>
        </div>
    <?php endif; ?>
</div>

<p class="text-muted small">
    <i class="bi bi-shield-lock me-1"></i>
    Aadhaar numbers are never shown in listings and mobile numbers are partially masked.
    Open a beneficiary's profile to see the full contact details held for them.
</p>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
  document.querySelectorAll('.jk-export').forEach(function (link) {
    link.addEventListener('click', function () {
      const params = new URLSearchParams(location.search);
      params.set('format', link.dataset.format);
      link.href = JK.url('/beneficiaries/export') + '?' + params.toString();
    });
  });
</script>
<?php \JanKavach\Core\View::endSection(); ?>
