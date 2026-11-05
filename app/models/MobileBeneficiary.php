<?php

namespace JanKavach\Models;

use JanKavach\Core\Model;

class MobileBeneficiary extends Model
{
    protected string $table = 'beneficiaries';

    public function listForUser(
        int $userId,
        ?string $search = null,
        ?int $villageId = null,
        ?int $journeyStage = null,
        int $page = 1,
        int $perPage = 20
    ): array {
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);
        $offset = ($page - 1) * $perPage;

        $where = [
            'b.field_user_id = :user_id',
            'b.deleted_at IS NULL'
        ];

        $bindings = [
            'user_id' => $userId
        ];

        /*
         * Journey Stage Logic
         *
         * 0 = Collected
         * 1 = Assessed
         * 2 = Applied
         * 3 = Tracking
         * 4 = Benefit
         */
        $journeyStageCase = "
            CASE

                WHEN EXISTS (
                    SELECT 1
                    FROM beneficiary_scheme_applications a
                    WHERE a.beneficiary_id = b.id
                      AND a.deleted_at IS NULL
                      AND a.status_id = 10
                ) THEN 4

                WHEN EXISTS (
                    SELECT 1
                    FROM beneficiary_scheme_applications a
                    WHERE a.beneficiary_id = b.id
                      AND a.deleted_at IS NULL
                      AND a.status_id IN (6, 7, 8, 9)
                ) THEN 3

                WHEN EXISTS (
                    SELECT 1
                    FROM beneficiary_scheme_applications a
                    WHERE a.beneficiary_id = b.id
                      AND a.deleted_at IS NULL
                ) THEN 2

                WHEN ba.id IS NULL
                     OR COALESCE(ba.status, '') <> 'Completed'
                     OR COALESCE(ba.completeness_pct, 0) < 100
                THEN 0

                ELSE 1

            END
        ";

        /*
         * Search filter
         */
        if ($search !== null && trim($search) !== '') {
            $where[] = '(
                b.name LIKE :search_name
                OR b.mobile LIKE :search_mobile
                OR v.name LIKE :search_village
            )';

            $searchValue = '%' . trim($search) . '%';

            $bindings['search_name'] = $searchValue;
            $bindings['search_mobile'] = $searchValue;
            $bindings['search_village'] = $searchValue;
        }

        /*
         * Village filter
         */
        if ($villageId !== null) {
            $where[] = 'b.village_id = :village_id';
            $bindings['village_id'] = $villageId;
        }

        $whereSql = implode(' AND ', $where);

        /*
         * Journey stage filter
         */
        $journeyStageCondition = '';

        if ($journeyStage !== null) {
            $journeyStageCondition = 'HAVING journey_stage = :journey_stage';
            $bindings['journey_stage'] = $journeyStage;
        }

        /*
         * ---------------------------------------------------------
         * Total beneficiaries
         * ---------------------------------------------------------
         */
        $countRow = $this->db->selectOne(
            "
            SELECT COUNT(*) AS total
            FROM (
                SELECT
                    b.id,
                    {$journeyStageCase} AS journey_stage

                FROM beneficiaries b

                LEFT JOIN villages v
                    ON v.id = b.village_id

                LEFT JOIN beneficiary_assessments ba
                    ON ba.id = (
                        SELECT MAX(ba2.id)
                        FROM beneficiary_assessments ba2
                        WHERE ba2.beneficiary_id = b.id
                    )

                WHERE {$whereSql}

                {$journeyStageCondition}
            ) AS beneficiary_count
            ",
            $bindings
        );

        $total = (int) ($countRow['total'] ?? 0);

        /*
         * ---------------------------------------------------------
         * Beneficiary list
         * ---------------------------------------------------------
         */
        $rows = $this->db->select(
            "
            SELECT
                b.id,
                b.beneficiary_code,
                b.name,
                b.gender,
                b.dob,
                b.age,
                b.mobile,
                b.state_id,
                b.district_id,
                b.block_id,
                b.gram_panchayat_id,
                b.village_id,
                v.name AS village_name,
                b.record_status,
                b.assessment_status,
                b.registration_date,

                {$journeyStageCase} AS journey_stage,

                (
                    SELECT COUNT(*)
                    FROM beneficiary_scheme_applications a
                    WHERE a.beneficiary_id = b.id
                      AND a.deleted_at IS NULL
                ) AS application_count

            FROM beneficiaries b

            LEFT JOIN villages v
                ON v.id = b.village_id

            LEFT JOIN beneficiary_assessments ba
                ON ba.id = (
                    SELECT MAX(ba2.id)
                    FROM beneficiary_assessments ba2
                    WHERE ba2.beneficiary_id = b.id
                )

            WHERE {$whereSql}

            {$journeyStageCondition}

            ORDER BY
                b.registration_date DESC,
                b.id DESC

            LIMIT {$perPage}
            OFFSET {$offset}
            ",
            $bindings
        );

        /*
         * Convert numeric values
         */
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];

            $row['age'] = $row['age'] !== null
                ? (int) $row['age']
                : null;

            $row['state_id'] = $row['state_id'] !== null
                ? (int) $row['state_id']
                : null;

            $row['district_id'] = $row['district_id'] !== null
                ? (int) $row['district_id']
                : null;

            $row['block_id'] = $row['block_id'] !== null
                ? (int) $row['block_id']
                : null;

            $row['gram_panchayat_id'] = $row['gram_panchayat_id'] !== null
                ? (int) $row['gram_panchayat_id']
                : null;

            $row['village_id'] = $row['village_id'] !== null
                ? (int) $row['village_id']
                : null;

            $row['journey_stage'] = (int) $row['journey_stage'];

            $row['application_count'] = (int) $row['application_count'];
        }

        unset($row);

        return [
            'items' => $rows,

            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $total > 0
                    ? (int) ceil($total / $perPage)
                    : 0
            ],

            'filters' => [
                'search' => $search,
                'village_id' => $villageId,
                'journey_stage' => $journeyStage
            ]
        ];
    }
    public function findForUser(
        int $beneficiaryId,
        int $userId
    ): ?array {
        $row = $this->db->selectOne(
            'SELECT
                b.id,
                b.beneficiary_code,
                b.registration_date,
                b.name,
                b.relation_type,
                b.relation_name,
                b.father_name,
                b.mother_name,
                b.spouse_name,
                b.gender,
                b.dob,
                b.age,
                b.mobile,
                b.alt_mobile,
                b.aadhaar_last4,
                b.has_aadhaar,
                b.state_id,
                st.name AS state_name,
                b.district_id,
                d.name AS district_name,
                b.block_id,
                bl.name AS block_name,
                b.gram_panchayat_id,
                gp.name AS gram_panchayat_name,
                b.village_id,
                COALESCE(v.name, b.village_name) AS village_name,
                b.post_office,
                b.address_line,
                b.pincode,
                b.area_type,
                b.same_as_permanent_address,
                b.current_state_id,
                cst.name AS current_state_name,
                b.current_district_id,
                cd.name AS current_district_name,
                b.current_block_id,
                cbl.name AS current_block_name,
                b.current_gram_panchayat_id,
                cgp.name AS current_gram_panchayat_name,
                b.current_village_id,
                COALESCE(cv.name, b.current_village_name) AS current_village_name,
                b.current_post_office,
                b.current_address_line,
                b.current_pincode,
                b.current_area_type,
                b.photo_path,
                b.record_status,
                b.assessment_status,
                b.last_assessed_at,
                b.last_matched_at,
                b.consent_given,
                b.consent_date,
                b.remarks,
                b.created_at,
                b.updated_at
            FROM beneficiaries b
            LEFT JOIN states st          ON st.id  = b.state_id
            LEFT JOIN districts d        ON d.id   = b.district_id
            LEFT JOIN blocks bl          ON bl.id  = b.block_id
            LEFT JOIN gram_panchayats gp  ON gp.id  = b.gram_panchayat_id
            LEFT JOIN villages v         ON v.id   = b.village_id
            LEFT JOIN states cst         ON cst.id = b.current_state_id
            LEFT JOIN districts cd       ON cd.id  = b.current_district_id
            LEFT JOIN blocks cbl         ON cbl.id = b.current_block_id
            LEFT JOIN gram_panchayats cgp ON cgp.id = b.current_gram_panchayat_id
            LEFT JOIN villages cv        ON cv.id  = b.current_village_id
            WHERE b.id = :beneficiary_id
              AND b.field_user_id = :user_id
              AND b.deleted_at IS NULL
            LIMIT 1',
            [
                'beneficiary_id' => $beneficiaryId,
                'user_id' => $userId,
            ]
        );

        if (!$row) {
            return null;
        }

        $row['id']                        = (int) $row['id'];
        $row['age']                       = $row['age'] !== null ? (int) $row['age'] : null;
        $row['has_aadhaar']               = (bool) $row['has_aadhaar'];
        $row['state_id']                  = $row['state_id'] !== null ? (int) $row['state_id'] : null;
        $row['district_id']               = $row['district_id'] !== null ? (int) $row['district_id'] : null;
        $row['block_id']                  = $row['block_id'] !== null ? (int) $row['block_id'] : null;
        $row['gram_panchayat_id']         = $row['gram_panchayat_id'] !== null ? (int) $row['gram_panchayat_id'] : null;
        $row['village_id']                = $row['village_id'] !== null ? (int) $row['village_id'] : null;
        $row['same_as_permanent_address'] = (bool) $row['same_as_permanent_address'];
        $row['current_state_id']          = $row['current_state_id'] !== null ? (int) $row['current_state_id'] : null;
        $row['current_district_id']       = $row['current_district_id'] !== null ? (int) $row['current_district_id'] : null;
        $row['current_block_id']          = $row['current_block_id'] !== null ? (int) $row['current_block_id'] : null;
        $row['current_gram_panchayat_id'] = $row['current_gram_panchayat_id'] !== null ? (int) $row['current_gram_panchayat_id'] : null;
        $row['current_village_id']        = $row['current_village_id'] !== null ? (int) $row['current_village_id'] : null;
        $row['consent_given']             = (bool) $row['consent_given'];

        return $row;
    }
}