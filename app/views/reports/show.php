<?php
/**
 * Generic report renderer: the controller supplies the columns, rows and
 * a small summary, and this view presents any of them.
 *
 * @var string $report
 * @var array  $definition, $columns, $rows, $summary, $filters, $options
 */
$pageSubtitle = $definition['description'];
$allowed = $definition['filters'] ?? [];
$limit   = (int)(setting('export_row_limit') ?? 20000);

ob_start(); ?>
    <a href="<?= e(url('/reports')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>All Reports
    </a>
    <?php if (can('report.export')): ?>
        <div class="btn-group">
            <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-download me-1"></i>Export
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item jk-export" data-format="excel" href="#"><i class="bi bi-file-earmark-excel me-2"></i>Excel (.xlsx)</a></li>
                <li><a class="dropdown-item jk-export" data-format="csv" href="#"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
                <li><a class="dropdown-item jk-export" data-format="pdf" target="_blank" href="#"><i class="bi bi-file-earmark-pdf me-2"></i>PDF / Print</a></li>
            </ul>
        </div>
    <?php endif; ?>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<!-- Filters, drawn from the report's own declaration -->
<form method="get" class="jk-filter-bar">
    <div class="row g-2">
        <?php if (in_array('date_from', $allowed, true)): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="date_from">From Date</label>
                <input type="date" class="form-control form-control-sm" id="date_from" name="date_from"
                       value="<?= e($filters['date_from'] ?? '') ?>">
            </div>
        <?php endif; ?>
        <?php if (in_array('date_to', $allowed, true)): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="date_to">To Date</label>
                <input type="date" class="form-control form-control-sm" id="date_to" name="date_to"
                       value="<?= e($filters['date_to'] ?? '') ?>" data-date-min-from="date_from">
            </div>
        <?php endif; ?>

        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="state_id">State</label>
            <select class="form-select form-select-sm" id="state_id" name="state_id">
                <option value="">All States</option>
                <?php foreach (($options['states'] ?? []) as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['state_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="district_id">District</label>
            <select class="form-select form-select-sm" id="district_id" name="district_id"
                    data-depends-on="state_id" data-server-filled="1"
                    data-endpoint="/geo/districts/{id}" data-placeholder="All Districts">
                <option value="">All Districts</option>
                <?php foreach (($options['districts'] ?? []) as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['district_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (in_array('scheme_id', $allowed, true)): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="scheme_id">Scheme</label>
                <select class="form-select form-select-sm" id="scheme_id" name="scheme_id">
                    <option value="">All schemes</option>
                    <?php foreach (($options['schemes'] ?? []) as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)($filters['scheme_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                            <?= e(str_limit($name, 36)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if (in_array('theme_id', $allowed, true)): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="theme_id">Theme</label>
                <select class="form-select form-select-sm" id="theme_id" name="theme_id">
                    <option value="">All themes</option>
                    <?php foreach (($options['themes'] ?? []) as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)($filters['theme_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if (in_array('beneficiary_type_id', $allowed, true)): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="beneficiary_type_id">Beneficiary Type</label>
                <select class="form-select form-select-sm" id="beneficiary_type_id" name="beneficiary_type_id">
                    <option value="">All</option>
                    <?php foreach (($options['beneficiary_types'] ?? []) as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)($filters['beneficiary_type_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if (in_array('status_id', $allowed, true)): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="status_id">Status</label>
                <select class="form-select form-select-sm" id="status_id" name="status_id">
                    <option value="">All statuses</option>
                    <?php foreach (($options['statuses'] ?? []) as $id => $name): ?>
                        <option value="<?= (int)$id ?>" <?= (int)($filters['status_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                            <?= e($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if (in_array('field_user_id', $allowed, true)): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="field_user_id">Field User</label>
                <select class="form-select form-select-sm" id="field_user_id" name="field_user_id">
                    <option value="">All users</option>
                    <?php foreach (($options['field_users'] ?? []) as $user): ?>
                        <option value="<?= (int)$user['id'] ?>" <?= (int)($filters['field_user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>>
                            <?= e($user['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if (in_array('gender', $allowed, true)): ?>
            <div class="col-6 col-md-3 col-lg-1">
                <label class="form-label" for="gender">Gender</label>
                <select class="form-select form-select-sm" id="gender" name="gender">
                    <option value="">All</option>
                    <?php foreach (['Male', 'Female', 'Other'] as $gender): ?>
                        <option value="<?= e($gender) ?>" <?= ($filters['gender'] ?? '') === $gender ? 'selected' : '' ?>>
                            <?= e($gender) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if (in_array('area_type', $allowed, true)): ?>
            <div class="col-6 col-md-3 col-lg-1">
                <label class="form-label" for="area_type">Area</label>
                <select class="form-select form-select-sm" id="area_type" name="area_type">
                    <option value="">All</option>
                    <option value="Rural" <?= ($filters['area_type'] ?? '') === 'Rural' ? 'selected' : '' ?>>Rural</option>
                    <option value="Urban" <?= ($filters['area_type'] ?? '') === 'Urban' ? 'selected' : '' ?>>Urban</option>
                </select>
            </div>
        <?php endif; ?>

        <?php if (in_array('scheme_level', $allowed, true)): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="scheme_level">Scheme Level</label>
                <select class="form-select form-select-sm" id="scheme_level" name="scheme_level">
                    <option value="">All</option>
                    <?php foreach (['National', 'State', 'National+State'] as $level): ?>
                        <option value="<?= e($level) ?>" <?= ($filters['scheme_level'] ?? '') === $level ? 'selected' : '' ?>>
                            <?= e($level) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if (in_array('scheme_status', $allowed, true)): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="scheme_status">Scheme Status</label>
                <select class="form-select form-select-sm" id="scheme_status" name="scheme_status">
                    <option value="">All</option>
                    <?php foreach (['Active', 'Dormant', 'Discontinued'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($filters['scheme_status'] ?? '') === $status ? 'selected' : '' ?>>
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if (in_array('eligibility_status', $allowed, true)): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="eligibility_status">Eligibility</label>
                <select class="form-select form-select-sm" id="eligibility_status" name="eligibility_status">
                    <option value="">All applicable</option>
                    <?php foreach (['Eligible', 'Potentially Eligible', 'Needs Verification', 'Not Eligible'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= ($filters['eligibility_status'] ?? '') === $status ? 'selected' : '' ?>>
                            <?= e($status) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if (in_array('followup_type', $allowed, true)): ?>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label" for="followup_type">Follow-up Type</label>
                <select class="form-select form-select-sm" id="followup_type" name="followup_type">
                    <option value="">All types</option>
                    <?php foreach (['Phone Call', 'Home Visit', 'Department Visit', 'Document Collection',
                                    'SMS/WhatsApp', 'Camp', 'Other'] as $type): ?>
                        <option value="<?= e($type) ?>" <?= ($filters['followup_type'] ?? '') === $type ? 'selected' : '' ?>>
                            <?= e($type) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="col-12 col-lg-3 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Run Report</button>
            <a href="<?= e(url('/reports/' . $report)) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </div>
</form>

<!-- Summary strip -->
<?php if (!empty($summary)): ?>
    <div class="jk-card">
        <div class="jk-card__body jk-card__body--tight">
            <div class="d-flex flex-wrap gap-3">
                <?php foreach ($summary as $label => $value): ?>
                    <div class="px-2">
                        <div class="text-muted tiny text-uppercase" style="letter-spacing:.05em"><?= e($label) ?></div>
                        <div class="h6 mb-0"><?= is_numeric($value) ? num($value) : e($value) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="jk-card">
    <div class="jk-card__head">
        <h3 class="jk-card__title"><i class="bi <?= e($definition['icon']) ?>"></i><?= e($definition['title']) ?></h3>
        <span class="text-muted small">
            <?= num(count($rows)) ?> row<?= count($rows) === 1 ? '' : 's' ?>
            <?php if (count($rows) >= $limit): ?>
                <span class="badge bg-warning text-dark ms-1">capped at the export limit</span>
            <?php endif; ?>
        </span>
    </div>
    <div class="jk-card__body jk-card__body--flush">
        <?php if (empty($rows)): ?>
            <div class="jk-empty">
                <i class="bi bi-inbox"></i>
                <h5>No data for this report</h5>
                <p>Nothing matched the filters, or there is no data of this kind in your area yet.</p>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap" style="max-height:640px">
                <table class="jk-table jk-table--compact">
                    <thead>
                        <tr>
                            <th style="width:46px" class="num">#</th>
                            <?php foreach (array_keys($columns) as $header): ?>
                                <th class="<?= preg_match('/(count|amount|total|%|no\.|submitted|approved|benefited|registered|assessed|applications|schemes|documents|missing|days|visits|follow-ups|initiated|villages|users|match)/i', $header) ? 'num' : '' ?>">
                                    <?= e($header) ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($rows, 0, 1000) as $index => $row): ?>
                            <tr>
                                <td class="num text-subtle"><?= $index + 1 ?></td>
                                <?php foreach ($columns as $header => $definitionColumn): ?>
                                    <?php
                                    $cell = is_callable($definitionColumn)
                                        ? $definitionColumn($row)
                                        : ($row[$definitionColumn] ?? null);
                                    $numeric = is_int($cell) || is_float($cell)
                                            || (is_string($cell) && $cell !== '' && is_numeric($cell));
                                    ?>
                                    <td class="<?= $numeric ? 'num' : '' ?>">
                                        <?php
                                        if ($cell === null || $cell === '') {
                                            echo '<span class="text-subtle">-</span>';
                                        } elseif (is_float($cell) || (is_string($cell) && preg_match('/^\d+\.\d+$/', $cell))) {
                                            echo e(rtrim(rtrim(number_format((float)$cell, 2), '0'), '.'));
                                        } elseif (is_int($cell)) {
                                            echo num($cell);
                                        } else {
                                            echo e(str_limit((string)$cell, 180));
                                        }
                                        ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (count($rows) > 1000): ?>
                <div class="p-3 border-top text-muted small">
                    <i class="bi bi-info-circle me-1"></i>
                    Showing the first 1,000 of <?= num(count($rows)) ?> rows on screen.
                    The export contains every row.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
  document.querySelectorAll('.jk-export').forEach(function (link) {
    link.addEventListener('click', function () {
      const params = new URLSearchParams(location.search);
      params.set('format', link.dataset.format);
      link.href = JK.url('/reports/<?= e($report) ?>/export') + '?' + params.toString();
    });
  });
</script>
<?php \JanKavach\Core\View::endSection(); ?>
