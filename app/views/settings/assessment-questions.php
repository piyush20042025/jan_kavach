<?php
/**
 * Assessment questionnaire editor (specification section 18 - the
 * "new questions can be added later" requirement).
 *
 * @var array $grouped, $lookupKeys, $attributes, $sections
 * @var array|null $editing
 */
$pageSubtitle = 'The socio-economic questionnaire field teams complete for each beneficiary';

ob_start(); ?>
    <a href="<?= e(url('/settings')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Settings
    </a>
    <a href="<?= e(url('/masters/lookups')) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-ui-checks me-1"></i>Dropdown Options
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<div class="alert alert-info d-flex gap-2 align-items-start">
    <i class="bi bi-info-circle mt-1"></i>
    <div>
        Questions added here appear on the beneficiary assessment form straight away.
        A question whose <strong>key</strong> matches a declared
        <a href="<?= e(url('/settings/eligibility-attributes')) ?>">eligibility attribute</a> also feeds the
        matching engine, so schemes can be written against it. A question with no matching attribute is still
        recorded &mdash; it just does not take part in matching.
    </div>
</div>

<div class="row g-3">
<div class="col-lg-4">
    <div class="jk-card" style="position:sticky;top:76px">
        <div class="jk-card__head">
            <h3 class="jk-card__title">
                <i class="bi bi-<?= $editing ? 'pencil' : 'plus-lg' ?>"></i>
                <?= $editing ? 'Edit' : 'Add' ?> Question
            </h3>
            <?php if ($editing): ?>
                <a href="<?= e(url('/settings/assessment-questions')) ?>" class="small">Cancel</a>
            <?php endif; ?>
        </div>
        <div class="jk-card__body">
            <form method="post" action="<?= e(url('/settings/assessment-questions')) ?>" data-guard>
                <?= csrf_field() ?>
                <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>

                <div class="mb-2">
                    <label class="form-label" for="section">Section <span class="req">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="section" name="section"
                           list="sectionList" maxlength="80" required value="<?= e($editing['section'] ?? '') ?>">
                    <datalist id="sectionList">
                        <?php foreach ($sections as $section): ?>
                            <option value="<?= e($section) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div class="mb-2">
                    <label class="form-label" for="question_key">Question Key <span class="req">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="question_key" name="question_key"
                           list="attributeList" maxlength="60" required
                           value="<?= e($editing['question_key'] ?? '') ?>"
                           <?= $editing ? 'readonly' : '' ?>>
                    <datalist id="attributeList">
                        <?php foreach ($attributes as $key => $label): ?>
                            <option value="<?= e($key) ?>"><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text">
                        Pick an existing eligibility attribute key to feed the matching engine,
                        or invent a new key for a question that is recorded only.
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label" for="label">Question Text <span class="req">*</span></label>
                    <input type="text" class="form-control form-control-sm" id="label" name="label"
                           maxlength="255" required value="<?= e($editing['label'] ?? '') ?>">
                </div>

                <div class="mb-2">
                    <label class="form-label" for="help_text">Help Text</label>
                    <input type="text" class="form-control form-control-sm" id="help_text" name="help_text"
                           maxlength="500" value="<?= e($editing['help_text'] ?? '') ?>">
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <label class="form-label" for="input_type">Input Type <span class="req">*</span></label>
                        <select class="form-select form-select-sm" id="input_type" name="input_type" required>
                            <?php foreach (['text', 'number', 'decimal', 'select', 'radio', 'checkbox',
                                            'date', 'textarea'] as $type): ?>
                                <option value="<?= e($type) ?>" <?= ($editing['input_type'] ?? 'text') === $type ? 'selected' : '' ?>>
                                    <?= e(ucfirst($type)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="lookup_key">Dropdown Key</label>
                        <input type="text" class="form-control form-control-sm" id="lookup_key" name="lookup_key"
                               list="lookupList" maxlength="60" value="<?= e($editing['lookup_key'] ?? '') ?>">
                        <datalist id="lookupList">
                            <?php foreach ($lookupKeys as $key): ?>
                                <option value="<?= e($key) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <label class="form-label" for="unit">Unit</label>
                        <input type="text" class="form-control form-control-sm" id="unit" name="unit"
                               maxlength="30" value="<?= e($editing['unit'] ?? '') ?>">
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="min_value">Min</label>
                        <input type="number" step="any" class="form-control form-control-sm" id="min_value"
                               name="min_value" value="<?= e($editing['min_value'] ?? '') ?>">
                    </div>
                    <div class="col-4">
                        <label class="form-label" for="max_value">Max</label>
                        <input type="number" step="any" class="form-control form-control-sm" id="max_value"
                               name="max_value" value="<?= e($editing['max_value'] ?? '') ?>">
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-label" for="sort_order">Sort Order</label>
                    <input type="number" class="form-control form-control-sm" id="sort_order" name="sort_order"
                           min="0" max="9999" value="<?= e($editing['sort_order'] ?? 100) ?>">
                </div>

                <div class="form-check mb-1">
                    <input class="form-check-input" type="checkbox" id="is_required" name="is_required" value="1"
                           <?= (int)($editing['is_required'] ?? 0) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_required">Required to complete an assessment</label>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                           <?= $editing === null || (int)($editing['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
                    <label class="form-check-label" for="is_active">Active (shown on the form)</label>
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-check-lg me-1"></i><?= $editing ? 'Save Question' : 'Add Question' ?>
                </button>
            </form>
        </div>
    </div>
</div>

<div class="col-lg-8">
    <?php foreach ($grouped as $section => $questions): ?>
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-clipboard-data"></i><?= e($section) ?></h3>
                <span class="badge bg-secondary"><?= count($questions) ?></span>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <div class="jk-table-wrap">
                    <table class="jk-table jk-table--compact">
                        <thead>
                            <tr><th style="min-width:200px">Question</th><th>Key</th><th>Type</th>
                                <th>Options From</th><th>Required</th><th>Feeds Matching</th>
                                <th>Status</th><th class="no-print"></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($questions as $question): ?>
                                <tr<?= (int)$question['is_active'] === 0 ? ' class="opacity-75"' : '' ?>>
                                    <td>
                                        <span class="fw-semibold"><?= e($question['label']) ?></span>
                                        <?php if (!empty($question['help_text'])): ?>
                                            <div class="text-subtle tiny"><?= e(str_limit($question['help_text'], 66)) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><code class="small"><?= e($question['question_key']) ?></code></td>
                                    <td><span class="jk-chip"><?= e($question['input_type']) ?></span></td>
                                    <td class="small text-muted">
                                        <?php if (!empty($question['lookup_key'])): ?>
                                            <a href="<?= e(url('/masters/lookups?search=' . urlencode($question['lookup_key']))) ?>">
                                                <?= e($question['lookup_key']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-subtle">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= (int)$question['is_required'] === 1
                                            ? '<span class="badge bg-danger-subtle text-danger">Required</span>'
                                            : '<span class="text-subtle tiny">Optional</span>' ?></td>
                                    <td>
                                        <?php if ((int)$question['feeds_eligibility'] === 1): ?>
                                            <span class="badge bg-success-subtle text-success"
                                                  data-bs-toggle="tooltip"
                                                  title="Maps to attribute: <?= e($question['attribute_label'] ?? $question['question_key']) ?>">
                                                <i class="bi bi-lightning-charge-fill"></i> Yes
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border"
                                                  data-bs-toggle="tooltip"
                                                  title="No eligibility attribute uses this key, so the answer is stored only">
                                                Stored only
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= (int)$question['is_active'] === 1
                                            ? '<span class="badge bg-success">Active</span>'
                                            : '<span class="badge bg-secondary">Hidden</span>' ?></td>
                                    <td class="jk-table__actions no-print">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= e(query_url(['edit' => (int)$question['id']])) ?>"
                                               class="btn btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                                            <form method="post"
                                                  action="<?= e(url('/settings/assessment-questions/' . (int)$question['id'] . '/toggle')) ?>"
                                                  class="d-inline">
                                                <?= csrf_field() ?>
                                                <button class="btn btn-outline-secondary" type="submit">
                                                    <i class="bi bi-<?= (int)$question['is_active'] === 1 ? 'eye-slash' : 'eye' ?>"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($grouped)): ?>
        <div class="jk-card">
            <div class="jk-empty">
                <i class="bi bi-clipboard-x"></i>
                <h5>No questions configured</h5>
                <p>Field teams cannot record socio-economic information until at least one question exists.</p>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>
