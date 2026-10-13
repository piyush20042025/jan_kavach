<?php
namespace JanKavach\Models;

use JanKavach\Core\Auth;
use JanKavach\Core\Model;

/**
 * Country > State > District > Block > Gram Panchayat > Village
 * (specification section 25).
 *
 * Every read here is scope-aware: a State Administrator asking for the state
 * list gets exactly one State back, so a dependent dropdown can never offer
 * a geography the user is not entitled to.
 */
class Geography extends Model
{
    protected string $table = 'states';

    // =================================================================
    // Reads for dependent dropdowns
    // =================================================================

    public function states(bool $activeOnly = true, bool $respectScope = true): array
    {
        $where = [];
        $bindings = [];

        if ($activeOnly) {
            $where[] = 'is_active = 1';
        }
        if ($respectScope && Auth::check() && Auth::dataScope() !== 'all') {
            $stateId = Auth::stateId();
            if ($stateId) {
                $where[] = 'id = :scope_state';
                $bindings['scope_state'] = $stateId;
            } else {
                return [];
            }
        }

        return $this->db->select(
            'SELECT id, state_code, name, type, region, is_programme_state FROM states'
            . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY is_programme_state DESC, name',
            $bindings
        );
    }

    public function stateOptions(bool $activeOnly = true, bool $respectScope = true): array
    {
        $out = [];
        foreach ($this->states($activeOnly, $respectScope) as $row) {
            $out[(int)$row['id']] = $row['name'];
        }
        return $out;
    }

    public function districts(?int $stateId, bool $activeOnly = true, bool $respectScope = true): array
    {
        if (!$stateId) {
            return [];
        }
        $where = ['state_id = :state'];
        $bindings = ['state' => $stateId];

        if ($activeOnly) {
            $where[] = 'is_active = 1';
        }
        // A district-scoped user only ever sees their own district.
        if ($respectScope && Auth::check() && in_array(Auth::dataScope(), ['district', 'own'], true)) {
            $districtId = Auth::districtId();
            if ($districtId) {
                $where[] = 'id = :scope_district';
                $bindings['scope_district'] = $districtId;
            }
        }

        return $this->db->select(
            'SELECT id, district_code, name, state_id FROM districts WHERE '
            . implode(' AND ', $where) . ' ORDER BY name',
            $bindings
        );
    }

    public function districtOptions(?int $stateId, bool $activeOnly = true, bool $respectScope = true): array
    {
        $out = [];
        foreach ($this->districts($stateId, $activeOnly, $respectScope) as $row) {
            $out[(int)$row['id']] = $row['name'];
        }
        return $out;
    }

    /** All districts the user may see, regardless of state - for filters. */
    public function allDistrictsInScope(): array
    {
        if (!Auth::check()) {
            return [];
        }
        $scope = Auth::dataScope();

        if ($scope === 'all') {
            return $this->db->select(
                'SELECT d.id, d.name, d.state_id, s.name AS state_name
                   FROM districts d JOIN states s ON s.id = d.state_id
                  WHERE d.is_active = 1 ORDER BY s.name, d.name');
        }
        if ($scope === 'state' && Auth::stateId()) {
            return $this->db->select(
                'SELECT d.id, d.name, d.state_id, s.name AS state_name
                   FROM districts d JOIN states s ON s.id = d.state_id
                  WHERE d.state_id = :s AND d.is_active = 1 ORDER BY d.name',
                ['s' => Auth::stateId()]);
        }
        if (Auth::districtId()) {
            return $this->db->select(
                'SELECT d.id, d.name, d.state_id, s.name AS state_name
                   FROM districts d JOIN states s ON s.id = d.state_id
                  WHERE d.id = :d', ['d' => Auth::districtId()]);
        }
        return [];
    }

    public function blocks(?int $districtId, bool $activeOnly = true): array
    {
        if (!$districtId) {
            return [];
        }
        $sql = 'SELECT id, name, code, block_type, district_id FROM blocks WHERE district_id = :d';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        return $this->db->select($sql . ' ORDER BY name', ['d' => $districtId]);
    }

    public function gramPanchayats(?int $blockId, bool $activeOnly = true): array
    {
        if (!$blockId) {
            return [];
        }
        $sql = 'SELECT id, name, code, block_id FROM gram_panchayats WHERE block_id = :b';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        return $this->db->select($sql . ' ORDER BY name', ['b' => $blockId]);
    }

    public function villages(?int $gramPanchayatId, bool $activeOnly = true): array
    {
        if (!$gramPanchayatId) {
            return [];
        }
        $sql = 'SELECT id, name, code, area_type, pincode, gram_panchayat_id
                  FROM villages WHERE gram_panchayat_id = :g';
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        return $this->db->select($sql . ' ORDER BY name', ['g' => $gramPanchayatId]);
    }

    public function villagesByBlock(?int $blockId, bool $activeOnly = true): array
    {
        if (!$blockId) {
            return [];
        }
        $sql = 'SELECT v.id, v.name, v.area_type, v.pincode, gp.name AS gp_name
                  FROM villages v LEFT JOIN gram_panchayats gp ON gp.id = v.gram_panchayat_id
                 WHERE v.block_id = :b';
        if ($activeOnly) {
            $sql .= ' AND v.is_active = 1';
        }
        return $this->db->select($sql . ' ORDER BY v.name', ['b' => $blockId]);
    }

    // =================================================================
    // Validation and lookups
    // =================================================================

    /** Confirm the chain a form submitted actually hangs together. */
    public function validateChain(?int $stateId, ?int $districtId, ?int $blockId = null, ?int $gpId = null, ?int $villageId = null): array
    {
        $errors = [];

        if ($districtId && $stateId) {
            $ok = (int)$this->db->scalar('SELECT COUNT(*) FROM districts WHERE id = :d AND state_id = :s',
                ['d' => $districtId, 's' => $stateId]);
            if (!$ok) {
                $errors['district_id'] = 'The selected District does not belong to the selected State.';
            }
        }
        if ($blockId && $districtId) {
            $ok = (int)$this->db->scalar('SELECT COUNT(*) FROM blocks WHERE id = :b AND district_id = :d',
                ['b' => $blockId, 'd' => $districtId]);
            if (!$ok) {
                $errors['block_id'] = 'The selected Block does not belong to the selected District.';
            }
        }
        if ($gpId && $blockId) {
            $ok = (int)$this->db->scalar('SELECT COUNT(*) FROM gram_panchayats WHERE id = :g AND block_id = :b',
                ['g' => $gpId, 'b' => $blockId]);
            if (!$ok) {
                $errors['gram_panchayat_id'] = 'The selected Gram Panchayat does not belong to the selected Block.';
            }
        }
        if ($villageId && $gpId) {
            $ok = (int)$this->db->scalar('SELECT COUNT(*) FROM villages WHERE id = :v AND gram_panchayat_id = :g',
                ['v' => $villageId, 'g' => $gpId]);
            if (!$ok) {
                $errors['village_id'] = 'The selected Village does not belong to the selected Gram Panchayat.';
            }
        }
        return $errors;
    }

    public function stateName(?int $id): ?string
    {
        return $id ? $this->db->scalar('SELECT name FROM states WHERE id = :id', ['id' => $id]) : null;
    }

    public function districtName(?int $id): ?string
    {
        return $id ? $this->db->scalar('SELECT name FROM districts WHERE id = :id', ['id' => $id]) : null;
    }

    public function stateOfDistrict(?int $districtId): ?int
    {
        if (!$districtId) {
            return null;
        }
        $value = $this->db->scalar('SELECT state_id FROM districts WHERE id = :id', ['id' => $districtId]);
        return $value !== null ? (int)$value : null;
    }

    // =================================================================
    // Master maintenance
    // =================================================================

    public function saveState(?int $id, array $data): int
    {
        $payload = [
            'state_code'         => strtoupper(trim((string)($data['state_code'] ?? ''))),
            'name'               => trim((string)($data['name'] ?? '')),
            'type'               => in_array($data['type'] ?? '', ['State', 'UT'], true) ? $data['type'] : 'State',
            'region'             => $data['region'] ?? null,
            'is_programme_state' => !empty($data['is_programme_state']) ? 1 : 0,
            'country_id'         => 1,
        ];
        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }
        if ($id) {
            $this->db->update('states', $payload, ['id' => $id]);
            return $id;
        }
        return $this->db->insert('states', $payload);
    }

    public function saveDistrict(?int $id, array $data): int
    {
        $payload = [
            'state_id'      => (int)($data['state_id'] ?? 0),
            'name'          => trim((string)($data['name'] ?? '')),
            'district_code' => $data['district_code'] ?? null,
        ];
        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }
        if ($id) {
            $this->db->update('districts', $payload, ['id' => $id]);
            return $id;
        }
        return $this->db->insert('districts', $payload);
    }

    public function saveBlock(?int $id, array $data): int
    {
        $payload = [
            'district_id' => (int)($data['district_id'] ?? 0),
            'name'        => trim((string)($data['name'] ?? '')),
            'code'        => $data['code'] ?? null,
            'block_type'  => in_array($data['block_type'] ?? '', ['Rural', 'Urban', 'Mixed'], true) ? $data['block_type'] : 'Rural',
        ];
        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }
        if ($id) {
            $this->db->update('blocks', $payload, ['id' => $id]);
            return $id;
        }
        return $this->db->insert('blocks', $payload);
    }

    public function saveGramPanchayat(?int $id, array $data): int
    {
        $payload = [
            'block_id' => (int)($data['block_id'] ?? 0),
            'name'     => trim((string)($data['name'] ?? '')),
            'code'     => $data['code'] ?? null,
        ];
        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }
        if ($id) {
            $this->db->update('gram_panchayats', $payload, ['id' => $id]);
            return $id;
        }
        return $this->db->insert('gram_panchayats', $payload);
    }

    public function saveVillage(?int $id, array $data): int
    {
        $gpId = (int)($data['gram_panchayat_id'] ?? 0) ?: null;
        $blockId = (int)($data['block_id'] ?? 0);
        if (!$blockId && $gpId) {
            $blockId = (int)$this->db->scalar('SELECT block_id FROM gram_panchayats WHERE id = :g', ['g' => $gpId]);
        }

        $payload = [
            'gram_panchayat_id' => $gpId,
            'block_id'          => $blockId,
            'name'              => trim((string)($data['name'] ?? '')),
            'code'              => $data['code'] ?? null,
            'area_type'         => in_array($data['area_type'] ?? '', ['Rural', 'Urban'], true) ? $data['area_type'] : 'Rural',
            'pincode'           => $data['pincode'] ?? null,
        ];
        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }
        if ($id) {
            $this->db->update('villages', $payload, ['id' => $id]);
            return $id;
        }
        return $this->db->insert('villages', $payload);
    }

    /** Paginated district list for the masters screen. */
    public function districtList(?int $stateId, ?string $search): array
    {
        $where = ['1 = 1'];
        $bindings = [];

        if ($stateId) {
            $where[] = 'd.state_id = :s';
            $bindings['s'] = $stateId;
        } elseif (Auth::dataScope() !== 'all' && Auth::stateId()) {
            $where[] = 'd.state_id = :scope';
            $bindings['scope'] = Auth::stateId();
        }
        if ($search) {
            $where[] = 'd.name LIKE :q';
            $bindings['q'] = '%' . $search . '%';
        }

        return $this->db->select(
            'SELECT d.*, s.name AS state_name,
                    (SELECT COUNT(*) FROM blocks bl WHERE bl.district_id = d.id) AS block_count,
                    (SELECT COUNT(*) FROM beneficiaries b WHERE b.district_id = d.id AND b.deleted_at IS NULL) AS beneficiary_count
               FROM districts d JOIN states s ON s.id = d.state_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY s.name, d.name',
            $bindings
        );
    }

    public function blockList(?int $districtId, ?string $search): array
    {
        $where = ['1 = 1'];
        $bindings = [];

        if ($districtId) {
            $where[] = 'bl.district_id = :d';
            $bindings['d'] = $districtId;
        } elseif (Auth::dataScope() !== 'all' && Auth::stateId()) {
            $where[] = 'd.state_id = :scope';
            $bindings['scope'] = Auth::stateId();
        }
        if ($search) {
            $where[] = 'bl.name LIKE :q';
            $bindings['q'] = '%' . $search . '%';
        }

        return $this->db->select(
            'SELECT bl.*, d.name AS district_name, s.name AS state_name,
                    (SELECT COUNT(*) FROM gram_panchayats gp WHERE gp.block_id = bl.id) AS gp_count,
                    (SELECT COUNT(*) FROM villages v WHERE v.block_id = bl.id) AS village_count
               FROM blocks bl
               JOIN districts d ON d.id = bl.district_id
               JOIN states s    ON s.id = d.state_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY s.name, d.name, bl.name',
            $bindings
        );
    }

    public function gramPanchayatList(?int $blockId, ?string $search): array
    {
        $where = ['1 = 1'];
        $bindings = [];

        if ($blockId) {
            $where[] = 'gp.block_id = :b';
            $bindings['b'] = $blockId;
        } elseif (Auth::dataScope() !== 'all' && Auth::stateId()) {
            $where[] = 's.id = :scope';
            $bindings['scope'] = Auth::stateId();
        }
        if ($search) {
            $where[] = 'gp.name LIKE :q';
            $bindings['q'] = '%' . $search . '%';
        }

        return $this->db->select(
            'SELECT gp.*, bl.name AS block_name, d.name AS district_name, s.name AS state_name,
                    (SELECT COUNT(*) FROM villages v WHERE v.gram_panchayat_id = gp.id) AS village_count
               FROM gram_panchayats gp
               JOIN blocks bl   ON bl.id = gp.block_id
               JOIN districts d ON d.id = bl.district_id
               JOIN states s    ON s.id = d.state_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY s.name, d.name, bl.name, gp.name',
            $bindings
        );
    }

    public function villageList(?int $blockId, ?int $gpId, ?string $search, int $limit = 500): array
    {
        $where = ['1 = 1'];
        $bindings = [];

        if ($gpId) {
            $where[] = 'v.gram_panchayat_id = :g';
            $bindings['g'] = $gpId;
        } elseif ($blockId) {
            $where[] = 'v.block_id = :b';
            $bindings['b'] = $blockId;
        } elseif (Auth::dataScope() !== 'all' && Auth::stateId()) {
            $where[] = 's.id = :scope';
            $bindings['scope'] = Auth::stateId();
        }
        if ($search) {
            $where[] = 'v.name LIKE :q';
            $bindings['q'] = '%' . $search . '%';
        }

        return $this->db->select(
            'SELECT v.*, gp.name AS gp_name, bl.name AS block_name,
                    d.name AS district_name, s.name AS state_name,
                    (SELECT COUNT(*) FROM beneficiaries b WHERE b.village_id = v.id AND b.deleted_at IS NULL) AS beneficiary_count
               FROM villages v
               LEFT JOIN gram_panchayats gp ON gp.id = v.gram_panchayat_id
               JOIN blocks bl   ON bl.id = v.block_id
               JOIN districts d ON d.id = bl.district_id
               JOIN states s    ON s.id = d.state_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY s.name, d.name, bl.name, v.name LIMIT ' . (int)$limit,
            $bindings
        );
    }

    public function stateList(?string $search): array
    {
        $where = ['1 = 1'];
        $bindings = [];
        if ($search) {
            $where[] = '(s.name LIKE :q OR s.state_code LIKE :q2)';
            $bindings['q'] = '%' . $search . '%';
            $bindings['q2'] = '%' . $search . '%';
        }
        if (Auth::dataScope() !== 'all' && Auth::stateId()) {
            $where[] = 's.id = :scope';
            $bindings['scope'] = Auth::stateId();
        }

        return $this->db->select(
            'SELECT s.*,
                    (SELECT COUNT(*) FROM districts d WHERE d.state_id = s.id) AS district_count,
                    (SELECT COUNT(*) FROM schemes sc WHERE sc.state_id = s.id AND sc.deleted_at IS NULL) AS scheme_count,
                    (SELECT COUNT(*) FROM beneficiaries b WHERE b.state_id = s.id AND b.deleted_at IS NULL) AS beneficiary_count
               FROM states s WHERE ' . implode(' AND ', $where) . '
              ORDER BY s.is_programme_state DESC, s.name',
            $bindings
        );
    }
}
