<?php

namespace JanKavach\Models;

use JanKavach\Core\Model;

class MobileUser extends Model
{
    protected string $table = 'users';

    /**
     * Find a mobile user for login.
     */
    public function findForLogin(string $username): ?array
    {
        return $this->db->selectOne(
            'SELECT
                u.id,
                u.user_code,
                u.full_name,
                u.username,
                u.email,
                u.mobile,
                u.password_hash,
                u.is_active,
                u.role_id,
                u.state_id,
                u.district_id,
                u.block_id,
                u.designation,
                u.organization,
                u.locked_until,
                r.code AS role_code,
                r.name AS role_name,
                r.is_active AS role_active
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.username = :username
             LIMIT 1',
            [
                'username' => $username
            ]
        );
    }

    /**
     * Find active mobile user.
     */
    public function findActive(int $userId): ?array
    {
        return $this->db->selectOne(
            'SELECT
                u.id,
                u.user_code,
                u.full_name,
                u.username,
                u.email,
                u.mobile,
                u.is_active,
                u.role_id,
                u.state_id,
                u.district_id,
                u.block_id,
                u.designation,
                u.organization,
                r.code AS role_code,
                r.name AS role_name,
                r.is_active AS role_active
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.id = :user_id
               AND u.is_active = 1
               AND r.is_active = 1
             LIMIT 1',
            [
                'user_id' => $userId
            ]
        );
    }

    /**
     * Mobile dashboard data.
     *
     * All beneficiary-related data is restricted to the
     * authenticated Field User through beneficiaries.field_user_id.
     */
 public function dashboardData(int $userId): ?array
{
    $user = $this->findActive($userId);

    if (!$user) {
        return null;
    }

    /*
     * Use the same performance view that Admin uses.
     * This keeps Mobile Dashboard numbers consistent
     * with Admin Field Performance.
     */
    $performance = $this->db->selectOne(
        "SELECT *
           FROM vw_field_user_performance
          WHERE user_id = :user_id
          LIMIT 1",
        [
            'user_id' => $userId,
        ]
    ) ?: [];

    /*
     * Beneficiary count
     */
    $beneficiariesRegistered = (int) (
        $performance['beneficiaries_registered']
        ?? $this->db->scalar(
            'SELECT COUNT(*)
               FROM beneficiaries
              WHERE field_user_id = :user_id
                AND deleted_at IS NULL',
            ['user_id' => $userId]
        )
    );

    /*
     * Status-wise beneficiary count
     */
    $statusRows = $this->db->select(
        "SELECT
            record_status,
            COUNT(*) AS total
           FROM beneficiaries
          WHERE field_user_id = :user_id
            AND deleted_at IS NULL
          GROUP BY record_status
          ORDER BY record_status",
        [
            'user_id' => $userId,
        ]
    );

    $status = [];

    foreach ($statusRows as $row) {
        $status[] = [
            'status' => $row['record_status'],
            'count'  => (int) $row['total'],
        ];
    }

    /*
     * Recent beneficiaries
     */
    $recentBeneficiaries = $this->db->select(
        "SELECT
            b.id,
            b.beneficiary_code,
            b.name,
            b.mobile,
            b.record_status,
            b.assessment_status,
            b.registration_date,
            b.state_id,
            b.district_id,
            b.block_id
           FROM beneficiaries b
          WHERE b.field_user_id = :user_id
            AND b.deleted_at IS NULL
          ORDER BY b.registration_date DESC, b.id DESC
          LIMIT 10",
        [
            'user_id' => $userId,
        ]
    );

    foreach ($recentBeneficiaries as &$beneficiary) {
        $beneficiary['id'] = (int) $beneficiary['id'];

        $beneficiary['state_id'] = $beneficiary['state_id'] !== null
            ? (int) $beneficiary['state_id']
            : null;

        $beneficiary['district_id'] = $beneficiary['district_id'] !== null
            ? (int) $beneficiary['district_id']
            : null;

        $beneficiary['block_id'] = $beneficiary['block_id'] !== null
            ? (int) $beneficiary['block_id']
            : null;
    }

    unset($beneficiary);

    return [
        'user' => [
            'id' => (int) $user['id'],
            'user_code' => $user['user_code'],
            'name' => $user['full_name'],
            'username' => $user['username'],
            'email' => $user['email'],
            'mobile' => $user['mobile'],
            'designation' => $user['designation'] ?? null,
            'organization' => $user['organization'] ?? null,

            'role' => [
                'code' => $user['role_code'],
                'name' => $user['role_name'],
            ],

            'location' => [
                'state_id' => $user['state_id'] !== null
                    ? (int) $user['state_id']
                    : null,

                'district_id' => $user['district_id'] !== null
                    ? (int) $user['district_id']
                    : null,

                'block_id' => $user['block_id'] !== null
                    ? (int) $user['block_id']
                    : null,
            ],
        ],

        'summary' => [
            /*
             * Admin Field Performance values
             */
            'beneficiaries_registered' => $beneficiariesRegistered,

            'assessments_completed' => (int) (
                $performance['assessments_completed'] ?? 0
            ),

            'schemes_identified' => (int) (
                $performance['schemes_identified'] ?? 0
            ),

            'applications_initiated' => (int) (
                $performance['applications_initiated'] ?? 0
            ),

            'applications_submitted' => (int) (
                $performance['applications_submitted'] ?? 0
            ),

            'applications_approved' => (int) (
                $performance['applications_approved'] ?? 0
            ),

            'benefits_received' => (int) (
                $performance['benefits_received'] ?? 0
            ),

            'followups_done' => (int) (
                $performance['followups_done'] ?? 0
            ),

            'field_visits' => (int) (
                $performance['field_visits'] ?? 0
            ),

            /*
             * Keep status breakdown for mobile UI.
             */
            'status' => $status,
        ],

        'recent_beneficiaries' => $recentBeneficiaries,
    ];
}

    /**
     * Public user information for dashboard.
     */
    private function dashboardUser(array $user): array
    {
        return [
            'id'         => (int) $user['id'],
            'user_code'  => $user['user_code'],
            'name'       => $user['full_name'],
            'username'   => $user['username'],
            'email'      => $user['email'],
            'mobile'     => $user['mobile'],
            'designation' => $user['designation'] ?? null,
            'organization' => $user['organization'] ?? null,

            'role' => [
                'code' => $user['role_code'],
                'name' => $user['role_name'],
            ],

            'location' => [
                'state_id' => $user['state_id'] !== null
                    ? (int) $user['state_id']
                    : null,

                'district_id' => $user['district_id'] !== null
                    ? (int) $user['district_id']
                    : null,

                'block_id' => $user['block_id'] !== null
                    ? (int) $user['block_id']
                    : null,
            ],
        ];
    }
}