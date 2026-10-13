<?php
/**
 * The eligibility attribute vocabulary the rule builder offers
 * (specification section 9 - "any other configurable eligibility criteria").
 *
 * @var array $grouped, $operators, $lookupKeys
 * @var array|null $editing
 */
$pageSubtitle = 'The conditions schemes can be written against';

ob_start(); ?>
    <a href="<?= e(url('/settings')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Settings
    </a>
    <a href="<?= e(url('/settings/assessment-questions')) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-clipboard-data me-1"></i>Assessment Questions
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<div class="alert alert-info d-flex gap-2 align-items-start">
    <i class="bi bi-info-circle mt-1"></i>
    <div>
        Each attribute becomes a choice in every scheme's rule builder. The <strong>source</strong> says where
        its value comes from: a column on the beneficiary or household record, an answer to an assessment
        question, or a value derived in code (such as <em>senior citizen</em> from age).
        An attribute sourced from <em>assessment</em> also needs a question with the same key, so field teams
        have somewhere to record it.
    </div>
</div>

<div class="row g-3">
<div class="col-lg-4">
    <div class="jk-card" style="position:sticky;top:76px">
        <div class="jk-card__head">
            <h3 class="jk-card__title">
                <i class="bi bi-<?= $editing ? 'pencil' : 'plus-lg' ?>"></i>
                <?= $editing ? 'Edit' : 'Add' ?> Attribute
            </h3>
            <?php if ($editing): ?>
                <a href="<?= e(url('/settings/eligibility-attributes')) ?>" class="small">Cancel</a>
            <?php endif; ?>
        </div>
        <div class="jk-card__body">
            <form method="post" action="<?= e(url('/settings/eligibility-attributes')) ?>" data-guard>
                <?= csrf_field() ?>
                <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

                <div class="mb-2">
                    <label class="form-label" for="attribute_key">Attribute Key <span class="req">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="attribute_key" name="attribute_key"
                           maxlength="60" required value="<?= e($editing['attribute_key'] ?? '') ?>">
                    <div class="form-text">
                        Lower case with underscores, e.g. <code>tribal_household</code>.
                        The key cannot be changed once rules use it.
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label" for="label">Label <span class="req">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="label" name="label"
                           maxlength="150" required value="<?= e($editing['label'] ?? '') ?>">
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label" for="data_type">Data Type <span class="req">*</span></label>
                        <select class="form-select form-select-sm" id="data_type" name="data_type" required>
                            <?php foreach (['string', 'int', 'decimal', 'bool', 'enum', 'set', 'date'] as $type): ?>
                                <option value="<?= e($type) ?>" <?= ($editing['data_type'] ?? 'string') === $type ? 'selected' : '' ?>>
                                    <?= e($type) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="source">Value Source <span class="req">*</span></label>
                        <select class="form-select form-select-sm" id="source" name="source" required>
                            <?php foreach (['assessment' => 'Assessment answer',
                                            'beneficiary' => 'Beneficiary column',
                                            'household'   => 'Household column',
                                            'geo'         => 'Geography',
                                            'derived'     => 'Derived in code'] as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= ($editing['source'] ?? 'assessment') === $key ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-2" id="lookupWrap">
                    <label class="form-label" for="lookup_key">Dropdown Key</label>
                    <input type="text" class="form-control form-control-sm" id="lookup_key" name="lookup_key"
                           list="lookupList" maxlength="60" value="<?= e($editing['lookup_key'] ?? '') ?>">
                    <datalist id="lookupList">
                        <?php foreach ($lookupKeys as $key): ?>
                            <option value="<?= e($key) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text">Required for an enum, so the rule builder knows which values to offer.</div>
                </div>

                <div class="mb-2" id="columnWrap">
                    <label class="form-label" for="source_column">Source Column</label>
                    <input type="text" class="form-control form-control-sm" id="source_column" name="source_column"
                           maxlength="60" value="<?= e($editing['source_column'] ?? '') ?>">
                    <div class="form-text">The real column name, when the value lives on a table.</div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label" for="unit">Unit</label>
                        <input type="text" class="form-control form-control-sm" id="unit" name="unit"
                               maxlength="30" value="<?= e($editing['unit'] ?? '') ?>" placeholder="years, INR, %">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="group_label">Group</label>
                        <input type="text" class="form-control form-control-sm" id="group_label" name="group_label"
                               maxlength="60" value="<?= e($editing['group_label'] ?? 'General') ?>">
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control form-control-sm" id="description" name="description"
                              rows="2" maxlength="500"><?= e($editing['description'] ?? '') ?></textarea>
                </div>

                <div class="mb-2">
                    <label class="form-label" for="sort_order">Sort Order</label>
                    <input type="number" class="form-control form-control-sm" id="sort_order" name="sort_order"
                           min="0" max="9999" value="<?= e($editing['sort_order'] ?? 100) ?>">
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                           <?= $editing === null || (int)($editing['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Active (offered in the rule builder)</label>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save Attribute' : 'Add Attribute' ?>
                </button>
            </form>
        </div>
    </div>
</div>

<div class="col-lg-8">
    <?php foreach ($grouped as $group => $attributes): ?>
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-sliders"></i><?= e($group) ?></h3>
                <span class="badge bg-secondary"><?= count($attributes) ?></span>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <div class="jk-table-wrap">
                    <table class="jk-table jk-table--compact">
                        <thead>
                            <tr><th style="min-width:180px">Attribute</th><th>Key</th><th>Type</th>
                                <th>Source</th><th class="num">Used by Rules</th><th class="num">Data Coverage</th>
                                <th>Status</th><th class="no-print"></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attributes as $attribute): ?>
                                <tr<?= (int)$attribute['is_active'] === 0 ? ' class="opacity-75"' : '' ?>>
                                    <td>
                                        <span class="fw-semibold"><?= e($attribute['label']) ?></span>
                                        <?php if (!empty($attribute['description'])): ?>
                                            <div class="text-subtle tiny"><?= e(str_limit($attribute['description'], 70)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><code class="small"><?= e($attribute['attribute_key']) ?></code></td>
                                    <td>
                                        <span class="jk-chip"><?= e($attribute['data_type']) ?></span>
                                        <?php if (!empty($attribute['unit'])): ?>
                                            <span class="text-subtle tiny d-block"><?= e($attribute['unit']) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="small text-muted"><?= e($attribute['source']) ?></span>
                                        <?php if (!empty($attribute['source_column'])): ?>
                                            <code class="tiny d-block"><?= e($attribute['source_column']) ?></code>
                                        <?php endif; ?>
                                    </td>
                                    <td class="num">
                                        <?= (int)$attribute['rule_usage'] > 0
                                            ? '<span class="badge bg-primary-subtle text-primary">' . num($attribute['rule_usage']) . '</span>'
                                            : '<span class="text-subtle">-</span>' ?>
                                    </td>
                                    <td class="num">
                                        <?= (int)$attribute['data_coverage'] > 0
                                            ? num($attribute['data_coverage'])
                                            : '<span class="text-subtle">0</span>' ?>
                                    </td>
                                    <td><?= (int)$attribute['is_active'] === 1
                                            ? '<span class="badge bg-success">Active</span>'
                                            : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                                    <td class="text-end no-print">
                                        <a href="<?= e(query_url(['edit' => (int)$attribute['id']])) ?>"
                                           class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
</div>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
(function () {
  const type   = document.getElementById('data_type');
  const source = document.getElementById('source');
  const lookup = document.getElementById('lookupWrap');
  const column = document.getElementById('columnWrap');

  function sync() {
    // A lookup only makes sense for an enum; a column only for table sources.
    // enum and set both draw their values from a dropdown list.
    const needsLookup = type.value === 'enum' || type.value === 'set';
    lookup.style.opacity = needsLookup ? '1' : '.55';
    document.getElementById('lookup_key').required = needsLookup;

    const needsColumn = ['beneficiary', 'household', 'geo'].indexOf(source.value) >= 0;
    column.style.opacity = needsColumn ? '1' : '.55';
  }

  type.addEventListener('change', sync);
  source.addEventListener('change', sync);
  sync();
})();
</script>
<?php \JanKavach\Core\View::endSection(); ?>
