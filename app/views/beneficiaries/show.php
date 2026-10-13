<?php
/**
 * 360-degree beneficiary profile (specification section 24).
 *
 * @var array $beneficiary, $answers, $assessment, $documents, $outstanding,
 *            $matches, $applications, $followups, $benefits, $visits,
 *            $auditTrail, $timeline
 */
$pageSubtitle = $beneficiary['beneficiary_code'];
$household = $beneficiary['household'] ?? null;

$positiveMatches = array_values(array_filter($matches, fn($m) => $m['eligibility_status'] !== 'Not Eligible'));
$negativeMatches = array_values(array_filter($matches, fn($m) => $m['eligibility_status'] === 'Not Eligible'));

ob_start(); ?>
    <?php if (can('beneficiary.assess')): ?>
        <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/assessment')) ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-clipboard-check me-1"></i>Assessment
        </a>
    <?php endif; ?>
    <?php if (can('beneficiary.match')): ?>
        <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/matching')) ?>" class="btn btn-sm btn-accent">
            <i class="bi bi-check2-square me-1"></i>Eligible Schemes
        </a>
    <?php endif; ?>
    <?php if (can('beneficiary.edit')): ?>
        <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
    <?php endif; ?>
    <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/print')) ?>" target="_blank"
       class="btn btn-sm btn-outline-secondary"><i class="bi bi-printer me-1"></i>Print</a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<!-- Header card -->
<div class="jk-card">
    <div class="jk-card__body">
        <div class="d-flex flex-wrap gap-3 align-items-start">
            <span class="jk-avatar jk-avatar--lg"><?= e(initials($beneficiary['name'])) ?></span>

            <div class="flex-grow-1 min-width-0">
                <h2 class="h5 mb-1"><?= e($beneficiary['name']) ?></h2>
                <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <?= badge($beneficiary['record_status'], status_variant($beneficiary['record_status'])) ?>
                    <?= badge($beneficiary['assessment_status'] . ' assessment',
                              $beneficiary['assessment_status'] === 'Completed' ? 'success' : 'secondary') ?>
                    <span class="jk-chip"><?= e($beneficiary['gender']) ?><?= $beneficiary['age'] !== null ? ', ' . (int)$beneficiary['age'] . ' yrs' : '' ?></span>
                    <span class="jk-chip"><i class="bi bi-house"></i><?= e($beneficiary['area_type']) ?></span>
                    <?php if ((int)$beneficiary['consent_given'] === 1): ?>
                        <span class="jk-chip jk-chip--accent"><i class="bi bi-check2"></i>Consent recorded</span>
                    <?php else: ?>
                        <span class="jk-chip text-warning"><i class="bi bi-exclamation-triangle"></i>No consent recorded</span>
                    <?php endif; ?>
                </div>
                <div class="text-muted small">
                    <i class="bi bi-geo-alt me-1"></i>
                    <?= e(implode(', ', array_filter([
                        $beneficiary['village_display'], $beneficiary['block_name'],
                        $beneficiary['district_name'], $beneficiary['state_name'],
                    ]))) ?>
                    <?php if (!empty($beneficiary['pincode'])): ?> &ndash; <?= e($beneficiary['pincode']) ?><?php endif; ?>
                </div>
            </div>

            <div class="d-flex gap-3 flex-wrap">
                <div class="text-center">
                    <div class="h5 mb-0 text-success"><?= count($positiveMatches) ?></div>
                    <div class="tiny text-muted">Schemes identified</div>
                </div>
                <div class="text-center">
                    <div class="h5 mb-0 text-primary"><?= count($applications) ?></div>
                    <div class="tiny text-muted">Applications</div>
                </div>
                <div class="text-center">
                    <div class="h5 mb-0" style="color:#146c43"><?= count($benefits) ?></div>
                    <div class="tiny text-muted">Benefits received</div>
                </div>
                <div class="text-center">
                    <div class="h5 mb-0 <?= count($outstanding) > 0 ? 'text-danger' : 'text-muted' ?>"><?= count($outstanding) ?></div>
                    <div class="tiny text-muted">Documents pending</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Journey timeline -->
<div class="jk-card">
    <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-signpost-split"></i>Journey</h3></div>
    <div class="jk-card__body">
        <div class="jk-timeline">
            <?php foreach ($timeline as $step): ?>
                <div class="jk-timeline__item <?= !empty($step['done']) ? 'done' : '' ?>">
                    <span class="jk-timeline__dot"><i class="bi <?= e($step['icon']) ?>"></i></span>
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <div>
                            <p class="jk-timeline__title"><?= e($step['label']) ?></p>
                            <div class="jk-timeline__meta"><?= e($step['detail']) ?></div>
                        </div>
                        <?php if (!empty($step['date'])): ?>
                            <span class="text-muted small nowrap"><?= fmt_date($step['date']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabPersonal" type="button">Personal</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabHousehold" type="button">Household</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAssessment" type="button">
        Socio-Economic <span class="badge bg-secondary"><?= count($answers, COUNT_RECURSIVE) - count($answers) ?></span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabDocuments" type="button">
        Documents <span class="badge bg-secondary"><?= count($documents) ?></span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSchemes" type="button">
        Eligible Schemes <span class="badge bg-success"><?= count($positiveMatches) ?></span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabApplications" type="button">
        Applications <span class="badge bg-secondary"><?= count($applications) ?></span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabFollowups" type="button">
        Follow-ups <span class="badge bg-secondary"><?= count($followups) ?></span></button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabVisits" type="button">
        Field Visits <span class="badge bg-secondary"><?= count($visits) ?></span></button></li>
    <?php if (can('audit.view')): ?>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabAudit" type="button">Audit History</button></li>
    <?php endif; ?>
</ul>

<div class="tab-content">

    <!-- ===== Personal ===== -->
    <div class="tab-pane fade show active" id="tabPersonal">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="jk-card h-100">
                    <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-person"></i>Personal Information</h3></div>
                    <div class="jk-card__body">
                        <div class="jk-dl">
                            <div class="jk-dl__row"><div class="jk-dl__key">Beneficiary ID</div>
                                <div class="jk-dl__val"><code><?= e($beneficiary['beneficiary_code']) ?></code></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Registration Date</div>
                                <div class="jk-dl__val"><?= fmt_date($beneficiary['registration_date']) ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Name</div>
                                <div class="jk-dl__val"><?= e($beneficiary['name']) ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Father's Name</div>
                                <div class="jk-dl__val"><?= e($beneficiary['father_name'] ?: '-') ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Mother's Name</div>
                                <div class="jk-dl__val"><?= e($beneficiary['mother_name'] ?: '-') ?></div></div>
                            <?php if (!empty($beneficiary['spouse_name'])): ?>
                                <div class="jk-dl__row"><div class="jk-dl__key">Spouse Name</div>
                                    <div class="jk-dl__val"><?= e($beneficiary['spouse_name']) ?></div></div>
                            <?php endif; ?>
                            <?php if (!empty($beneficiary['relation_name'])): ?>
                                <div class="jk-dl__row"><div class="jk-dl__key">Care Of</div>
                                    <div class="jk-dl__val">
                                        <?= e($beneficiary['relation_name']) ?>
                                        <span class="text-subtle tiny">(<?= e($beneficiary['relation_type'] ?: 'Father') ?>)</span>
                                    </div></div>
                            <?php endif; ?>
                            <div class="jk-dl__row"><div class="jk-dl__key">Gender</div>
                                <div class="jk-dl__val"><?= e($beneficiary['gender']) ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Date of Birth</div>
                                <div class="jk-dl__val"><?= fmt_date($beneficiary['dob']) ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Age</div>
                                <div class="jk-dl__val"><?= $beneficiary['age'] !== null ? (int)$beneficiary['age'] . ' years' : '-' ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Mobile</div>
                                <div class="jk-dl__val"><?= e($beneficiary['mobile'] ?: '-') ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Alternate Mobile</div>
                                <div class="jk-dl__val"><?= e($beneficiary['alt_mobile'] ?: '-') ?></div></div>
                            <div class="jk-dl__row">
                                <div class="jk-dl__key">Aadhaar</div>
                                <div class="jk-dl__val">
                                    <span class="jk-masked"><?= mask_aadhaar($beneficiary['aadhaar_last4']) ?></span>
                                    <span class="d-block text-subtle tiny">
                                        Only the last four digits are stored; the full number is never kept.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="jk-card h-100">
                    <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-geo-alt"></i>Address &amp; Collection</h3></div>
                    <div class="jk-card__body">
                        <div class="jk-dl">
                            <div class="jk-dl__row"><div class="jk-dl__key">State</div>
                                <div class="jk-dl__val"><?= e($beneficiary['state_name'] ?? '-') ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">District</div>
                                <div class="jk-dl__val"><?= e($beneficiary['district_name'] ?? '-') ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Block</div>
                                <div class="jk-dl__val"><?= e($beneficiary['block_name'] ?? '-') ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Gram Panchayat</div>
                                <div class="jk-dl__val"><?= e($beneficiary['gp_name'] ?? '-') ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Village</div>
                                <div class="jk-dl__val"><?= e($beneficiary['village_display'] ?? '-') ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Address</div>
                                <div class="jk-dl__val"><?= e($beneficiary['address_line'] ?? '-') ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">PIN Code</div>
                                <div class="jk-dl__val"><?= e($beneficiary['pincode'] ?? '-') ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Rural / Urban</div>
                                <div class="jk-dl__val"><?= e($beneficiary['area_type']) ?></div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Collected By</div>
                                <div class="jk-dl__val">
                                    <?= e($beneficiary['field_user_name'] ?? '-') ?>
                                    <?php if (!empty($beneficiary['field_user_mobile'])): ?>
                                        <span class="d-block text-subtle tiny"><?= e($beneficiary['field_user_mobile']) ?></span>
                                    <?php endif; ?>
                                </div></div>
                            <div class="jk-dl__row"><div class="jk-dl__key">Record Created</div>
                                <div class="jk-dl__val"><?= fmt_datetime($beneficiary['created_at']) ?>
                                    <?php if (!empty($beneficiary['created_by_name'])): ?>
                                        <span class="d-block text-subtle tiny">by <?= e($beneficiary['created_by_name']) ?></span>
                                    <?php endif; ?>
                                </div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Household ===== -->
    <div class="tab-pane fade" id="tabHousehold">
        <div class="row g-3">
            <div class="col-lg-6">
                <div class="jk-card h-100">
                    <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-house-door"></i>Household Information</h3></div>
                    <div class="jk-card__body">
                        <?php if (!$household): ?>
                            <p class="text-muted mb-0">
                                No household information has been recorded. Household income and ration
                                card details are now asked on the
                                <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/assessment')) ?>">profile
                                questionnaire</a>.
                            </p>
                        <?php else: ?>
                            <div class="jk-dl">
                                <div class="jk-dl__row"><div class="jk-dl__key">Household Head</div>
                                    <div class="jk-dl__val"><?= e($household['household_head_name'] ?? '-') ?></div></div>
                                <div class="jk-dl__row"><div class="jk-dl__key">Family Size</div>
                                    <div class="jk-dl__val"><?= $household['family_size'] !== null ? (int)$household['family_size'] . ' members' : '-' ?></div></div>
                                <div class="jk-dl__row"><div class="jk-dl__key">Adults / Children</div>
                                    <div class="jk-dl__val"><?= (int)$household['adults_count'] ?> / <?= (int)$household['children_count'] ?></div></div>
                                <div class="jk-dl__row"><div class="jk-dl__key">Earning Members</div>
                                    <div class="jk-dl__val"><?= $household['earning_members'] !== null ? (int)$household['earning_members'] : '-' ?></div></div>
                                <div class="jk-dl__row"><div class="jk-dl__key">Annual Family Income</div>
                                    <div class="jk-dl__val fw-semibold"><?= money($household['annual_income']) ?></div></div>
                                <div class="jk-dl__row"><div class="jk-dl__key">Main Income Source</div>
                                    <div class="jk-dl__val"><?= e($household['income_source'] ?? '-') ?></div></div>
                                <div class="jk-dl__row"><div class="jk-dl__key">BPL Status</div>
                                    <div class="jk-dl__val"><?= badge($household['bpl_status'], status_variant($household['bpl_status'])) ?></div></div>
                                <div class="jk-dl__row"><div class="jk-dl__key">Ration Card</div>
                                    <div class="jk-dl__val">
                                        <?= e($household['ration_card_type'] ?: 'Not held') ?>
                                        <?php if (!empty($household['ration_card_last4'])): ?>
                                            <span class="jk-masked ms-1">XXXX<?= e($household['ration_card_last4']) ?></span>
                                        <?php endif; ?>
                                    </div></div>
                                <div class="jk-dl__row"><div class="jk-dl__key">House Type</div>
                                    <div class="jk-dl__val"><?= e($household['house_type'] ?? '-') ?></div></div>
                                <div class="jk-dl__row"><div class="jk-dl__key">Electricity / Toilet</div>
                                    <div class="jk-dl__val"><?= yes_no($household['has_electricity']) ?> / <?= yes_no($household['has_toilet']) ?></div></div>
                                <div class="jk-dl__row"><div class="jk-dl__key">Drinking Water</div>
                                    <div class="jk-dl__val"><?= e($household['drinking_water_source'] ?? '-') ?></div></div>
                                <div class="jk-dl__row"><div class="jk-dl__key">Land Holding</div>
                                    <div class="jk-dl__val"><?= $household['land_holding_acres'] !== null
                                        ? rtrim(rtrim(number_format((float)$household['land_holding_acres'], 2), '0'), '.') . ' acres' : '-' ?></div></div>
                                <div class="jk-dl__row"><div class="jk-dl__key">Livestock</div>
                                    <div class="jk-dl__val"><?= $household['livestock_count'] !== null ? (int)$household['livestock_count'] : '-' ?></div></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="jk-card h-100">
                    <div class="jk-card__head">
                        <h3 class="jk-card__title"><i class="bi bi-people"></i>Household Members</h3>
                        <?php if (can('beneficiary.edit')): ?>
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
                                <i class="bi bi-plus-lg me-1"></i>Add
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="jk-card__body jk-card__body--flush">
                        <?php if (empty($beneficiary['members'])): ?>
                            <p class="text-muted p-3 mb-0">No household members have been recorded.</p>
                        <?php else: ?>
                            <div class="jk-table-wrap">
                                <table class="jk-table jk-table--compact">
                                    <thead><tr><th>Name</th><th>Relation</th><th>Gender</th><th class="num">Age</th><th>Flags</th>
                                        <?php if (can('beneficiary.edit')): ?><th></th><?php endif; ?></tr></thead>
                                    <tbody>
                                        <?php foreach ($beneficiary['members'] as $member): ?>
                                            <tr>
                                                <td class="fw-semibold"><?= e($member['name']) ?></td>
                                                <td><?= e($member['relation'] ?? '-') ?></td>
                                                <td><?= e($member['gender'] ?? '-') ?></td>
                                                <td class="num"><?= $member['age'] !== null ? (int)$member['age'] : '-' ?></td>
                                                <td>
                                                    <?php if ((int)$member['is_disabled'] === 1): ?>
                                                        <span class="badge bg-warning text-dark">Disability</span>
                                                    <?php endif; ?>
                                                    <?php if ((int)$member['is_student'] === 1): ?>
                                                        <span class="badge bg-info text-dark">Student</span>
                                                    <?php endif; ?>
                                                </td>
                                                <?php if (can('beneficiary.edit')): ?>
                                                    <td class="text-end">
                                                        <form method="post" class="d-inline"
                                                              action="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/members/' . (int)$member['id'] . '/delete')) ?>">
                                                            <?= csrf_field() ?>
                                                            <button class="btn btn-sm btn-link text-danger p-0" type="submit"
                                                                    data-confirm="Remove this household member?">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== Socio-economic ===== -->
    <div class="tab-pane fade" id="tabAssessment">
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-clipboard-data"></i>Socio-Economic Information</h3>
                <div class="d-flex gap-2 align-items-center">
                    <?php if ($assessment): ?>
                        <span class="text-muted small">
                            Assessed <?= fmt_date($assessment['assessment_date']) ?>
                            <?= !empty($assessment['assessed_by_name']) ? 'by ' . e($assessment['assessed_by_name']) : '' ?>
                            &middot; <?= e($assessment['completeness_pct']) ?>% complete
                        </span>
                    <?php endif; ?>
                    <?php if (can('beneficiary.assess')): ?>
                        <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/assessment')) ?>"
                           class="btn btn-sm btn-outline-primary">
                            <?= $assessment ? 'Update' : 'Start' ?> assessment
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="jk-card__body">
                <?php if (empty($answers)): ?>
                    <div class="jk-empty">
                        <i class="bi bi-clipboard-x"></i>
                        <h5>No assessment recorded</h5>
                        <p>Until the socio-economic assessment is completed, scheme matching has very little to
                           work with and most schemes will report &ldquo;Needs Verification&rdquo;.</p>
                        <?php if (can('beneficiary.assess')): ?>
                            <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/assessment')) ?>"
                               class="btn btn-sm btn-primary">Complete the assessment</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($answers as $section => $items): ?>
                            <div class="col-lg-6">
                                <h4 class="h6 text-primary border-bottom pb-1 mb-2"><?= e($section) ?></h4>
                                <div class="jk-dl">
                                    <?php foreach ($items as $answer): ?>
                                        <div class="jk-dl__row">
                                            <div class="jk-dl__key"><?= e($answer['label'] ?: ucwords(str_replace('_', ' ', $answer['question_key']))) ?></div>
                                            <div class="jk-dl__val">
                                                <?php
                                                if ($answer['value_bool'] !== null) {
                                                    echo yes_no_badge($answer['value_bool']);
                                                } elseif ($answer['option_label'] !== null) {
                                                    echo e($answer['option_label']);
                                                } elseif ($answer['value_number'] !== null) {
                                                    echo e(rtrim(rtrim(number_format((float)$answer['value_number'], 2), '0'), '.'));
                                                    if (!empty($answer['unit'])) echo ' ' . e($answer['unit']);
                                                } elseif ($answer['value_date'] !== null) {
                                                    echo fmt_date($answer['value_date']);
                                                } else {
                                                    echo e($answer['value_text'] ?? '-');
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== Documents ===== -->
    <div class="tab-pane fade" id="tabDocuments">
        <?php if (!empty($outstanding)): ?>
            <div class="alert alert-warning">
                <div class="d-flex gap-2 align-items-start">
                    <i class="bi bi-exclamation-triangle mt-1"></i>
                    <div>
                        <strong><?= count($outstanding) ?> mandatory document(s) still needed</strong>
                        for the schemes this beneficiary matched:
                        <div class="d-flex flex-wrap gap-1 mt-2">
                            <?php foreach ($outstanding as $document): ?>
                                <span class="jk-chip" data-bs-toggle="tooltip"
                                      title="Required by: <?= e($document['required_by']) ?>">
                                    <i class="bi bi-file-earmark-x"></i><?= e($document['name']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-folder"></i>Uploaded Documents</h3>
                <?php if (can('beneficiary.document')): ?>
                    <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/documents')) ?>"
                       class="btn btn-sm btn-outline-primary"><i class="bi bi-upload me-1"></i>Manage documents</a>
                <?php endif; ?>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <?php if (empty($documents)): ?>
                    <div class="jk-empty">
                        <i class="bi bi-folder-x"></i>
                        <h5>No documents uploaded</h5>
                        <p>Supporting documents are needed before most applications can be submitted.</p>
                    </div>
                <?php else: ?>
                    <div class="jk-table-wrap">
                        <table class="jk-table jk-table--compact">
                            <thead><tr><th>Document</th><th>Number</th><th>Issued</th><th>Expires</th>
                                <th>Verification</th><th>Uploaded</th><th class="no-print"></th></tr></thead>
                            <tbody>
                                <?php foreach ($documents as $document): ?>
                                    <?php $expired = !empty($document['expiry_date']) && strtotime($document['expiry_date']) < time(); ?>
                                    <tr class="<?= $expired ? 'jk-row-warning' : '' ?>">
                                        <td class="fw-semibold"><?= e($document['document_name']) ?></td>
                                        <td class="jk-masked">
                                            <?= mask_document_number($document['document_number'], (bool)$document['is_sensitive']) ?>
                                        </td>
                                        <td><?= fmt_date($document['issue_date']) ?></td>
                                        <td>
                                            <?= fmt_date($document['expiry_date']) ?>
                                            <?php if ($expired): ?><span class="badge bg-warning text-dark ms-1">Expired</span><?php endif; ?>
                                        </td>
                                        <td><?= badge($document['verification_status'], status_variant($document['verification_status'])) ?></td>
                                        <td class="text-muted small">
                                            <?= fmt_date($document['created_at']) ?>
                                            <?php if (!empty($document['uploaded_by_name'])): ?>
                                                <span class="d-block tiny">by <?= e($document['uploaded_by_name']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end no-print">
                                            <?php if (can('beneficiary.document')): ?>
                                                <a href="<?= e(url('/documents/' . (int)$document['id'] . '/view')) ?>"
                                                   target="_blank" class="btn btn-sm btn-outline-secondary"
                                                   data-bs-toggle="tooltip" title="View file"><i class="bi bi-eye"></i></a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== Eligible schemes ===== -->
    <div class="tab-pane fade" id="tabSchemes">
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-check2-square"></i>Potentially Eligible Schemes</h3>
                <?php if (can('beneficiary.match')): ?>
                    <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/matching')) ?>"
                       class="btn btn-sm btn-outline-primary">Open matching workspace</a>
                <?php endif; ?>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <?php if (empty($positiveMatches)): ?>
                    <div class="jk-empty">
                        <i class="bi bi-search"></i>
                        <h5>No schemes identified yet</h5>
                        <p>Run scheme matching to compare this beneficiary's profile against the scheme catalogue.</p>
                        <?php if (can('beneficiary.match')): ?>
                            <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/matching')) ?>"
                               class="btn btn-sm btn-primary">Find eligible schemes</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="jk-table-wrap">
                        <table class="jk-table jk-table--compact">
                            <thead><tr><th>Scheme</th><th style="width:130px">Match</th><th>Eligibility</th>
                                <th>Missing Documents</th><th class="no-print">Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($positiveMatches as $match): ?>
                                    <?php
                                    $missing = json_list($match['missing_documents']);
                                    $variant = eligibility_variant($match['eligibility_status']);
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="<?= e(url('/schemes/' . (int)$match['scheme_id'])) ?>" class="fw-semibold">
                                                <?= e(str_limit($match['short_name'] ?: $match['scheme_name'], 44)) ?>
                                            </a>
                                            <div class="text-muted tiny">
                                                <?= e($match['scheme_level']) ?>
                                                <?= !empty($match['theme_name']) ? ' &middot; ' . e($match['theme_name']) : '' ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="jk-match-pct text-<?= e($variant) ?>"><?= (int)$match['match_percent'] ?>%</span>
                                                <div class="jk-match-bar flex-grow-1">
                                                    <span style="width:<?= (float)$match['match_percent'] ?>%;background:var(--bs-<?= e($variant) ?>)"></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?= badge($match['eligibility_status'], $variant) ?></td>
                                        <td>
                                            <?php if (empty($missing)): ?>
                                                <span class="badge bg-success-subtle text-success">None</span>
                                            <?php else: ?>
                                                <span class="text-danger small"><?= e(str_limit(implode(', ', $missing), 60)) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="no-print">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/matching/' . (int)$match['scheme_id'] . '/explain')) ?>"
                                                   class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Why this matched">
                                                    <i class="bi bi-question-circle"></i>
                                                </a>
                                                <?php if (!empty($match['application_id'])): ?>
                                                    <a href="<?= e(url('/applications/' . (int)$match['application_id'])) ?>"
                                                       class="btn btn-outline-primary">Track</a>
                                                <?php elseif (can('application.create')): ?>
                                                    <a href="<?= e(url('/applications/create?beneficiary_id=' . (int)$beneficiary['id'] . '&scheme_id=' . (int)$match['scheme_id'])) ?>"
                                                       class="btn btn-outline-primary">Apply</a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($negativeMatches)): ?>
                        <div class="p-3 border-top">
                            <button class="btn btn-sm btn-outline-secondary" type="button"
                                    data-bs-toggle="collapse" data-bs-target="#notEligible">
                                Show <?= count($negativeMatches) ?> scheme(s) this beneficiary does not qualify for
                            </button>
                            <div class="collapse mt-2" id="notEligible">
                                <div class="jk-table-wrap">
                                    <table class="jk-table jk-table--compact">
                                        <thead><tr><th>Scheme</th><th>Reason</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($negativeMatches as $match): ?>
                                                <tr>
                                                    <td><a href="<?= e(url('/schemes/' . (int)$match['scheme_id'])) ?>">
                                                        <?= e(str_limit($match['short_name'] ?: $match['scheme_name'], 40)) ?></a></td>
                                                    <td class="text-muted small"><?= e(str_limit($match['reason_text'] ?? '', 160)) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== Applications ===== -->
    <div class="tab-pane fade" id="tabApplications">
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-file-earmark-text"></i>Scheme Applications</h3>
                <?php if (can('application.create')): ?>
                    <a href="<?= e(url('/applications/create?beneficiary_id=' . (int)$beneficiary['id'])) ?>"
                       class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Initiate</a>
                <?php endif; ?>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <?php if (empty($applications)): ?>
                    <div class="jk-empty">
                        <i class="bi bi-file-earmark-x"></i>
                        <h5>No applications yet</h5>
                        <p>Link this beneficiary to a scheme to start tracking their application.</p>
                    </div>
                <?php else: ?>
                    <div class="jk-table-wrap">
                        <table class="jk-table jk-table--compact">
                            <thead><tr><th>Application</th><th>Scheme</th><th>Status</th><th>Submitted</th>
                                <th>Approved</th><th>Benefit</th><th>Next Follow-up</th></tr></thead>
                            <tbody>
                                <?php foreach ($applications as $application): ?>
                                    <tr>
                                        <td><a href="<?= e(url('/applications/' . (int)$application['id'])) ?>" class="fw-semibold">
                                            <?= e($application['application_code']) ?></a></td>
                                        <td><?= e(str_limit($application['scheme_short_name'] ?: $application['scheme_name'], 34)) ?></td>
                                        <td><span class="badge" style="background:<?= e($application['status_color'] ?: '#6c757d') ?>">
                                            <?= e($application['status_name']) ?></span></td>
                                        <td><?= fmt_date($application['submission_date']) ?></td>
                                        <td><?= fmt_date($application['approval_date']) ?></td>
                                        <td>
                                            <?php if ((int)$application['benefit_received'] === 1): ?>
                                                <span class="badge bg-success">
                                                    <?= $application['benefit_amount'] !== null ? money($application['benefit_amount']) : 'Received' ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-subtle">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="<?= !empty($application['next_followup_date']) && strtotime($application['next_followup_date']) < time() ? 'text-danger fw-semibold' : '' ?>">
                                            <?= fmt_date($application['next_followup_date']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($benefits)): ?>
            <div class="jk-card">
                <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-award"></i>Benefits Received</h3></div>
                <div class="jk-card__body jk-card__body--flush">
                    <div class="jk-table-wrap">
                        <table class="jk-table jk-table--compact">
                            <thead><tr><th>Date</th><th>Scheme</th><th>Type</th><th class="num">Amount</th>
                                <th>Mode</th><th>Reference</th></tr></thead>
                            <tbody>
                                <?php foreach ($benefits as $benefit): ?>
                                    <tr>
                                        <td><?= fmt_date($benefit['received_date']) ?></td>
                                        <td><?= e($benefit['short_name'] ?: $benefit['scheme_name']) ?></td>
                                        <td><?= e($benefit['benefit_type'] ?? '-') ?></td>
                                        <td class="num fw-semibold"><?= money($benefit['amount']) ?></td>
                                        <td><?= e($benefit['payment_mode']) ?></td>
                                        <td class="text-muted small"><?= e($benefit['reference_no'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== Follow-ups ===== -->
    <div class="tab-pane fade" id="tabFollowups">
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-telephone"></i>Follow-up History</h3>
                <?php if (can('followup.create')): ?>
                    <a href="<?= e(url('/followups/create?beneficiary_id=' . (int)$beneficiary['id'])) ?>"
                       class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>Record</a>
                <?php endif; ?>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <?php if (empty($followups)): ?>
                    <div class="jk-empty"><i class="bi bi-telephone-x"></i>
                        <h5>No follow-ups recorded</h5>
                        <p>Follow-up calls and visits logged against this beneficiary appear here.</p></div>
                <?php else: ?>
                    <div class="jk-table-wrap">
                        <table class="jk-table jk-table--compact">
                            <thead><tr><th>Date</th><th>Type</th><th>Scheme</th><th>Discussion</th>
                                <th>Outcome</th><th>Next</th><th>By</th></tr></thead>
                            <tbody>
                                <?php foreach ($followups as $followup): ?>
                                    <tr>
                                        <td class="nowrap"><?= fmt_date($followup['followup_date']) ?></td>
                                        <td><?= e($followup['followup_type']) ?></td>
                                        <td><?= e(str_limit($followup['scheme_short_name'] ?: ($followup['scheme_name'] ?? '-'), 24)) ?></td>
                                        <td class="text-muted small"><?= e(str_limit($followup['discussion'] ?? '-', 90)) ?></td>
                                        <td><?= badge($followup['outcome'], status_variant($followup['outcome'])) ?></td>
                                        <td class="nowrap"><?= fmt_date($followup['next_followup_date']) ?></td>
                                        <td class="text-muted small"><?= e($followup['responsible_name'] ?? $followup['created_by_name'] ?? '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== Field visits ===== -->
    <div class="tab-pane fade" id="tabVisits">
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-geo"></i>Field Visits</h3>
                <?php if (can('beneficiary.edit')): ?>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addVisitModal">
                        <i class="bi bi-plus-lg me-1"></i>Record Visit
                    </button>
                <?php endif; ?>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <?php if (empty($visits)): ?>
                    <div class="jk-empty"><i class="bi bi-geo"></i><h5>No field visits recorded</h5></div>
                <?php else: ?>
                    <div class="jk-table-wrap">
                        <table class="jk-table jk-table--compact">
                            <thead><tr><th>Date</th><th>Type</th><th>Field User</th><th>Purpose</th><th>Observations</th></tr></thead>
                            <tbody>
                                <?php foreach ($visits as $visit): ?>
                                    <tr>
                                        <td class="nowrap"><?= fmt_date($visit['visit_date']) ?></td>
                                        <td><?= e($visit['visit_type']) ?></td>
                                        <td><?= e($visit['field_user_name'] ?? '-') ?></td>
                                        <td class="text-muted small"><?= e(str_limit($visit['purpose'] ?? '-', 60)) ?></td>
                                        <td class="text-muted small"><?= e(str_limit($visit['observations'] ?? '-', 80)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ===== Audit ===== -->
    <?php if (can('audit.view')): ?>
        <div class="tab-pane fade" id="tabAudit">
            <div class="jk-card">
                <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-clock-history"></i>Audit History</h3></div>
                <div class="jk-card__body jk-card__body--flush">
                    <?php if (empty($auditTrail)): ?>
                        <div class="jk-empty"><i class="bi bi-clock-history"></i><h5>No audit entries</h5></div>
                    <?php else: ?>
                        <div class="jk-table-wrap">
                            <table class="jk-table jk-table--compact">
                                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Description</th><th>IP</th></tr></thead>
                                <tbody>
                                    <?php foreach ($auditTrail as $entry): ?>
                                        <tr>
                                            <td class="nowrap"><?= fmt_datetime($entry['created_at']) ?></td>
                                            <td><?= e($entry['user_name'] ?? 'System') ?></td>
                                            <td><?= badge($entry['action'], 'secondary') ?></td>
                                            <td class="text-muted small"><?= e(str_limit($entry['description'] ?? '-', 90)) ?></td>
                                            <td class="text-subtle tiny"><?= e($entry['ip_address'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add household member -->
<?php if (can('beneficiary.edit')): ?>
<div class="modal fade" id="addMemberModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post"
              action="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/members')) ?>" data-guard>
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title">Add Household Member</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label" for="m_name">Name <span class="req">*</span></label>
                        <input type="text" class="form-control" id="m_name" name="name" required maxlength="150">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="m_relation">Relation</label>
                        <select class="form-select" id="m_relation" name="relation">
                            <?php foreach (['Spouse', 'Son', 'Daughter', 'Mother', 'Father', 'Brother', 'Sister', 'Other'] as $relation): ?>
                                <option value="<?= e($relation) ?>"><?= e($relation) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="m_gender">Gender</label>
                        <select class="form-select" id="m_gender" name="gender">
                            <option value="">-</option>
                            <option value="Female">Female</option>
                            <option value="Male">Male</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="m_age">Age</label>
                        <input type="number" class="form-control" id="m_age" name="age" min="0" max="120">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="m_occupation">Occupation</label>
                        <input type="text" class="form-control" id="m_occupation" name="occupation" maxlength="80">
                    </div>
                    <div class="col-12 d-flex gap-3 mt-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="m_disabled" name="is_disabled" value="1">
                            <label class="form-check-label" for="m_disabled">Has a disability</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="m_student" name="is_student" value="1">
                            <label class="form-check-label" for="m_student">Currently studying</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Member</button>
            </div>
        </form>
    </div>
</div>

<!-- Record field visit -->
<div class="modal fade" id="addVisitModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" method="post"
              action="<?= e(url('/beneficiaries/' . (int)$beneficiary['id'] . '/visits')) ?>" data-guard>
            <?= csrf_field() ?>
            <div class="modal-header">
                <h5 class="modal-title">Record Field Visit</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label" for="v_date">Visit Date <span class="req">*</span></label>
                        <input type="date" class="form-control" id="v_date" name="visit_date" required
                               max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="v_type">Visit Type <span class="req">*</span></label>
                        <select class="form-select" id="v_type" name="visit_type" required>
                            <?php foreach (['Assessment', 'Document Collection', 'Follow-up', 'Verification',
                                            'Awareness Camp', 'Other'] as $type): ?>
                                <option value="<?= e($type) ?>"><?= e($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="v_purpose">Purpose</label>
                        <input type="text" class="form-control" id="v_purpose" name="purpose" maxlength="255">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="v_obs">Observations</label>
                        <textarea class="form-control" id="v_obs" name="observations" rows="3"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Record Visit</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
