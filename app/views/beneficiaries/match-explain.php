<?php
/**
 * "Why did this scheme match?" (specification section 20).
 *
 * @var array $beneficiary, $match, $rules
 * @var JanKavach\Libraries\EligibilityEngine $engine
 */
$pageSubtitle = $beneficiary['name'] . ' &rarr; ' . ($match['short_name'] ?: $match['scheme_name']);

$passed  = json_list($match['passed_reasons']);
$failed  = json_list($match['failed_reasons']);
$unknown = json_list($match['unknown_reasons']);
$missing = json_list($match['missing_documents']);
$variant = eligibility_variant($match['eligibility_status']);
$percent = (float)($match['match_percent'] ?? 0);

ob_start(); ?>
    <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/matching')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>All Matches
    </a>
    <a href="<?= e(url('/schemes/' . (int)$match['scheme_id'])) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-journal-text me-1"></i>Scheme Details
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<!-- Verdict -->
<div class="jk-card">
    <div class="jk-card__body">
        <div class="row g-3 align-items-center">
            <div class="col-md-3 text-center">
                <div class="display-5 fw-bold text-<?= e($variant) ?>"><?= (int)$percent ?>%</div>
                <div class="jk-match-bar mx-auto" style="max-width:150px">
                    <span style="width:<?= $percent ?>%;background:var(--bs-<?= e($variant) ?>)"></span>
                </div>
                <div class="mt-2"><?= badge($match['eligibility_status'], $variant) ?></div>
            </div>

            <div class="col-md-9">
                <h2 class="h5 mb-1">
                    <a href="<?= e(url('/schemes/' . (int)$match['scheme_id'])) ?>">
                        <?= e($match['scheme_name']) ?>
                    </a>
                </h2>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <span class="jk-chip"><?= e($match['scheme_code'] ?? '') ?></span>
                    <span class="jk-chip"><?= e($match['scheme_level']) ?></span>
                    <?php if (!empty($match['theme_name'])): ?>
                        <?= badge_color($match['theme_name'], $match['theme_color'] ?: '#6c757d') ?>
                    <?php endif; ?>
                </div>
                <p class="mb-0"><?= e($match['reason_text'] ?? 'No explanation is available.') ?></p>
                <?php if (!empty($match['matched_at'])): ?>
                    <div class="text-subtle tiny mt-2">Evaluated <?= fmt_datetime($match['matched_at']) ?></div>
                <?php else: ?>
                    <div class="text-subtle tiny mt-2">
                        Evaluated just now &mdash; this result is not yet saved against the beneficiary.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Conditions met -->
    <div class="col-lg-4">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title text-success">
                    <i class="bi bi-check-circle"></i>Conditions Met
                    <span class="badge bg-success"><?= count($passed) ?></span>
                </h3>
            </div>
            <div class="jk-card__body">
                <?php if (empty($passed)): ?>
                    <p class="text-muted mb-0 small">No condition was satisfied.</p>
                <?php else: ?>
                    <ul class="jk-reason-list jk-reason-list--pass">
                        <?php foreach ($passed as $reason): ?>
                            <li>
                                <strong><?= e($reason['label'] ?? '') ?></strong> is
                                <?= e($reason['actual'] ?? '') ?>
                                <?php if (!empty($reason['expected'])): ?>
                                    <span class="text-muted">(scheme requires <?= e($reason['expected']) ?>)</span>
                                <?php endif; ?>
                                <?php if (!empty($reason['rule'])): ?>
                                    <span class="jk-rule-origin"><?= e(str_limit($reason['rule'], 120)) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Conditions not met -->
    <div class="col-lg-4">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title text-danger">
                    <i class="bi bi-x-circle"></i>Conditions Not Met
                    <span class="badge bg-danger"><?= count($failed) ?></span>
                </h3>
            </div>
            <div class="jk-card__body">
                <?php if (empty($failed)): ?>
                    <p class="text-muted mb-0 small">Every condition that could be tested was satisfied.</p>
                <?php else: ?>
                    <ul class="jk-reason-list jk-reason-list--fail">
                        <?php foreach ($failed as $reason): ?>
                            <li>
                                <strong><?= e($reason['label'] ?? '') ?></strong> is
                                <?= e($reason['actual'] ?? '') ?>,
                                but the scheme requires <?= e($reason['expected'] ?? '') ?>
                                <?php if (!empty($reason['mandatory'])): ?>
                                    <span class="badge bg-danger ms-1">Disqualifying</span>
                                <?php endif; ?>
                                <?php if (!empty($reason['rule'])): ?>
                                    <span class="jk-rule-origin"><?= e(str_limit($reason['rule'], 120)) ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Unknown -->
    <div class="col-lg-4">
        <div class="jk-card h-100">
            <div class="jk-card__head">
                <h3 class="jk-card__title text-warning">
                    <i class="bi bi-question-circle"></i>Still To Confirm
                    <span class="badge bg-warning text-dark"><?= count($unknown) ?></span>
                </h3>
            </div>
            <div class="jk-card__body">
                <?php if (empty($unknown)): ?>
                    <p class="text-muted mb-0 small">Every condition had a recorded value.</p>
                <?php else: ?>
                    <p class="text-muted small">
                        These conditions could not be tested because the beneficiary has no value recorded.
                        They are neither passed nor failed.
                    </p>
                    <ul class="jk-reason-list jk-reason-list--unknown">
                        <?php foreach ($unknown as $reason): ?>
                            <li>
                                <strong><?= e($reason['label'] ?? '') ?></strong> not recorded
                                <?php if (!empty($reason['expected'])): ?>
                                    <span class="text-muted">(scheme requires <?= e($reason['expected']) ?>)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (can('beneficiary.assess')): ?>
                        <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/assessment')) ?>"
                           class="btn btn-sm btn-outline-primary w-100 mt-2">
                            <i class="bi bi-clipboard-check me-1"></i>Record the missing answers
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Missing documents -->
<?php if (!empty($missing)): ?>
    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-folder-x"></i>Documents Required But Not On File</h3>
        </div>
        <div class="jk-card__body">
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($missing as $document): ?>
                    <span class="jk-chip text-danger"><i class="bi bi-file-earmark-x"></i><?= e($document) ?></span>
                <?php endforeach; ?>
            </div>
            <?php if (can('beneficiary.document')): ?>
                <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/documents')) ?>"
                   class="btn btn-sm btn-outline-primary mt-3">
                    <i class="bi bi-upload me-1"></i>Upload documents
                </a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Full rule set -->
<div class="jk-card">
    <div class="jk-card__head">
        <h3 class="jk-card__title"><i class="bi bi-sliders"></i>All Conditions for This Scheme</h3>
        <?php if (can('scheme.rules')): ?>
            <a href="<?= e(url('/schemes/' . (int)$match['scheme_id'] . '/rules')) ?>" class="small">Edit rules</a>
        <?php endif; ?>
    </div>
    <div class="jk-card__body jk-card__body--flush">
        <?php if (empty($rules)): ?>
            <div class="jk-empty">
                <i class="bi bi-sliders"></i>
                <h5>This scheme has no configured conditions</h5>
                <p>Without rules the engine cannot judge eligibility, so it reports
                   &ldquo;Needs Verification&rdquo; and leaves the decision to the field team.</p>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap">
                <table class="jk-table jk-table--compact">
                    <thead>
                        <tr><th>Group</th><th>Condition</th><th>Operator</th><th>Required Value</th>
                            <th class="num">Weight</th><th>Mandatory</th><th>Source of the rule</th></tr>
                    </thead>
                    <tbody>
                        <?php $operators = $engine->operators(); ?>
                        <?php foreach ($rules as $rule): ?>
                            <tr>
                                <td><?= (int)$rule['rule_group'] ?></td>
                                <td class="fw-semibold"><?= e($engine->attributeLabel($rule['attribute_key'])) ?></td>
                                <td><?= e($operators[$rule['operator']] ?? $rule['operator']) ?></td>
                                <td>
                                    <?php
                                    $value = $rule['value_list']
                                        ? implode(', ', array_map(
                                            fn($v) => $engine->displayValue($rule['attribute_key'], trim($v)),
                                            explode(',', $rule['value_list'])))
                                        : $engine->displayValue($rule['attribute_key'], $rule['value1']);
                                    if ($rule['operator'] === 'between') {
                                        $value .= ' to ' . $engine->displayValue($rule['attribute_key'], $rule['value2']);
                                    }
                                    echo e($value);
                                    ?>
                                </td>
                                <td class="num"><?= (int)$rule['weight'] ?></td>
                                <td><?= (int)$rule['is_mandatory'] === 1
                                        ? '<span class="badge bg-danger-subtle text-danger">Required</span>'
                                        : '<span class="badge bg-light text-muted border">Scored</span>' ?></td>
                                <td class="text-muted" style="font-size:.74rem">
                                    <?= e(str_limit($rule['description'] ?? '-', 110)) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="p-3 border-top small text-muted">
                <strong>How the percentage is worked out.</strong>
                Each condition carries a weight. The match percentage is the weight of the conditions met divided
                by the total weight of the conditions in the best-scoring group. A failed <em>required</em>
                condition sets the result to Not Eligible regardless of the score.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Next step -->
<div class="d-flex flex-wrap gap-2">
    <?php if (can('application.create') && $match['eligibility_status'] !== 'Not Eligible'): ?>
        <a href="<?= e(url('/applications/create?beneficiary_id=' . (int)$beneficiary['id'] . '&scheme_id=' . (int)$match['scheme_id'])) ?>"
           class="btn btn-primary">
            <i class="bi bi-file-earmark-plus me-1"></i>Initiate an application for this scheme
        </a>
    <?php endif; ?>
    <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/matching')) ?>" class="btn btn-outline-secondary">
        Back to all matches
    </a>
</div>
