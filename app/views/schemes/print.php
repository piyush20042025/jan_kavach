<?php
/**
 * Printable scheme fact sheet.
 * @var array $scheme
 * @var JanKavach\Libraries\EligibilityEngine $engine
 */
?>
<div class="jk-print-head">
    <div>
        <h1><?= e($scheme['name']) ?></h1>
        <div class="text-muted small">
            <?= e($scheme['scheme_code']) ?>
            <?= !empty($scheme['short_name']) ? ' &middot; ' . e($scheme['short_name']) : '' ?>
            &middot; <?= e($scheme['scheme_level']) ?>
            <?= !empty($scheme['state_name']) ? ' &middot; ' . e($scheme['state_name']) : '' ?>
        </div>
    </div>
    <div class="jk-print-meta">
        <img class="jk-print-logo mb-1" src="<?= e(asset('images/logo-sm.png')) ?>"
             alt="<?= e($appName ?? 'Jan Kavach') ?>" height="42">
        <div><strong>Jan Kavach</strong></div>
        <div>Scheme Fact Sheet</div>
        <div>Printed <?= fmt_datetime(date('Y-m-d H:i:s')) ?></div>
    </div>
</div>

<table class="jk-table jk-table--compact mb-3">
    <tbody>
        <tr><th style="width:190px">Scheme Status</th><td><?= e($scheme['scheme_status']) ?></td>
            <th style="width:170px">Active Enrolment</th><td><?= yes_no($scheme['active_enrollment']) ?></td></tr>
        <tr><th>Theme</th><td><?= e(implode(', ', array_column($scheme['themes'], 'name')) ?: '-') ?></td>
            <th>Service Approach</th><td><?= e($scheme['service_approach_name'] ?? '-') ?></td></tr>
        <tr><th>Beneficiary Type</th><td><?= e(implode(', ', array_column($scheme['beneficiary_types'], 'name')) ?: '-') ?></td>
            <th>Priority / Feasibility</th>
            <td><?= e(($scheme['priority_name'] ?? 'Not ranked')) ?> /
                <?= e($scheme['feasibility_name'] ?? 'Not ranked') ?></td></tr>
        <tr><th>Implementing Ministry</th><td><?= e($scheme['ministry_agency'] ?: ($scheme['ministry_name'] ?: '-')) ?></td>
            <th>Department</th><td><?= e($scheme['department_agency'] ?: ($scheme['department_name'] ?: '-')) ?></td></tr>
    </tbody>
</table>

<?php if (!empty($scheme['target_group_summary'])): ?>
    <h2 class="h6 text-primary">Target Group</h2>
    <p class="jk-prose"><?= nl2br_e($scheme['target_group_summary']) ?></p>
<?php endif; ?>

<?php if (!empty($scheme['eligibility_summary'])): ?>
    <h2 class="h6 text-primary">Eligibility Criteria</h2>
    <p class="jk-prose"><?= nl2br_e($scheme['eligibility_summary']) ?></p>
<?php endif; ?>

<?php if (!empty($scheme['rules'])): ?>
    <h2 class="h6 text-primary">Structured Eligibility Rules</h2>
    <table class="jk-table jk-table--compact mb-3">
        <thead><tr><th>Group</th><th>Condition</th><th>Requirement</th><th>Mandatory</th></tr></thead>
        <tbody>
            <?php $operators = $engine->operators(); ?>
            <?php foreach ($scheme['rules'] as $rule): ?>
                <tr>
                    <td><?= (int)$rule['rule_group'] ?></td>
                    <td><?= e($rule['attribute_label'] ?: $rule['attribute_key']) ?></td>
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
                        ?>
                        <?= e($operators[$rule['operator']] ?? $rule['operator']) ?> <?= e($value) ?>
                    </td>
                    <td><?= (int)$rule['is_mandatory'] === 1 ? 'Yes' : 'No' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (!empty($scheme['benefit_description'])): ?>
    <h2 class="h6 text-primary">Benefits</h2>
    <?php if ($scheme['benefit_amount'] !== null || !empty($scheme['benefit_amount_text'])): ?>
        <p class="mb-1"><strong>Amount:</strong>
            <?= $scheme['benefit_amount'] !== null ? money($scheme['benefit_amount']) : e($scheme['benefit_amount_text']) ?>
            <?= !empty($scheme['benefit_mode']) ? ' (' . e($scheme['benefit_mode']) . ')' : '' ?>
        </p>
    <?php endif; ?>
    <p class="jk-prose"><?= nl2br_e($scheme['benefit_description']) ?></p>
<?php endif; ?>

<?php if (!empty($scheme['documents'])): ?>
    <h2 class="h6 text-primary">Required Documents</h2>
    <table class="jk-table jk-table--compact mb-3">
        <thead><tr><th>Document</th><th>Category</th><th>Mandatory</th><th>Remarks</th></tr></thead>
        <tbody>
            <?php foreach ($scheme['documents'] as $document): ?>
                <tr>
                    <td><?= e($document['name']) ?></td>
                    <td><?= e($document['category']) ?></td>
                    <td><?= (int)$document['is_mandatory'] === 1 ? 'Yes' : 'No' ?></td>
                    <td><?= e($document['remarks'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (!empty($scheme['application_process'])): ?>
    <h2 class="h6 text-primary">How to Apply</h2>
    <p class="mb-1">
        <strong>Mode:</strong> <?= e($scheme['application_mode']) ?>
        <?php if (!empty($scheme['helpline_number'])): ?>
            &middot; <strong>Helpline:</strong> <?= e($scheme['helpline_number']) ?>
        <?php endif; ?>
    </p>
    <p class="jk-prose"><?= nl2br_e($scheme['application_process']) ?></p>
    <?php if (!empty($scheme['portal_url'])): ?>
        <p class="small"><strong>Portal:</strong> <?= e($scheme['portal_url']) ?></p>
    <?php endif; ?>
<?php endif; ?>

<?php if (!empty($scheme['source_url'])): ?>
    <h2 class="h6 text-primary">Source</h2>
    <p class="small">
        <?= e($scheme['source_url']) ?>
        <?php if (!empty($scheme['last_verified_date'])): ?>
            <br>Last verified <?= fmt_date($scheme['last_verified_date']) ?>
            <?= !empty($scheme['verified_by']) ? ' by ' . e($scheme['verified_by']) : '' ?>
        <?php endif; ?>
    </p>
<?php endif; ?>

<hr>
<p class="text-muted" style="font-size:.72rem">
    Generated from Jan Kavach &mdash; Social Protection Management Information System.
    Scheme details are recorded from the official source shown above; confirm current
    eligibility and benefit amounts with the implementing department before advising a beneficiary.
</p>
