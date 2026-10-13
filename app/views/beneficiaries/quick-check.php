<?php
/**
 * Standalone eligibility check - answer a few questions and see which
 * schemes could apply, without creating a beneficiary record.
 *
 * @var array $attributes (grouped), $lookups, $states, $submitted
 * @var array|null $results
 * @var int|null $checkState
 */
$pageSubtitle = 'Check which schemes a citizen might qualify for, before registering them';

// The handful of attributes most worth asking at first contact.
$quickKeys = ['age', 'gender', 'area_type', 'social_category', 'annual_income', 'bpl_status',
              'marital_status', 'occupation', 'disability_status', 'pregnancy_status',
              'lactating_status', 'widow_status', 'is_student', 'farmer_category',
              'worker_category', 'has_bank_account', 'has_aadhaar', 'ration_card_type',
              'family_size', 'land_holding', 'education_level'];

$flat = [];
foreach ($attributes as $group => $items) {
    foreach ($items as $key => $attr) {
        $flat[$key] = $attr + ['group' => $group];
    }
}

require VIEW_PATH . '/partials/page-head.php';
?>

<div class="row g-3">
<div class="col-lg-5">
    <form method="post" action="<?= e(url('/matching/eligibility-check')) ?>" data-guard>
        <?= csrf_field() ?>

        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-person-lines-fill"></i>Citizen Profile</h3>
            </div>
            <div class="jk-card__body">
                <p class="text-muted small">
                    Fill in whatever is known. Anything left blank simply counts as &ldquo;not recorded&rdquo;,
                    which keeps a scheme at <em>Needs Verification</em> rather than ruling it in or out.
                </p>

                <div class="mb-3">
                    <label class="form-label" for="state_id">State <span class="req">*</span></label>
                    <select class="form-select" id="state_id" name="state_id" required>
                        <option value="">Select the State</option>
                        <?php foreach ($states as $id => $name): ?>
                            <option value="<?= (int)$id ?>" <?= (int)($checkState ?? 0) === (int)$id ? 'selected' : '' ?>>
                                <?= e($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Decides which State schemes are considered alongside the national ones.</div>
                </div>

                <div class="row g-2">
                    <?php foreach ($quickKeys as $key): ?>
                        <?php
                        if (!isset($flat[$key])) continue;
                        $attr = $flat[$key];
                        $current = $submitted[$key] ?? '';
                        $options = !empty($attr['lookup_key']) ? ($lookups[$attr['lookup_key']] ?? []) : [];
                        ?>
                        <div class="col-md-6">
                            <label class="form-label" for="p_<?= e($key) ?>"><?= e($attr['label']) ?></label>

                            <?php if ($attr['data_type'] === 'bool'): ?>
                                <select class="form-select form-select-sm" id="p_<?= e($key) ?>" name="profile[<?= e($key) ?>]">
                                    <option value="">Not known</option>
                                    <option value="1" <?= (string)$current === '1' ? 'selected' : '' ?>>Yes</option>
                                    <option value="0" <?= (string)$current === '0' ? 'selected' : '' ?>>No</option>
                                </select>

                            <?php elseif ($options): ?>
                                <select class="form-select form-select-sm" id="p_<?= e($key) ?>" name="profile[<?= e($key) ?>]">
                                    <option value="">Not known</option>
                                    <?php foreach ($options as $code => $label): ?>
                                        <option value="<?= e($code) ?>" <?= (string)$current === (string)$code ? 'selected' : '' ?>>
                                            <?= e($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                            <?php elseif (in_array($attr['data_type'], ['int', 'decimal'], true)): ?>
                                <div class="input-group input-group-sm">
                                    <input type="number" class="form-control" id="p_<?= e($key) ?>"
                                           name="profile[<?= e($key) ?>]" min="0"
                                           step="<?= $attr['data_type'] === 'decimal' ? '0.01' : '1' ?>"
                                           value="<?= e($current) ?>">
                                    <?php if (!empty($attr['unit'])): ?>
                                        <span class="input-group-text"><?= e($attr['unit']) ?></span>
                                    <?php endif; ?>
                                </div>

                            <?php else: ?>
                                <input type="text" class="form-control form-control-sm" id="p_<?= e($key) ?>"
                                       name="profile[<?= e($key) ?>]" value="<?= e($current) ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="jk-card__foot d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">
                    <i class="bi bi-search me-1"></i>Find Applicable Schemes
                </button>
                <a href="<?= e(url('/matching/eligibility-check')) ?>" class="btn btn-outline-secondary">Reset</a>
            </div>
        </div>
    </form>
</div>

<div class="col-lg-7">
    <?php if ($results === null): ?>
        <div class="jk-card">
            <div class="jk-empty">
                <i class="bi bi-search"></i>
                <h5>Enter a profile to see applicable schemes</h5>
                <p>
                    This check runs the same eligibility engine used for registered beneficiaries, but stores
                    nothing. It is meant for a first conversation &mdash; at an awareness camp, or over the
                    phone &mdash; before a household is formally registered.
                </p>
                <?php if (can('beneficiary.create')): ?>
                    <a href="<?= e(url('/beneficiaries/create')) ?>" class="btn btn-sm btn-outline-primary">
                        Or register the beneficiary properly
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title">
                    <i class="bi bi-list-check"></i>Applicable Schemes
                    <span class="badge bg-success"><?= count($results) ?></span>
                </h3>
                <span class="text-muted small"><?= count($submitted) ?> detail(s) supplied</span>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <?php if (empty($results)): ?>
                    <div class="jk-empty">
                        <i class="bi bi-inbox"></i>
                        <h5>No scheme matched this profile</h5>
                        <p>Supplying more details usually widens the result. A profile with very little
                           information will mostly produce &ldquo;Needs Verification&rdquo; rather than matches.</p>
                    </div>
                <?php else: ?>
                    <div class="jk-table-wrap">
                        <table class="jk-table jk-table--compact">
                            <thead><tr><th>Scheme</th><th style="width:110px">Match</th><th>Eligibility</th><th class="no-print"></th></tr></thead>
                            <tbody>
                                <?php foreach ($results as $result): ?>
                                    <?php $variant = eligibility_variant($result['eligibility_status']); ?>
                                    <tr>
                                        <td>
                                            <a href="<?= e(url('/schemes/' . (int)$result['scheme_id'])) ?>" class="fw-semibold">
                                                <?= e($result['scheme_short_name'] ?: str_limit($result['scheme_name'], 48)) ?>
                                            </a>
                                            <div class="text-muted tiny">
                                                <?= e($result['scheme_level']) ?>
                                                <?= !empty($result['theme_name']) ? ' &middot; ' . e($result['theme_name']) : '' ?>
                                            </div>
                                            <?php if (!empty($result['reason_text'])): ?>
                                                <div class="text-muted" style="font-size:.74rem">
                                                    <?= e(str_limit($result['reason_text'], 130)) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="jk-match-pct text-<?= e($variant) ?>"><?= (int)$result['match_percent'] ?>%</span>
                                            <div class="jk-match-bar mt-1">
                                                <span style="width:<?= (float)$result['match_percent'] ?>%;background:var(--bs-<?= e($variant) ?>)"></span>
                                            </div>
                                        </td>
                                        <td><?= badge($result['eligibility_status'], $variant) ?></td>
                                        <td class="text-end no-print">
                                            <a href="<?= e(url('/schemes/' . (int)$result['scheme_id'])) ?>"
                                               class="btn btn-sm btn-outline-secondary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="jk-card__foot">
                <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <span class="text-muted small">
                        <i class="bi bi-info-circle me-1"></i>
                        Nothing was saved. Register the household to keep this result and track applications.
                    </span>
                    <?php if (can('beneficiary.create')): ?>
                        <a href="<?= e(url('/beneficiaries/create')) ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-person-plus me-1"></i>Register this beneficiary
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>
