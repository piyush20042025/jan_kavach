<?php
/**
 * "Find Eligible Schemes" for one beneficiary (specification section 20).
 *
 * @var array $beneficiary, $matches, $summary, $profile, $outstanding
 * @var JanKavach\Libraries\EligibilityEngine $engine
 * @var string|null $lastMatched
 * @var bool $assessmentComplete
 */
$pageSubtitle = $beneficiary['name'] . ' (' . $beneficiary['beneficiary_code'] . ')';

$positive = array_values(array_filter($matches, fn($m) => $m['eligibility_status'] !== 'Not Eligible'));
$negative = array_values(array_filter($matches, fn($m) => $m['eligibility_status'] === 'Not Eligible'));

ob_start(); ?>
    <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'])) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Profile
    </a>
    <form method="post" action="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/matching')) ?>"
          class="d-inline" data-guard>
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-primary">
            <i class="bi bi-arrow-repeat me-1"></i><?= $lastMatched ? 'Re-run Matching' : 'Run Matching' ?>
        </button>
    </form>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<?php if (!$assessmentComplete): ?>
    <div class="alert alert-warning d-flex gap-2 align-items-start">
        <i class="bi bi-exclamation-triangle mt-1"></i>
        <div>
            <strong>The socio-economic assessment is not complete.</strong>
            Matching still runs, but most conditions will come back as &ldquo;not yet recorded&rdquo;, which keeps
            schemes at <em>Needs Verification</em> instead of grading them properly.
            <?php if (can('beneficiary.assess')): ?>
                <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/assessment')) ?>" class="alert-link">
                    Complete the assessment
                </a> for a reliable result.
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<!-- Summary tiles -->
<div class="jk-kpi-grid">
    <?php
    $tiles = [
        ['value' => num($summary['Eligible'] ?? 0),             'label' => 'Eligible',             'icon' => 'bi-check-circle',      'color' => '#198754', 'soft' => '#e6f4ec'],
        ['value' => num($summary['Potentially Eligible'] ?? 0), 'label' => 'Potentially Eligible', 'icon' => 'bi-question-circle',   'color' => '#f59e0b', 'soft' => '#fdf3e0'],
        ['value' => num($summary['Needs Verification'] ?? 0),   'label' => 'Needs Verification',   'icon' => 'bi-search',            'color' => '#0dcaf0', 'soft' => '#e3f8fd'],
        ['value' => num($summary['Not Eligible'] ?? 0),         'label' => 'Not Eligible',         'icon' => 'bi-x-circle',          'color' => '#6c757d', 'soft' => '#eef0f2'],
        ['value' => num($summary['missing_documents'] ?? 0),    'label' => 'Documents Missing',    'icon' => 'bi-folder-x',          'color' => '#dc3545', 'soft' => '#fdecec'],
    ];
    foreach ($tiles as $tile) {
        extract(['kpiValue' => $tile['value'], 'kpiLabel' => $tile['label'], 'kpiIcon' => $tile['icon'],
                 'kpiColor' => $tile['color'], 'kpiSoft' => $tile['soft'], 'kpiUrl' => null, 'kpiHint' => null]);
        include VIEW_PATH . '/partials/kpi.php';
    }
    ?>
</div>

<div class="row g-3">
<div class="col-xl-8">
    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-check2-square"></i>Potentially Eligible Schemes</h3>
            <?php if ($lastMatched): ?>
                <span class="text-muted small">Last matched <?= time_ago($lastMatched) ?></span>
            <?php endif; ?>
        </div>
        <div class="jk-card__body jk-card__body--flush">
            <?php if (empty($positive)): ?>
                <div class="jk-empty">
                    <i class="bi bi-search"></i>
                    <h5><?= $lastMatched ? 'No applicable schemes found' : 'Matching has not been run yet' ?></h5>
                    <p>
                        <?= $lastMatched
                            ? 'Nothing in the current catalogue applies to this profile. Completing more of the assessment, or adding schemes for this State, may change that.'
                            : 'Run matching to compare this beneficiary against every scheme available in their State.' ?>
                    </p>
                    <form method="post" action="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/matching')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-arrow-repeat me-1"></i>Run matching now
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <div class="jk-table-wrap">
                    <table class="jk-table">
                        <thead>
                            <tr>
                                <th style="min-width:230px">Scheme</th>
                                <th style="width:140px">Match %</th>
                                <th>Eligibility</th>
                                <th style="min-width:170px">Missing Documents</th>
                                <th class="no-print" style="width:120px">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($positive as $match): ?>
                                <?php
                                $missing = json_list($match['missing_documents']);
                                $variant = eligibility_variant($match['eligibility_status']);
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?= e(url('/schemes/' . (int)$match['scheme_id'])) ?>" class="fw-semibold">
                                            <?= e($match['short_name'] ?: str_limit($match['scheme_name'], 46)) ?>
                                        </a>
                                        <div class="text-muted tiny">
                                            <?php if (!empty($match['theme_name'])): ?>
                                                <span class="jk-theme-dot" style="background:<?= e($match['theme_color'] ?: '#6c757d') ?>"></span>
                                                <?= e($match['theme_name']) ?> &middot;
                                            <?php endif; ?>
                                            <?= e($match['scheme_level']) ?>
                                            <?php if (!empty($match['priority_rank'])): ?>
                                                &middot; Priority <?= (int)$match['priority_rank'] ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($match['reason_text'])): ?>
                                            <div class="text-muted small mt-1" style="font-size:.76rem">
                                                <?= e(str_limit($match['reason_text'], 155)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="jk-match-pct text-<?= e($variant) ?>">
                                                <?= (int)$match['match_percent'] ?>%
                                            </span>
                                        </div>
                                        <div class="jk-match-bar mt-1">
                                            <span style="width:<?= (float)$match['match_percent'] ?>%;background:var(--bs-<?= e($variant) ?>)"></span>
                                        </div>
                                        <div class="text-subtle tiny mt-1">
                                            <?= (int)$match['rules_passed'] ?>/<?= (int)$match['rules_total'] ?> conditions met
                                            <?php if ((int)$match['rules_unknown'] > 0): ?>
                                                &middot; <?= (int)$match['rules_unknown'] ?> unknown
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?= badge($match['eligibility_status'], $variant) ?></td>
                                    <td>
                                        <?php if (empty($missing)): ?>
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="bi bi-check2 me-1"></i>None
                                            </span>
                                        <?php else: ?>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach (array_slice($missing, 0, 3) as $document): ?>
                                                    <span class="jk-chip text-danger" style="font-size:.7rem">
                                                        <?= e($document) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                                <?php if (count($missing) > 3): ?>
                                                    <span class="jk-chip" style="font-size:.7rem"
                                                          data-bs-toggle="tooltip" title="<?= e(implode(', ', array_slice($missing, 3))) ?>">
                                                        +<?= count($missing) - 3 ?> more
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print">
                                        <div class="d-flex flex-column gap-1">
                                            <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/matching/' . (int)$match['scheme_id'] . '/explain')) ?>"
                                               class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-question-circle me-1"></i>Why?
                                            </a>
                                            <?php if (!empty($match['application_id'])): ?>
                                                <a href="<?= e(url('/applications/' . (int)$match['application_id'])) ?>"
                                                   class="btn btn-sm btn-outline-primary">Track</a>
                                            <?php elseif (can('application.create')): ?>
                                                <a href="<?= e(url('/applications/create?beneficiary_id=' . (int)$beneficiary['id'] . '&scheme_id=' . (int)$match['scheme_id'])) ?>"
                                                   class="btn btn-sm btn-primary">Apply</a>
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
    </div>

    <?php if (!empty($negative)): ?>
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-x-circle"></i>Schemes That Do Not Apply</h3>
                <button class="btn btn-sm btn-outline-secondary" type="button"
                        data-bs-toggle="collapse" data-bs-target="#notEligibleList">
                    Show <?= count($negative) ?>
                </button>
            </div>
            <div class="collapse" id="notEligibleList">
                <div class="jk-card__body jk-card__body--flush">
                    <div class="jk-table-wrap">
                        <table class="jk-table jk-table--compact">
                            <thead><tr><th>Scheme</th><th>Why not</th><th class="no-print"></th></tr></thead>
                            <tbody>
                                <?php foreach ($negative as $match): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= e(url('/schemes/' . (int)$match['scheme_id'])) ?>">
                                                <?= e($match['short_name'] ?: str_limit($match['scheme_name'], 40)) ?>
                                            </a>
                                        </td>
                                        <td class="text-muted small"><?= e(str_limit($match['reason_text'] ?? '', 190)) ?></td>
                                        <td class="no-print text-end">
                                            <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/matching/' . (int)$match['scheme_id'] . '/explain')) ?>"
                                               class="btn btn-sm btn-link p-0">Details</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Side: the profile the engine used -->
<div class="col-xl-4">
    <?php if (!empty($outstanding)): ?>
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-folder-x"></i>Documents To Collect</h3>
                <?php if (can('beneficiary.document')): ?>
                    <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/documents')) ?>" class="small">Upload</a>
                <?php endif; ?>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <div class="list-group list-group-flush">
                    <?php foreach ($outstanding as $document): ?>
                        <div class="list-group-item py-2">
                            <div class="fw-semibold" style="font-size:.84rem"><?= e($document['name']) ?></div>
                            <div class="text-muted tiny">Needed for: <?= e(str_limit($document['required_by'], 70)) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-person-lines-fill"></i>Profile Used for Matching</h3>
        </div>
        <div class="jk-card__body">
            <?php if (empty($profile)): ?>
                <p class="text-muted mb-0 small">
                    No profile attributes are recorded, so nothing can be matched.
                    Complete the assessment first.
                </p>
            <?php else: ?>
                <p class="text-muted small">
                    These are the values the engine compared against each scheme's conditions.
                </p>
                <?php foreach ($profile as $group => $items): ?>
                    <h4 class="h6 text-primary border-bottom pb-1 mb-2 mt-3" style="font-size:.78rem">
                        <?= e($group) ?>
                    </h4>
                    <div class="jk-dl">
                        <?php foreach ($items as $item): ?>
                            <div class="jk-dl__row">
                                <div class="jk-dl__key"><?= e($item['label']) ?></div>
                                <div class="jk-dl__val"><?= e($item['value']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php if (can('beneficiary.assess')): ?>
            <div class="jk-card__foot">
                <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/assessment')) ?>"
                   class="btn btn-sm btn-outline-primary w-100">
                    <i class="bi bi-pencil me-1"></i>Update the assessment
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>
