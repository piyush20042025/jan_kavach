<?php
/**
 * Import preview: column mapping, per-row validation and the decision on
 * what to do with each row (specification section 37).
 *
 * @var array $batch, $headers, $map, $fields, $rows, $counts
 * @var string|null $statusFilter
 */
$pageSubtitle = $batch['original_name'] . ' &middot; ' . $batch['batch_code'];
$isImported = $batch['status'] === 'Imported';

ob_start(); ?>
    <a href="<?= e(url('/import/schemes')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Imports
    </a>
    <?php if ((int)$counts['Error'] > 0): ?>
        <a href="<?= e(url('/import/schemes/' . (int)$batch['id'] . '/errors')) ?>"
           class="btn btn-sm btn-outline-danger">
            <i class="bi bi-exclamation-triangle me-1"></i><?= (int)$counts['Error'] ?> Rejected
        </a>
    <?php endif; ?>
    <?php if (!$isImported && $batch['status'] !== 'Cancelled'): ?>
        <form method="post" action="<?= e(url('/import/schemes/' . (int)$batch['id'] . '/cancel')) ?>" class="d-inline">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-danger" type="submit"
                    data-confirm="Cancel this import? The uploaded file is removed and nothing is written.">
                Cancel Import
            </button>
        </form>
    <?php endif; ?>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<?php if ($isImported): ?>
    <div class="alert alert-success d-flex gap-2 align-items-start">
        <i class="bi bi-check-circle mt-1"></i>
        <div>
            <strong>Import complete.</strong> <?= e($batch['message'] ?? '') ?>
            Completed <?= fmt_datetime($batch['completed_at']) ?>.
            <div class="mt-2">
                <a href="<?= e(url('/schemes')) ?>" class="btn btn-sm btn-primary">View the scheme master</a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Row status summary -->
<div class="jk-kpi-grid">
    <?php
    $tiles = [
        ['value' => num($batch['total_rows']), 'label' => 'Scheme Records Read', 'icon' => 'bi-list-ol',        'color' => '#0f4c81', 'soft' => '#e8f0f8'],
        ['value' => num($counts['Valid']),     'label' => 'Ready to Import',     'icon' => 'bi-check-circle',   'color' => '#198754', 'soft' => '#e6f4ec'],
        ['value' => num($counts['Duplicate']), 'label' => 'Possible Duplicates', 'icon' => 'bi-files',          'color' => '#f59e0b', 'soft' => '#fdf3e0'],
        ['value' => num($counts['Error']),     'label' => 'Errors',              'icon' => 'bi-x-circle',       'color' => '#dc3545', 'soft' => '#fdecec'],
        ['value' => num($counts['Inserted']),  'label' => 'Inserted',            'icon' => 'bi-plus-circle',    'color' => '#146c43', 'soft' => '#e3f2e9'],
        ['value' => num($counts['Updated']),   'label' => 'Updated',             'icon' => 'bi-pencil-square',  'color' => '#1a6bb0', 'soft' => '#e8f0f8'],
        ['value' => num($counts['Skipped']),   'label' => 'Skipped',             'icon' => 'bi-skip-forward',   'color' => '#6c757d', 'soft' => '#eef0f2'],
    ];
    foreach ($tiles as $tile) {
        extract(['kpiValue' => $tile['value'], 'kpiLabel' => $tile['label'], 'kpiIcon' => $tile['icon'],
                 'kpiColor' => $tile['color'], 'kpiSoft' => $tile['soft'], 'kpiUrl' => null, 'kpiHint' => null]);
        include VIEW_PATH . '/partials/kpi.php';
    }
    ?>
</div>

<!-- Column mapping -->
<?php if (!$isImported): ?>
    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-arrow-left-right"></i>Column Mapping</h3>
            <button class="btn btn-sm btn-outline-secondary" type="button"
                    data-bs-toggle="collapse" data-bs-target="#mappingPanel">
                Adjust mapping
            </button>
        </div>
        <div class="collapse" id="mappingPanel">
            <div class="jk-card__body">
                <p class="text-muted small">
                    Each field below is matched to a column in your file. Change any that were guessed wrongly,
                    then re-validate.
                </p>
                <form method="get" action="<?= e(url('/import/schemes/' . (int)$batch['id'] . '/preview')) ?>">
                    <input type="hidden" name="revalidate" value="1">
                    <div class="row g-2">
                        <?php foreach ($fields as $field => $label): ?>
                            <div class="col-md-4 col-lg-3">
                                <label class="form-label" for="map_<?= e($field) ?>">
                                    <?= e($label) ?>
                                    <?php if (in_array($field, ['name', 'natstate'], true)): ?>
                                        <span class="req">*</span>
                                    <?php endif; ?>
                                </label>
                                <select class="form-select form-select-sm" id="map_<?= e($field) ?>"
                                        name="map[<?= e($field) ?>]">
                                    <option value="">Not mapped</option>
                                    <?php foreach ($headers as $index => $header): ?>
                                        <?php if (trim((string)$header) === '') continue; ?>
                                        <option value="<?= (int)$index ?>"
                                            <?= isset($map[$field]) && (int)$map[$field] === (int)$index ? 'selected' : '' ?>>
                                            <?= e(str_limit($header, 44)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary mt-3">
                        <i class="bi bi-arrow-repeat me-1"></i>Re-validate with this mapping
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Row preview -->
<form method="post" action="<?= e(url('/import/schemes/' . (int)$batch['id'] . '/process')) ?>" data-guard>
<?= csrf_field() ?>

<div class="jk-card">
    <div class="jk-card__head">
        <h3 class="jk-card__title"><i class="bi bi-table"></i>Rows</h3>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?php foreach (['' => 'All', 'Valid' => 'Ready', 'Duplicate' => 'Duplicates', 'Error' => 'Errors',
                            'Inserted' => 'Inserted', 'Updated' => 'Updated', 'Skipped' => 'Skipped'] as $key => $label): ?>
                <?php if ($key !== '' && (int)($counts[$key] ?? 0) === 0) continue; ?>
                <a href="<?= e(query_url(['status' => $key ?: null])) ?>"
                   class="jk-chip <?= (string)$statusFilter === (string)$key ? 'jk-chip--primary' : '' ?>">
                    <?= e($label) ?>
                    <?php if ($key !== ''): ?><strong class="ms-1"><?= num($counts[$key]) ?></strong><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="jk-card__body jk-card__body--flush">
        <?php if (empty($rows)): ?>
            <div class="jk-empty">
                <i class="bi bi-inbox"></i>
                <h5>No rows in this view</h5>
                <p>Choose a different status filter above.</p>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap" style="max-height:620px">
                <table class="jk-table jk-table--compact">
                    <thead>
                        <tr>
                            <th class="num">Row</th>
                            <th style="min-width:230px">Scheme Name</th>
                            <th>Level / State</th>
                            <th>Theme</th>
                            <th>Beneficiary Type</th>
                            <th>Status</th>
                            <th style="min-width:200px">Message</th>
                            <?php if (!$isImported): ?><th style="width:130px">Action</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <?php
                            $data = json_list($row['raw_data']);
                            $rowClass = match ($row['status']) {
                                'Error'     => 'jk-row-danger',
                                'Duplicate' => 'jk-row-warning',
                                'Inserted', 'Updated' => 'jk-row-success',
                                default     => '',
                            };
                            $multi = fn($v) => is_array($v) ? implode(', ', $v) : (string)($v ?? '');
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td class="num text-subtle"><?= (int)$row['row_no'] ?></td>
                                <td class="fw-semibold"><?= e(str_limit($row['scheme_name'] ?? '(no name)', 52)) ?></td>
                                <td class="small"><?= e(str_limit($multi($data['natstate'] ?? null) ?: '-', 24)) ?></td>
                                <td class="small text-muted"><?= e(str_limit($multi($data['theme'] ?? null) ?: '-', 26)) ?></td>
                                <td class="small text-muted"><?= e(str_limit($multi($data['beneficiary_type'] ?? null) ?: '-', 26)) ?></td>
                                <td>
                                    <?= badge($row['status'], match ($row['status']) {
                                        'Valid'     => 'success',
                                        'Duplicate' => 'warning',
                                        'Error'     => 'danger',
                                        'Inserted'  => 'success',
                                        'Updated'   => 'info',
                                        default     => 'secondary',
                                    }) ?>
                                </td>
                                <td class="small text-muted"><?= e(str_limit($row['message'] ?? '-', 140)) ?></td>
                                <?php if (!$isImported): ?>
                                    <td>
                                        <?php if ($row['status'] === 'Error'): ?>
                                            <span class="text-subtle tiny">Cannot import</span>
                                        <?php else: ?>
                                            <select class="form-select form-select-sm" name="action[<?= (int)$row['id'] ?>]">
                                                <?php if ($row['status'] === 'Duplicate'): ?>
                                                    <option value="skip"   <?= $row['action'] === 'skip' ? 'selected' : '' ?>>Skip</option>
                                                    <option value="update" <?= $row['action'] === 'update' ? 'selected' : '' ?>>Update existing</option>
                                                <?php else: ?>
                                                    <option value="insert" <?= $row['action'] !== 'skip' ? 'selected' : '' ?>>Import</option>
                                                    <option value="skip"   <?= $row['action'] === 'skip' ? 'selected' : '' ?>>Skip</option>
                                                <?php endif; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$isImported && $batch['status'] !== 'Cancelled'): ?>
        <div class="jk-card__foot">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label" for="duplicate_action">Default for duplicates</label>
                    <select class="form-select form-select-sm" id="duplicate_action" name="duplicate_action">
                        <option value="skip">Skip &mdash; leave the existing scheme untouched</option>
                        <option value="update">Update &mdash; overwrite the existing scheme</option>
                    </select>
                    <div class="form-text">Applied to any duplicate row you did not decide individually.</div>
                </div>
                <div class="col-md-8 text-md-end">
                    <p class="text-muted small mb-2">
                        <?= num($counts['Valid']) ?> row(s) ready
                        <?php if ((int)$counts['Duplicate'] > 0): ?>
                            and <?= num($counts['Duplicate']) ?> possible duplicate(s)
                        <?php endif; ?>
                        <?php if ((int)$counts['Error'] > 0): ?>
                            &mdash; <?= num($counts['Error']) ?> row(s) with errors will be left out
                        <?php endif; ?>.
                    </p>
                    <button type="submit" class="btn btn-primary"
                            data-confirm="Import the selected rows into the scheme master?">
                        <i class="bi bi-database-add me-1"></i>Import Schemes
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
</form>

<?php if (!$isImported): ?>
    <div class="alert alert-info d-flex gap-2 align-items-start">
        <i class="bi bi-info-circle mt-1"></i>
        <div>
            Imported schemes arrive with their classification, benefits, source link and any documents named in
            their text &mdash; but with <strong>no eligibility rules</strong>. Until rules are configured they
            report &ldquo;Needs Verification&rdquo; for every beneficiary rather than matching automatically.
            Use <a href="<?= e(url('/schemes?has_rules=no')) ?>">Schemes &rsaquo; filter by rules not configured</a>
            to find them afterwards.
        </div>
    </div>
<?php endif; ?>
