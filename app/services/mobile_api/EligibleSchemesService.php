<?php

namespace JanKavach\Services\mobile_api;

use JanKavach\Core\Audit;
use JanKavach\Core\Database;
use JanKavach\Libraries\EligibilityEngine;
use JanKavach\Models\Beneficiary;

class EligibleSchemesService
{
    private Database $db;
    private EligibilityEngine $engine;
    private Beneficiary $beneficiaries;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->engine = new EligibilityEngine($this->db);
        $this->beneficiaries = new Beneficiary();
    }

    /**
     * Evaluate and return ONLY Eligible schemes
     * for a beneficiary owned by the authenticated Field User (serviceman).
     */
    public function evaluateForUser(
        int $beneficiaryId,
        int $userId,
        array $payload = [],
        bool $recalculate = true
    ): ?array {
        $beneficiary = $this->findOwnedBeneficiary($beneficiaryId, $userId);

        if (!$beneficiary) {
            return null;
        }

        // If the mobile client submits or updates questionnaire answers in this step,
        // persist them before running the eligibility engine.
        if (
            isset($payload['answers']) &&
            is_array($payload['answers']) &&
            $payload['answers'] !== []
        ) {
            $complete = isset($payload['complete'])
                ? filter_var($payload['complete'], FILTER_VALIDATE_BOOLEAN)
                : true;

            $this->beneficiaries->saveAssessment(
                $beneficiaryId,
                $payload['answers'],
                $userId,
                $complete
            );
        }

        // Run matching when requested (POST) or when the beneficiary has never been matched.
        if ($recalculate || empty($beneficiary['last_matched_at'])) {
            $results = $this->engine->matchAndStore($beneficiaryId, true);

            // Stamp matched_by with the authenticated mobile field user ID.
            $this->db->execute(
                'UPDATE beneficiary_scheme_matches
                    SET matched_by = :user_id
                  WHERE beneficiary_id = :beneficiary_id',
                [
                    'user_id' => $userId,
                    'beneficiary_id' => $beneficiaryId,
                ]
            );

            $eligibleCount = count(array_filter(
                $results,
                static fn(array $r): bool => $r['eligibility_status'] === EligibilityEngine::ELIGIBLE
            ));

            Audit::log('API', 'Matched', [
                'entity_type' => 'Beneficiary',
                'entity_id' => $beneficiaryId,
                'entity_label' => $beneficiary['name'],
                'description' => sprintf(
                    'Mobile scheme matching run by user #%d: %d scheme(s) evaluated, %d Eligible.',
                    $userId,
                    count($results),
                    $eligibleCount
                ),
            ]);

            // Refresh beneficiary timestamps/status after matching.
            $beneficiary = $this->findOwnedBeneficiary($beneficiaryId, $userId) ?? $beneficiary;
        }

        // Summary counts for this beneficiary.
        $summary = $this->matchSummary($beneficiaryId);

        // Fetch matching schemes ordered strictly by priority (Priority 1 first, Priority 2 second, Priority NULL last).
        $statusFilter = (string) ($payload['status'] ?? ($_GET['status'] ?? 'Eligible'));
        $rows = $this->fetchEligibleSchemeMatches($beneficiaryId, $statusFilter);
        $eligibleSchemes = array_map([$this, 'formatSchemeMatch'], $rows);

        return [
            'beneficiary' => [
                'id' => (int) $beneficiary['id'],
                'beneficiary_code' => $beneficiary['beneficiary_code'],
                'name' => $beneficiary['name'],
                'gender' => $beneficiary['gender'],
                'dob' => $beneficiary['dob'],
                'age' => $beneficiary['age'] !== null ? (int) $beneficiary['age'] : null,
                'mobile' => $beneficiary['mobile'],
                'state_id' => $beneficiary['state_id'] !== null ? (int) $beneficiary['state_id'] : null,
                'state_name' => $beneficiary['state_name'],
                'district_id' => $beneficiary['district_id'] !== null ? (int) $beneficiary['district_id'] : null,
                'district_name' => $beneficiary['district_name'],
                'village_id' => $beneficiary['village_id'] !== null ? (int) $beneficiary['village_id'] : null,
                'village_name' => $beneficiary['village_name'],
                'area_type' => $beneficiary['area_type'],
                'record_status' => $beneficiary['record_status'],
                'assessment_status' => $beneficiary['assessment_status'],
                'last_assessed_at' => $beneficiary['last_assessed_at'],
                'last_matched_at' => $beneficiary['last_matched_at'],
            ],
            'summary' => $summary,
            'eligible_schemes' => $eligibleSchemes,
        ];
    }

    /**
     * Strictly enforce that the beneficiary belongs to the authenticated serviceman.
     */
    private function findOwnedBeneficiary(int $beneficiaryId, int $userId): ?array
    {
        return $this->db->selectOne(
            'SELECT
                b.id,
                b.beneficiary_code,
                b.name,
                b.gender,
                b.dob,
                b.age,
                b.mobile,
                b.state_id,
                st.name AS state_name,
                b.district_id,
                d.name AS district_name,
                b.block_id,
                b.gram_panchayat_id,
                b.village_id,
                COALESCE(v.name, b.village_name) AS village_name,
                b.area_type,
                b.record_status,
                b.assessment_status,
                b.last_assessed_at,
                b.last_matched_at,
                b.field_user_id
             FROM beneficiaries b
             LEFT JOIN states st ON st.id = b.state_id
             LEFT JOIN districts d ON d.id = b.district_id
             LEFT JOIN villages v ON v.id = b.village_id
             WHERE b.id = :beneficiary_id
               AND (b.field_user_id = :user_id OR b.created_by = :user_id2)
               AND b.deleted_at IS NULL
             LIMIT 1',
            [
                'beneficiary_id' => $beneficiaryId,
                'user_id' => $userId,
                'user_id2' => $userId,
            ]
        );
    }

    /**
     * Count stored matches for the beneficiary.
     */
    private function matchSummary(int $beneficiaryId): array
    {
        $rows = $this->db->select(
            'SELECT
                m.eligibility_status,
                COUNT(*) AS total
             FROM beneficiary_scheme_matches m
             INNER JOIN schemes s ON s.id = m.scheme_id
             WHERE m.beneficiary_id = :beneficiary_id
               AND m.is_dismissed = 0
               AND s.deleted_at IS NULL
             GROUP BY m.eligibility_status',
            ['beneficiary_id' => $beneficiaryId]
        );

        $totalEvaluated = 0;
        $eligibleCount = 0;
        $potentialCount = 0;
        $verifyCount = 0;
        $notEligibleCount = 0;

        foreach ($rows as $row) {
            $count = (int) $row['total'];
            $totalEvaluated += $count;

            if ($row['eligibility_status'] === EligibilityEngine::ELIGIBLE) {
                $eligibleCount = $count;
            } elseif ($row['eligibility_status'] === EligibilityEngine::POTENTIAL) {
                $potentialCount = $count;
            } elseif ($row['eligibility_status'] === EligibilityEngine::VERIFY) {
                $verifyCount = $count;
            } elseif ($row['eligibility_status'] === EligibilityEngine::NOT) {
                $notEligibleCount = $count;
            }
        }

        return [
            'total_evaluated' => $totalEvaluated,
            'eligible_count' => $eligibleCount,
            'potential_count' => $potentialCount,
            'verify_count' => $verifyCount,
            'not_eligible_count' => $notEligibleCount,
        ];
    }

    /**
     * Query matching schemes for the beneficiary ordered strictly by priority:
     * Priority ID 1 first, Priority ID 2 second, ascending for other non-null priority IDs,
     * NULL priority IDs last, with secondary tie-break on match percentage and scheme ID.
     */
    private function fetchEligibleSchemeMatches(int $beneficiaryId, string $statusFilter = 'Eligible'): array
    {
        $statusClause = "AND m.eligibility_status = 'Eligible'";
        $normalized = strtolower(trim($statusFilter));

        if ($normalized === 'all') {
            $statusClause = "AND m.eligibility_status <> 'Not Eligible'";
        } elseif ($normalized === 'positive' || $normalized === 'both') {
            $statusClause = "AND m.eligibility_status IN ('Eligible', 'Potentially Eligible')";
        } elseif ($normalized === 'potentially eligible' || $normalized === 'potential') {
            $statusClause = "AND m.eligibility_status = 'Potentially Eligible'";
        } elseif ($normalized === 'needs verification' || $normalized === 'verify') {
            $statusClause = "AND m.eligibility_status = 'Needs Verification'";
        }

        return $this->db->select(
            "SELECT
                m.id AS match_id,
                m.beneficiary_id,
                m.scheme_id,
                m.match_percent,
                m.eligibility_status,
                m.rules_total,
                m.rules_passed,
                m.rules_failed,
                m.rules_unknown,
                m.passed_reasons,
                m.failed_reasons,
                m.unknown_reasons,
                m.missing_documents,
                m.missing_doc_count,
                m.reason_text,
                m.matched_at,
                s.scheme_code,
                s.name AS scheme_name,
                s.short_name,
                s.description,
                s.scheme_level,
                s.state_id,
                st.name AS state_name,
                s.scheme_status,
                s.target_group_summary,
                s.eligibility_summary,
                s.benefit_type,
                s.benefit_description,
                s.benefit_amount,
                s.benefit_amount_text,
                s.benefit_mode,
                s.benefit_frequency,
                s.application_mode,
                s.application_process,
                s.portal_url,
                s.source_url,
                s.source_website,
                s.helpline_number,
                th.id AS theme_id,
                th.name AS theme_name,
                th.color AS theme_color,
                th.icon AS theme_icon,
                s.priority_id,
                pl.name AS priority_name,
                pl.rank_value AS priority_rank,
                sa.name AS service_approach,
                (
                    SELECT a.id
                    FROM beneficiary_scheme_applications a
                    WHERE a.beneficiary_id = m.beneficiary_id
                      AND a.scheme_id = m.scheme_id
                      AND a.deleted_at IS NULL
                    LIMIT 1
                ) AS application_id
             FROM beneficiary_scheme_matches m
             INNER JOIN schemes s ON s.id = m.scheme_id
             LEFT JOIN states st ON st.id = s.state_id
             LEFT JOIN scheme_themes th ON th.id = s.theme_id
             LEFT JOIN priority_levels pl ON pl.id = s.priority_id
             LEFT JOIN service_approaches sa ON sa.id = s.service_approach_id
             WHERE m.beneficiary_id = :beneficiary_id
               AND m.is_dismissed = 0
               AND s.deleted_at IS NULL
               {$statusClause}
             ORDER BY
               CASE WHEN s.priority_id IS NULL THEN 1 ELSE 0 END ASC,
               s.priority_id ASC,
               m.match_percent DESC,
               s.id ASC",
            ['beneficiary_id' => $beneficiaryId]
        );
    }

    /**
     * Format a single scheme match row for the mobile JSON response.
     */
    private function formatSchemeMatch(array $row): array
    {
        return [
            'match_id' => (int) $row['match_id'],
            'scheme_id' => (int) $row['scheme_id'],
            'scheme_code' => $row['scheme_code'],
            'scheme_name' => $row['scheme_name'],
            'short_name' => $row['short_name'],
            'description' => $row['description'],
            'scheme_level' => $row['scheme_level'],
            'state_id' => $row['state_id'] !== null ? (int) $row['state_id'] : null,
            'state_name' => $row['state_name'],
            'scheme_status' => $row['scheme_status'],
            'source_url' => $row['source_url'],
            'source_website' => $row['source_website'],
            'theme' => [
                'id' => $row['theme_id'] !== null ? (int) $row['theme_id'] : null,
                'name' => $row['theme_name'],
                'color' => $row['theme_color'],
                'icon' => $row['theme_icon'],
            ],
            'priority' => [
                'id' => $row['priority_id'] !== null ? (int) $row['priority_id'] : null,
                'name' => $row['priority_name'],
                'rank' => $row['priority_rank'] !== null ? (int) $row['priority_rank'] : null,
            ],
            'service_approach' => $row['service_approach'],
            'eligibility_status' => $row['eligibility_status'],
            'match_percent' => (float) $row['match_percent'],
            'rules' => [
                'total' => (int) $row['rules_total'],
                'passed' => (int) $row['rules_passed'],
                'failed' => (int) $row['rules_failed'],
                'unknown' => (int) $row['rules_unknown'],
            ],
            'reason_text' => $row['reason_text'],
            'passed_reasons' => $this->decodeJsonList($row['passed_reasons'] ?? null),
            'failed_reasons' => $this->decodeJsonList($row['failed_reasons'] ?? null),
            'unknown_reasons' => $this->decodeJsonList($row['unknown_reasons'] ?? null),
            'missing_documents' => $this->decodeJsonList($row['missing_documents'] ?? null),
            'missing_doc_count' => (int) ($row['missing_doc_count'] ?? 0),
            'benefit' => [
                'type' => $row['benefit_type'],
                'description' => $row['benefit_description'],
                'amount' => $row['benefit_amount'] !== null ? (float) $row['benefit_amount'] : null,
                'amount_text' => $row['benefit_amount_text'],
                'mode' => $row['benefit_mode'],
                'frequency' => $row['benefit_frequency'],
            ],
            'application' => [
                'mode' => $row['application_mode'],
                'process' => $row['application_process'],
                'portal_url' => $row['portal_url'],
                'source_url' => $row['source_url'],
                'source_website' => $row['source_website'],
                'helpline_number' => $row['helpline_number'],
                'application_id' => $row['application_id'] !== null ? (int) $row['application_id'] : null,
            ],
            'matched_at' => $row['matched_at'],
        ];
    }

    private function decodeJsonList(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }
}
