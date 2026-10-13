<?php
/**
 * Printable beneficiary profile.
 *
 * Aadhaar stays masked even here: a printed copy is the most likely to be
 * left somewhere it should not be.
 *
 * @var array $beneficiary, $answers, $matches, $applications, $documents, $timeline
 */
$household = $beneficiary['household'] ?? null;
?>
<div class="jk-print-head">
    <div>
        <h1><?= e($beneficiary['name']) ?></h1>
        <div class="text-muted small">
            <?= e($beneficiary['beneficiary_code']) ?> &middot;
            <?= e($beneficiary['gender']) ?><?= $beneficiary['age'] !== null ? ', ' . (int)$beneficiary['age'] . ' years' : '' ?>
            &middot; <?= e(implode(', ', array_filter([
                $beneficiary['village_display'], $beneficiary['block_name'],
                $beneficiary['district_name'], $beneficiary['state_name']]))) ?>
        </div>
    </div>
    <div class="jk-print-meta">
        <img class="jk-print-logo mb-1" src="<?= e(asset('images/logo-sm.png')) ?>"
             alt="<?= e($appName ?? 'Jan Kavach') ?>" height="42">
        <div><strong>Jan Kavach</strong></div>
        <div>Beneficiary Profile</div>
        <div>Printed <?= fmt_datetime(date('Y-m-d H:i:s')) ?></div>
    </div>
</div>

<h2 class="h6 text-primary">Personal Information</h2>
<table class="jk-table jk-table--compact mb-3">
    <tbody>
        <tr><th style="width:160px">Registration Date</th><td><?= fmt_date($beneficiary['registration_date']) ?></td>
            <th style="width:160px">Record Status</th><td><?= e($beneficiary['record_status']) ?></td></tr>
        <tr><th>Father's Name</th><td><?= e($beneficiary['father_name'] ?: '-') ?></td>
            <th>Mother's Name</th><td><?= e($beneficiary['mother_name'] ?: '-') ?></td></tr>
        <tr><th>Spouse Name</th><td><?= e($beneficiary['spouse_name'] ?: '-') ?></td>
            <th>Date of Birth</th><td><?= fmt_date($beneficiary['dob']) ?></td></tr>
        <tr><th>Mobile</th><td><?= e($beneficiary['mobile'] ?: '-') ?></td>
            <th>Aadhaar</th><td><?= mask_aadhaar($beneficiary['aadhaar_last4']) ?></td></tr>
        <tr><th>Post Office</th><td><?= e($beneficiary['post_office'] ?: '-') ?></td>
            <th>PIN Code</th><td><?= e($beneficiary['pincode'] ?: '-') ?></td></tr>
        <tr><th>Rural / Urban</th><td><?= e($beneficiary['area_type']) ?></td>
            <th>Care Of</th><td><?= e($beneficiary['relation_name'] ?: '-') ?></td></tr>
        <tr><th>Collected By</th><td><?= e($beneficiary['field_user_name'] ?? '-') ?></td>
            <th>Assessment</th><td><?= e($beneficiary['assessment_status']) ?></td></tr>
    </tbody>
</table>

<?php if ($household): ?>
    <h2 class="h6 text-primary">Household Information</h2>
    <table class="jk-table jk-table--compact mb-3">
        <tbody>
            <tr><th style="width:160px">Household Head</th><td><?= e($household['household_head_name'] ?? '-') ?></td>
                <th style="width:160px">Family Size</th><td><?= $household['family_size'] !== null ? (int)$household['family_size'] : '-' ?></td></tr>
            <tr><th>Annual Income</th><td><?= money($household['annual_income']) ?></td>
                <th>BPL Status</th><td><?= e($household['bpl_status']) ?></td></tr>
            <tr><th>Ration Card</th><td><?= e($household['ration_card_type'] ?: 'Not held') ?></td>
                <th>House Type</th><td><?= e($household['house_type'] ?? '-') ?></td></tr>
            <tr><th>Land Holding</th><td><?= $household['land_holding_acres'] !== null
                    ? rtrim(rtrim(number_format((float)$household['land_holding_acres'], 2), '0'), '.') . ' acres' : '-' ?></td>
                <th>Electricity / Toilet</th><td><?= yes_no($household['has_electricity']) ?> / <?= yes_no($household['has_toilet']) ?></td></tr>
        </tbody>
    </table>
<?php endif; ?>

<?php if (!empty($answers)): ?>
    <h2 class="h6 text-primary">Socio-Economic Assessment</h2>
    <table class="jk-table jk-table--compact mb-3">
        <tbody>
            <?php foreach ($answers as $section => $items): ?>
                <tr><th colspan="4" style="background:#f0f4f9"><?= e($section) ?></th></tr>
                <?php
                $chunks = array_chunk($items, 2);
                foreach ($chunks as $pair): ?>
                    <tr>
                        <?php foreach ($pair as $answer): ?>
                            <th style="width:22%"><?= e($answer['label'] ?: ucwords(str_replace('_', ' ', $answer['question_key']))) ?></th>
                            <td style="width:28%">
                                <?php
                                if ($answer['value_bool'] !== null) {
                                    echo yes_no($answer['value_bool']);
                                } elseif ($answer['option_label'] !== null) {
                                    echo e($answer['option_label']);
                                } elseif ($answer['value_number'] !== null) {
                                    echo e(rtrim(rtrim(number_format((float)$answer['value_number'], 2), '0'), '.'));
                                } else {
                                    echo e($answer['value_text'] ?? '-');
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                        <?php if (count($pair) === 1): ?><th></th><td></td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (!empty($matches)): ?>
    <h2 class="h6 text-primary">Potentially Eligible Schemes</h2>
    <table class="jk-table jk-table--compact mb-3">
        <thead><tr><th>Scheme</th><th>Match %</th><th>Eligibility</th><th>Missing Documents</th></tr></thead>
        <tbody>
            <?php foreach ($matches as $match): ?>
                <tr>
                    <td><?= e($match['short_name'] ?: $match['scheme_name']) ?></td>
                    <td><?= (int)$match['match_percent'] ?>%</td>
                    <td><?= e($match['eligibility_status']) ?></td>
                    <td><?= e(implode(', ', json_list($match['missing_documents'])) ?: 'None') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (!empty($applications)): ?>
    <h2 class="h6 text-primary">Scheme Applications</h2>
    <table class="jk-table jk-table--compact mb-3">
        <thead><tr><th>Application</th><th>Scheme</th><th>Status</th><th>Submitted</th><th>Benefit</th></tr></thead>
        <tbody>
            <?php foreach ($applications as $application): ?>
                <tr>
                    <td><?= e($application['application_code']) ?></td>
                    <td><?= e($application['scheme_short_name'] ?: $application['scheme_name']) ?></td>
                    <td><?= e($application['status_name']) ?></td>
                    <td><?= fmt_date($application['submission_date']) ?></td>
                    <td><?= (int)$application['benefit_received'] === 1
                            ? ($application['benefit_amount'] !== null ? money($application['benefit_amount']) : 'Received')
                            : '-' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php if (!empty($documents)): ?>
    <h2 class="h6 text-primary">Documents on File</h2>
    <table class="jk-table jk-table--compact mb-3">
        <thead><tr><th>Document</th><th>Number</th><th>Issued</th><th>Expires</th><th>Verification</th></tr></thead>
        <tbody>
            <?php foreach ($documents as $document): ?>
                <tr>
                    <td><?= e($document['document_name']) ?></td>
                    <td><?= mask_document_number($document['document_number'], (bool)$document['is_sensitive']) ?></td>
                    <td><?= fmt_date($document['issue_date']) ?></td>
                    <td><?= fmt_date($document['expiry_date']) ?></td>
                    <td><?= e($document['verification_status']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h2 class="h6 text-primary">Journey</h2>
<table class="jk-table jk-table--compact mb-3">
    <tbody>
        <?php foreach ($timeline as $step): ?>
            <tr>
                <td style="width:26px"><?= !empty($step['done']) ? '&#10003;' : '&ndash;' ?></td>
                <th style="width:190px"><?= e($step['label']) ?></th>
                <td style="width:110px"><?= fmt_date($step['date'] ?? null, '') ?></td>
                <td class="text-muted"><?= e($step['detail']) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<hr>
<p class="text-muted" style="font-size:.72rem">
    Generated from Jan Kavach &mdash; Social Protection Management Information System.
    This document contains personal information about an identified individual: handle, store and
    dispose of it accordingly. Aadhaar and other sensitive identifiers are deliberately masked.
</p>
