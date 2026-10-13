<?php
/**
 * Scheme detail page (specification section 35).
 *
 * @var array $scheme, $related, $usage
 * @var JanKavach\Libraries\EligibilityEngine $engine
 */
$pageSubtitle = $scheme['scheme_code'];

ob_start(); ?>
    <?php if (can('beneficiary.match')): ?>
        <a href="<?= e(url('/matching/eligibility-check?scheme_id=' . (int)$scheme['id'])) ?>"
           class="btn btn-sm btn-accent">
            <i class="bi bi-check2-square me-1"></i>Check Eligibility
        </a>
    <?php endif; ?>
    <?php if (can('beneficiary.create')): ?>
        <a href="<?= e(url('/beneficiaries/create')) ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-person-plus me-1"></i>Identify Beneficiary
        </a>
    <?php endif; ?>
    <?php if (can('application.create')): ?>
        <a href="<?= e(url('/applications/create?scheme_id=' . (int)$scheme['id'])) ?>"
           class="btn btn-sm btn-outline-primary">
            <i class="bi bi-file-earmark-plus me-1"></i>Initiate Application
        </a>
    <?php endif; ?>
    <div class="btn-group">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">More</button>
        <ul class="dropdown-menu dropdown-menu-end">
            <?php if (can('scheme.edit')): ?>
                <li><a class="dropdown-item" href="<?= e(url('/schemes/' . (int)$scheme['id'] . '/edit')) ?>">
                    <i class="bi bi-pencil me-2"></i>Edit scheme</a></li>
                <li><a class="dropdown-item" href="<?= e(url('/schemes/' . (int)$scheme['id'] . '/documents')) ?>">
                    <i class="bi bi-file-earmark-text me-2"></i>Required documents</a></li>
            <?php endif; ?>
            <?php if (can('scheme.rules')): ?>
                <li><a class="dropdown-item" href="<?= e(url('/schemes/' . (int)$scheme['id'] . '/rules')) ?>">
                    <i class="bi bi-sliders me-2"></i>Eligibility rules</a></li>
            <?php endif; ?>
            <li><a class="dropdown-item" href="<?= e(url('/schemes/' . (int)$scheme['id'] . '/print')) ?>" target="_blank">
                <i class="bi bi-printer me-2"></i>Print / Save as PDF</a></li>
        </ul>
    </div>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<!-- Badge strip -->
<div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <?php if ($scheme['scheme_level'] === 'National'): ?>
        <span class="badge bg-primary"><i class="bi bi-flag me-1"></i>National</span>
    <?php elseif ($scheme['scheme_level'] === 'National+State'): ?>
        <span class="badge bg-info text-dark"><i class="bi bi-flag me-1"></i>National + State</span>
    <?php else: ?>
        <span class="badge" style="background:#2a9d8f"><i class="bi bi-map me-1"></i>State</span>
    <?php endif; ?>

    <?php if (!empty($scheme['state_name'])): ?>
        <span class="jk-chip"><i class="bi bi-geo-alt"></i><?= e($scheme['state_name']) ?></span>
    <?php endif; ?>

    <?= badge($scheme['scheme_status'], status_variant($scheme['scheme_status'])) ?>

    <?php if ((int)$scheme['active_enrollment'] === 1): ?>
        <span class="badge bg-success-subtle text-success">Enrolment Open</span>
    <?php else: ?>
        <span class="badge bg-secondary-subtle text-secondary">Enrolment Closed</span>
    <?php endif; ?>

    <?php foreach ($scheme['themes'] as $theme): ?>
        <?= badge_color($theme['name'], $theme['color']) ?>
    <?php endforeach; ?>

    <?php if (!empty($scheme['priority_rank'])): ?>
        <span class="badge bg-danger">Priority <?= (int)$scheme['priority_rank'] ?></span>
    <?php endif; ?>
    <?php if (!empty($scheme['feasibility_rank'])): ?>
        <span class="badge bg-success">Feasibility <?= (int)$scheme['feasibility_rank'] ?>/5</span>
    <?php endif; ?>
    <?php if ((int)$scheme['is_active'] === 0): ?>
        <span class="badge bg-secondary">Record Inactive</span>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">

        <!-- About -->
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-info-circle"></i>About the Scheme</h3></div>
            <div class="jk-card__body">
                <?php if (!empty($scheme['description'])): ?>
                    <div class="jk-prose mb-3"><?= nl2br_e($scheme['description']) ?></div>
                <?php endif; ?>

                <div class="jk-dl">
                    <div class="jk-dl__row">
                        <div class="jk-dl__key">Scheme ID</div>
                        <div class="jk-dl__val"><code><?= e($scheme['scheme_code']) ?></code></div>
                    </div>
                    <div class="jk-dl__row">
                        <div class="jk-dl__key">Scheme Name</div>
                        <div class="jk-dl__val"><?= e($scheme['name']) ?></div>
                    </div>
                    <?php if (!empty($scheme['short_name'])): ?>
                        <div class="jk-dl__row">
                            <div class="jk-dl__key">Short Name</div>
                            <div class="jk-dl__val"><?= e($scheme['short_name']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($scheme['ministry_agency']) || !empty($scheme['ministry_name'])): ?>
                        <div class="jk-dl__row">
                            <div class="jk-dl__key">Implementing Ministry</div>
                            <div class="jk-dl__val"><?= e($scheme['ministry_agency'] ?: $scheme['ministry_name']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($scheme['department_agency']) || !empty($scheme['department_name'])): ?>
                        <div class="jk-dl__row">
                            <div class="jk-dl__key">Implementing Department</div>
                            <div class="jk-dl__val"><?= e($scheme['department_agency'] ?: $scheme['department_name']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($scheme['launch_year'])): ?>
                        <div class="jk-dl__row">
                            <div class="jk-dl__key">Launch Year</div>
                            <div class="jk-dl__val"><?= (int)$scheme['launch_year'] ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="jk-dl__row">
                        <div class="jk-dl__key">Priority Ranking</div>
                        <div class="jk-dl__val">
                            <?php if (!empty($scheme['priority_name'])): ?>
                                <?= e($scheme['priority_name']) ?>
                            <?php else: ?>
                                <span class="text-muted">Not ranked</span>
                                <span class="text-subtle tiny d-block">Blank in the source workbook; set it under Edit.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="jk-dl__row">
                        <div class="jk-dl__key">Feasibility Ranking</div>
                        <div class="jk-dl__val">
                            <?= !empty($scheme['feasibility_name'])
                                ? e($scheme['feasibility_name'] . ' (' . (int)$scheme['feasibility_rank'] . '/5)')
                                : '<span class="text-muted">Not ranked</span>' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Who can benefit -->
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-people"></i>Who Can Benefit?</h3></div>
            <div class="jk-card__body">
                <?php if (!empty($scheme['beneficiary_types'])): ?>
                    <div class="mb-3">
                        <div class="text-muted small fw-semibold mb-1">Beneficiary Type</div>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($scheme['beneficiary_types'] as $type): ?>
                                <span class="jk-chip jk-chip--primary">
                                    <i class="bi <?= e($type['icon'] ?: 'bi-people') ?>"></i><?= e($type['name']) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($scheme['target_groups'])): ?>
                    <div class="mb-3">
                        <div class="text-muted small fw-semibold mb-1">Target Groups</div>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($scheme['target_groups'] as $group): ?>
                                <span class="jk-chip jk-chip--accent"><?= e($group['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($scheme['target_group_summary'])): ?>
                    <div>
                        <div class="text-muted small fw-semibold mb-1">Target Group (as recorded in the source)</div>
                        <div class="jk-prose"><?= nl2br_e($scheme['target_group_summary']) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (empty($scheme['beneficiary_types']) && empty($scheme['target_group_summary'])): ?>
                    <p class="text-muted mb-0">No beneficiary classification has been recorded for this scheme.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Eligibility -->
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-check2-square"></i>Eligibility Criteria</h3>
                <?php if (can('scheme.rules')): ?>
                    <a href="<?= e(url('/schemes/' . (int)$scheme['id'] . '/rules')) ?>" class="small">
                        <?= count($scheme['rules']) > 0 ? 'Edit rules' : 'Configure rules' ?>
                    </a>
                <?php endif; ?>
            </div>
            <div class="jk-card__body">
                <?php if (!empty($scheme['eligibility_summary'])): ?>
                    <div class="mb-3">
                        <div class="text-muted small fw-semibold mb-1">As recorded in the source document</div>
                        <div class="jk-prose"><?= nl2br_e($scheme['eligibility_summary']) ?></div>
                    </div>
                <?php endif; ?>

                <div class="text-muted small fw-semibold mb-2">
                    Structured rules used for automatic matching
                    <span class="badge bg-secondary ms-1"><?= count($scheme['rules']) ?></span>
                </div>

                <?php if (empty($scheme['rules'])): ?>
                    <div class="alert alert-warning mb-0 d-flex gap-2 align-items-start">
                        <i class="bi bi-exclamation-triangle mt-1"></i>
                        <div>
                            No structured eligibility rules are configured, so this scheme will be reported as
                            <strong>Needs Verification</strong> for every beneficiary rather than matched automatically.
                            <?php if (can('scheme.rules')): ?>
                                <a href="<?= e(url('/schemes/' . (int)$scheme['id'] . '/rules')) ?>" class="alert-link">
                                    Configure the rules
                                </a>.
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <?php
                    $grouped = [];
                    foreach ($scheme['rules'] as $rule) {
                        $grouped[(int)$rule['rule_group']][] = $rule;
                    }
                    ?>
                    <?php foreach ($grouped as $groupNo => $rules): ?>
                        <?php if (count($grouped) > 1): ?>
                            <div class="jk-rule-group-head">
                                Qualifying route <?= (int)$groupNo ?>
                                <?php if ($groupNo > 1): ?><span class="text-muted">(alternative)</span><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="jk-table-wrap">
                            <table class="jk-table jk-table--compact">
                                <thead>
                                    <tr>
                                        <th>Condition</th><th>Requirement</th>
                                        <th class="num">Weight</th><th>Mandatory</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rules as $rule): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-semibold"><?= e($rule['attribute_label'] ?: $rule['attribute_key']) ?></span>
                                                <?php if (!empty($rule['description'])): ?>
                                                    <span class="d-block text-subtle tiny"><?= e(str_limit($rule['description'], 150)) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $operators = $engine->operators();
                                                $value = $rule['value_list']
                                                    ? implode(', ', array_map(
                                                        fn($v) => $engine->displayValue($rule['attribute_key'], trim($v)),
                                                        explode(',', $rule['value_list'])))
                                                    : $engine->displayValue($rule['attribute_key'], $rule['value1']);
                                                if ($rule['operator'] === 'between') {
                                                    $value .= ' to ' . $engine->displayValue($rule['attribute_key'], $rule['value2']);
                                                }
                                                ?>
                                                <span class="text-muted"><?= e($operators[$rule['operator']] ?? $rule['operator']) ?></span>
                                                <strong><?= e($value) ?></strong>
                                                <?php if (!empty($rule['unit'])): ?>
                                                    <span class="text-muted"><?= e($rule['unit']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="num"><?= (int)$rule['weight'] ?></td>
                                            <td>
                                                <?php if ((int)$rule['is_mandatory'] === 1): ?>
                                                    <span class="badge bg-danger-subtle text-danger"
                                                          data-bs-toggle="tooltip"
                                                          title="Failing this condition rules the beneficiary out">Required</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border">Scored</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($grouped) > 1): ?>
                        <p class="text-muted small mb-0 mt-2">
                            <i class="bi bi-info-circle me-1"></i>A beneficiary qualifies by satisfying
                            <strong>any one</strong> of the routes above; within a route, every condition applies.
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Benefits -->
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-gift"></i>Benefits</h3></div>
            <div class="jk-card__body">
                <?php if (!empty($scheme['benefit_description']) || !empty($scheme['benefits'])): ?>
                    <div class="jk-dl mb-3">
                        <?php if (!empty($scheme['benefit_type'])): ?>
                            <div class="jk-dl__row">
                                <div class="jk-dl__key">Type of Benefit</div>
                                <div class="jk-dl__val"><?= e($scheme['benefit_type']) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if ($scheme['benefit_amount'] !== null): ?>
                            <div class="jk-dl__row">
                                <div class="jk-dl__key">Benefit Amount</div>
                                <div class="jk-dl__val fw-semibold"><?= money($scheme['benefit_amount']) ?></div>
                            </div>
                        <?php elseif (!empty($scheme['benefit_amount_text'])): ?>
                            <div class="jk-dl__row">
                                <div class="jk-dl__key">Benefit Amount</div>
                                <div class="jk-dl__val"><?= e($scheme['benefit_amount_text']) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($scheme['benefit_mode'])): ?>
                            <div class="jk-dl__row">
                                <div class="jk-dl__key">Recurring / One-time</div>
                                <div class="jk-dl__val"><?= e($scheme['benefit_mode']) ?></div>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($scheme['benefit_frequency'])): ?>
                            <div class="jk-dl__row">
                                <div class="jk-dl__key">Benefit Frequency</div>
                                <div class="jk-dl__val"><?= e($scheme['benefit_frequency']) ?></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($scheme['benefit_description'])): ?>
                        <div class="text-muted small fw-semibold mb-1">Benefit Description</div>
                        <div class="jk-prose"><?= nl2br_e($scheme['benefit_description']) ?></div>
                    <?php endif; ?>

                    <?php if (!empty($scheme['benefit_conditions'])): ?>
                        <div class="mt-3">
                            <div class="text-muted small fw-semibold mb-1">Conditions</div>
                            <div class="jk-prose"><?= nl2br_e($scheme['benefit_conditions']) ?></div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-muted mb-0">No benefit details have been recorded for this scheme.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Required documents -->
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title">
                    <i class="bi bi-file-earmark-text"></i>Required Documents
                    <span class="badge bg-secondary"><?= count($scheme['documents']) ?></span>
                </h3>
                <?php if (can('scheme.edit')): ?>
                    <a href="<?= e(url('/schemes/' . (int)$scheme['id'] . '/documents')) ?>" class="small">Manage</a>
                <?php endif; ?>
            </div>
            <div class="jk-card__body <?= empty($scheme['documents']) ? '' : 'jk-card__body--flush' ?>">
                <?php if (empty($scheme['documents'])): ?>
                    <p class="text-muted mb-0">No documents have been linked to this scheme yet.</p>
                <?php else: ?>
                    <div class="jk-table-wrap">
                        <table class="jk-table jk-table--compact">
                            <thead>
                                <tr><th>Document</th><th>Category</th><th>Mandatory</th><th>Validity Required</th><th>Remarks</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($scheme['documents'] as $document): ?>
                                    <tr>
                                        <td class="fw-semibold"><?= e($document['name']) ?></td>
                                        <td><span class="jk-chip"><?= e($document['category']) ?></span></td>
                                        <td><?= (int)$document['is_mandatory'] === 1
                                                ? '<span class="badge bg-danger-subtle text-danger">Mandatory</span>'
                                                : '<span class="badge bg-light text-muted border">Optional</span>' ?></td>
                                        <td><?= yes_no_badge($document['validity_required']) ?></td>
                                        <td class="text-muted small"><?= e(str_limit($document['remarks'] ?? '-', 60)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- How to apply -->
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-send"></i>How to Apply</h3></div>
            <div class="jk-card__body">
                <div class="jk-dl mb-3">
                    <div class="jk-dl__row">
                        <div class="jk-dl__key">Application Mode</div>
                        <div class="jk-dl__val"><?= badge($scheme['application_mode'] ?: 'Offline', 'info') ?></div>
                    </div>
                    <?php if (!empty($scheme['portal_url'])): ?>
                        <div class="jk-dl__row">
                            <div class="jk-dl__key">Official Portal</div>
                            <div class="jk-dl__val"><?= link_external($scheme['portal_url']) ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="jk-dl__row">
                        <div class="jk-dl__key">Common Service Centre</div>
                        <div class="jk-dl__val"><?= yes_no_badge($scheme['csc_available']) ?></div>
                    </div>
                    <?php if (!empty($scheme['physical_office'])): ?>
                        <div class="jk-dl__row">
                            <div class="jk-dl__key">Physical Office</div>
                            <div class="jk-dl__val"><?= e($scheme['physical_office']) ?></div>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($scheme['helpline_number'])): ?>
                        <div class="jk-dl__row">
                            <div class="jk-dl__key">Helpline</div>
                            <div class="jk-dl__val">
                                <a href="tel:<?= e(preg_replace('/\s+/', '', $scheme['helpline_number'])) ?>">
                                    <i class="bi bi-telephone me-1"></i><?= e($scheme['helpline_number']) ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($scheme['application_process'])): ?>
                    <div class="text-muted small fw-semibold mb-1">Application Process</div>
                    <div class="jk-prose"><?= nl2br_e($scheme['application_process']) ?></div>
                <?php endif; ?>

                <?php if (!empty($scheme['application_instructions'])): ?>
                    <div class="mt-3">
                        <div class="text-muted small fw-semibold mb-1">Additional Instructions</div>
                        <div class="jk-prose"><?= nl2br_e($scheme['application_instructions']) ?></div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ============================================================= -->
    <div class="col-lg-4">

        <!-- Service approach -->
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-diagram-3"></i>Service Approach</h3></div>
            <div class="jk-card__body">
                <?php if (!empty($scheme['service_approach_name'])): ?>
                    <?= badge_color($scheme['service_approach_name'], $scheme['service_approach_color'] ?: '#0dcaf0') ?>
                    <p class="text-muted small mb-0 mt-2">
                        How the programme engages with this scheme in the field.
                    </p>
                <?php else: ?>
                    <p class="text-muted mb-0 small">No service approach recorded.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Source -->
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-link-45deg"></i>Source Information</h3></div>
            <div class="jk-card__body">
                <div class="jk-dl">
                    <div class="jk-dl__row">
                        <div class="jk-dl__key">Official Source</div>
                        <div class="jk-dl__val"><?= link_external($scheme['source_url']) ?></div>
                    </div>
                    <?php if (!empty($scheme['source_website'])): ?>
                        <div class="jk-dl__row">
                            <div class="jk-dl__key">Government Website</div>
                            <div class="jk-dl__val"><?= e($scheme['source_website']) ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="jk-dl__row">
                        <div class="jk-dl__key">Last Verified</div>
                        <div class="jk-dl__val">
                            <?= !empty($scheme['last_verified_date'])
                                ? fmt_date($scheme['last_verified_date'])
                                : '<span class="text-muted">Not verified yet</span>' ?>
                        </div>
                    </div>
                    <?php if (!empty($scheme['verified_by'])): ?>
                        <div class="jk-dl__row">
                            <div class="jk-dl__key">Verified By</div>
                            <div class="jk-dl__val"><?= e($scheme['verified_by']) ?></div>
                        </div>
                    <?php endif; ?>
                    <div class="jk-dl__row">
                        <div class="jk-dl__key">Last Updated</div>
                        <div class="jk-dl__val">
                            <?= fmt_datetime($scheme['updated_at']) ?>
                            <?php if (!empty($scheme['updated_by_name'])): ?>
                                <span class="d-block text-subtle tiny">by <?= e($scheme['updated_by_name']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applicable geography -->
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-geo-alt"></i>Applicable Area</h3></div>
            <div class="jk-card__body">
                <?php if ($scheme['scheme_level'] === 'National'): ?>
                    <p class="mb-0">
                        <i class="bi bi-flag text-primary me-1"></i>
                        Applies across all States and Union Territories.
                    </p>
                <?php else: ?>
                    <div class="d-flex flex-wrap gap-1">
                        <?php if ($scheme['scheme_level'] === 'National+State'): ?>
                            <span class="jk-chip jk-chip--primary"><i class="bi bi-flag"></i>National</span>
                        <?php endif; ?>
                        <?php foreach ($scheme['states'] as $state): ?>
                            <span class="jk-chip jk-chip--accent"><i class="bi bi-geo-alt"></i><?= e($state['name']) ?></span>
                        <?php endforeach; ?>
                        <?php if (empty($scheme['states']) && !empty($scheme['state_name'])): ?>
                            <span class="jk-chip jk-chip--accent"><i class="bi bi-geo-alt"></i><?= e($scheme['state_name']) ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Usage -->
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-graph-up"></i>Programme Usage</h3></div>
            <div class="jk-card__body">
                <div class="jk-dl">
                    <div class="jk-dl__row">
                        <div class="jk-dl__key">Beneficiaries matched</div>
                        <div class="jk-dl__val fw-semibold"><?= num($usage['matches']) ?></div>
                    </div>
                    <div class="jk-dl__row">
                        <div class="jk-dl__key">Applications</div>
                        <div class="jk-dl__val fw-semibold"><?= num($usage['applications']) ?></div>
                    </div>
                    <div class="jk-dl__row">
                        <div class="jk-dl__key">Benefits recorded</div>
                        <div class="jk-dl__val fw-semibold"><?= num($usage['benefits']) ?></div>
                    </div>
                    <div class="jk-dl__row">
                        <div class="jk-dl__key">Detail page views</div>
                        <div class="jk-dl__val"><?= num($scheme['view_count']) ?></div>
                    </div>
                </div>
                <?php if (can('application.view') && $usage['applications'] > 0): ?>
                    <a href="<?= e(url('/applications?scheme_id=' . (int)$scheme['id'])) ?>"
                       class="btn btn-sm btn-outline-primary w-100 mt-2">View applications</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Related -->
        <?php if (!empty($related)): ?>
            <div class="jk-card">
                <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-collection"></i>Related Schemes</h3></div>
                <div class="jk-card__body jk-card__body--flush">
                    <div class="list-group list-group-flush">
                        <?php foreach ($related as $item): ?>
                            <a href="<?= e(url('/schemes/' . (int)$item['id'])) ?>"
                               class="list-group-item list-group-item-action py-2">
                                <div class="fw-semibold" style="font-size:.84rem">
                                    <?= e(str_limit($item['short_name'] ?: $item['name'], 40)) ?>
                                </div>
                                <div class="text-muted tiny">
                                    <?= e($item['scheme_level']) ?>
                                    <?= !empty($item['state_name']) ? ' &middot; ' . e($item['state_name']) : '' ?>
                                    <?= !empty($item['theme_name']) ? ' &middot; ' . e($item['theme_name']) : '' ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
