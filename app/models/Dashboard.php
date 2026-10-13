<?php
namespace JanKavach\Models;

use JanKavach\Core\Auth;
use JanKavach\Core\Database;
use JanKavach\Core\Model;
use JanKavach\Core\Scope;

/**
 * Dashboard KPIs and chart datasets (specification sections 4, 5, 27).
 *
 * Every query here is filtered twice: by the user's geographic scope, and by
 * whatever filters the user chose on screen (which are themselves clamped to
 * that scope before they arrive).
 */
class Dashboard extends Model
{
    protected string $table = 'beneficiaries';

    // =================================================================
    // KPI cards (section 4)
    // =================================================================

    public function kpis(array $filters = []): array
    {
        $scheme      = $this->schemeCounts($filters);
        $geography   = $this->geographyCounts();
        $beneficiary = $this->beneficiaryCounts($filters);
        $application = $this->applicationCounts($filters);
        $followup    = (new Followup())->dueSummary($filters);

        return array_merge($scheme, $geography, $beneficiary, $application, [
            'followups_overdue'  => $followup['overdue'],
            'followups_today'    => $followup['today'],
            'followups_upcoming' => $followup['upcoming'],
            'followups_pending'  => $followup['overdue'] + $followup['today'] + $followup['upcoming'],
        ]);
    }

    private function schemeCounts(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::schemeClause('s');
        $where = [$scopeSql, 's.deleted_at IS NULL'];

        if (!empty($filters['state_id'])) {
            $where[] = "(s.state_id = :k_state OR s.scheme_level = 'National')";
            $bindings['k_state'] = (int)$filters['state_id'];
        }
        if (!empty($filters['theme_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM scheme_theme_map m WHERE m.scheme_id = s.id AND m.theme_id = :k_theme)';
            $bindings['k_theme'] = (int)$filters['theme_id'];
        }

        $row = $this->db->selectOne(
            'SELECT COUNT(*) AS total,
                    SUM(s.scheme_level = "National") AS national,
                    SUM(s.scheme_level IN ("State","National+State")) AS state_specific,
                    SUM(s.is_active = 1 AND s.scheme_status = "Active") AS active,
                    SUM(s.active_enrollment = 1) AS enrolling,
                    SUM((SELECT COUNT(*) FROM scheme_eligibility_rules r
                          WHERE r.scheme_id = s.id AND r.is_active = 1) > 0) AS with_rules
               FROM schemes s WHERE ' . implode(' AND ', $where),
            $bindings
        ) ?: [];

        return [
            'total_schemes'     => (int)($row['total'] ?? 0),
            'national_schemes'  => (int)($row['national'] ?? 0),
            'state_schemes'     => (int)($row['state_specific'] ?? 0),
            'active_schemes'    => (int)($row['active'] ?? 0),
            'enrolling_schemes' => (int)($row['enrolling'] ?? 0),
            'schemes_with_rules'=> (int)($row['with_rules'] ?? 0),
        ];
    }

    private function geographyCounts(): array
    {
        $scope = Auth::dataScope();

        if ($scope === 'all') {
            return [
                'total_states'    => (int)$this->db->scalar('SELECT COUNT(*) FROM states WHERE is_active = 1'),
                'total_districts' => (int)$this->db->scalar('SELECT COUNT(*) FROM districts WHERE is_active = 1'),
            ];
        }
        $stateId = Auth::stateId();
        return [
            'total_states'    => $stateId ? 1 : 0,
            'total_districts' => $scope === 'state'
                ? (int)$this->db->scalar('SELECT COUNT(*) FROM districts WHERE state_id = :s AND is_active = 1', ['s' => $stateId])
                : (Auth::districtId() ? 1 : 0),
        ];
    }

    private function beneficiaryCounts(array $filters): array
    {
        [$where, $bindings] = $this->beneficiaryWhere($filters);

        $row = $this->db->selectOne(
            'SELECT COUNT(*) AS total,
                    SUM(b.assessment_status = "Completed") AS assessed,
                    SUM(b.gender = "Male") AS male,
                    SUM(b.gender = "Female") AS female,
                    SUM(b.gender = "Other") AS other_gender,
                    SUM(b.area_type = "Rural") AS rural,
                    SUM(b.area_type = "Urban") AS urban,
                    SUM(b.last_matched_at IS NOT NULL) AS matched
               FROM beneficiaries b WHERE ' . $where,
            $bindings
        ) ?: [];

        // Beneficiaries with at least one positive match.
        $eligible = (int)$this->db->scalar(
            'SELECT COUNT(DISTINCT m.beneficiary_id)
               FROM beneficiary_scheme_matches m
               JOIN beneficiaries b ON b.id = m.beneficiary_id
              WHERE m.eligibility_status IN ("Eligible","Potentially Eligible") AND ' . $where,
            $bindings
        );

        $documentsPending = (int)$this->db->scalar(
            'SELECT COUNT(DISTINCT m.beneficiary_id)
               FROM beneficiary_scheme_matches m
               JOIN beneficiaries b ON b.id = m.beneficiary_id
              WHERE m.missing_doc_count > 0
                AND m.eligibility_status IN ("Eligible","Potentially Eligible") AND ' . $where,
            $bindings
        );

        return [
            'total_beneficiaries'    => (int)($row['total'] ?? 0),
            'beneficiaries_assessed' => (int)($row['assessed'] ?? 0),
            'beneficiaries_matched'  => (int)($row['matched'] ?? 0),
            'eligible_beneficiaries' => $eligible,
            'documents_pending'      => $documentsPending,
            'male'                   => (int)($row['male'] ?? 0),
            'female'                 => (int)($row['female'] ?? 0),
            'other_gender'           => (int)($row['other_gender'] ?? 0),
            'rural'                  => (int)($row['rural'] ?? 0),
            'urban'                  => (int)($row['urban'] ?? 0),
        ];
    }

    private function applicationCounts(array $filters): array
    {
        [$where, $bindings] = $this->applicationWhere($filters);

        $row = $this->db->selectOne(
            'SELECT COUNT(*) AS total,
                    SUM(a.initiated_date  IS NOT NULL) AS initiated,
                    SUM(a.submission_date IS NOT NULL) AS submitted,
                    SUM(a.approval_date   IS NOT NULL) AS approved,
                    SUM(a.benefit_received = 1)        AS benefited,
                    SUM(st.code = "REJECTED")          AS rejected,
                    COALESCE(SUM(a.benefit_amount), 0) AS benefit_total
               FROM beneficiary_scheme_applications a
               JOIN application_statuses st ON st.id = a.status_id
               JOIN beneficiaries b ON b.id = a.beneficiary_id
              WHERE ' . $where,
            $bindings
        ) ?: [];

        return [
            'total_applications'      => (int)($row['total'] ?? 0),
            'applications_initiated'  => (int)($row['initiated'] ?? 0),
            'applications_submitted'  => (int)($row['submitted'] ?? 0),
            'applications_approved'   => (int)($row['approved'] ?? 0),
            'applications_rejected'   => (int)($row['rejected'] ?? 0),
            'benefits_received_count' => (int)($row['benefited'] ?? 0),
            'benefit_amount_total'    => (float)($row['benefit_total'] ?? 0),
        ];
    }

    // =================================================================
    // WHERE builders shared across the charts
    // =================================================================

    private function beneficiaryWhere(array $filters, string $alias = 'b'): array
    {
        [$scopeSql, $bindings] = Scope::clause($alias);
        $where = [$scopeSql, "{$alias}.deleted_at IS NULL"];

        foreach (['state_id', 'district_id', 'block_id'] as $key) {
            if (!empty($filters[$key])) {
                $where[] = "{$alias}.{$key} = :b_{$key}";
                $bindings['b_' . $key] = (int)$filters[$key];
            }
        }
        if (!empty($filters['gender'])) {
            $where[] = "{$alias}.gender = :b_gender";
            $bindings['b_gender'] = $filters['gender'];
        }
        if (!empty($filters['area_type'])) {
            $where[] = "{$alias}.area_type = :b_area";
            $bindings['b_area'] = $filters['area_type'];
        }
        if (!empty($filters['field_user_id'])) {
            $where[] = "{$alias}.field_user_id = :b_user";
            $bindings['b_user'] = (int)$filters['field_user_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = "{$alias}.registration_date >= :b_from";
            $bindings['b_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "{$alias}.registration_date <= :b_to";
            $bindings['b_to'] = $filters['date_to'];
        }

        return [implode(' AND ', $where), $bindings];
    }

    private function applicationWhere(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::clause('b');
        $where = [$scopeSql, 'a.deleted_at IS NULL', 'b.deleted_at IS NULL'];

        if (!empty($filters['state_id'])) {
            $where[] = 'a.state_id = :a_state';
            $bindings['a_state'] = (int)$filters['state_id'];
        }
        if (!empty($filters['district_id'])) {
            $where[] = 'a.district_id = :a_district';
            $bindings['a_district'] = (int)$filters['district_id'];
        }
        if (!empty($filters['scheme_id'])) {
            $where[] = 'a.scheme_id = :a_scheme';
            $bindings['a_scheme'] = (int)$filters['scheme_id'];
        }
        if (!empty($filters['status_id'])) {
            $where[] = 'a.status_id = :a_status';
            $bindings['a_status'] = (int)$filters['status_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(a.created_at) >= :a_from';
            $bindings['a_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(a.created_at) <= :a_to';
            $bindings['a_to'] = $filters['date_to'];
        }

        return [implode(' AND ', $where), $bindings];
    }

    // =================================================================
    // Scheme distribution charts (section 5)
    // =================================================================

    public function schemesByLevel(array $filters = []): array
    {
        [$scopeSql, $bindings] = Scope::schemeClause('s');
        $where = [$scopeSql, 's.deleted_at IS NULL'];
        if (!empty($filters['state_id'])) {
            $where[] = "(s.state_id = :c_state OR s.scheme_level = 'National')";
            $bindings['c_state'] = (int)$filters['state_id'];
        }

        return $this->db->select(
            'SELECT s.scheme_level AS label, COUNT(*) AS value
               FROM schemes s WHERE ' . implode(' AND ', $where) . '
              GROUP BY s.scheme_level ORDER BY value DESC',
            $bindings
        );
    }

    public function schemesByTheme(array $filters = [], int $limit = 12): array
    {
        [$scopeSql, $bindings] = Scope::schemeClause('s');
        $where = [$scopeSql, 's.deleted_at IS NULL'];
        if (!empty($filters['state_id'])) {
            $where[] = "(s.state_id = :c_state OR s.scheme_level = 'National')";
            $bindings['c_state'] = (int)$filters['state_id'];
        }

        return $this->db->select(
            'SELECT th.name AS label, th.color, COUNT(DISTINCT s.id) AS value
               FROM scheme_theme_map stm
               JOIN schemes s        ON s.id  = stm.scheme_id
               JOIN scheme_themes th ON th.id = stm.theme_id
              WHERE ' . implode(' AND ', $where) . '
              GROUP BY th.id ORDER BY value DESC, th.name LIMIT ' . (int)$limit,
            $bindings
        );
    }

    public function schemesByBeneficiaryType(array $filters = [], int $limit = 12): array
    {
        [$scopeSql, $bindings] = Scope::schemeClause('s');
        $where = [$scopeSql, 's.deleted_at IS NULL'];
        if (!empty($filters['state_id'])) {
            $where[] = "(s.state_id = :c_state OR s.scheme_level = 'National')";
            $bindings['c_state'] = (int)$filters['state_id'];
        }

        return $this->db->select(
            'SELECT bt.name AS label, COUNT(DISTINCT s.id) AS value
               FROM scheme_beneficiary_types sbt
               JOIN schemes s            ON s.id  = sbt.scheme_id
               JOIN beneficiary_types bt ON bt.id = sbt.beneficiary_type_id
              WHERE ' . implode(' AND ', $where) . '
              GROUP BY bt.id ORDER BY value DESC, bt.name LIMIT ' . (int)$limit,
            $bindings
        );
    }

    public function schemesByState(int $limit = 15): array
    {
        return $this->db->select(
            "SELECT st.name AS label, COUNT(*) AS value
               FROM schemes s JOIN states st ON st.id = s.state_id
              WHERE s.deleted_at IS NULL AND s.scheme_level IN ('State','National+State')
              GROUP BY st.id ORDER BY value DESC LIMIT " . (int)$limit
        );
    }

    /**
     * Priority distribution.  The source workbook left the priority column
     * blank, so unranked schemes are reported as such rather than hidden.
     */
    public function schemesByPriority(array $filters = []): array
    {
        [$scopeSql, $bindings] = Scope::schemeClause('s');
        $where = [$scopeSql, 's.deleted_at IS NULL'];
        if (!empty($filters['state_id'])) {
            $where[] = "(s.state_id = :c_state OR s.scheme_level = 'National')";
            $bindings['c_state'] = (int)$filters['state_id'];
        }

        return $this->db->select(
            "SELECT COALESCE(pl.name, 'Not Ranked') AS label,
                    COALESCE(pl.color, '#adb5bd') AS color,
                    COALESCE(pl.rank_value, 99) AS rank_value,
                    COUNT(*) AS value
               FROM schemes s
               LEFT JOIN priority_levels pl ON pl.id = s.priority_id
              WHERE " . implode(' AND ', $where) . '
              GROUP BY pl.id ORDER BY rank_value',
            $bindings
        );
    }

    public function schemesByServiceApproach(array $filters = []): array
    {
        [$scopeSql, $bindings] = Scope::schemeClause('s');
        $where = [$scopeSql, 's.deleted_at IS NULL'];

        return $this->db->select(
            "SELECT COALESCE(sa.name, 'Not Specified') AS label,
                    COALESCE(sa.color, '#adb5bd') AS color, COUNT(*) AS value
               FROM schemes s
               LEFT JOIN service_approaches sa ON sa.id = s.service_approach_id
              WHERE " . implode(' AND ', $where) . '
              GROUP BY sa.id ORDER BY value DESC',
            $bindings
        );
    }

    // =================================================================
    // Beneficiary analytics
    // =================================================================

    public function beneficiariesByState(array $filters = [], int $limit = 15): array
    {
        [$where, $bindings] = $this->beneficiaryWhere($filters);
        return $this->db->select(
            'SELECT st.name AS label, COUNT(*) AS value
               FROM beneficiaries b JOIN states st ON st.id = b.state_id
              WHERE ' . $where . ' GROUP BY st.id ORDER BY value DESC LIMIT ' . (int)$limit,
            $bindings
        );
    }

    public function beneficiariesByDistrict(array $filters = [], int $limit = 15): array
    {
        [$where, $bindings] = $this->beneficiaryWhere($filters);
        return $this->db->select(
            'SELECT d.name AS label, st.name AS state_name, COUNT(*) AS value
               FROM beneficiaries b
               JOIN districts d ON d.id = b.district_id
               JOIN states st   ON st.id = b.state_id
              WHERE ' . $where . ' GROUP BY d.id ORDER BY value DESC LIMIT ' . (int)$limit,
            $bindings
        );
    }

    public function beneficiariesByGender(array $filters = []): array
    {
        [$where, $bindings] = $this->beneficiaryWhere($filters);
        return $this->db->select(
            'SELECT b.gender AS label, COUNT(*) AS value FROM beneficiaries b
              WHERE ' . $where . ' GROUP BY b.gender ORDER BY value DESC',
            $bindings
        );
    }

    public function beneficiariesByAgeGroup(array $filters = []): array
    {
        [$where, $bindings] = $this->beneficiaryWhere($filters);
        return $this->db->select(
            "SELECT CASE
                      WHEN b.age IS NULL   THEN 'Unknown'
                      WHEN b.age <= 5      THEN '0-5'
                      WHEN b.age <= 14     THEN '6-14'
                      WHEN b.age <= 17     THEN '15-17'
                      WHEN b.age <= 35     THEN '18-35'
                      WHEN b.age <= 59     THEN '36-59'
                      ELSE '60+'
                    END AS label,
                    COUNT(*) AS value
               FROM beneficiaries b WHERE " . $where . '
              GROUP BY label
              ORDER BY FIELD(label, "0-5","6-14","15-17","18-35","36-59","60+","Unknown")',
            $bindings
        );
    }

    public function beneficiariesByArea(array $filters = []): array
    {
        [$where, $bindings] = $this->beneficiaryWhere($filters);
        return $this->db->select(
            'SELECT b.area_type AS label, COUNT(*) AS value FROM beneficiaries b
              WHERE ' . $where . ' GROUP BY b.area_type',
            $bindings
        );
    }

    public function beneficiariesByCategory(array $filters = []): array
    {
        [$where, $bindings] = $this->beneficiaryWhere($filters);
        return $this->db->select(
            "SELECT COALESCE(lo.label, 'Not Recorded') AS label, COUNT(*) AS value
               FROM beneficiaries b
               LEFT JOIN beneficiary_profile_attributes pa
                      ON pa.beneficiary_id = b.id AND pa.attribute_key = 'social_category'
               LEFT JOIN lookup_options lo
                      ON lo.lookup_key = 'social_category' AND lo.code = pa.value_text
              WHERE " . $where . ' GROUP BY lo.id ORDER BY value DESC',
            $bindings
        );
    }

    /** Registrations per month, for the trend line. */
    public function registrationTrend(array $filters = [], int $months = 12): array
    {
        [$where, $bindings] = $this->beneficiaryWhere($filters);
        $bindings['months'] = $months;

        return $this->db->select(
            "SELECT DATE_FORMAT(b.registration_date, '%Y-%m') AS period,
                    DATE_FORMAT(b.registration_date, '%b %Y') AS label,
                    COUNT(*) AS value
               FROM beneficiaries b
              WHERE " . $where . '
                AND b.registration_date >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
              GROUP BY period ORDER BY period',
            $bindings
        );
    }

    // =================================================================
    // Scheme coverage and application analytics
    // =================================================================

    /** Top schemes by uptake, with the whole pipeline per scheme. */
    public function schemeCoverage(array $filters = [], int $limit = 12): array
    {
        [$where, $bindings] = $this->applicationWhere($filters);

        return $this->db->select(
            'SELECT s.id, COALESCE(s.short_name, s.name) AS label, s.name AS scheme_name,
                    s.scheme_level, th.name AS theme_name,
                    COUNT(*) AS applications,
                    SUM(a.submission_date IS NOT NULL) AS submitted,
                    SUM(a.approval_date IS NOT NULL)   AS approved,
                    SUM(a.benefit_received = 1)        AS benefited,
                    COALESCE(SUM(a.benefit_amount), 0) AS benefit_amount
               FROM beneficiary_scheme_applications a
               JOIN schemes s ON s.id = a.scheme_id
               JOIN beneficiaries b ON b.id = a.beneficiary_id
               LEFT JOIN scheme_themes th ON th.id = s.theme_id
              WHERE ' . $where . '
              GROUP BY s.id ORDER BY applications DESC, benefited DESC LIMIT ' . (int)$limit,
            $bindings
        );
    }

    /** How many beneficiaries were matched to each scheme. */
    public function schemesMatched(array $filters = [], int $limit = 12): array
    {
        [$where, $bindings] = $this->beneficiaryWhere($filters);

        return $this->db->select(
            "SELECT COALESCE(s.short_name, s.name) AS label, COUNT(*) AS value,
                    SUM(m.eligibility_status = 'Eligible') AS eligible,
                    SUM(m.eligibility_status = 'Potentially Eligible') AS potential
               FROM beneficiary_scheme_matches m
               JOIN schemes s       ON s.id = m.scheme_id
               JOIN beneficiaries b ON b.id = m.beneficiary_id
              WHERE m.eligibility_status IN ('Eligible','Potentially Eligible') AND " . $where . '
              GROUP BY s.id ORDER BY value DESC LIMIT ' . (int)$limit,
            $bindings
        );
    }

    public function applicationsByStatus(array $filters = []): array
    {
        [$where, $bindings] = $this->applicationWhere($filters);
        return $this->db->select(
            'SELECT st.name AS label, st.color, st.stage_order, COUNT(*) AS value
               FROM beneficiary_scheme_applications a
               JOIN application_statuses st ON st.id = a.status_id
               JOIN beneficiaries b ON b.id = a.beneficiary_id
              WHERE ' . $where . ' GROUP BY st.id ORDER BY st.stage_order',
            $bindings
        );
    }

    public function eligibilityDistribution(array $filters = []): array
    {
        [$where, $bindings] = $this->beneficiaryWhere($filters);
        return $this->db->select(
            'SELECT m.eligibility_status AS label, COUNT(*) AS value
               FROM beneficiary_scheme_matches m
               JOIN beneficiaries b ON b.id = m.beneficiary_id
              WHERE ' . $where . '
              GROUP BY m.eligibility_status
              ORDER BY FIELD(m.eligibility_status,"Eligible","Potentially Eligible","Needs Verification","Not Eligible")',
            $bindings
        );
    }

    /** District-level coverage table, used by the state dashboard. */
    public function districtCoverage(array $filters = [], int $limit = 40): array
    {
        [$where, $bindings] = $this->beneficiaryWhere($filters);

        return $this->db->select(
            'SELECT d.id, d.name AS district_name, st.name AS state_name,
                    COUNT(DISTINCT b.id) AS beneficiaries,
                    SUM(b.assessment_status = "Completed") AS assessed,
                    (SELECT COUNT(DISTINCT m.beneficiary_id) FROM beneficiary_scheme_matches m
                      WHERE m.beneficiary_id IN (SELECT id FROM beneficiaries WHERE district_id = d.id AND deleted_at IS NULL)
                        AND m.eligibility_status IN ("Eligible","Potentially Eligible")) AS eligible,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a2
                      WHERE a2.district_id = d.id AND a2.deleted_at IS NULL) AS applications,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a3
                      WHERE a3.district_id = d.id AND a3.submission_date IS NOT NULL AND a3.deleted_at IS NULL) AS submitted,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a4
                      WHERE a4.district_id = d.id AND a4.benefit_received = 1 AND a4.deleted_at IS NULL) AS benefited
               FROM beneficiaries b
               JOIN districts d ON d.id = b.district_id
               JOIN states st   ON st.id = b.state_id
              WHERE ' . $where . '
              GROUP BY d.id ORDER BY beneficiaries DESC LIMIT ' . (int)$limit,
            $bindings
        );
    }

    /** State roll-up for the national dashboard. */
    public function stateCoverage(int $limit = 40): array
    {
        [$where, $bindings] = $this->beneficiaryWhere([]);

        return $this->db->select(
            'SELECT st.id, st.name AS state_name, st.state_code,
                    COUNT(DISTINCT b.id) AS beneficiaries,
                    SUM(b.assessment_status = "Completed") AS assessed,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a2
                      WHERE a2.state_id = st.id AND a2.deleted_at IS NULL) AS applications,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a3
                      WHERE a3.state_id = st.id AND a3.benefit_received = 1 AND a3.deleted_at IS NULL) AS benefited,
                    (SELECT COUNT(*) FROM schemes s
                      WHERE s.deleted_at IS NULL AND (s.state_id = st.id OR s.scheme_level = "National")) AS schemes_available
               FROM beneficiaries b
               JOIN states st ON st.id = b.state_id
              WHERE ' . $where . '
              GROUP BY st.id ORDER BY beneficiaries DESC LIMIT ' . (int)$limit,
            $bindings
        );
    }

    /** Applications per month for the trend line. */
    public function applicationTrend(array $filters = [], int $months = 12): array
    {
        [$where, $bindings] = $this->applicationWhere($filters);
        $bindings['months'] = $months;

        return $this->db->select(
            "SELECT DATE_FORMAT(a.created_at, '%Y-%m') AS period,
                    DATE_FORMAT(a.created_at, '%b %Y') AS label,
                    COUNT(*) AS value,
                    SUM(a.submission_date IS NOT NULL) AS submitted,
                    SUM(a.benefit_received = 1) AS benefited
               FROM beneficiary_scheme_applications a
               JOIN beneficiaries b ON b.id = a.beneficiary_id
              WHERE " . $where . '
                AND a.created_at >= DATE_SUB(CURDATE(), INTERVAL :months MONTH)
              GROUP BY period ORDER BY period',
            $bindings
        );
    }

    /**
     * Where applications get stuck: average days spent in each status for
     * applications that have not moved on.
     */
    public function bottlenecks(array $filters = []): array
    {
        [$where, $bindings] = $this->applicationWhere($filters);

        return $this->db->select(
            'SELECT st.name AS label, st.color, COUNT(*) AS value,
                    ROUND(AVG(DATEDIFF(CURDATE(), a.updated_at))) AS avg_days_waiting
               FROM beneficiary_scheme_applications a
               JOIN application_statuses st ON st.id = a.status_id
               JOIN beneficiaries b ON b.id = a.beneficiary_id
              WHERE ' . $where . " AND st.is_terminal = 0
              GROUP BY st.id HAVING value > 0
              ORDER BY avg_days_waiting DESC, value DESC",
            $bindings
        );
    }

    /** Recent activity feed for the dashboard sidebar. */
    public function recentActivity(int $limit = 10): array
    {
        [$scopeSql, $bindings] = Scope::clause('b');

        return $this->db->select(
            'SELECT b.id, b.beneficiary_code, b.name, b.registration_date, b.record_status,
                    d.name AS district_name, u.full_name AS field_user_name, b.created_at
               FROM beneficiaries b
               LEFT JOIN districts d ON d.id = b.district_id
               LEFT JOIN users u     ON u.id = b.field_user_id
              WHERE ' . $scopeSql . ' AND b.deleted_at IS NULL
              ORDER BY b.created_at DESC, b.id DESC LIMIT ' . (int)$limit,
            $bindings
        );
    }

    /** Top performing field users, for the dashboard leaderboard. */
    public function topFieldUsers(int $limit = 8): array
    {
        return array_slice((new User())->performance([]), 0, $limit);
    }
}
