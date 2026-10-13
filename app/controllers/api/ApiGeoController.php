<?php
namespace JanKavach\Controllers\Api;

use JanKavach\Core\Controller;
use JanKavach\Models\Geography;

/**
 * Geographic hierarchy over the API, scope-aware like every other endpoint.
 */
class ApiGeoController extends Controller
{
    private Geography $geo;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->geo = new Geography();
    }

    public function states(): void
    {
        $this->jsonSuccess(['states' => $this->geo->states()]);
    }

    public function districts(string $stateId): void
    {
        $this->jsonSuccess([
            'state_id'  => (int)$stateId,
            'districts' => $this->geo->districts((int)$stateId),
        ]);
    }

    public function blocks(string $districtId): void
    {
        $this->jsonSuccess([
            'district_id' => (int)$districtId,
            'blocks'      => $this->geo->blocks((int)$districtId),
        ]);
    }

    public function gramPanchayats(string $blockId): void
    {
        $this->jsonSuccess([
            'block_id'        => (int)$blockId,
            'gram_panchayats' => $this->geo->gramPanchayats((int)$blockId),
        ]);
    }

    public function villages(string $gpId): void
    {
        $this->jsonSuccess([
            'gram_panchayat_id' => (int)$gpId,
            'villages'          => $this->geo->villages((int)$gpId),
        ]);
    }
}
