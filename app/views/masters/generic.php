<?php
/**
 * Generic master editor, driven by Master::MASTERS.
 *
 * @var string $master
 * @var array  $definition, $rows, $search, $states
 * @var array|null $grouped, $editing
 */
$pageSubtitle = $definition['description'];
$fields = $definition['fields'];
$canManage = can('master.manage');

ob_start(); ?>
    <a href="<?= e(url('/masters')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>All Masters
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php';

/** Render one input for a master field. */
$renderField = function (string $column, array $field, $value) use ($states) {
    $id = 'f_' . $column;
    $required = !empty($field['required']) ? 'required' : '';
    $max = !empty($field['maxlength']) ? 'maxlength="' . (int)$field['maxlength'] . '"' : '';

    switch ($field['type']) {
        case 'textarea':
            return '<textarea class="form-control form-control-sm" id="' . e($id) . '" name="' . e($column) . '" rows="2" '
                 . $required . ' ' . $max . '>' . e($value) . '</textarea>';

        case 'select':
            $html = '<select class="form-select form-select-sm" id="' . e($id) . '" name="' . e($column) . '" ' . $required . '>';
            if (empty($field['required'])) {
                $html .= '<option value="">Not set</option>';
            }
            foreach ($field['options'] as $option) {
                $selected = (string)$value === (string)$option ? ' selected' : '';
                $html .= '<option value="' . e($option) . '"' . $selected . '>' . e($option) . '</option>';
            }
            return $html . '</select>';

        case 'state':
            $html = '<select class="form-select form-select-sm" id="' . e($id) . '" name="' . e($column) . '">';
            $html .= '<option value="">Not state-specific</option>';
            foreach ($states as $stateId => $stateName) {
                $selected = (int)$value === (int)$stateId ? ' selected' : '';
                $html .= '<option value="' . (int)$stateId . '"' . $selected . '>' . e($stateName) . '</option>';
            }
            return $html . '</select>';

        case 'checkbox':
            return '<div class="form-check mt-1">'
                 . '<input class="form-check-input" type="checkbox" id="' . e($id) . '" name="' . e($column) . '" value="1"'
                 . ((int)$value === 1 ? ' checked' : '') . '>'
                 . '<label class="form-check-label small" for="' . e($id) . '">Yes</label></div>';

        case 'color':
            return '<input type="color" class="form-control form-control-sm form-control-color" id="' . e($id) . '" '
                 . 'name="' . e($column) . '" value="' . e($value ?: '#0f4c81') . '">';

        case 'number':
            return '<input type="number" class="form-control form-control-sm" id="' . e($id) . '" '
                 . 'name="' . e($column) . '" value="' . e($value) . '" ' . $required . '>';

        case 'url':
            return '<input type="url" class="form-control form-control-sm" id="' . e($id) . '" '
                 . 'name="' . e($column) . '" value="' . e($value) . '" ' . $required . ' ' . $max . '>';

        default:
            return '<input type="text" class="form-control form-control-sm" id="' . e($id) . '" '
                 . 'name="' . e($column) . '" value="' . e($value) . '" ' . $required . ' ' . $max . '>';
    }
};

/** Render one master row as a table row. */
$renderRow = function (array $row) use ($definition, $fields, $master, $canManage) { ?>
    <tr<?= (int)($row['is_active'] ?? 1) === 0 ? ' class="opacity-75"' : '' ?>>
        <?php foreach ($fields as $column => $field): ?>
            <td class="<?= $field['type'] === 'number' ? 'num' : '' ?>">
                <?php
                $value = $row[$column] ?? null;
                if ($field['type'] === 'checkbox') {
                    echo yes_no_badge($value);
                } elseif ($field['type'] === 'color') {
                    echo '<span class="jk-theme-dot" style="background:' . e($value ?: '#6c757d') . '"></span>'
                       . '<span class="tiny text-muted">' . e($value ?: '-') . '</span>';
                } elseif ($field['type'] === 'url' && $value) {
                    echo link_external($value);
                } elseif ($field['type'] === 'state') {
                    echo e($row['state_name'] ?? ($value ? '#' . $value : 'National'));
                } elseif ($column === 'name' || $column === 'label') {
                    echo '<span class="fw-semibold">' . e($value) . '</span>';
                } elseif ($field['type'] === 'textarea') {
                    echo '<span class="text-muted small">' . e(str_limit((string)$value, 70)) . '</span>';
                } else {
                    echo $value === null || $value === '' ? '<span class="text-subtle">-</span>' : e(str_limit((string)$value, 60));
                }
                ?>
            </td>
        <?php endforeach; ?>
        <td><?= (int)($row['is_active'] ?? 1) === 1
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>' ?></td>
        <?php if ($canManage): ?>
            <td class="jk-table__actions no-print">
                <div class="btn-group btn-group-sm">
                    <a href="<?= e(query_url(['edit' => (int)$row['id']])) ?>#masterForm"
                       class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Edit">
                        <i class="bi bi-pencil"></i>
                    </a>
                    <form method="post" action="<?= e(url('/masters/' . $master . '/' . (int)$row['id'] . '/toggle')) ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-secondary" type="submit" data-bs-toggle="tooltip"
                                title="<?= (int)($row['is_active'] ?? 1) === 1 ? 'Deactivate' : 'Activate' ?>">
                            <i class="bi bi-<?= (int)($row['is_active'] ?? 1) === 1 ? 'pause' : 'play' ?>-circle"></i>
                        </button>
                    </form>
                    <form method="post" action="<?= e(url('/masters/' . $master . '/' . (int)$row['id'] . '/delete')) ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-danger" type="submit" data-bs-toggle="tooltip" title="Delete"
                                data-confirm="Delete this entry? It is only removed if nothing references it.">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            </td>
        <?php endif; ?>
    </tr>
<?php };
?>

<div class="row g-3">
<?php if ($canManage): ?>
    <div class="col-lg-4">
        <div class="jk-card" id="masterForm" style="position:sticky;top:76px">
            <div class="jk-card__head">
                <h3 class="jk-card__title">
                    <i class="bi bi-<?= $editing ? 'pencil' : 'plus-lg' ?>"></i>
                    <?= $editing ? 'Edit' : 'Add' ?> <?= e($definition['singular']) ?>
                </h3>
                <?php if ($editing): ?>
                    <a href="<?= e(url('/masters/' . $master)) ?>" class="small">Cancel edit</a>
                <?php endif; ?>
            </div>
            <div class="jk-card__body">
                <form method="post" action="<?= e(url('/masters/' . $master)) ?>" data-guard>
                    <?= csrf_field() ?>
                    <?php if ($editing): ?>
                        <input type="hidden" name="id" value="<?= (int)$editing['id'] ?>">
                    <?php endif; ?>

                    <?php foreach ($fields as $column => $field): ?>
                        <div class="mb-2">
                            <label class="form-label" for="f_<?= e($column) ?>">
                                <?= e($field['label']) ?>
                                <?php if (!empty($field['required'])): ?><span class="req">*</span><?php endif; ?>
                            </label>
                            <?= $renderField($column, $field, $editing[$column] ?? '') ?>
                            <?php if (!empty($field['help'])): ?>
                                <div class="form-text"><?= e($field['help']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                               <?= $editing === null || (int)($editing['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">Active</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save Changes' : 'Add ' . e($definition['singular']) ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="col-lg-<?= $canManage ? 8 : 12 ?>">
    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title">
                <i class="bi <?= e($definition['icon']) ?>"></i><?= e($definition['label']) ?>
                <span class="badge bg-secondary"><?= count($rows) ?></span>
            </h3>
            <form method="get" class="d-flex gap-2">
                <input type="search" class="form-control form-control-sm" name="search"
                       value="<?= e($search ?? '') ?>" placeholder="Filter&hellip;" style="width:200px">
                <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            </form>
        </div>

        <div class="jk-card__body jk-card__body--flush">
            <?php if (empty($rows)): ?>
                <div class="jk-empty">
                    <i class="bi <?= e($definition['icon']) ?>"></i>
                    <h5>No entries</h5>
                    <p><?= !empty($search) ? 'Nothing matched that filter.' : 'Use the form to add the first entry.' ?></p>
                </div>
            <?php elseif ($grouped !== null): ?>
                <!-- Grouped presentation (dropdown options) -->
                <div class="accordion accordion-flush" id="masterGroups">
                    <?php $i = 0; foreach ($grouped as $groupKey => $groupRows): $i++; ?>
                        <div class="accordion-item">
                            <h4 class="accordion-header">
                                <button class="accordion-button <?= $i > 1 ? 'collapsed' : '' ?>" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#group<?= $i ?>">
                                    <code class="me-2"><?= e($groupKey) ?></code>
                                    <span class="badge bg-secondary ms-auto me-2"><?= count($groupRows) ?></span>
                                </button>
                            </h4>
                            <div id="group<?= $i ?>" class="accordion-collapse collapse <?= $i === 1 ? 'show' : '' ?>"
                                 data-bs-parent="#masterGroups">
                                <div class="accordion-body p-0">
                                    <div class="jk-table-wrap">
                                        <table class="jk-table jk-table--compact">
                                            <thead>
                                                <tr>
                                                    <?php foreach ($fields as $field): ?>
                                                        <th><?= e($field['label']) ?></th>
                                                    <?php endforeach; ?>
                                                    <th>Status</th>
                                                    <?php if ($canManage): ?><th></th><?php endif; ?>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($groupRows as $row) { $renderRow($row); } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="jk-table-wrap">
                    <table class="jk-table jk-table--compact">
                        <thead>
                            <tr>
                                <?php foreach ($fields as $field): ?>
                                    <th class="<?= $field['type'] === 'number' ? 'num' : '' ?>"><?= e($field['label']) ?></th>
                                <?php endforeach; ?>
                                <th>Status</th>
                                <?php if ($canManage): ?><th class="jk-table__actions no-print">Actions</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $row) { $renderRow($row); } ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($definition['usage'])): ?>
            <div class="jk-card__foot text-muted small">
                <i class="bi bi-info-circle me-1"></i>
                An entry that is referenced by existing records cannot be deleted &mdash; deactivate it instead,
                so historical data keeps its meaning.
            </div>
        <?php endif; ?>
    </div>
</div>
</div>
