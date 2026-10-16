<?php

namespace JanKavach\Services\mobile_api;

use JanKavach\Core\Audit;
use JanKavach\Core\Database;

class MobileFollowupService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Mobile follow-up dashboard metrics.
     *
     * Exactly four summary cards:
     * Card 1: Total Users Registered (unique beneficiaries assigned to this serviceman)
     * Card 2: Total Applied (scheme-tracking records with status = 'Applied')
     * Card 3: Pending (scheme-tracking records with status = 'Pending', including untracked eligible schemes)
     * Card 4: Complete (scheme-tracking records with status = 'Complete' where benefit received is confirmed)
     */
    public function dashboardSummary(int $userId): array
    {
        // Card 1: Unique registered or followed-up beneficiaries for this serviceman
        $totalRegistered = (int) $this->db->scalar(
            'SELECT COUNT(DISTINCT b.id)
               FROM beneficiaries b
              WHERE (b.field_user_id = :user_id OR b.id IN (SELECT beneficiary_id FROM mobile_scheme_followups WHERE field_user_id = :user_id2))
                AND b.deleted_at IS NULL',
            ['user_id' => $userId, 'user_id2' => $userId]
        );

        // Count tracked records in mobile_scheme_followups for this user
        $trackedCounts = $this->db->select(
            'SELECT msf.status, msf.pending_reason, COUNT(*) AS cnt
               FROM mobile_scheme_followups msf
               JOIN beneficiaries b ON b.id = msf.beneficiary_id
              WHERE (b.field_user_id = :user_id OR msf.field_user_id = :user_id2)
                AND b.deleted_at IS NULL
              GROUP BY msf.status, msf.pending_reason',
            ['user_id' => $userId, 'user_id2' => $userId]
        );

        $totalApplied = 0;
        $totalComplete = 0;
        $pendingTracked = 0;
        $reasonCounts = [
            'Not applied yet'       => 0,
            'Application pending'   => 0,
            'Document issue'        => 0,
            'Verification pending'  => 0,
        ];

        foreach ($trackedCounts as $row) {
            $cnt = (int) $row['cnt'];
            $st  = $row['status'];
            $pr  = $row['pending_reason'];

            if ($st === 'Applied') {
                $totalApplied += $cnt;
            } elseif ($st === 'Complete') {
                $totalComplete += $cnt;
            } elseif ($st === 'Pending') {
                $pendingTracked += $cnt;
                if ($pr && isset($reasonCounts[$pr])) {
                    $reasonCounts[$pr] += $cnt;
                } else {
                    $reasonCounts['Not applied yet'] += $cnt;
                }
            }
        }

        // Count eligible schemes across all beneficiaries of this user that have NOT yet been tracked in mobile_scheme_followups.
        // These are confirmed eligible schemes waiting for field follow-up, hence they belong to "Not applied yet".
        $untrackedEligibleSchemes = (int) $this->db->scalar(
            "SELECT COUNT(*)
               FROM beneficiary_scheme_matches m
               JOIN beneficiaries b ON b.id = m.beneficiary_id
               JOIN schemes s ON s.id = m.scheme_id
               LEFT JOIN mobile_scheme_followups msf
                      ON msf.beneficiary_id = m.beneficiary_id
                     AND msf.scheme_id = m.scheme_id
              WHERE b.field_user_id = :user_id
                AND b.deleted_at IS NULL
                AND s.deleted_at IS NULL
                AND m.is_dismissed = 0
                AND m.eligibility_status = 'Eligible'
                AND msf.id IS NULL",
            ['user_id' => $userId]
        );

        $reasonCounts['Not applied yet'] += $untrackedEligibleSchemes;
        $totalPending = $pendingTracked + $untrackedEligibleSchemes;

        // Follow-ups due breakdown for today / overdue / upcoming
        $dueCounts = $this->db->selectOne(
            "SELECT
                SUM(CASE WHEN msf.next_followup_date < CURDATE() THEN 1 ELSE 0 END) AS overdue,
                SUM(CASE WHEN msf.next_followup_date = CURDATE() THEN 1 ELSE 0 END) AS today,
                SUM(CASE WHEN msf.next_followup_date > CURDATE()
                          AND msf.next_followup_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS upcoming
               FROM mobile_scheme_followups msf
               JOIN beneficiaries b ON b.id = msf.beneficiary_id
              WHERE (b.field_user_id = :user_id OR msf.field_user_id = :user_id2)
                AND b.deleted_at IS NULL
                AND msf.status = 'Pending'
                AND msf.next_followup_date IS NOT NULL",
            ['user_id' => $userId, 'user_id2' => $userId]
        ) ?: [];

        return [
            'summary_cards' => [
                'total_users_registered' => $totalRegistered,
                'total_applied'          => $totalApplied,
                'pending'                => $totalPending,
                'complete'               => $totalComplete,
            ],
            'pending_breakdown' => [
                'not_applied_yet'      => (int) $reasonCounts['Not applied yet'],
                'application_pending'  => (int) $reasonCounts['Application pending'],
                'document_issue'       => (int) $reasonCounts['Document issue'],
                'verification_pending' => (int) $reasonCounts['Verification pending'],
            ],
            'followup_due' => [
                'overdue'  => (int) ($dueCounts['overdue'] ?? 0),
                'today'    => (int) ($dueCounts['today'] ?? 0),
                'upcoming' => (int) ($dueCounts['upcoming'] ?? 0),
            ],
            'counting_rules' => [
                'total_users_registered' => 'Count of unique active beneficiaries registered or assigned to the authenticated Service Man.',
                'total_applied'          => 'Count of scheme-tracking records where the application has actually been submitted (status = Applied).',
                'pending'                => 'Count of scheme-tracking records where the beneficiary has not applied yet, application is pending, or a document issue exists.',
                'complete'               => 'Count of scheme-tracking records where the beneficiary has received the scheme benefit with recorded confirmation.',
            ],
        ];
    }

    /**
     * Follow-up Register with search, filtering, and pagination.
     */
    public function register(int $userId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);
        $offset = ($page - 1) * $perPage;

        $where = [
            'b.deleted_at IS NULL',
            's.deleted_at IS NULL',
            'm.is_dismissed = 0',
            "m.eligibility_status = 'Eligible'",
        ];
        $bindings = [];

        if (!empty($filters['beneficiary_id'])) {
            $where[] = 'b.id = :filter_beneficiary_id';
            $bindings['filter_beneficiary_id'] = (int) $filters['beneficiary_id'];
        } else {
            $where[] = '(b.field_user_id = :user_id OR msf.field_user_id = :user_id2)';
            $bindings['user_id'] = $userId;
            $bindings['user_id2'] = $userId;
        }

        // Search: Beneficiary name, code, mobile, or scheme name
        if (!empty($filters['search'])) {
            $like = '%' . trim((string) $filters['search']) . '%';
            $where[] = '(b.name LIKE :s_name OR b.beneficiary_code LIKE :s_code OR b.mobile LIKE :s_mobile OR s.name LIKE :s_scheme)';
            $bindings['s_name']   = $like;
            $bindings['s_code']   = $like;
            $bindings['s_mobile'] = $like;
            $bindings['s_scheme'] = $like;
        }

        // Scheme filter
        if (!empty($filters['scheme_id'])) {
            $where[] = 's.id = :filter_scheme_id';
            $bindings['filter_scheme_id'] = (int) $filters['scheme_id'];
        }

        // Beneficiary filter
        if (!empty($filters['beneficiary_id'])) {
            $where[] = 'b.id = :filter_beneficiary_id';
            $bindings['filter_beneficiary_id'] = (int) $filters['beneficiary_id'];
        }

        // Status filter: Pending, Applied, Complete
        if (!empty($filters['status'])) {
            $statusVal = ucfirst(strtolower(trim((string) $filters['status'])));
            if (in_array($statusVal, ['Pending', 'Applied', 'Complete'], true)) {
                if ($statusVal === 'Pending') {
                    $where[] = "(msf.status = 'Pending' OR msf.id IS NULL)";
                } else {
                    $where[] = 'msf.status = :filter_status';
                    $bindings['filter_status'] = $statusVal;
                }
            }
        }

        // Pending reason filter
        if (!empty($filters['pending_reason'])) {
            $reasonVal = trim((string) $filters['pending_reason']);
            if (in_array($reasonVal, ['Not applied yet', 'Application pending', 'Document issue', 'Verification pending'], true)) {
                if ($reasonVal === 'Not applied yet') {
                    $where[] = "(msf.pending_reason = 'Not applied yet' OR (msf.id IS NULL AND (msf.status IS NULL OR msf.status = 'Pending')))";
                } else {
                    $where[] = 'msf.pending_reason = :filter_pending_reason';
                    $bindings['filter_pending_reason'] = $reasonVal;
                }
            }
        }

        // Follow-up date / due filter
        if (!empty($filters['followup_date'])) {
            $where[] = 'msf.next_followup_date = :filter_followup_date';
            $bindings['filter_followup_date'] = $filters['followup_date'];
        } elseif (!empty($filters['due'])) {
            $due = strtolower(trim((string) $filters['due']));
            if ($due === 'overdue') {
                $where[] = 'msf.next_followup_date < CURDATE()';
            } elseif ($due === 'today') {
                $where[] = 'msf.next_followup_date = CURDATE()';
            } elseif ($due === 'upcoming') {
                $where[] = 'msf.next_followup_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
            }
        }

        $whereClause = implode(' AND ', $where);

        $fromSql = "FROM beneficiary_scheme_matches m
                    JOIN beneficiaries b ON b.id = m.beneficiary_id
                    JOIN schemes s ON s.id = m.scheme_id
                    LEFT JOIN mobile_scheme_followups msf
                           ON msf.beneficiary_id = m.beneficiary_id
                          AND msf.scheme_id = m.scheme_id
                    LEFT JOIN document_types dt ON dt.id = msf.document_type_id
                    LEFT JOIN villages v ON v.id = b.village_id
                    LEFT JOIN users u ON u.id = msf.field_user_id
                   WHERE {$whereClause}";

        // Total count
        $total = (int) $this->db->scalar("SELECT COUNT(*) {$fromSql}", $bindings);

        // Paginated rows
        $sql = "SELECT
                    msf.id AS followup_id,
                    b.id AS beneficiary_id,
                    b.beneficiary_code,
                    b.name AS beneficiary_name,
                    b.mobile AS beneficiary_mobile,
                    b.gender AS beneficiary_gender,
                    b.age AS beneficiary_age,
                    COALESCE(v.name, b.village_name) AS village_name,
                    s.id AS scheme_id,
                    s.scheme_code,
                    s.name AS scheme_name,
                    s.short_name AS scheme_short_name,
                    s.application_mode,
                    s.source_url,
                    s.source_website,
                    s.portal_url,
                    m.eligibility_status,
                    COALESCE(msf.status, 'Pending') AS follow_up_status,
                    CASE
                        WHEN msf.status = 'Applied' THEN NULL
                        WHEN msf.status = 'Complete' THEN NULL
                        WHEN msf.pending_reason IS NOT NULL THEN msf.pending_reason
                        ELSE 'Not applied yet'
                    END AS pending_reason,
                    msf.document_type_id,
                    dt.name AS document_name,
                    dt.code AS document_code,
                    msf.document_issue_type,
                    msf.document_issue_description,
                    msf.document_issue_date,
                    msf.application_number,
                    msf.submission_date,
                    msf.benefit_received_date,
                    msf.next_followup_date,
                    msf.notes,
                    u.full_name AS updated_by_name,
                    msf.updated_at AS last_updated_at
                {$fromSql}
                ORDER BY
                    CASE
                        WHEN msf.next_followup_date = CURDATE() THEN 1
                        WHEN msf.next_followup_date < CURDATE() THEN 2
                        WHEN msf.next_followup_date IS NOT NULL THEN 3
                        ELSE 4
                    END ASC,
                    msf.updated_at DESC,
                    b.name ASC
                LIMIT {$perPage} OFFSET {$offset}";

        $rows = $this->db->select($sql, $bindings);

        $items = array_map(function (array $r): array {
            return [
                'followup_id' => $r['followup_id'] !== null ? (int) $r['followup_id'] : null,
                'beneficiary' => [
                    'id'     => (int) $r['beneficiary_id'],
                    'code'   => $r['beneficiary_code'],
                    'name'   => $r['beneficiary_name'],
                    'mobile' => $r['beneficiary_mobile'],
                    'gender' => $r['beneficiary_gender'],
                    'age'    => $r['beneficiary_age'] !== null ? (int) $r['beneficiary_age'] : null,
                    'village'=> $r['village_name'],
                ],
                'scheme' => [
                    'id'               => (int) $r['scheme_id'],
                    'code'             => $r['scheme_code'],
                    'name'             => $r['scheme_name'],
                    'short_name'       => $r['scheme_short_name'],
                    'application_mode' => $r['application_mode'],
                    'source_url'       => $r['source_url'],
                    'source_website'   => $r['source_website'],
                    'portal_url'       => $r['portal_url'],
                ],
                'eligibility_status'     => $r['eligibility_status'],
                'status'                 => $r['follow_up_status'],
                'pending_reason'         => $r['pending_reason'],
                'document_issue'         => $r['document_type_id'] ? [
                    'document_type_id' => (int) $r['document_type_id'],
                    'document_name'    => $r['document_name'],
                    'document_code'    => $r['document_code'],
                    'issue_type'       => $r['document_issue_type'],
                    'description'      => $r['document_issue_description'],
                    'recorded_date'    => $r['document_issue_date'],
                ] : null,
                'application_number'     => $r['application_number'],
                'submission_date'        => $r['submission_date'],
                'benefit_received_date'  => $r['benefit_received_date'],
                'next_followup_date'     => $r['next_followup_date'],
                'notes'                  => $r['notes'],
                'last_updated_by'        => $r['updated_by_name'],
                'last_updated_at'        => $r['last_updated_at'],
            ];
        }, $rows);

        return [
            'items' => $items,
            'meta'  => [
                'total'        => $total,
                'page'         => $page,
                'per_page'     => $perPage,
                'last_page'    => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
                'has_more'     => ($page * $perPage) < $total,
            ],
            'filters' => $filters,
        ];
    }

    /**
     * Get beneficiary details with all their eligible schemes, each scheme's
     * independent tracking status, required documents, and application URLs.
     */
    public function beneficiaryDetail(int $beneficiaryId, int $userId): ?array
    {
        $beneficiary = $this->db->selectOne(
            'SELECT
                b.id,
                b.beneficiary_code,
                b.name,
                b.gender,
                b.dob,
                b.age,
                b.mobile,
                b.alt_mobile,
                b.relation_type,
                b.relation_name,
                b.state_id,
                st.name AS state_name,
                b.district_id,
                d.name AS district_name,
                b.block_id,
                bk.name AS block_name,
                b.gram_panchayat_id,
                gp.name AS gram_panchayat_name,
                b.village_id,
                COALESCE(v.name, b.village_name) AS village_name,
                b.area_type,
                b.record_status,
                b.assessment_status,
                b.registration_date,
                b.last_matched_at
             FROM beneficiaries b
             LEFT JOIN states st ON st.id = b.state_id
             LEFT JOIN districts d ON d.id = b.district_id
             LEFT JOIN blocks bk ON bk.id = b.block_id
             LEFT JOIN gram_panchayats gp ON gp.id = b.gram_panchayat_id
             LEFT JOIN villages v ON v.id = b.village_id
            WHERE b.id = :id
              AND b.deleted_at IS NULL
            LIMIT 1',
            [
                'id' => $beneficiaryId,
            ]
        );

        if (!$beneficiary) {
            return null;
        }

        // Fetch all confirmed 'Eligible' schemes for this beneficiary
        $eligibleSchemes = $this->db->select(
            "SELECT
                m.id AS match_id,
                m.match_percent,
                m.eligibility_status,
                m.reason_text,
                m.matched_at,
                s.id AS scheme_id,
                s.scheme_code,
                s.name AS scheme_name,
                s.short_name AS scheme_short_name,
                s.description AS scheme_description,
                s.scheme_level,
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
                msf.id AS followup_id,
                COALESCE(msf.status, 'Pending') AS follow_up_status,
                CASE
                    WHEN msf.status = 'Applied' THEN NULL
                    WHEN msf.status = 'Complete' THEN NULL
                    WHEN msf.pending_reason IS NOT NULL THEN msf.pending_reason
                    ELSE 'Not applied yet'
                END AS pending_reason,
                msf.document_type_id,
                dt.name AS document_name,
                dt.code AS document_code,
                msf.document_issue_type,
                msf.document_issue_description,
                msf.document_issue_date,
                msf.application_number,
                msf.submission_date,
                msf.benefit_received_date,
                msf.next_followup_date,
                msf.notes,
                msf.updated_at AS last_updated_at,
                u.full_name AS updated_by_name
             FROM beneficiary_scheme_matches m
             INNER JOIN schemes s ON s.id = m.scheme_id
             LEFT JOIN scheme_themes th ON th.id = s.theme_id
             LEFT JOIN mobile_scheme_followups msf
                    ON msf.beneficiary_id = m.beneficiary_id
                   AND msf.scheme_id = m.scheme_id
             LEFT JOIN document_types dt ON dt.id = msf.document_type_id
             LEFT JOIN users u ON u.id = msf.field_user_id
            WHERE m.beneficiary_id = :beneficiary_id
              AND m.is_dismissed = 0
              AND m.eligibility_status = 'Eligible'
              AND s.deleted_at IS NULL
            ORDER BY msf.status ASC, s.name ASC",
            ['beneficiary_id' => $beneficiaryId]
        );

        $schemesList = [];
        foreach ($eligibleSchemes as $row) {
            $schemeId = (int) $row['scheme_id'];

            // Fetch required documents for this scheme
            $docs = $this->db->select(
                'SELECT
                    sd.id AS scheme_document_id,
                    sd.is_mandatory,
                    sd.validity_required,
                    sd.remarks AS document_remarks,
                    dt.id AS document_type_id,
                    dt.code AS document_code,
                    dt.name AS document_name,
                    dt.category,
                    dt.description
                 FROM scheme_documents sd
                 INNER JOIN document_types dt ON dt.id = sd.document_type_id
                WHERE sd.scheme_id = :scheme_id
                ORDER BY sd.is_mandatory DESC, sd.sort_order ASC, dt.name ASC',
                ['scheme_id' => $schemeId]
            );

            $schemesList[] = [
                'scheme_id'          => $schemeId,
                'scheme_code'        => $row['scheme_code'],
                'scheme_name'        => $row['scheme_name'],
                'short_name'         => $row['scheme_short_name'],
                'description'        => $row['scheme_description'],
                'scheme_level'       => $row['scheme_level'],
                'theme'              => [
                    'id'    => $row['theme_id'] !== null ? (int) $row['theme_id'] : null,
                    'name'  => $row['theme_name'],
                    'color' => $row['theme_color'],
                    'icon'  => $row['theme_icon'],
                ],
                'benefit'            => [
                    'type'        => $row['benefit_type'],
                    'description' => $row['benefit_description'],
                    'amount'      => $row['benefit_amount'] !== null ? (float) $row['benefit_amount'] : null,
                    'amount_text' => $row['benefit_amount_text'],
                    'mode'        => $row['benefit_mode'],
                    'frequency'   => $row['benefit_frequency'],
                ],
                'application'        => [
                    'mode'            => $row['application_mode'],
                    'process'         => $row['application_process'],
                    'portal_url'      => $row['portal_url'],
                    'source_url'      => $row['source_url'],
                    'source_website'  => $row['source_website'],
                    'helpline_number' => $row['helpline_number'],
                ],
                'required_documents' => array_map(function (array $d): array {
                    return [
                        'document_type_id'  => (int) $d['document_type_id'],
                        'document_code'     => $d['document_code'],
                        'document_name'     => $d['document_name'],
                        'category'          => $d['category'],
                        'is_mandatory'      => (bool) $d['is_mandatory'],
                        'validity_required' => (bool) $d['validity_required'],
                        'remarks'           => $d['document_remarks'],
                    ];
                }, $docs),
                'tracking'           => [
                    'followup_id'           => $row['followup_id'] !== null ? (int) $row['followup_id'] : null,
                    'status'                => $row['follow_up_status'],
                    'pending_reason'        => $row['pending_reason'],
                    'document_issue'        => $row['document_type_id'] ? [
                        'document_type_id' => (int) $row['document_type_id'],
                        'document_name'    => $row['document_name'],
                        'document_code'    => $row['document_code'],
                        'issue_type'       => $row['document_issue_type'],
                        'description'      => $row['document_issue_description'],
                        'recorded_date'    => $row['document_issue_date'],
                    ] : null,
                    'application_number'    => $row['application_number'],
                    'submission_date'       => $row['submission_date'],
                    'benefit_received_date' => $row['benefit_received_date'],
                    'next_followup_date'    => $row['next_followup_date'],
                    'notes'                 => $row['notes'],
                    'last_updated_by'       => $row['updated_by_name'],
                    'last_updated_at'       => $row['last_updated_at'],
                ],
            ];
        }

        return [
            'beneficiary' => [
                'id'                 => (int) $beneficiary['id'],
                'beneficiary_code'   => $beneficiary['beneficiary_code'],
                'name'               => $beneficiary['name'],
                'gender'             => $beneficiary['gender'],
                'dob'                => $beneficiary['dob'],
                'age'                => $beneficiary['age'] !== null ? (int) $beneficiary['age'] : null,
                'mobile'             => $beneficiary['mobile'],
                'alt_mobile'         => $beneficiary['alt_mobile'],
                'relation_type'      => $beneficiary['relation_type'],
                'relation_name'      => $beneficiary['relation_name'],
                'state_id'           => $beneficiary['state_id'] !== null ? (int) $beneficiary['state_id'] : null,
                'state_name'         => $beneficiary['state_name'],
                'district_id'        => $beneficiary['district_id'] !== null ? (int) $beneficiary['district_id'] : null,
                'district_name'      => $beneficiary['district_name'],
                'block_id'           => $beneficiary['block_id'] !== null ? (int) $beneficiary['block_id'] : null,
                'block_name'         => $beneficiary['block_name'],
                'gram_panchayat_id'  => $beneficiary['gram_panchayat_id'] !== null ? (int) $beneficiary['gram_panchayat_id'] : null,
                'gram_panchayat_name'=> $beneficiary['gram_panchayat_name'],
                'village_id'         => $beneficiary['village_id'] !== null ? (int) $beneficiary['village_id'] : null,
                'village_name'       => $beneficiary['village_name'],
                'area_type'          => $beneficiary['area_type'],
                'record_status'      => $beneficiary['record_status'],
                'assessment_status'  => $beneficiary['assessment_status'],
                'registration_date'  => $beneficiary['registration_date'],
                'last_matched_at'    => $beneficiary['last_matched_at'],
            ],
            'eligible_schemes_count' => count($schemesList),
            'eligible_schemes'       => $schemesList,
        ];
    }

    /**
     * Manually record or update follow-up progress for a specific beneficiary + scheme combination.
     * Supports:
     * - Scenario A: Not applied yet (Status: Pending, Reason: Not applied yet, Next followup date)
     * - Scenario B: Applied (Status: Applied, Submission date, Application reference number, Notes)
     * - Scenario C: Document issue (Status: Pending, Reason: Document issue, Document type, Issue type & description, Next followup date)
     * - Scenario D: Complete (Status: Complete, Benefit received date, Confirmation notes)
     */
    public function recordFollowup(int $userId, array $data): array
    {
        $beneficiaryId = (int) ($data['beneficiary_id'] ?? 0);
        $schemeId      = (int) ($data['scheme_id'] ?? 0);

        if ($beneficiaryId <= 0 || $schemeId <= 0) {
            throw new \InvalidArgumentException('Both beneficiary_id and scheme_id are required.');
        }

        // Verify beneficiary exists
        $beneficiary = $this->db->selectOne(
            'SELECT id, name, beneficiary_code, field_user_id
               FROM beneficiaries
              WHERE id = :id AND deleted_at IS NULL
              LIMIT 1',
            ['id' => $beneficiaryId]
        );

        if (!$beneficiary) {
            throw new \RuntimeException('Beneficiary not found.');
        }

        // Verify scheme is eligible for this beneficiary
        $match = $this->db->selectOne(
            "SELECT id, scheme_id, eligibility_status
               FROM beneficiary_scheme_matches
              WHERE beneficiary_id = :beneficiary_id
                AND scheme_id = :scheme_id
                AND eligibility_status = 'Eligible'
                AND is_dismissed = 0
              LIMIT 1",
            [
                'beneficiary_id' => $beneficiaryId,
                'scheme_id'      => $schemeId,
            ]
        );

        if (!$match) {
            throw new \RuntimeException('This scheme is not confirmed eligible for this beneficiary.');
        }

        // Validate status
        $status = ucfirst(strtolower(trim((string) ($data['status'] ?? 'Pending'))));
        if (!in_array($status, ['Pending', 'Applied', 'Complete'], true)) {
            throw new \InvalidArgumentException('Status must be one of: Pending, Applied, Complete.');
        }

        $pendingReason            = null;
        $documentTypeId           = null;
        $documentIssueType        = null;
        $documentIssueDescription = null;
        $documentIssueDate        = null;
        $applicationNumber        = null;
        $submissionDate           = null;
        $benefitReceivedDate      = null;
        $nextFollowupDate         = null;
        $notes                    = !empty($data['notes']) ? trim((string) $data['notes']) : null;

        // Apply scenario-specific validation and field assignment
        if ($status === 'Pending') {
            $reason = trim((string) ($data['pending_reason'] ?? 'Not applied yet'));
            $validReasons = ['Not applied yet', 'Application pending', 'Document issue', 'Verification pending'];
            if (!in_array($reason, $validReasons, true)) {
                $reason = 'Not applied yet';
            }
            $pendingReason = $reason;

            if ($reason === 'Document issue') {
                // Scenario C: Document issue
                $docTypeId = (int) ($data['document_type_id'] ?? 0);
                if ($docTypeId <= 0) {
                    throw new \InvalidArgumentException('document_type_id is required when recording a document issue.');
                }
                $docExists = $this->db->scalar('SELECT id FROM document_types WHERE id = :id', ['id' => $docTypeId]);
                if (!$docExists) {
                    throw new \InvalidArgumentException('Invalid document_type_id provided.');
                }
                $documentTypeId = $docTypeId;

                $issueType = ucfirst(strtolower(trim((string) ($data['document_issue_type'] ?? 'Missing'))));
                $validIssueTypes = ['Missing', 'Incorrect', 'Expired', 'Rejected', 'Other'];
                $documentIssueType = in_array($issueType, $validIssueTypes, true) ? $issueType : 'Missing';

                $documentIssueDescription = !empty($data['document_issue_description'])
                    ? trim((string) $data['document_issue_description']) : null;

                $documentIssueDate = !empty($data['document_issue_date'])
                    ? $this->validateDate($data['document_issue_date'], 'document_issue_date', false)
                    : date('Y-m-d');

                // Next follow-up date is required for document issues
                if (empty($data['next_followup_date'])) {
                    throw new \InvalidArgumentException('next_followup_date is required when recording a document issue.');
                }
                $nextFollowupDate = $this->validateDate($data['next_followup_date'], 'next_followup_date', true);
            } else {
                // Scenario A or other pending reasons
                if (!empty($data['next_followup_date'])) {
                    $nextFollowupDate = $this->validateDate($data['next_followup_date'], 'next_followup_date', true);
                }
            }

            // Carry forward submission_date if previously submitted
            if (!empty($data['submission_date'])) {
                $submissionDate = $this->validateDate($data['submission_date'], 'submission_date', false);
            }
            if (!empty($data['application_number'])) {
                $applicationNumber = trim((string) $data['application_number']);
            }
        } elseif ($status === 'Applied') {
            // Scenario B: Beneficiary has applied
            if (empty($data['submission_date'])) {
                throw new \InvalidArgumentException('submission_date is required when status is Applied.');
            }
            $submissionDate = $this->validateDate($data['submission_date'], 'submission_date', false);

            if (!empty($data['application_number'])) {
                $applicationNumber = trim((string) $data['application_number']);
            }
            if (!empty($data['next_followup_date'])) {
                $nextFollowupDate = $this->validateDate($data['next_followup_date'], 'next_followup_date', true);
            }
            // Document issue is cleared upon successful application
            $pendingReason = null;
        } elseif ($status === 'Complete') {
            // Scenario D: Benefit received
            if (empty($data['benefit_received_date'])) {
                throw new \InvalidArgumentException('benefit_received_date is required when marking status Complete.');
            }
            $benefitReceivedDate = $this->validateDate($data['benefit_received_date'], 'benefit_received_date', false);

            // Carry forward or accept submission date if available
            if (!empty($data['submission_date'])) {
                $submissionDate = $this->validateDate($data['submission_date'], 'submission_date', false);
            }
            if (!empty($data['application_number'])) {
                $applicationNumber = trim((string) $data['application_number']);
            }
            $pendingReason = null;
        }

        // Save or update in mobile_scheme_followups using prepared UPSERT
        $upsertSql = "INSERT INTO mobile_scheme_followups (
                        beneficiary_id, scheme_id, status, pending_reason,
                        document_type_id, document_issue_type, document_issue_description, document_issue_date,
                        application_number, submission_date, benefit_received_date, next_followup_date,
                        notes, field_user_id
                      ) VALUES (
                        :b_id, :s_id, :status, :pending_reason,
                        :doc_id, :doc_type, :doc_desc, :doc_date,
                        :app_num, :sub_date, :ben_date, :next_date,
                        :notes, :user_id
                      ) ON DUPLICATE KEY UPDATE
                        status = VALUES(status),
                        pending_reason = VALUES(pending_reason),
                        document_type_id = VALUES(document_type_id),
                        document_issue_type = VALUES(document_issue_type),
                        document_issue_description = VALUES(document_issue_description),
                        document_issue_date = VALUES(document_issue_date),
                        application_number = COALESCE(VALUES(application_number), application_number),
                        submission_date = COALESCE(VALUES(submission_date), submission_date),
                        benefit_received_date = VALUES(benefit_received_date),
                        next_followup_date = VALUES(next_followup_date),
                        notes = VALUES(notes),
                        field_user_id = VALUES(field_user_id),
                        updated_at = CURRENT_TIMESTAMP";

        $bindings = [
            'b_id'           => $beneficiaryId,
            's_id'           => $schemeId,
            'status'         => $status,
            'pending_reason' => $pendingReason,
            'doc_id'         => $documentTypeId,
            'doc_type'       => $documentIssueType,
            'doc_desc'       => $documentIssueDescription,
            'doc_date'       => $documentIssueDate,
            'app_num'        => $applicationNumber,
            'sub_date'       => $submissionDate,
            'ben_date'       => $benefitReceivedDate,
            'next_date'      => $nextFollowupDate,
            'notes'          => $notes,
            'user_id'        => $userId,
        ];

        $this->db->run($upsertSql, $bindings);

        // Fetch the fresh persisted record
        $record = $this->db->selectOne(
            "SELECT msf.*,
                    b.name AS beneficiary_name, b.beneficiary_code,
                    s.name AS scheme_name, s.scheme_code,
                    dt.name AS document_name, dt.code AS document_code,
                    u.full_name AS updated_by_name
               FROM mobile_scheme_followups msf
               JOIN beneficiaries b ON b.id = msf.beneficiary_id
               JOIN schemes s ON s.id = msf.scheme_id
               LEFT JOIN document_types dt ON dt.id = msf.document_type_id
               LEFT JOIN users u ON u.id = msf.field_user_id
              WHERE msf.beneficiary_id = :b_id AND msf.scheme_id = :s_id
              LIMIT 1",
            ['b_id' => $beneficiaryId, 's_id' => $schemeId]
        );

        Audit::log('API', 'MobileFollowup', [
            'entity_type'  => 'BeneficiarySchemeFollowup',
            'entity_id'    => $record['id'],
            'entity_label' => sprintf('%s - %s', $beneficiary['beneficiary_code'], $record['scheme_name']),
            'description'  => sprintf(
                'Follow-up updated by user #%d: Status=%s, Reason=%s',
                $userId,
                $status,
                $pendingReason ?: 'None'
            ),
        ]);

        return [
            'id'                     => (int) $record['id'],
            'beneficiary_id'         => (int) $record['beneficiary_id'],
            'beneficiary_code'       => $record['beneficiary_code'],
            'beneficiary_name'       => $record['beneficiary_name'],
            'scheme_id'              => (int) $record['scheme_id'],
            'scheme_code'            => $record['scheme_code'],
            'scheme_name'            => $record['scheme_name'],
            'status'                 => $record['status'],
            'pending_reason'         => $record['pending_reason'],
            'document_issue'         => $record['document_type_id'] ? [
                'document_type_id' => (int) $record['document_type_id'],
                'document_name'    => $record['document_name'],
                'document_code'    => $record['document_code'],
                'issue_type'       => $record['document_issue_type'],
                'description'      => $record['document_issue_description'],
                'recorded_date'    => $record['document_issue_date'],
            ] : null,
            'application_number'     => $record['application_number'],
            'submission_date'        => $record['submission_date'],
            'benefit_received_date'  => $record['benefit_received_date'],
            'next_followup_date'     => $record['next_followup_date'],
            'notes'                  => $record['notes'],
            'last_updated_by'        => $record['updated_by_name'],
            'updated_at'             => $record['updated_at'],
        ];
    }

    /**
     * Active document types list for populating dropdowns during document issue recording.
     */
    public function documentTypes(): array
    {
        return $this->db->select(
            'SELECT id, code, name, category, description, requires_number, validity_required
               FROM document_types
              WHERE is_active = 1
              ORDER BY category ASC, sort_order ASC, name ASC'
        );
    }

    /**
     * Fetch required and optional documents for a specific scheme.
     */
    public function schemeDocuments(int $schemeId): array
    {
        return $this->db->select(
            'SELECT
                sd.id AS scheme_document_id,
                sd.is_mandatory,
                sd.validity_required,
                sd.remarks,
                dt.id AS document_type_id,
                dt.code AS document_code,
                dt.name AS document_name,
                dt.category,
                dt.description
             FROM scheme_documents sd
             INNER JOIN document_types dt ON dt.id = sd.document_type_id
            WHERE sd.scheme_id = :scheme_id
            ORDER BY sd.is_mandatory DESC, sd.sort_order ASC, dt.name ASC',
            ['scheme_id' => $schemeId]
        );
    }

    /**
     * Validate date string format (YYYY-MM-DD).
     */
    private function validateDate(mixed $dateStr, string $fieldLabel, bool $allowFuture = true): string
    {
        if (!is_string($dateStr) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStr)) {
            throw new \InvalidArgumentException("{$fieldLabel} must be a valid date in YYYY-MM-DD format.");
        }

        $y = (int) substr($dateStr, 0, 4);
        $m = (int) substr($dateStr, 5, 2);
        $d = (int) substr($dateStr, 8, 2);

        if (!checkdate($m, $d, $y)) {
            throw new \InvalidArgumentException("{$fieldLabel} contains an invalid calendar date.");
        }

        if (!$allowFuture && $dateStr > date('Y-m-d')) {
            throw new \InvalidArgumentException("{$fieldLabel} cannot be a future date.");
        }

        return $dateStr;
    }
}
