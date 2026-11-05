<?php

namespace JanKavach\Controllers\mobile_api;

use JanKavach\Core\Controller;
use JanKavach\Core\Audit;
use JanKavach\Middleware\mobile_api\AuthMiddleware;
use JanKavach\Models\Beneficiary;
use JanKavach\Models\Geography;

class BeneficiaryRegistrationController extends Controller
{
    // How many times to retry the insert if another request grabs the same code.
    private const MAX_CODE_RETRIES = 5;

    private Beneficiary $beneficiaries;
    private Geography $geo;

    public function __construct($request = null)
    {
        parent::__construct($request);

        $this->beneficiaries = new Beneficiary();
        $this->geo = new Geography();
    }

    public function store(): void
    {
        $userId = (int) AuthMiddleware::id();

        if ($userId <= 0) {
            $this->jsonError('Unauthenticated.', null, 401);
            return;
        }

        $data = $this->request->all();

        if (!is_array($data)) {
            $data = [];
        }

        // Required fields from the existing Admin form.
        $required = [
            'name',
            'gender',
            'state_id',
            'district_id',
            'registration_date',
            'area_type',
        ];

        $errors = [];

        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field))
                    . ' is required.';
            }
        }

        if (!in_array($data['gender'] ?? '', ['Male', 'Female', 'Other'], true)) {
            $errors['gender'] = 'Invalid gender.';
        }

        if (!in_array($data['area_type'] ?? '', ['Rural', 'Urban'], true)) {
            $errors['area_type'] = 'Invalid area type.';
        }

        if (
            isset($data['relation_type']) &&
            !in_array(
                $data['relation_type'],
                ['Father', 'Husband', 'Mother', 'Guardian', 'Other'],
                true
            )
        ) {
            $errors['relation_type'] = 'Invalid relation type.';
        }

        $registrationDate = $data['registration_date'] ?? '';

        if (
            !is_string($registrationDate) ||
            !preg_match('/^\d{4}-\d{2}-\d{2}$/', $registrationDate) ||
            !checkdate(
                (int) substr($registrationDate, 5, 2),
                (int) substr($registrationDate, 8, 2),
                (int) substr($registrationDate, 0, 4)
            ) ||
            $registrationDate > date('Y-m-d')
        ) {
            $errors['registration_date'] = 'Invalid registration date.';
        }

        $stateId = (int) ($data['state_id'] ?? 0);
        $districtId = (int) ($data['district_id'] ?? 0);

        if (!$errors) {
            $chainErrors = $this->geo->validateChain(
                $stateId,
                $districtId,
                !empty($data['block_id']) ? (int) $data['block_id'] : null,
                !empty($data['gram_panchayat_id'])
                ? (int) $data['gram_panchayat_id'] : null,
                !empty($data['village_id'])
                ? (int) $data['village_id'] : null
            );

            if ($chainErrors) {
                $this->jsonError(
                    'The geography selection is inconsistent.',
                    $chainErrors,
                    422
                );
                return;
            }
        }

        if ($errors) {
            $this->jsonError('Validation failed.', $errors, 422);
            return;
        }

        // The registration belongs to the authenticated field user.
        // Do not accept field_user_id from the mobile request.
        $user = $this->db->selectOne(
            'SELECT id, state_id, district_id, is_active, role_id
             FROM users
             WHERE id = :id
             LIMIT 1',
            ['id' => $userId]
        );

        if (!$user || (int) $user['is_active'] !== 1) {
            $this->jsonError('User is inactive or not found.', null, 403);
            return;
        }

        // The mobile API is for FIELD_USER accounts only.
        if ((int) $user['role_id'] !== 4) {
            $this->jsonError('This account cannot register beneficiaries.', null, 403);
            return;
        }

        // Prevent registering a beneficiary outside the user's assigned area.
        if (
            !empty($user['state_id']) &&
            (int) $user['state_id'] !== $stateId
        ) {
            $this->jsonError('Selected state is outside your assigned area.', null, 403);
            return;
        }

        if (
            !empty($user['district_id']) &&
            (int) $user['district_id'] !== $districtId
        ) {
            $this->jsonError('Selected district is outside your assigned area.', null, 403);
            return;
        }

        $dob = $data['dob'] ?? null;
        $dob = $dob !== '' ? $dob : null;

        if ($dob !== null) {
            $parsedDob = \DateTime::createFromFormat('!Y-m-d', (string) $dob);

            if (
                !$parsedDob ||
                $parsedDob->format('Y-m-d') !== $dob ||
                $dob > date('Y-m-d')
            ) {
                $this->jsonError(
                    'Invalid date of birth.',
                    ['dob' => 'Enter a valid past date.'],
                    422
                );
                return;
            }
        }

        $age = $dob
            ? age_from_dob($dob)
            : (isset($data['age']) && $data['age'] !== ''
                ? (int) $data['age']
                : null);

        if ($age === null || $age < 0 || $age > 120) {
            $this->jsonError(
                'Enter a valid age or date of birth.',
                ['age' => 'Age is required and must be between 0 and 120.'],
                422
            );
            return;
        }

        // Store only the last four Aadhaar digits and the existing one-way hash.
        $aadhaarDigits = preg_replace(
            '/\D/',
            '',
            (string) ($data['aadhaar_number'] ?? '')
        );

        if ($aadhaarDigits !== '' && strlen($aadhaarDigits) !== 12) {
            $this->jsonError(
                'Invalid Aadhaar number.',
                ['aadhaar_number' => 'Enter a valid 12-digit Aadhaar number.'],
                422
            );
            return;
        }

        $mobile = $this->normaliseMobile($data['mobile'] ?? null);
        $altMobile = $this->normaliseMobile($data['alt_mobile'] ?? null);

        $aadhaarHash = $aadhaarDigits !== ''
            ? hash_aadhaar($aadhaarDigits)
            : null;

        $aadhaarLast4 = $aadhaarDigits !== ''
            ? substr($aadhaarDigits, -4)
            : null;

        // Father / husband / guardian name used as an extra identity signal.
        $guardianName = trim((string) (
            $data['relation_name'] ?? $data['father_name'] ?? ''
        ));

        // A name alone never counts as a duplicate: another identifying
        // detail (Aadhaar, mobile, DOB or guardian name) must also match.
        $duplicates = $this->findMobileDuplicates(
            $aadhaarHash,
            trim((string) $data['name']),
            $mobile,
            $dob,
            $districtId,
            $guardianName !== '' ? $guardianName : null
        );

        if ($duplicates && empty($data['confirm_duplicate'])) {
            $this->jsonError(
                'A similar beneficiary already exists.',
                [
                    'duplicates' => array_map(
                        static fn($item) => [
                            'id' => (int) $item['id'],
                            'code' => $item['beneficiary_code'],
                            'name' => $item['name'],
                        ],
                        $duplicates
                    ),
                    'confirm_duplicate_required' => true,
                ],
                409
            );
            return;
        }

        $consent = filter_var(
            $data['consent_given'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $sameAsPermanent = array_key_exists('same_as_permanent_address', $data)
            ? filter_var($data['same_as_permanent_address'], FILTER_VALIDATE_BOOLEAN)
            : (empty($data['current_state_id']) && empty($data['current_district_id']));

        if ($sameAsPermanent) {
            $currentStateId     = $stateId;
            $currentDistrictId  = $districtId;
            $currentBlockId     = !empty($data['block_id']) ? (int) $data['block_id'] : null;
            $currentGpId        = !empty($data['gram_panchayat_id']) ? (int) $data['gram_panchayat_id'] : null;
            $currentVillageId   = !empty($data['village_id']) ? (int) $data['village_id'] : null;
            $currentVillageName = $data['village_name'] ?? null;
            $currentPostOffice  = $data['post_office'] ?? null;
            $currentAddressLine = $data['address_line'] ?? null;
            $currentPincode     = $data['pincode'] ?? null;
            $currentAreaType    = $data['area_type'] ?? 'Rural';
        } else {
            $currentStateId     = !empty($data['current_state_id']) ? (int) $data['current_state_id'] : null;
            $currentDistrictId  = !empty($data['current_district_id']) ? (int) $data['current_district_id'] : null;
            $currentBlockId     = !empty($data['current_block_id']) ? (int) $data['current_block_id'] : null;
            $currentGpId        = !empty($data['current_gram_panchayat_id']) ? (int) $data['current_gram_panchayat_id'] : null;
            $currentVillageId   = !empty($data['current_village_id']) ? (int) $data['current_village_id'] : null;
            $currentVillageName = $data['current_village_name'] ?? null;
            $currentPostOffice  = $data['current_post_office'] ?? null;
            $currentAddressLine = $data['current_address_line'] ?? null;
            $currentPincode     = $data['current_pincode'] ?? null;
            $currentAreaType    = in_array($data['current_area_type'] ?? '', ['Rural', 'Urban'], true)
                ? $data['current_area_type']
                : ($data['area_type'] ?? 'Rural');

            if (!$currentStateId) {
                $this->jsonError('Validation failed.', ['current_state_id' => 'Current State is required.'], 422);
                return;
            }
            if (!$currentDistrictId) {
                $this->jsonError('Validation failed.', ['current_district_id' => 'Current District is required.'], 422);
                return;
            }

            $currentChainErrors = $this->geo->validateChain(
                $currentStateId,
                $currentDistrictId,
                $currentBlockId,
                $currentGpId,
                $currentVillageId
            );
            if ($currentChainErrors) {
                $prefixed = [];
                foreach ($currentChainErrors as $k => $v) {
                    $prefixed['current_' . $k] = $v;
                }
                $this->jsonError('The current geography selection is inconsistent.', $prefixed, 422);
                return;
            }
        }

        $beneficiaryData = [
            'registration_date' => $registrationDate,
            'name' => trim((string) $data['name']),
            'relation_type' => $data['relation_type'] ?? 'Father',
            'relation_name' => $data['relation_name'] ?? null,
            'father_name' => $data['father_name'] ?? null,
            'mother_name' => $data['mother_name'] ?? null,
            'spouse_name' => $data['spouse_name'] ?? null,
            'gender' => $data['gender'],
            'dob' => $dob,
            'age' => $age,
            'mobile' => $mobile,
            'alt_mobile' => $altMobile,
            'aadhaar_last4' => $aadhaarLast4,
            'aadhaar_hash' => $aadhaarHash,
            'has_aadhaar' => $aadhaarDigits !== '' ? 1 : 0,
            'state_id' => $stateId,
            'district_id' => $districtId,
            'block_id' => !empty($data['block_id'])
                ? (int) $data['block_id'] : null,
            'gram_panchayat_id' => !empty($data['gram_panchayat_id'])
                ? (int) $data['gram_panchayat_id'] : null,
            'village_id' => !empty($data['village_id'])
                ? (int) $data['village_id'] : null,
            'village_name' => $data['village_name'] ?? null,
            'post_office' => $data['post_office'] ?? null,
            'address_line' => $data['address_line'] ?? null,
            'pincode' => $data['pincode'] ?? null,
            'area_type' => $data['area_type'],
            'same_as_permanent_address' => $sameAsPermanent ? 1 : 0,
            'current_state_id' => $currentStateId,
            'current_district_id' => $currentDistrictId,
            'current_block_id' => $currentBlockId,
            'current_gram_panchayat_id' => $currentGpId,
            'current_village_id' => $currentVillageId,
            'current_village_name' => $currentVillageName,
            'current_post_office' => $currentPostOffice,
            'current_address_line' => $currentAddressLine,
            'current_pincode' => $currentPincode,
            'current_area_type' => $currentAreaType,
            'consent_given' => $consent ? 1 : 0,
            'consent_date' => $consent ? date('Y-m-d') : null,
            'field_user_id' => $userId,
            'remarks' => $data['remarks'] ?? null,
        ];

        try {
            $beneficiaryId = null;

            for ($attempt = 1; $attempt <= self::MAX_CODE_RETRIES; $attempt++) {
                try {
                    $beneficiaryId = $this->db->transaction(
                        function () use (&$beneficiaryData, $data, $userId, $stateId) {
                            // Generate the code inside the transaction,
                            // right before the insert, so it uses the latest DB state.
                            $beneficiaryData['beneficiary_code'] =
                                $this->nextUniqueMobileBeneficiaryCode($stateId);

                            $id = $this->beneficiaries->create($beneficiaryData);

                            $this->beneficiaries->saveVisit([
                                'beneficiary_id' => $id,
                                'visit_date' => $beneficiaryData['registration_date'],
                                'visit_type' => 'Registration',
                                'field_user_id' => $userId,
                                'state_id' => $stateId,
                                'district_id' => $beneficiaryData['district_id'],
                                'block_id' => $beneficiaryData['block_id'],
                                'village_id' => $beneficiaryData['village_id'],
                                'households_covered' => 1,
                                'purpose' => 'Beneficiary registration',
                            ]);

                            if (
                                isset($data['answers']) &&
                                is_array($data['answers']) &&
                                $data['answers'] !== []
                            ) {
                                $this->beneficiaries->saveAssessment(
                                    $id,
                                    $data['answers'],
                                    $userId,
                                    !empty($data['complete'])
                                );
                            }

                            return $id;
                        }
                    );

                    break; // success
                } catch (\PDOException $e) {
                    // Retry only when another request took the same code.
                    if (
                        !$this->isDuplicateCodeError($e) ||
                        $attempt === self::MAX_CODE_RETRIES
                    ) {
                        throw $e;
                    }
                }
            }

            // Return the code that is really stored, not just the generated one.
            $stored = $this->db->selectOne(
                'SELECT beneficiary_code FROM beneficiaries WHERE id = :id LIMIT 1',
                ['id' => $beneficiaryId]
            );

            if ($stored && !empty($stored['beneficiary_code'])) {
                $beneficiaryData['beneficiary_code'] = $stored['beneficiary_code'];
            }
        } catch (\Throwable $e) {
            error_log(
                '[Mobile Beneficiary Registration] ' .
                $e->getMessage() . PHP_EOL .
                $e->getTraceAsString()
            );

            // Keep internal exception details out of the API response.
            $this->jsonError(
                'Beneficiary registration failed.',
                null,
                500
            );
            return;
        }

        Audit::created(
            'API',
            'Beneficiary',
            $beneficiaryId,
            $beneficiaryData['name'],
            array_diff_key($beneficiaryData, ['aadhaar_hash' => 1]),
            'Registered through mobile API'
        );

        $this->jsonSuccess([
            'id' => $beneficiaryId,
            'code' => $beneficiaryData['beneficiary_code'],
            'record_status' => 'Registered',
            'assessment_submitted' => !empty($data['answers']),
        ], 'Beneficiary registered successfully.');
    }

    public function update(int $id): void
    {
        $userId = (int) AuthMiddleware::id();
        if ($userId <= 0) {
            $this->jsonError('Unauthenticated.', null, 401);
            return;
        }

        $beneficiary = $this->db->selectOne(
            'SELECT * FROM beneficiaries WHERE id = :id AND field_user_id = :user_id AND deleted_at IS NULL LIMIT 1',
            ['id' => $id, 'user_id' => $userId]
        );

        if (!$beneficiary) {
            $this->jsonError('Beneficiary not found or access denied.', null, 404);
            return;
        }

        $data = $this->request->all();
        if (!is_array($data)) {
            $data = [];
        }

        $allowed = [
            'name', 'gender', 'dob', 'age', 'mobile', 'alt_mobile',
            'relation_type', 'relation_name', 'father_name', 'mother_name', 'spouse_name',
            'post_office', 'address_line', 'pincode', 'area_type', 'village_name',
            'remarks', 'consent_given',
            'same_as_permanent_address', 'current_state_id', 'current_district_id',
            'current_block_id', 'current_gram_panchayat_id', 'current_village_id',
            'current_village_name', 'current_post_office', 'current_address_line',
            'current_pincode', 'current_area_type',
        ];

        $updateData = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (array_key_exists('same_as_permanent_address', $data)) {
            $same = filter_var($data['same_as_permanent_address'], FILTER_VALIDATE_BOOLEAN);
            $updateData['same_as_permanent_address'] = $same ? 1 : 0;
            if ($same) {
                $updateData['current_state_id']          = $updateData['state_id'] ?? $beneficiary['state_id'];
                $updateData['current_district_id']       = $updateData['district_id'] ?? $beneficiary['district_id'];
                $updateData['current_block_id']          = array_key_exists('block_id', $updateData) ? $updateData['block_id'] : $beneficiary['block_id'];
                $updateData['current_gram_panchayat_id'] = array_key_exists('gram_panchayat_id', $updateData) ? $updateData['gram_panchayat_id'] : $beneficiary['gram_panchayat_id'];
                $updateData['current_village_id']        = array_key_exists('village_id', $updateData) ? $updateData['village_id'] : $beneficiary['village_id'];
                $updateData['current_village_name']      = array_key_exists('village_name', $updateData) ? $updateData['village_name'] : $beneficiary['village_name'];
                $updateData['current_post_office']       = array_key_exists('post_office', $updateData) ? $updateData['post_office'] : $beneficiary['post_office'];
                $updateData['current_address_line']      = array_key_exists('address_line', $updateData) ? $updateData['address_line'] : $beneficiary['address_line'];
                $updateData['current_pincode']           = array_key_exists('pincode', $updateData) ? $updateData['pincode'] : $beneficiary['pincode'];
                $updateData['current_area_type']         = $updateData['area_type'] ?? $beneficiary['area_type'];
            } else {
                $currentStateId    = !empty($updateData['current_state_id']) ? (int)$updateData['current_state_id'] : (int)$beneficiary['current_state_id'];
                $currentDistrictId = !empty($updateData['current_district_id']) ? (int)$updateData['current_district_id'] : (int)$beneficiary['current_district_id'];
                if ($currentStateId && $currentDistrictId) {
                    $chainErrors = $this->geo->validateChain(
                        $currentStateId, $currentDistrictId,
                        !empty($updateData['current_block_id']) ? (int)$updateData['current_block_id'] : null,
                        !empty($updateData['current_gram_panchayat_id']) ? (int)$updateData['current_gram_panchayat_id'] : null,
                        !empty($updateData['current_village_id']) ? (int)$updateData['current_village_id'] : null
                    );
                    if ($chainErrors) {
                        $this->jsonError('The current geography selection is inconsistent.', $chainErrors, 422);
                        return;
                    }
                }
            }
        }

        if (empty($updateData)) {
            $this->jsonError('Nothing to update.', null, 422);
            return;
        }

        $this->beneficiaries->update($id, $updateData);

        Audit::modified(
            'API',
            'Beneficiary',
            $id,
            $updateData['name'] ?? $beneficiary['name'],
            array_diff_key($beneficiary, ['aadhaar_hash' => 1]),
            $updateData,
            'Updated through mobile API'
        );

        $this->jsonSuccess(['id' => $id], 'Beneficiary updated successfully.');
    }

    private function findMobileDuplicates(
        ?string $aadhaarHash,
        string $name,
        ?string $mobile,
        ?string $dob,
        int $districtId,
        ?string $guardianName = null
    ): array {
        $clauses = [];
        $bindings = [];

        $exactName = mb_strtolower(trim($name));

        // 1. Same Aadhaar = same person.
        if ($aadhaarHash !== null && $aadhaarHash !== '') {
            $clauses[] = 'b.aadhaar_hash = :hash';
            $bindings['hash'] = $aadhaarHash;
        }

        // 2. Same mobile + same name.
        if ($mobile !== null && $mobile !== '') {
            $clauses[] = '(b.mobile = :mobile AND LOWER(TRIM(b.name)) = :name_mobile)';
            $bindings['mobile'] = $mobile;
            $bindings['name_mobile'] = $exactName;
        }

        // 3. Same DOB + same name + same district.
        if ($dob !== null && $dob !== '') {
            $clauses[] = '(
                b.dob = :dob
                AND LOWER(TRIM(b.name)) = :name_dob
                AND b.district_id = :district_dob
            )';
            $bindings['dob'] = $dob;
            $bindings['name_dob'] = $exactName;
            $bindings['district_dob'] = $districtId;
        }

        // 4. Same name + same father/guardian name + same district.
        if ($guardianName !== null && $guardianName !== '') {
            $clauses[] = '(
                LOWER(TRIM(b.name)) = :name_guardian
                AND LOWER(TRIM(COALESCE(NULLIF(b.relation_name, \'\'), NULLIF(b.father_name, \'\')))) = :guardian
                AND b.district_id = :district_guardian
            )';
            $bindings['name_guardian'] = $exactName;
            $bindings['guardian'] = mb_strtolower(trim($guardianName));
            $bindings['district_guardian'] = $districtId;
        }

        // Nothing strong enough to compare, so no duplicate can be claimed.
        if (!$clauses) {
            return [];
        }

        $sql = '
            SELECT
                b.id,
                b.beneficiary_code,
                b.name,
                b.gender,
                b.age,
                b.mobile,
                b.registration_date,
                d.name AS district_name,
                COALESCE(v.name, b.village_name) AS village_name
            FROM beneficiaries b
            LEFT JOIN districts d ON d.id = b.district_id
            LEFT JOIN villages v ON v.id = b.village_id
            WHERE b.deleted_at IS NULL
              AND (' . implode(' OR ', $clauses) . ')
            ORDER BY b.id DESC
            LIMIT 10
        ';

        return $this->db->select($sql, $bindings);
    }

    private function nextUniqueMobileBeneficiaryCode(int $stateId): string
    {
        // Reuse the existing model's state/year prefix without changing Admin logic.
        $generated = $this->beneficiaries->nextBeneficiaryCode($stateId);

        if (!preg_match('/^(.*-)(\d+)$/', $generated, $matches)) {
            throw new \RuntimeException(
                'Invalid beneficiary code format: ' . $generated
            );
        }

        $prefix = $matches[1];
        $width = strlen($matches[2]);

        // Escape LIKE wildcards (% and _) in the prefix.
        $likePrefix = addcslashes($prefix, '\\%_') . '%';

        // Position is derived from the trusted generated prefix, not request input.
        $suffixPos = strlen($prefix) + 1;

        // Only consider codes whose suffix is purely numeric.
        $row = $this->db->selectOne(
            'SELECT COALESCE(
                 MAX(CAST(SUBSTRING(beneficiary_code, ' . $suffixPos . ') AS UNSIGNED)),
                 0
             ) AS max_suffix
             FROM beneficiaries
             WHERE beneficiary_code LIKE :prefix
               AND SUBSTRING(beneficiary_code, ' . $suffixPos . ') REGEXP \'^[0-9]+$\'',
            ['prefix' => $likePrefix]
        );

        // Never go below the model's own suggestion, never repeat the DB max.
        $number = max((int) ($row['max_suffix'] ?? 0) + 1, (int) $matches[2]);

        // Check candidates against the unique column before returning.
        for ($i = 0; $i < 100; $i++, $number++) {
            $candidate = $prefix . str_pad(
                (string) $number,
                $width,
                '0',
                STR_PAD_LEFT
            );

            $existing = $this->db->selectOne(
                'SELECT id FROM beneficiaries WHERE beneficiary_code = :code LIMIT 1',
                ['code' => $candidate]
            );

            if (!$existing) {
                return $candidate;
            }
        }

        throw new \RuntimeException(
            'Could not generate a unique beneficiary code.'
        );
    }

    private function isDuplicateCodeError(\PDOException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            && stripos($e->getMessage(), 'beneficiary_code') !== false;
    }

    private function normaliseMobile($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $value);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) !== 10) {
            return null;
        }

        return $digits;
    }
}