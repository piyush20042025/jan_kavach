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
