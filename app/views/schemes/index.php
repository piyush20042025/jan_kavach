<?php
/**
 * Scheme listing (specification section 15).
 *
 * @var JanKavach\Core\Paginator $paginator
 * @var array $filters, $options
 * @var string $sortKey, $sortDir
 * @var int $unrankedCount
 */
$pageSubtitle = 'Central and State social security schemes available to ' . ($scopeLabel ?: 'the programme');

$activeFilters = array_filter($filters, fn($v) => $v !== null && $v !== '');

ob_start(); ?>
    <?php if (can('scheme.create')): ?>
        <a href="<?= e(url('/schemes/create')) ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Add Scheme
        </a>
    <?php endif; ?>
    <?php if (can('scheme.import')): ?>
        <a href="<?= e(url('/import/schemes')) ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-upload me-1"></i>Import
        </a>
    <?php endif; ?>
    <?php if (can('scheme.export')): ?>
        <div class="btn-group">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-download me-1"></i>Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="<?= e(query_url(['format' => 'excel']) . '&' . http_build_query([]) ) ?>"
                       onclick="this.href='<?= e(url('/schemes/export')) ?>?'+new URLSearchParams(Object.assign(Object.fromEntries(new URLSearchParams(location.search)),{format:'excel'})).toString()">
                    <i class="bi bi-file-earmark-excel me-2"></i>Excel</a></li>
                <li><a class="dropdown-item" href="#"
                       onclick="this.href='<?= e(url('/schemes/export')) ?>?'+new URLSearchParams(Object.assign(Object.fromEntries(new URLSearchParams(location.search)),{format:'csv'})).toString()">
                    <i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
                <li><a class="dropdown-item" href="#" target="_blank"
                       onclick="this.href='<?= e(url('/schemes/export')) ?>?'+new URLSearchParams(Object.assign(Object.fromEntries(new URLSearchParams(location.search)),{format:'pdf'})).toString()">
                    <i class="bi bi-file-earmark-pdf me-2"></i>PDF / Print</a></li>
            </ul>
        </div>
    <?php endif; ?>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<?php if ($unrankedCount > 0 && can('scheme.edit')): ?>
    <div class="alert alert-info d-flex gap-2 align-items-start">
        <i class="bi bi-info-circle mt-1"></i>
        <div>
            <strong><?= num($unrankedCount) ?> scheme(s) have no priority ranking.</strong>
            The source workbook left the Priority and Feasibility columns blank, so they were imported as
            unranked rather than guessed. Set them per scheme under <em>Edit</em>, or manage the bands under
            <a href="<?= e(url('/masters/priorities')) ?>">Masters &rsaquo; Priority Ranking</a>.
        </div>
    </div>
<?php endif; ?>

<!-- Filters (section 15) -->
<form method="get" class="jk-filter-bar" id="schemeFilters">
    <div class="row g-2">
        <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label" for="search">Search</label>
            <div class="position-relative">
                <input type="search" class="form-control form-control-sm" id="search" name="search"
                       value="<?= e($filters['search'] ?? '') ?>"
                       placeholder="Scheme name, acronym or code">
            </div>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="scheme_level">National / State</label>
            <select class="form-select form-select-sm" id="scheme_level" name="scheme_level" data-auto-submit>
                <option value="">All</option>
                <?php foreach (['National', 'State', 'National+State'] as $level): ?>
                    <option value="<?= e($level) ?>" <?= ($filters['scheme_level'] ?? '') === $level ? 'selected' : '' ?>>
                        <?= e($level) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="state_id">State</label>
            <select class="form-select form-select-sm" id="state_id" name="state_id" data-auto-submit>
                <option value="">All States</option>
                <?php foreach ($options['states'] as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['state_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="theme_id">Theme</label>
            <select class="form-select form-select-sm" id="theme_id" name="theme_id" data-auto-submit>
                <option value="">All Themes</option>
                <?php foreach ($options['themes'] as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['theme_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="beneficiary_type_id">Beneficiary Type</label>
            <select class="form-select form-select-sm" id="beneficiary_type_id" name="beneficiary_type_id" data-auto-submit>
                <option value="">All</option>
                <?php foreach ($options['beneficiary_types'] as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['beneficiary_type_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="service_approach_id">Service Approach</label>
            <select class="form-select form-select-sm" id="service_approach_id" name="service_approach_id" data-auto-submit>
                <option value="">All</option>
                <?php foreach ($options['service_approaches'] as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['service_approach_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="priority_id">Priority</label>
            <select class="form-select form-select-sm" id="priority_id" name="priority_id" data-auto-submit>
                <option value="">All</option>
                <?php foreach ($options['priorities'] as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['priority_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e(str_limit($name, 34)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="feasibility_id">Feasibility</label>
            <select class="form-select form-select-sm" id="feasibility_id" name="feasibility_id" data-auto-submit>
                <option value="">All</option>
                <?php foreach ($options['feasibility'] as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['feasibility_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="scheme_status">Scheme Status</label>
            <select class="form-select form-select-sm" id="scheme_status" name="scheme_status" data-auto-submit>
                <option value="">All</option>
                <?php foreach (['Active', 'Dormant', 'Discontinued'] as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filters['scheme_status'] ?? '') === $status ? 'selected' : '' ?>>
                        <?= e($status) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="has_rules">Eligibility Rules</label>
            <select class="form-select form-select-sm" id="has_rules" name="has_rules" data-auto-submit>
                <option value="">All</option>
                <option value="yes" <?= ($filters['has_rules'] ?? '') === 'yes' ? 'selected' : '' ?>>Configured</option>
                <option value="no"  <?= ($filters['has_rules'] ?? '') === 'no'  ? 'selected' : '' ?>>Not configured</option>
            </select>
        </div>

        <div class="col-12 col-lg-4 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search me-1"></i>Search</button>
            <?php if ($activeFilters): ?>
                <a href="<?= e(url('/schemes')) ?>" class="btn btn-sm btn-outline-secondary">Clear all</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- Listing -->
<div class="jk-card">
    <div class="jk-card__body jk-card__body--flush">
        <?php if ($paginator->isEmpty()): ?>
            <div class="jk-empty">
                <i class="bi bi-journal-x"></i>
                <h5>No schemes matched</h5>
                <p>
                    <?= $activeFilters
                        ? 'Try widening or clearing the filters above.'
                        : 'The scheme master is empty. Import the scheme workbook or add a scheme manually to begin.' ?>
                </p>
                <div class="d-flex gap-2 justify-content-center">
                    <?php if ($activeFilters): ?>
                        <a href="<?= e(url('/schemes')) ?>" class="btn btn-sm btn-outline-secondary">Clear filters</a>
                    <?php endif; ?>
                    <?php if (can('scheme.create')): ?>
                        <a href="<?= e(url('/schemes/create')) ?>" class="btn btn-sm btn-primary">Add a scheme</a>
                    <?php endif; ?>
                    <?php if (can('scheme.import')): ?>
                        <a href="<?= e(url('/import/schemes')) ?>" class="btn btn-sm btn-outline-primary">Import from Excel</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap">
                <table class="jk-table">
                    <thead>
                        <tr>
                            <th style="min-width:110px"><?= sort_link('Scheme ID', 'code', $sortKey, $sortDir) ?></th>
                            <th style="min-width:270px"><?= sort_link('Scheme Name', 'name', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Level', 'level', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('State', 'state', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Theme', 'theme', $sortKey, $sortDir) ?></th>
                            <th style="min-width:150px">Beneficiary Type</th>
                            <th><?= sort_link('Priority', 'priority', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Feasibility', 'feasibility', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Status', 'status', $sortKey, $sortDir) ?></th>
                            <th class="num"><?= sort_link('Docs', 'documents', $sortKey, $sortDir) ?></th>
                            <th class="num"><?= sort_link('Rules', 'rules', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Updated', 'updated', $sortKey, $sortDir) ?></th>
                            <th class="jk-table__actions no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginator->items as $scheme): ?>
                            <tr<?= (int)$scheme['is_active'] === 0 ? ' class="opacity-75"' : '' ?>>
                                <td class="nowrap">
                                    <code class="small"><?= e($scheme['scheme_code']) ?></code>
                                </td>
                                <td>
                                    <a href="<?= e(url('/schemes/' . (int)$scheme['id'])) ?>" class="fw-semibold">
                                        <?= e(str_limit($scheme['name'], 62)) ?>
                                    </a>
                                    <?php if (!empty($scheme['short_name'])): ?>
                                        <span class="badge bg-light text-dark border ms-1"><?= e($scheme['short_name']) ?></span>
                                    <?php endif; ?>
                                    <?php if ((int)$scheme['is_active'] === 0): ?>
                                        <span class="badge bg-secondary ms-1">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="nowrap">
                                    <?php if ($scheme['scheme_level'] === 'National'): ?>
                                        <span class="badge bg-primary">National</span>
                                    <?php elseif ($scheme['scheme_level'] === 'National+State'): ?>
                                        <span class="badge bg-info text-dark">National + State</span>
                                    <?php else: ?>
                                        <span class="badge" style="background:#2a9d8f">State</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= e($scheme['state_name'] ?? '-') ?></td>
                                <td>
                                    <?php if (!empty($scheme['theme_name'])): ?>
                                        <span class="jk-theme-dot" style="background:<?= e($scheme['theme_color'] ?: '#6c757d') ?>"></span>
                                        <?= e($scheme['theme_name']) ?>
                                    <?php else: ?>
                                        <span class="text-subtle">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= e(str_limit($scheme['beneficiary_types'] ?? '-', 34)) ?></td>
                                <td class="nowrap">
                                    <?php if (!empty($scheme['priority_rank'])): ?>
                                        <span class="badge bg-danger">P<?= (int)$scheme['priority_rank'] ?></span>
                                    <?php else: ?>
                                        <span class="text-subtle tiny">Not ranked</span>
                                    <?php endif; ?>
                                </td>
                                <td class="nowrap">
                                    <?php if (!empty($scheme['feasibility_rank'])): ?>
                                        <span class="badge bg-success"><?= (int)$scheme['feasibility_rank'] ?>/5</span>
                                    <?php else: ?>
                                        <span class="text-subtle tiny">Not ranked</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= badge($scheme['scheme_status'], status_variant($scheme['scheme_status'])) ?></td>
                                <td class="num">
                                    <?php if ((int)$scheme['document_count'] > 0): ?>
                                        <?= (int)$scheme['document_count'] ?>
                                    <?php else: ?>
                                        <span class="text-subtle">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="num">
                                    <?php if ((int)$scheme['rule_count'] > 0): ?>
                                        <span class="badge bg-success-subtle text-success"><?= (int)$scheme['rule_count'] ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis"
                                              data-bs-toggle="tooltip"
                                              title="Without rules this scheme cannot be matched automatically">none</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted tiny nowrap"><?= fmt_date($scheme['updated_at']) ?></td>
                                <td class="jk-table__actions no-print">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= e(url('/schemes/' . (int)$scheme['id'])) ?>"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (can('scheme.edit')): ?>
                                            <a href="<?= e(url('/schemes/' . (int)$scheme['id'] . '/edit')) ?>"
                                               class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (can('scheme.rules')): ?>
                                            <a href="<?= e(url('/schemes/' . (int)$scheme['id'] . '/rules')) ?>"
                                               class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Eligibility rules">
                                                <i class="bi bi-sliders"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split"
                                                data-bs-toggle="dropdown" aria-label="More actions"></button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item" href="<?= e(url('/schemes/' . (int)$scheme['id'])) ?>">
                                                <i class="bi bi-eye me-2"></i>View details</a></li>
                                            <li><a class="dropdown-item" href="<?= e(url('/schemes/' . (int)$scheme['id'] . '/print')) ?>" target="_blank">
                                                <i class="bi bi-printer me-2"></i>Print scheme</a></li>
                                            <?php if (can('scheme.edit')): ?>
                                                <li><a class="dropdown-item" href="<?= e(url('/schemes/' . (int)$scheme['id'] . '/documents')) ?>">
                                                    <i class="bi bi-file-earmark-text me-2"></i>Required documents</a></li>
                                            <?php endif; ?>
                                            <?php if (can('scheme.create')): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="post" action="<?= e(url('/schemes/' . (int)$scheme['id'] . '/copy')) ?>" class="m-0">
                                                        <?= csrf_field() ?>
                                                        <button class="dropdown-item" type="submit"
                                                                data-confirm="Create a copy of this scheme? The copy starts inactive.">
                                                            <i class="bi bi-files me-2"></i>Copy scheme
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            <?php if (can('scheme.edit')): ?>
                                                <li>
                                                    <form method="post" action="<?= e(url('/schemes/' . (int)$scheme['id'] . '/toggle')) ?>" class="m-0">
                                                        <?= csrf_field() ?>
                                                        <button class="dropdown-item" type="submit">
                                                            <i class="bi bi-<?= (int)$scheme['is_active'] === 1 ? 'pause' : 'play' ?>-circle me-2"></i>
                                                            <?= (int)$scheme['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                            <?php if (can('scheme.delete')): ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="post" action="<?= e(url('/schemes/' . (int)$scheme['id'] . '/delete')) ?>" class="m-0">
                                                        <?= csrf_field() ?>
                                                        <button class="dropdown-item text-danger" type="submit"
                                                                data-confirm="Delete this scheme permanently? This cannot be undone.">
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
            <?php $recordNoun = 'scheme'; require VIEW_PATH . '/partials/pagination.php'; ?>
        </div>
    <?php endif; ?>
</div>
