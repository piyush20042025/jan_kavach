<?php
namespace JanKavach\Controllers\Api;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Paginator;
use JanKavach\Core\Scope;
use JanKavach\Core\Validator;
use JanKavach\Models\Beneficiary;
use JanKavach\Models\Geography;
use JanKavach\Models\Master;

/**
 * Beneficiary endpoints - the main surface a field application would use.
 */
class ApiBeneficiaryController extends Controller
{
    private Beneficiary $beneficiaries;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->beneficiaries = new Beneficiary();
    }

    public function index(): void
    {
        $stateId    = $this->request->int('state_id');
        $districtId = $this->request->int('district_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);

        $filters = [
            'search'            => $this->request->filled('search') ? (string)$this->request->input('search') : null,
            'state_id'          => $stateId,
            'district_id'       => $districtId,
            'record_status'     => $this->inOrNull('record_status',
                                    ['Registered', 'Assessed', 'Matched', 'In Process', 'Benefited', 'Closed', 'Rejected']),
            'assessment_status' => $this->inOrNull('assessment_status', ['Pending', 'In Progress', 'Completed']),
            'field_user_id'     => $this->request->int('field_user_id'),
            'date_from'         => $this->dateOrNull('date_from'),
            'date_to'           => $this->dateOrNull('date_to'),
        ];

        $paginator = $this->beneficiaries->paginate(
            $filters,
            Paginator::pageFrom($this->request),
            min(100, Paginator::perPageFrom($this->request)),
            'v.registration_date', 'DESC'
        );

        $this->jsonSuccess([
            'beneficiaries' => array_map(fn($b) => [
                'id'         => (int)$b['id'],
                'code'       => $b['beneficiary_code'],
                'name'       => $b['name'],
                'gender'     => $b['gender'],
                'age'        => $b['age'] !== null ? (int)$b['age'] : null,
                // Contact details stay masked over the API by default.
                'mobile'     => mask_mobile($b['mobile'] ?? null),
                'state'      => $b['state_name'],
                'district'   => $b['district_name'],
                'village'    => $b['village_name'],
                'registered' => $b['registration_date'],
                'status'     => $b['record_status'],
                'assessment' => $b['assessment_status'],
                'schemes_identified' => (int)$b['schemes_identified'],
                'applications'       => (int)$b['applications_count'],
            ], $paginator->items),
            'meta' => $paginator->toArray()['meta'],
        ]);
    }

    public function show(string $id): void
    {
        $beneficiary = $this->beneficiaries->findDetailed((int)$id);
        if (!$beneficiary) {
            $this->jsonError('Beneficiary not found.', null, 404);
        }
        $this->assertAccess($beneficiary);

        Audit::viewed('API', 'Beneficiary', (int)$id, $beneficiary['name']);

        $this->jsonSuccess([
            'id'        => (int)$beneficiary['id'],
            'code'      => $beneficiary['beneficiary_code'],
            'name'      => $beneficiary['name'],
            'relation'  => ['type' => $beneficiary['relation_type'], 'name' => $beneficiary['relation_name']],
            'father_name' => $beneficiary['father_name'] ?? null,
            'mother_name' => $beneficiary['mother_name'] ?? null,
            'spouse_name' => $beneficiary['spouse_name'] ?? null,
            'gender'    => $beneficiary['gender'],
            'dob'       => $beneficiary['dob'],
            'age'       => $beneficiary['age'] !== null ? (int)$beneficiary['age'] : null,
            'mobile'    => mask_mobile($beneficiary['mobile']),
            'aadhaar'   => ['available' => (bool)$beneficiary['has_aadhaar'], 'masked' => mask_aadhaar($beneficiary['aadhaar_last4'])],
            'geography' => [
                'state'           => $beneficiary['state_name'],
                'state_id'        => (int)$beneficiary['state_id'],
                'district'        => $beneficiary['district_name'],
                'district_id'     => (int)$beneficiary['district_id'],
                'block'           => $beneficiary['block_name'],
                'gram_panchayat'  => $beneficiary['gp_name'],
                'village'         => $beneficiary['village_display'],
                'pincode'         => $beneficiary['pincode'],
                'area_type'       => $beneficiary['area_type'],
            ],
            'same_as_permanent_address' => (bool)$beneficiary['same_as_permanent_address'],
            'address_line'    => $beneficiary['address_line'],
            'post_office'     => $beneficiary['post_office'],
            'current_address' => [
                'same_as_permanent' => (bool)$beneficiary['same_as_permanent_address'],
                'state'             => $beneficiary['current_state_name'],
                'state_id'          => $beneficiary['current_state_id'] !== null ? (int)$beneficiary['current_state_id'] : null,
                'district'          => $beneficiary['current_district_name'],
                'district_id'       => $beneficiary['current_district_id'] !== null ? (int)$beneficiary['current_district_id'] : null,
                'block'             => $beneficiary['current_block_name'],
                'block_id'          => $beneficiary['current_block_id'] !== null ? (int)$beneficiary['current_block_id'] : null,
                'gram_panchayat'    => $beneficiary['current_gp_name'],
                'gram_panchayat_id' => $beneficiary['current_gram_panchayat_id'] !== null ? (int)$beneficiary['current_gram_panchayat_id'] : null,
                'village'           => $beneficiary['current_village_display'],
                'village_id'        => $beneficiary['current_village_id'] !== null ? (int)$beneficiary['current_village_id'] : null,
                'village_name'      => $beneficiary['current_village_name'],
                'post_office'       => $beneficiary['current_post_office'],
                'address_line'      => $beneficiary['current_address_line'],
                'pincode'           => $beneficiary['current_pincode'],
                'area_type'         => $beneficiary['current_area_type'],
            ],
            'household' => $beneficiary['household'],
            'members'   => $beneficiary['members'],
            'status'     => $beneficiary['record_status'],
            'assessment' => $beneficiary['assessment_status'],
            'registered' => $beneficiary['registration_date'],
            'field_user' => $beneficiary['field_user_name'],
            'counts' => [
                'documents'    => (int)$this->db->scalar('SELECT COUNT(*) FROM beneficiary_documents WHERE beneficiary_id = :id', ['id' => (int)$id]),
                'matches'      => (int)$this->db->scalar("SELECT COUNT(*) FROM beneficiary_scheme_matches WHERE beneficiary_id = :id AND eligibility_status <> 'Not Eligible'", ['id' => (int)$id]),
                'applications' => (int)$this->db->scalar('SELECT COUNT(*) FROM beneficiary_scheme_applications WHERE beneficiary_id = :id AND deleted_at IS NULL', ['id' => (int)$id]),
            ],
        ]);
    }

    public function store(): void
    {
        $validator = Validator::make($this->request->all(), [
            'name'        => 'required|string|max:150|no_html',
            'gender'      => 'required|in:Male,Female,Other',
            'dob'         => 'nullable|date|date_before_today',
            'age'         => 'nullable|integer|between:0,120',
            'mobile'      => 'nullable|mobile',
            'aadhaar_number' => 'nullable|aadhaar',
            'state_id'    => 'required|integer|exists:states,id',
            'district_id' => 'required|integer|exists:districts,id',
            'area_type'   => 'required|in:Rural,Urban',
        ], ['name' => 'Beneficiary Name', 'state_id' => 'State', 'district_id' => 'District']);

        if ($validator->fails()) {
            $this->jsonError('Validation failed.', $validator->firstErrors(), 422);
        }

        $stateId    = (int)$this->request->input('state_id');
        $districtId = (int)$this->request->input('district_id');

        $chainErrors = (new Geography())->validateChain($stateId, $districtId,
            $this->request->int('block_id'), $this->request->int('gram_panchayat_id'),
            $this->request->int('village_id'));
        if ($chainErrors) {
            $this->jsonError('The geography selection is inconsistent.', $chainErrors, 422);
        }
        Scope::assertAccess($stateId, $districtId, Auth::id());

        $dob = $this->dateOrNull('dob');
        $age = $dob ? age_from_dob($dob) : $this->request->int('age');
        if ($age === null) {
            $this->jsonError('Either a date of birth or an age is required.', ['age' => 'Required'], 422);
        }

        $aadhaarDigits = preg_replace('/\D/', '', (string)($this->request->input('aadhaar_number') ?? ''));

        // Duplicate check before writing, so a flaky connection retry cannot
        // create the same household twice.
        $duplicates = $this->beneficiaries->findDuplicates(
            $aadhaarDigits !== '' ? hash_aadhaar($aadhaarDigits) : null,
            (string)$this->request->input('name'),
            $this->request->input('mobile'), $dob, $districtId);

        if ($duplicates && !$this->request->bool('confirm_duplicate')) {
            $this->jsonError('A similar beneficiary already exists. Re-send with confirm_duplicate=true to register anyway.', [
                'duplicates' => array_map(fn($d) => [
                    'id' => (int)$d['id'], 'code' => $d['beneficiary_code'], 'name' => $d['name'],
                ], $duplicates),
            ], 409);
        }

        $sameAsPermanent = $this->request->has('same_as_permanent_address')
            ? $this->request->bool('same_as_permanent_address')
            : (empty($this->request->input('current_state_id')) && empty($this->request->input('current_district_id')));

        if ($sameAsPermanent) {
            $currentStateId     = $stateId;
            $currentDistrictId  = $districtId;
            $currentBlockId     = $this->request->int('block_id');
            $currentGpId        = $this->request->int('gram_panchayat_id');
            $currentVillageId   = $this->request->int('village_id');
            $currentVillageName = $this->request->input('village_name') ?: null;
            $currentPostOffice  = $this->request->input('post_office') ?: null;
            $currentAddressLine = $this->request->input('address_line') ?: null;
            $currentPincode     = $this->request->input('pincode') ?: null;
            $currentAreaType    = (string)$this->request->input('area_type', 'Rural');
        } else {
            $currentStateId     = $this->request->int('current_state_id') ?: null;
            $currentDistrictId  = $this->request->int('current_district_id') ?: null;
            $currentBlockId     = $this->request->int('current_block_id') ?: null;
            $currentGpId        = $this->request->int('current_gram_panchayat_id') ?: null;
            $currentVillageId   = $this->request->int('current_village_id') ?: null;
            $currentVillageName = $this->request->input('current_village_name') ?: null;
            $currentPostOffice  = $this->request->input('current_post_office') ?: null;
            $currentAddressLine = $this->request->input('current_address_line') ?: null;
            $currentPincode     = $this->request->input('current_pincode') ?: null;
            $currentAreaType    = (string)$this->request->input('current_area_type', 'Rural');

            if (!$currentStateId) {
                $this->jsonError('Validation failed.', ['current_state_id' => 'Current State is required.'], 422);
            }
            if (!$currentDistrictId) {
                $this->jsonError('Validation failed.', ['current_district_id' => 'Current District is required.'], 422);
            }

            $currentChainErrors = (new Geography())->validateChain(
                $currentStateId, $currentDistrictId,
                $currentBlockId, $currentGpId, $currentVillageId
            );
            if ($currentChainErrors) {
                $prefixed = [];
                foreach ($currentChainErrors as $k => $v) {
                    $prefixed['current_' . $k] = $v;
                }
                $this->jsonError('The current geography selection is inconsistent.', $prefixed, 422);
            }
        }

        $data = [
            'beneficiary_code'  => $this->beneficiaries->nextBeneficiaryCode($stateId),
            'registration_date' => $this->dateOrNull('registration_date') ?: date('Y-m-d'),
            'name'              => (string)$this->request->input('name'),
            'relation_type'     => $this->request->input('relation_type') ?: 'Father',
            'relation_name'     => $this->request->input('relation_name') ?: null,
            'father_name'       => $this->request->input('father_name') ?: null,
            'mother_name'       => $this->request->input('mother_name') ?: null,
            'spouse_name'       => $this->request->input('spouse_name') ?: null,
            'post_office'       => $this->request->input('post_office') ?: null,
            'address_line'      => $this->request->input('address_line') ?: null,
            'gender'            => (string)$this->request->input('gender'),
            'dob'               => $dob,
            'age'               => $age,
            'mobile'            => $this->request->input('mobile') ?: null,
            'aadhaar_last4'     => $aadhaarDigits !== '' ? substr($aadhaarDigits, -4) : null,
            'aadhaar_hash'      => $aadhaarDigits !== '' ? hash_aadhaar($aadhaarDigits) : null,
            'has_aadhaar'       => $aadhaarDigits !== '' ? 1 : 0,
            'state_id'          => $stateId,
            'district_id'       => $districtId,
            'block_id'          => $this->request->int('block_id'),
            'gram_panchayat_id' => $this->request->int('gram_panchayat_id'),
            'village_id'        => $this->request->int('village_id'),
            'village_name'      => $this->request->input('village_name') ?: null,
            'pincode'           => $this->request->input('pincode') ?: null,
            'area_type'         => (string)$this->request->input('area_type'),
            'same_as_permanent_address' => $sameAsPermanent ? 1 : 0,
            'current_state_id'          => $currentStateId,
            'current_district_id'       => $currentDistrictId,
            'current_block_id'          => $currentBlockId,
            'current_gram_panchayat_id' => $currentGpId,
            'current_village_id'        => $currentVillageId,
            'current_village_name'      => $currentVillageName,
            'current_post_office'       => $currentPostOffice,
            'current_address_line'      => $currentAddressLine,
            'current_pincode'           => $currentPincode,
            'current_area_type'         => $currentAreaType,
            'consent_given'     => $this->request->bool('consent_given') ? 1 : 0,
            'field_user_id'     => Auth::id(),
        ];

        $beneficiaryId = $this->beneficiaries->create($data);
        Audit::created('API', 'Beneficiary', $beneficiaryId, $data['name'],
            array_diff_key($data, ['aadhaar_hash' => 1]), 'Registered through the API');

        $this->jsonSuccess([
            'id'   => $beneficiaryId,
            'code' => $data['beneficiary_code'],
        ], 'Beneficiary registered.');
    }

    public function update(string $id): void
    {
        $beneficiary = $this->beneficiaries->find((int)$id);
        if (!$beneficiary) {
            $this->jsonError('Beneficiary not found.', null, 404);
        }
        $this->assertAccess($beneficiary);

        $allowed = ['name', 'relation_type', 'relation_name', 'father_name', 'mother_name',
                    'spouse_name', 'mobile', 'alt_mobile', 'pincode', 'address_line',
                    'area_type', 'village_name', 'post_office', 'remarks',
                    'same_as_permanent_address', 'current_state_id', 'current_district_id',
                    'current_block_id', 'current_gram_panchayat_id', 'current_village_id',
                    'current_village_name', 'current_post_office', 'current_address_line',
                    'current_pincode', 'current_area_type'];

        $data = [];
        foreach ($allowed as $field) {
            if ($this->request->filled($field)) {
                $data[$field] = $this->request->input($field);
            }
        }
        if ($data === []) {
            $this->jsonError('Nothing to update.', null, 422);
        }

        if (isset($data['same_as_permanent_address'])) {
            $same = filter_var($data['same_as_permanent_address'], FILTER_VALIDATE_BOOLEAN);
            $data['same_as_permanent_address'] = $same ? 1 : 0;
            if ($same) {
                $data['current_state_id']          = $data['state_id'] ?? $beneficiary['state_id'];
                $data['current_district_id']       = $data['district_id'] ?? $beneficiary['district_id'];
                $data['current_block_id']          = array_key_exists('block_id', $data) ? $data['block_id'] : $beneficiary['block_id'];
                $data['current_gram_panchayat_id'] = array_key_exists('gram_panchayat_id', $data) ? $data['gram_panchayat_id'] : $beneficiary['gram_panchayat_id'];
                $data['current_village_id']        = array_key_exists('village_id', $data) ? $data['village_id'] : $beneficiary['village_id'];
                $data['current_village_name']      = array_key_exists('village_name', $data) ? $data['village_name'] : $beneficiary['village_name'];
                $data['current_post_office']       = array_key_exists('post_office', $data) ? $data['post_office'] : $beneficiary['post_office'];
                $data['current_address_line']      = array_key_exists('address_line', $data) ? $data['address_line'] : $beneficiary['address_line'];
                $data['current_pincode']           = array_key_exists('pincode', $data) ? $data['pincode'] : $beneficiary['pincode'];
                $data['current_area_type']         = $data['area_type'] ?? $beneficiary['area_type'];
            }
        }

        $before = $beneficiary;
        $this->beneficiaries->update((int)$id, $data);
        $after = $this->beneficiaries->find((int)$id);

        Audit::modified('API', 'Beneficiary', (int)$id, $after['name'] ?? null,
            array_diff_key($before, ['aadhaar_hash' => 1]),
            array_diff_key($after ?: [], ['aadhaar_hash' => 1]));

        $this->jsonSuccess(['id' => (int)$id], 'Beneficiary updated.');
    }

    /**
     * The questionnaire definition, so a mobile app renders new questions
     * without needing a new release.
     */
    public function questions(): void
    {
        $master = new Master();
        $sections = $master->assessmentForm();

        $payload = [];
        foreach ($sections as $section => $questions) {
            $payload[] = [
                'section'   => $section,
                'questions' => array_map(fn($q) => [
                    'key'        => $q['question_key'],
                    'label'      => $q['label'],
                    'help'       => $q['help_text'],
                    'input_type' => $q['input_type'],
                    'unit'       => $q['unit'],
                    'required'   => (bool)$q['is_required'],
                    'feeds_eligibility' => (bool)$q['feeds_eligibility'],
                    'options'    => isset($q['options'])
                        ? array_map(fn($label, $code) => ['code' => $code, 'label' => $label],
                                    $q['options'], array_keys($q['options']))
                        : [],
                ], $questions),
            ];
        }

        $this->jsonSuccess(['sections' => $payload]);
    }

    public function assessment(string $id): void
    {
        $beneficiary = $this->beneficiaries->find((int)$id);
        if (!$beneficiary) {
            $this->jsonError('Beneficiary not found.', null, 404);
        }
        $this->assertAccess($beneficiary);

        $answers = [];
        foreach ($this->beneficiaries->assessmentAnswers((int)$id) as $answer) {
            $answers[$answer['question_key']] = [
                'value' => $answer['value_bool'] !== null
                    ? ((int)$answer['value_bool'] === 1)
                    : ($answer['value_number'] !== null ? (float)$answer['value_number'] : $answer['value_text']),
                'label' => $answer['option_label'] ?? null,
            ];
        }

        $this->jsonSuccess([
            'beneficiary_id' => (int)$id,
            'assessment'     => $this->beneficiaries->latestAssessment((int)$id),
            'answers'        => $answers,
        ]);
    }

    public function saveAssessment(string $id): void
    {
        $beneficiary = $this->beneficiaries->find((int)$id);
        if (!$beneficiary) {
            $this->jsonError('Beneficiary not found.', null, 404);
        }
        $this->assertAccess($beneficiary);

        $answers = $this->request->input('answers');
        if (!is_array($answers) || $answers === []) {
            $json = $this->request->json();
            $answers = $json['answers'] ?? [];
        }
        if (!is_array($answers) || $answers === []) {
            $this->jsonError('No answers were supplied.', null, 422);
        }

        $complete = $this->request->bool('complete', true);
        $assessmentId = $this->beneficiaries->saveAssessment((int)$id, $answers, Auth::id(), $complete);

        Audit::log('API', 'Modified', [
            'entity_type'  => 'Beneficiary',
            'entity_id'    => (int)$id,
            'entity_label' => $beneficiary['name'],
            'description'  => 'Assessment submitted through the API (#' . $assessmentId . ')',
        ]);

        $this->jsonSuccess([
            'assessment_id' => $assessmentId,
            'complete'      => $complete,
        ], 'Assessment saved.');
    }

    public function documents(string $id): void
    {
        $beneficiary = $this->beneficiaries->find((int)$id);
        if (!$beneficiary) {
            $this->jsonError('Beneficiary not found.', null, 404);
        }
        $this->assertAccess($beneficiary);

        $this->jsonSuccess([
            'documents' => array_map(fn($d) => [
                'id'       => (int)$d['id'],
                'type'     => $d['document_name'],
                'number'   => mask_document_number($d['document_number'], (bool)$d['is_sensitive']),
                'issued'   => $d['issue_date'],
                'expires'  => $d['expiry_date'],
                'status'   => $d['verification_status'],
                'uploaded' => $d['created_at'],
                'url'      => url('/documents/' . (int)$d['id'] . '/view'),
            ], $this->beneficiaries->documents((int)$id)),
        ]);
    }

    private function assertAccess(array $beneficiary): void
    {
        Scope::assertAccess(
            (int)$beneficiary['state_id'],
            (int)$beneficiary['district_id'],
            $beneficiary['field_user_id'] ? (int)$beneficiary['field_user_id'] : null
        );
    }
}
