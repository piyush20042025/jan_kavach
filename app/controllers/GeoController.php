<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Controller;
use JanKavach\Models\Geography;

/**
 * Dependent dropdown endpoints (specification section 16).
 *
 * Every response is scope-aware: asking for the districts of a State the
 * user is not assigned to returns only what they are entitled to see, so the
 * dropdown cannot be used to enumerate other geographies.
 */
class GeoController extends Controller
{
    private Geography $geo;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->geo = new Geography();
    }

    public function states(): void
    {
        $this->jsonSuccess($this->options($this->geo->states(), 'name'));
    }

    public function districts(string $stateId): void
    {
        $this->jsonSuccess($this->options($this->geo->districts((int)$stateId), 'name'));
    }

    public function blocks(string $districtId): void
    {
        $this->jsonSuccess($this->options($this->geo->blocks((int)$districtId), 'name'));
    }

    public function gramPanchayats(string $blockId): void
    {
        $this->jsonSuccess($this->options($this->geo->gramPanchayats((int)$blockId), 'name'));
    }

    public function villages(string $gpId): void
    {
        $rows = $this->geo->villages((int)$gpId);
        $this->jsonSuccess(array_map(fn($r) => [
            'id'        => (int)$r['id'],
            'name'      => $r['name'],
            'area_type' => $r['area_type'] ?? null,
            'pincode'   => $r['pincode'] ?? null,
        ], $rows));
    }

    public function villagesByBlock(string $blockId): void
    {
        $rows = $this->geo->villagesByBlock((int)$blockId);
        $this->jsonSuccess(array_map(fn($r) => [
            'id'        => (int)$r['id'],
            'name'      => $r['name'] . ($r['gp_name'] ? ' (' . $r['gp_name'] . ')' : ''),
            'area_type' => $r['area_type'] ?? null,
            'pincode'   => $r['pincode'] ?? null,
        ], $rows));
    }

    /** Uniform {id, name} shape, so one JS helper fills every dropdown. */
    private function options(array $rows, string $labelColumn): array
    {
        return array_map(fn($r) => [
            'id'   => (int)$r['id'],
            'name' => $r[$labelColumn] ?? '',
        ], $rows);
    }
}
