<?php

namespace JanKavach\Controllers\mobile_api;

use JanKavach\Core\Controller;
use JanKavach\Models\Geography;

class GeographyController extends Controller
{
    private Geography $geo;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->geo = new Geography();
    }

    /**
     * GET /geography/states
     */
    public function states(): void
    {
        $rows = $this->geo->states();

        $this->jsonSuccess(
            $this->options($rows),
            'States fetched successfully.'
        );
    }

    /**
     * GET /geography/districts/{stateId}
     */
    public function districts(string $stateId): void
    {
        $stateId = (int) $stateId;

        if ($stateId <= 0) {
            $this->jsonError(
                'Valid state ID is required.',
                null,
                422
            );
            return;
        }

        $rows = $this->geo->districts($stateId);

        $this->jsonSuccess(
            $this->options($rows),
            'Districts fetched successfully.'
        );
    }

    /**
     * GET /geography/blocks/{districtId}
     */
    public function blocks(string $districtId): void
    {
        $districtId = (int) $districtId;

        if ($districtId <= 0) {
            $this->jsonError(
                'Valid district ID is required.',
                null,
                422
            );
            return;
        }

        $rows = $this->geo->blocks($districtId);

        $this->jsonSuccess(
            $this->options($rows),
            'Blocks fetched successfully.'
        );
    }

    /**
     * GET /geography/gram-panchayats/{blockId}
     */
    public function gramPanchayats(string $blockId): void
    {
        $blockId = (int) $blockId;

        if ($blockId <= 0) {
            $this->jsonError(
                'Valid block ID is required.',
                null,
                422
            );
            return;
        }

        $rows = $this->geo->gramPanchayats($blockId);

        $this->jsonSuccess(
            $this->options($rows),
            'Gram Panchayats fetched successfully.'
        );
    }

    /**
     * GET /geography/villages/{gpId}
     */
    public function villages(string $gpId): void
    {
        $gpId = (int) $gpId;

        if ($gpId <= 0) {
            $this->jsonError(
                'Valid Gram Panchayat ID is required.',
                null,
                422
            );
            return;
        }

        $rows = $this->geo->villages($gpId);

        $data = array_map(
            static fn(array $row): array => [
                'id'        => (int) $row['id'],
                'name'      => $row['name'],
                'area_type' => $row['area_type'] ?? null,
                'pincode'   => $row['pincode'] ?? null,
            ],
            $rows
        );

        $this->jsonSuccess(
            $data,
            'Villages fetched successfully.'
        );
    }

    /**
     * GET /geography/villages-by-block/{blockId}
     */
    public function villagesByBlock(string $blockId): void
    {
        $blockId = (int) $blockId;

        if ($blockId <= 0) {
            $this->jsonError(
                'Valid block ID is required.',
                null,
                422
            );
            return;
        }

        $rows = $this->geo->villagesByBlock($blockId);

        $data = array_map(
            static fn(array $row): array => [
                'id' => (int) $row['id'],
                'name' => $row['name']
                    . (!empty($row['gp_name'])
                        ? ' (' . $row['gp_name'] . ')'
                        : ''),
                'area_type' => $row['area_type'] ?? null,
                'pincode'   => $row['pincode'] ?? null,
            ],
            $rows
        );

        $this->jsonSuccess(
            $data,
            'Villages fetched successfully.'
        );
    }

    /**
     * GET /geography/master-data
     *
     * Returns combined geography master data for offline beneficiary registration:
     * states, districts, blocks, gram_panchayats, and villages.
     */
    public function masterData(): void
    {
        try {
            // 1. States
            $states = $this->db->select(
                "SELECT id, name FROM states WHERE is_active = 1 ORDER BY name ASC"
            );

            // 2. Districts (with state_id)
            $districts = $this->db->select(
                "SELECT id, state_id, name FROM districts WHERE is_active = 1 ORDER BY name ASC"
            );

            // 3. Blocks (with district_id)
            $blocks = $this->db->select(
                "SELECT id, district_id, name FROM blocks WHERE is_active = 1 ORDER BY name ASC"
            );

            // 4. Gram Panchayats (with block_id)
            $gramPanchayats = $this->db->select(
                "SELECT id, block_id, name FROM gram_panchayats WHERE is_active = 1 ORDER BY name ASC"
            );

            // 5. Villages (with block_id, gram_panchayat_id, area_type, pincode)
            $villages = $this->db->select(
                "SELECT id, block_id, gram_panchayat_id, name, area_type, pincode 
                   FROM villages 
                  WHERE is_active = 1 
                  ORDER BY name ASC"
            );

            $payload = [
                'states' => array_map(
                    static fn(array $r): array => [
                        'id'   => (int) $r['id'],
                        'name' => $r['name'],
                    ],
                    $states
                ),
                'districts' => array_map(
                    static fn(array $r): array => [
                        'id'       => (int) $r['id'],
                        'state_id' => (int) $r['state_id'],
                        'name'     => $r['name'],
                    ],
                    $districts
                ),
                'blocks' => array_map(
                    static fn(array $r): array => [
                        'id'          => (int) $r['id'],
                        'district_id' => (int) $r['district_id'],
                        'name'        => $r['name'],
                    ],
                    $blocks
                ),
                'gram_panchayats' => array_map(
                    static fn(array $r): array => [
                        'id'       => (int) $r['id'],
                        'block_id' => (int) $r['block_id'],
                        'name'     => $r['name'],
                    ],
                    $gramPanchayats
                ),
                'villages' => array_map(
                    static fn(array $r): array => [
                        'id'                => (int) $r['id'],
                        'block_id'          => (int) $r['block_id'],
                        'gram_panchayat_id' => $r['gram_panchayat_id'] !== null ? (int) $r['gram_panchayat_id'] : null,
                        'name'              => $r['name'],
                        'area_type'         => $r['area_type'] ?? null,
                        'pincode'           => $r['pincode'] ?? null,
                    ],
                    $villages
                ),
            ];

            $this->jsonSuccess(
                $payload,
                'Geography master data fetched successfully.'
            );
        } catch (\Throwable $e) {
            error_log('[Mobile Geography Master Data] ' . $e->getMessage());
            $this->jsonError(
                'Failed to fetch geography master data: ' . $e->getMessage(),
                null,
                500
            );
        }
    }

    /**
     * Convert geography rows to a consistent dropdown format.
     */
    private function options(array $rows): array
    {
        return array_map(
            static fn(array $row): array => [
                'id'   => (int) $row['id'],
                'name' => $row['name'] ?? '',
            ],
            $rows
        );
    }
}
