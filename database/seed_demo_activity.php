<?php
/**
 * JAN KAVACH - Demonstration activity generator
 *
 * Run AFTER the SQL seeds.  This script drives the application's own
 * EligibilityEngine over every seeded beneficiary, then derives a plausible
 * pipeline of applications, follow-ups and benefit records from the matches
 * it produced.  Using the live engine (rather than hard-coded rows) means the
 * demo data always agrees with the eligibility rules currently in the
 * database.
 *
 * Usage:   php database/seed_demo_activity.php
 *          php database/seed_demo_activity.php --matches-only
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("This script must be run from the command line.\n");
}

$root = dirname(__DIR__);
require_once $root . '/app/core/App.php';

// Boot the framework without dispatching a request.
$settings = require $root . '/config/config.php';
require_once $root . '/app/core/Config.php';
JanKavach\Core\Config::load($settings);

spl_autoload_register(function (string $class): void {
    $prefix = 'JanKavach\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $segments  = explode('\\', substr($class, strlen($prefix)));
    $className = array_pop($segments);
    $dir       = strtolower(implode('/', $segments));
    $path      = APP_PATH . '/' . ($dir !== '' ? $dir . '/' : '') . $className . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});
foreach (glob(APP_PATH . '/helpers/*.php') ?: [] as $helper) {
    require_once $helper;
}

date_default_timezone_set(JanKavach\Core\Config::get('app.timezone', 'Asia/Kolkata'));

use JanKavach\Core\Database;
use JanKavach\Libraries\EligibilityEngine;

$db     = Database::getInstance();
$engine = new EligibilityEngine($db);

$matchesOnly = in_array('--matches-only', $argv, true);
mt_srand(20260929);   // deterministic

echo "Jan Kavach - demonstration activity generator\n";
echo str_repeat('=', 62), "\n";

// ---------------------------------------------------------------------
// 1. Scheme matching for every beneficiary
// ---------------------------------------------------------------------
$beneficiaries = $db->select(
    'SELECT b.id, b.name, b.state_id, b.district_id, b.field_user_id, b.registration_date,
            b.assessment_status
       FROM beneficiaries b
      WHERE b.deleted_at IS NULL
      ORDER BY b.id'
);

echo 'Matching ', count($beneficiaries), " beneficiaries against the scheme catalogue...\n";

$db->execute('DELETE FROM benefits_received');
$db->execute('DELETE FROM application_followups');
$db->execute('DELETE FROM application_status_history');
$db->execute('DELETE FROM beneficiary_scheme_applications');
$db->execute('DELETE FROM beneficiary_scheme_matches');

$totals = ['Eligible' => 0, 'Potentially Eligible' => 0, 'Needs Verification' => 0, 'Not Eligible' => 0];
$matchedPerBeneficiary = [];
$done = 0;

foreach ($beneficiaries as $b) {
    // Beneficiaries whose assessment is not finished are still matched, but
    // the engine will report most rules as "unknown" - which is the honest
    // outcome and shows up as Needs Verification.
    $results = $engine->matchAndStore((int)$b['id'], true);

    foreach ($results as $r) {
        $totals[$r['eligibility_status']] = ($totals[$r['eligibility_status']] ?? 0) + 1;
    }
    $matchedPerBeneficiary[(int)$b['id']] = array_values(array_filter(
        $results,
        fn($r) => $r['eligibility_status'] !== EligibilityEngine::NOT
    ));

    if ((++$done % 25) === 0) {
        echo '  ', $done, ' / ', count($beneficiaries), "\n";
    }
}

echo "\nMatch results across all beneficiaries:\n";
foreach ($totals as $status => $count) {
    printf("  %-22s %6d\n", $status, $count);
}

if ($matchesOnly) {
    echo "\n--matches-only supplied: stopping before the application pipeline.\n";
    exit(0);
}

// ---------------------------------------------------------------------
// 2. Applications derived from the matches
// ---------------------------------------------------------------------
$statuses = $db->select('SELECT id, code, name, stage_order, funnel_stage FROM application_statuses ORDER BY stage_order');
$statusByCode = [];
foreach ($statuses as $s) {
    $statusByCode[$s['code']] = $s;
}

// A realistic funnel: most identified records are still early in the pipeline.
$pipeline = [
    ['code' => 'IDENTIFIED',         'weight' => 18],
    ['code' => 'ELIGIBILITY_CHECKED','weight' => 14],
    ['code' => 'DOCUMENTS_PENDING',  'weight' => 13],
    ['code' => 'READY_TO_APPLY',     'weight' => 9],
    ['code' => 'INITIATED',          'weight' => 12],
    ['code' => 'SUBMITTED',          'weight' => 11],
    ['code' => 'UNDER_VERIFICATION', 'weight' => 7],
    ['code' => 'APPROVED',           'weight' => 6],
    ['code' => 'BENEFIT_RECEIVED',   'weight' => 7],
    ['code' => 'REJECTED',           'weight' => 3],
];
$pipelineBag = [];
foreach ($pipeline as $p) {
    for ($i = 0; $i < $p['weight']; $i++) {
        $pipelineBag[] = $p['code'];
    }
}

$appSeq = 0;
$counts = ['applications' => 0, 'followups' => 0, 'benefits' => 0, 'history' => 0];
$today  = new DateTimeImmutable('2026-09-29');

foreach ($beneficiaries as $b) {
    $benId   = (int)$b['id'];
    $matches = $matchedPerBeneficiary[$benId] ?? [];
    if ($matches === []) {
        continue;
    }

    // Prefer the strongest matches; take between 0 and 4 of them.
    usort($matches, fn($x, $y) => $y['match_percent'] <=> $x['match_percent']);
    $take = min(count($matches), mt_rand(0, 4));
    if ($take === 0) {
        continue;
    }
    $chosen = array_slice($matches, 0, $take);

    $regDate = new DateTimeImmutable($b['registration_date']);

    foreach ($chosen as $m) {
        $statusCode = $pipelineBag[mt_rand(0, count($pipelineBag) - 1)];
        // An unverified match should not appear as approved or paid.
        if ($m['eligibility_status'] === EligibilityEngine::VERIFY
            && in_array($statusCode, ['APPROVED', 'BENEFIT_RECEIVED', 'UNDER_VERIFICATION'], true)) {
            $statusCode = 'ELIGIBILITY_CHECKED';
        }
        // Documents outstanding: keep it at the documents stage.
        if (($m['missing_doc_count'] ?? 0) > 0
            && in_array($statusCode, ['READY_TO_APPLY', 'INITIATED', 'SUBMITTED'], true)) {
            $statusCode = 'DOCUMENTS_PENDING';
        }

        $status = $statusByCode[$statusCode];
        $stage  = (int)$status['stage_order'];

        $appSeq++;
        $referral  = $regDate->add(new DateInterval('P' . mt_rand(1, 20) . 'D'));
        $initiated = $stage >= 5  ? $referral->add(new DateInterval('P' . mt_rand(2, 25) . 'D'))  : null;
        $submitted = $stage >= 6  ? ($initiated ?? $referral)->add(new DateInterval('P' . mt_rand(1, 15) . 'D')) : null;
        $verified  = $stage >= 7  ? ($submitted ?? $referral)->add(new DateInterval('P' . mt_rand(3, 20) . 'D')) : null;
        $approved  = in_array($statusCode, ['APPROVED', 'BENEFIT_RECEIVED'], true)
                     ? ($verified ?? $submitted ?? $referral)->add(new DateInterval('P' . mt_rand(5, 30) . 'D')) : null;
        $benefitOn = $statusCode === 'BENEFIT_RECEIVED'
                     ? ($approved ?? $referral)->add(new DateInterval('P' . mt_rand(5, 35) . 'D')) : null;

        // Never let a derived date run past today.
        $clamp = fn(?DateTimeImmutable $d) => ($d !== null && $d > $today) ? $today : $d;
        $initiated = $clamp($initiated);
        $submitted = $clamp($submitted);
        $verified  = $clamp($verified);
        $approved  = $clamp($approved);
        $benefitOn = $clamp($benefitOn);

        $benefitReceived = $statusCode === 'BENEFIT_RECEIVED' ? 1 : 0;
        $benefitAmount   = null;
        if ($benefitReceived) {
            $schemeAmount = $db->scalar('SELECT benefit_amount FROM schemes WHERE id = :id', ['id' => $m['scheme_id']]);
            $benefitAmount = $schemeAmount !== null
                ? (float)$schemeAmount
                : (float)(mt_rand(10, 240) * 250);
        }

        $needsFollowup = !in_array($statusCode, ['BENEFIT_RECEIVED', 'CLOSED'], true);
        $nextFollowup  = null;
        if ($needsFollowup) {
            // Spread follow-ups around today so the dashboard shows overdue,
            // due-today and upcoming buckets.
            $offset = mt_rand(-45, 30);
            $nextFollowup = $today->add(new DateInterval('P' . abs($offset) . 'D'));
            if ($offset < 0) {
                $nextFollowup = $today->sub(new DateInterval('P' . abs($offset) . 'D'));
            }
        }

        $appId = $db->insert('beneficiary_scheme_applications', [
            'application_code'   => sprintf('JK-APP-%06d', $appSeq),
            'beneficiary_id'     => $benId,
            'scheme_id'          => (int)$m['scheme_id'],
            'eligibility_status' => $m['eligibility_status'],
            'status_id'          => (int)$status['id'],
            'referral_date'      => $referral->format('Y-m-d'),
            'initiated_date'     => $initiated?->format('Y-m-d'),
            'application_number' => $submitted ? ('DEPT/' . date('Y') . '/' . str_pad((string)$appSeq, 6, '0', STR_PAD_LEFT)) : null,
            'submission_date'    => $submitted?->format('Y-m-d'),
            'department'         => null,
            'verification_date'  => $verified?->format('Y-m-d'),
            'approval_date'      => $approved?->format('Y-m-d'),
            'rejection_reason'   => $statusCode === 'REJECTED'
                ? 'Supporting documents incomplete at the time of departmental scrutiny'
                : null,
            'benefit_received'   => $benefitReceived,
            'benefit_amount'     => $benefitAmount,
            'benefit_date'       => $benefitOn?->format('Y-m-d'),
            'next_followup_date' => $nextFollowup?->format('Y-m-d'),
            'remarks'            => null,
            'assigned_to'        => $b['field_user_id'] ? (int)$b['field_user_id'] : null,
            'state_id'           => (int)$b['state_id'],
            'district_id'        => (int)$b['district_id'],
            'created_by'         => $b['field_user_id'] ? (int)$b['field_user_id'] : null,
        ]);
        $counts['applications']++;

        // ---- status history --------------------------------------------
        $previous = null;
        foreach ($statuses as $s) {
            if ((int)$s['stage_order'] > $stage) {
                break;
            }
            if ($s['code'] === 'REJECTED' && $statusCode !== 'REJECTED') {
                continue;
            }
            $db->insert('application_status_history', [
                'application_id' => $appId,
                'from_status_id' => $previous,
                'to_status_id'   => (int)$s['id'],
                'remarks'        => 'Status advanced to ' . $s['name'],
                'changed_by'     => $b['field_user_id'] ? (int)$b['field_user_id'] : null,
                'changed_at'     => $referral->add(new DateInterval('P' . (int)$s['stage_order'] . 'D'))->format('Y-m-d H:i:s'),
            ]);
            $previous = (int)$s['id'];
            $counts['history']++;
        }

        // ---- follow-ups -------------------------------------------------
        $followupCount = mt_rand(0, 3);
        for ($i = 0; $i < $followupCount; $i++) {
            $fDate = $referral->add(new DateInterval('P' . mt_rand(5, 120) . 'D'));
            if ($fDate > $today) {
                continue;
            }
            $types = ['Phone Call', 'Home Visit', 'Department Visit', 'Document Collection', 'SMS/WhatsApp', 'Camp'];
            $outcomes = ['Resolved', 'Pending', 'Escalated', 'Not Reachable', 'Rescheduled'];
            $db->insert('application_followups', [
                'application_id'      => $appId,
                'beneficiary_id'      => $benId,
                'scheme_id'           => (int)$m['scheme_id'],
                'followup_date'       => $fDate->format('Y-m-d'),
                'followup_type'       => $types[mt_rand(0, count($types) - 1)],
                'discussion'          => 'Discussed application progress with the beneficiary and advised on the next step.',
                'status_id'           => (int)$status['id'],
                'outcome'             => $outcomes[mt_rand(0, count($outcomes) - 1)],
                'next_followup_date'  => $nextFollowup?->format('Y-m-d'),
                'responsible_user_id' => $b['field_user_id'] ? (int)$b['field_user_id'] : null,
                'state_id'            => (int)$b['state_id'],
                'district_id'         => (int)$b['district_id'],
                'created_by'          => $b['field_user_id'] ? (int)$b['field_user_id'] : null,
            ]);
            $counts['followups']++;
        }

        // ---- benefit record --------------------------------------------
        if ($benefitReceived && $benefitOn) {
            $modes = ['DBT', 'Cheque', 'In-kind', 'Service', 'Insurance Cover'];
            $db->insert('benefits_received', [
                'application_id' => $appId,
                'beneficiary_id' => $benId,
                'scheme_id'      => (int)$m['scheme_id'],
                'benefit_type'   => $db->scalar('SELECT benefit_type FROM schemes WHERE id = :id', ['id' => $m['scheme_id']]),
                'amount'         => $benefitAmount,
                'received_date'  => $benefitOn->format('Y-m-d'),
                'payment_mode'   => $modes[mt_rand(0, count($modes) - 1)],
                'reference_no'   => 'TXN' . mt_rand(100000000, 999999999),
                'instalment_no'  => mt_rand(1, 3),
                'remarks'        => null,
                'state_id'       => (int)$b['state_id'],
                'district_id'    => (int)$b['district_id'],
                'recorded_by'    => $b['field_user_id'] ? (int)$b['field_user_id'] : null,
            ]);
            $counts['benefits']++;

            $db->update('beneficiaries', ['record_status' => 'Benefited'], ['id' => $benId]);
        } elseif ($stage >= 5) {
            $current = $db->scalar('SELECT record_status FROM beneficiaries WHERE id = :id', ['id' => $benId]);
            if (in_array($current, ['Registered', 'Assessed', 'Matched'], true)) {
                $db->update('beneficiaries', ['record_status' => 'In Process'], ['id' => $benId]);
            }
        }
    }
}

echo "\nApplication pipeline created:\n";
printf("  applications        %6d\n", $counts['applications']);
printf("  status history rows %6d\n", $counts['history']);
printf("  follow-ups          %6d\n", $counts['followups']);
printf("  benefit records     %6d\n", $counts['benefits']);

// ---------------------------------------------------------------------
// 3. Notifications for anything needing attention
// ---------------------------------------------------------------------
$db->execute('DELETE FROM notification_reads');
$db->execute('DELETE FROM notifications');

require_once APP_PATH . '/libraries/Notifier.php';
$generated = JanKavach\Libraries\Notifier::generateSystemNotifications();
echo "\nNotifications generated: ", $generated, "\n";

echo "\nDone. Sign in at /login with admin / Admin@123\n";
