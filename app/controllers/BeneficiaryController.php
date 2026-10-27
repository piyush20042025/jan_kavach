<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Config;
use JanKavach\Core\Controller;
use JanKavach\Core\Flash;
use JanKavach\Core\Paginator;
use JanKavach\Core\Response;
use JanKavach\Core\Scope;
use JanKavach\Core\Validator;
use JanKavach\Libraries\EligibilityEngine;
use JanKavach\Libraries\Exporter;
use JanKavach\Models\Beneficiary;
use JanKavach\Models\BeneficiaryDocument;
use JanKavach\Models\Geography;
use JanKavach\Models\Master;
use JanKavach\Models\User;

/**
 * Beneficiary registration, assessment and the 360-degree profile
 * (specification sections 17, 18, 23, 24).
 */
class BeneficiaryController extends Controller
{
    private Beneficiary $beneficiaries;
    private Geography $geo;
    private Master $masters;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->beneficiaries = new Beneficiary();
        $this->geo     = new Geography();
        $this->masters = new Master();
    }

    // =================================================================
    // Listing
    // =================================================================

    public function index(): void
    {
        $filters = $this->beneficiaryFilters();
        [$column, $dir, $sortKey] = $this->sort(Beneficiary::SORTS, 'created', 'DESC');

        $paginator = $this->beneficiaries->paginate(
            $filters,
            Paginator::pageFrom($this->request),
            Paginator::perPageFrom($this->request),
            $column, $dir, $_GET ?? []
        );

        $this->view('beneficiaries/index', [
            'pageTitle'   => 'All Beneficiaries',
            'breadcrumbs' => [['label' => 'Beneficiaries', 'url' => '/beneficiaries'], ['label' => 'All Beneficiaries']],
            'paginator'   => $paginator,
            'filters'     => $filters,
            'sortKey'     => $sortKey,
            'sortDir'     => $dir,
            'states'      => $this->geo->stateOptions(),
            'districts'   => $this->geo->districtOptions($filters['state_id'] ?? null),
            'fieldUsers'  => (new User())->fieldUsers($filters['state_id'] ?? null, $filters['district_id'] ?? null),
            'schemes'     => $this->db->pluck(
                'SELECT id, COALESCE(short_name, name) FROM schemes
                  WHERE deleted_at IS NULL AND is_active = 1 ORDER BY name LIMIT 400'),
        ]);
    }

    public function export(): void
    {
        $format  = $this->inOrNull('format', ['excel', 'csv', 'pdf']) ?? 'excel';
        $filters = $this->beneficiaryFilters();
        [$column, $dir] = $this->sort(Beneficiary::SORTS, 'created', 'DESC');

        $rows = $this->beneficiaries->allFiltered($filters, $column, $dir);

        // Aadhaar is never exported, and the mobile number is masked unless
        // the exporter holds the permission to see full contact details.
        $showFullMobile = Auth::can('beneficiary.export') && Auth::isSuperAdmin();

        Exporter::send($format, [
            'Beneficiary ID'    => 'beneficiary_code',
            'Name'              => 'name',
            'Gender'            => 'gender',
            'Age'               => fn($r) => $r['age'] ?? '-',
            'Mobile'            => fn($r) => $showFullMobile ? ($r['mobile'] ?? '-') : mask_mobile($r['mobile'] ?? null),
            'State'             => 'state_name',
            'District'          => 'district_name',
            'Block'             => fn($r) => $r['block_name'] ?? '-',
            'Village'           => fn($r) => $r['village_name'] ?? '-',
            'Rural / Urban'     => 'area_type',
            'Registration Date' => fn($r) => fmt_date($r['registration_date']),
            'Record Status'     => 'record_status',
            'Assessment'        => 'assessment_status',
            'Schemes Identified'=> fn($r) => (int)$r['schemes_identified'],
            'Applications'      => fn($r) => (int)$r['applications_count'],
            'Submitted'         => fn($r) => (int)$r['applications_submitted'],
            'Benefits Received' => fn($r) => (int)$r['benefits_received'],
            'Documents on File' => fn($r) => (int)$r['document_count'],
            'Field User'        => fn($r) => $r['field_user_name'] ?? '-',
        ], $rows, 'beneficiary-register', [
            'title'       => 'Beneficiary Registration Report',
            'subtitle'    => 'Aadhaar numbers are never exported. Mobile numbers are masked unless full access is held.',
            'module'      => 'Beneficiaries',
            'filters'     => $this->describeFilters($filters),
            'orientation' => 'landscape',
        ]);
    }

    // =================================================================
    // 360-degree profile (section 24)
    // =================================================================

    public function show(string $id): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->findDetailed((int)$id), 'Beneficiary');
        $this->assertAccess($beneficiary);

        Audit::viewed('Beneficiaries', 'Beneficiary', (int)$beneficiary['id'], $beneficiary['name']);

        $documents = new BeneficiaryDocument();

        $this->view('beneficiaries/show', [
            'pageTitle'   => $beneficiary['name'],
            'breadcrumbs' => [
                ['label' => 'Beneficiaries', 'url' => '/beneficiaries'],
                ['label' => str_limit($beneficiary['name'], 40)],
            ],
            'beneficiary' => $beneficiary,
            'answers'     => $this->groupAnswers($this->beneficiaries->assessmentAnswers((int)$id)),
            'assessment'  => $this->beneficiaries->latestAssessment((int)$id),
            'documents'   => $this->beneficiaries->documents((int)$id),
            'outstanding' => $documents->outstandingFor((int)$id),
            'matches'     => $this->beneficiaries->matches((int)$id, false),
            'applications'=> $this->beneficiaries->applications((int)$id),
            'followups'   => $this->beneficiaries->followups((int)$id),
            'benefits'    => $this->beneficiaries->benefits((int)$id),
            'visits'      => $this->beneficiaries->visits((int)$id),
            'auditTrail'  => Auth::can('audit.view') ? $this->beneficiaries->auditHistory((int)$id, 30) : [],
            'timeline'    => $this->beneficiaries->timeline((int)$id, $beneficiary),
        ]);
    }

    public function printProfile(string $id): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->findDetailed((int)$id), 'Beneficiary');
        $this->assertAccess($beneficiary);

        $this->bare('beneficiaries/print', [
            'pageTitle'   => $beneficiary['name'],
            'beneficiary' => $beneficiary,
            'answers'     => $this->groupAnswers($this->beneficiaries->assessmentAnswers((int)$id)),
            'matches'     => $this->beneficiaries->matches((int)$id, true),
            'applications'=> $this->beneficiaries->applications((int)$id),
            'documents'   => $this->beneficiaries->documents((int)$id),
            'timeline'    => $this->beneficiaries->timeline((int)$id, $beneficiary),
            'autoPrint'   => true,
        ], 'layouts/print');
    }

    // =================================================================
    // Create / edit
    // =================================================================

    public function create(): void
    {
        $this->view('beneficiaries/form', array_merge($this->formData(null), [
            'pageTitle'   => 'Register Beneficiary',
            'breadcrumbs' => [['label' => 'Beneficiaries', 'url' => '/beneficiaries'], ['label' => 'Register Beneficiary']],
            'beneficiary' => null,
        ]));
    }

    public function edit(string $id): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->findDetailed((int)$id), 'Beneficiary');
        $this->assertAccess($beneficiary);

        $this->view('beneficiaries/form', array_merge($this->formData($beneficiary), [
            'pageTitle'   => 'Edit Beneficiary',
            'breadcrumbs' => [
                ['label' => 'Beneficiaries', 'url' => '/beneficiaries'],
                ['label' => str_limit($beneficiary['name'], 40), 'url' => '/beneficiaries/' . (int)$id],
                ['label' => 'Edit'],
            ],
            'beneficiary' => $beneficiary,
        ]));
    }

    public function store(): void
    {
        $data = $this->validateBeneficiary(null);

        if (!$this->request->bool('confirm_duplicate')) {
            $duplicates = $this->beneficiaries->findDuplicates(
                $data['aadhaar_hash'], $data['name'], $data['mobile'],
                $data['dob'], (int)$data['district_id']
            );
            if ($duplicates) {
                Flash::warning('A similar beneficiary is already registered: '
                    . implode('; ', array_map(fn($d) => $d['beneficiary_code'] . ' - ' . $d['name']
                        . ' (' . ($d['village_name'] ?: $d['district_name']) . ')', $duplicates))
                    . '. Tick "Register anyway" if this is a different person.');
                $this->backWithErrors(['name' => 'Possible duplicate registration.'], '/beneficiaries/create');
            }
        }

        $beneficiaryId = $this->db->transaction(function () use ($data) {
            $data['beneficiary_code'] = $this->beneficiaries->nextBeneficiaryCode((int)$data['state_id']);
            $id = $this->beneficiaries->create($data);

            // A registration is also a field visit.
            $this->beneficiaries->saveVisit([
                'beneficiary_id' => $id,
                'visit_date'     => $data['registration_date'],
                'visit_type'     => 'Registration',
                'field_user_id'  => (int)($data['field_user_id'] ?: Auth::id()),
                'state_id'       => (int)$data['state_id'],
                'district_id'    => (int)$data['district_id'],
                'block_id'       => $data['block_id'],
                'village_id'     => $data['village_id'],
                'households_covered' => 1,
                'purpose'        => 'Beneficiary registration',
            ]);
            return $id;
        });

        // The profile questionnaire is filled in on the same screen; it is
        // recorded as a completed assessment when every required question was
        // answered, and as a draft otherwise.
        $saved = $this->saveProfileAnswers($beneficiaryId);

        Audit::created('Beneficiaries', 'Beneficiary', $beneficiaryId, $data['name'],
            array_diff_key($data, ['aadhaar_hash' => 1]));

        if ($saved['answered'] === 0) {
            Flash::success('Beneficiary registered. Complete the profile questionnaire to identify applicable schemes.');
            $this->redirect('/beneficiaries/' . $beneficiaryId . '/assessment');
        }

        Flash::success($saved['complete']
            ? 'Beneficiary registered and the profile questionnaire recorded. Run scheme matching to identify applicable schemes.'
            : 'Beneficiary registered. The questionnaire was saved as a draft - '
              . $saved['missing'] . ' required question(s) still need an answer.');
        $this->redirect('/beneficiaries/' . $beneficiaryId
            . ($saved['complete'] ? '/matching' : '/assessment'));
    }

    public function update(string $id): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->find((int)$id), 'Beneficiary');
        $this->assertAccess($beneficiary);
        $beneficiaryId = (int)$id;

        $data = $this->validateBeneficiary($beneficiaryId);
        unset($data['beneficiary_code']);   // never reissue an existing code

        $before = $beneficiary;

        // The household block is no longer part of this form, so an edit must
        // leave any household record already on file untouched rather than
        // blanking it with fields the form never posted.
        $this->db->transaction(function () use ($beneficiaryId, $data) {
            $this->beneficiaries->update($beneficiaryId, $data);
        });

        $this->saveProfileAnswers($beneficiaryId);

        $after = $this->beneficiaries->find($beneficiaryId);
        Audit::modified('Beneficiaries', 'Beneficiary', $beneficiaryId, $data['name'],
            $before, $after ?: []);

        Flash::success('Beneficiary details updated. Re-run scheme matching if the socio-economic details changed.');
        $this->redirect('/beneficiaries/' . $beneficiaryId);
    }

    public function destroy(string $id): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->find((int)$id), 'Beneficiary');
        $this->assertAccess($beneficiary);

        $benefits = (int)$this->db->scalar(
            'SELECT COUNT(*) FROM benefits_received WHERE beneficiary_id = :id', ['id' => (int)$id]);

        if ($benefits > 0) {
            $message = 'This beneficiary has ' . $benefits . ' recorded benefit(s) and cannot be deleted. '
                     . 'Close the record instead so the benefit history is preserved.';
            if ($this->request->isAjax()) {
                $this->jsonError($message, null, 409);
            }
            Flash::error($message);
            $this->back('/beneficiaries/' . (int)$id);
        }

        $this->beneficiaries->delete((int)$id);
        Audit::deleted('Beneficiaries', 'Beneficiary', (int)$id, $beneficiary['name'],
            array_diff_key($beneficiary, ['aadhaar_hash' => 1]));

        Flash::success('Beneficiary record deleted.');
        $this->redirect('/beneficiaries');
    }

    public function checkDuplicate(): void
    {
        $name     = (string)$this->request->post('name', '');
        $mobile   = $this->request->post('mobile');
        $dob      = $this->dateOrNull('dob');
        $district = $this->request->int('district_id');
        $aadhaar  = $this->request->post('aadhaar_number');
        $ignoreId = $this->request->int('id');

        if (trim($name) === '' || !$district) {
            $this->jsonSuccess(['duplicates' => []], 'Not enough information to check yet.');
        }

        $hash = null;
        if ($aadhaar && preg_match('/^\d{12}$/', preg_replace('/\D/', '', (string)$aadhaar))) {
            $hash = hash_aadhaar((string)$aadhaar);
        }

        $duplicates = $this->beneficiaries->findDuplicates($hash, $name, $mobile, $dob, $district, $ignoreId);

        $this->jsonSuccess([
            'duplicates' => array_map(fn($d) => [
                'id'      => (int)$d['id'],
                'code'    => $d['beneficiary_code'],
                'name'    => $d['name'],
                'gender'  => $d['gender'],
                'age'     => $d['age'],
                'mobile'  => mask_mobile($d['mobile']),
                'village' => $d['village_name'] ?: $d['district_name'],
                'url'     => url('/beneficiaries/' . (int)$d['id']),
            ], $duplicates),
        ], $duplicates ? count($duplicates) . ' possible duplicate(s) found.' : 'No duplicates found.');
    }

    // =================================================================
    // Socio-economic assessment (section 18)
    // =================================================================

    public function assessment(string $id): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->findDetailed((int)$id), 'Beneficiary');
        $this->assertAccess($beneficiary);

        // Existing answers, so the form reopens where it was left.
        $existing = $this->beneficiaries->answerMap((int)$id);

        $this->view('beneficiaries/assessment', [
            'pageTitle'   => 'Beneficiary Assessment',
            'breadcrumbs' => [
                ['label' => 'Beneficiaries', 'url' => '/beneficiaries'],
                ['label' => str_limit($beneficiary['name'], 40), 'url' => '/beneficiaries/' . (int)$id],
                ['label' => 'Assessment'],
            ],
            'beneficiary' => $beneficiary,
            'sections'    => $this->masters->assessmentForm(),
            'answers'     => $existing,
            'assessment'  => $this->beneficiaries->latestAssessment((int)$id),
        ]);
    }

    public function saveAssessment(string $id): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->find((int)$id), 'Beneficiary');
        $this->assertAccess($beneficiary);
        $beneficiaryId = (int)$id;

        $complete = !$this->request->bool('save_draft');
        $answers  = $_POST['answers'] ?? [];
        if (!is_array($answers)) {
            $answers = [];
        }

        // Required questions must be answered before completion.
        if ($complete) {
            $missing = [];
            foreach ($this->masters->assessmentQuestions() as $question) {
                if ((int)$question['is_required'] === 1) {
                    $value = $answers[$question['question_key']] ?? null;
                    if ($value === null || $value === '') {
                        $missing[] = $question['label'];
                    }
                }
            }
            if ($missing) {
                Flash::error('Please answer these required questions before marking the assessment complete: '
                    . implode(', ', array_slice($missing, 0, 6))
                    . (count($missing) > 6 ? ' and ' . (count($missing) - 6) . ' more' : '')
                    . '. You can also save it as a draft.');
                $this->back('/beneficiaries/' . $beneficiaryId . '/assessment');
            }
        }

        $assessmentId = $this->beneficiaries->saveAssessment($beneficiaryId, $answers, Auth::id(), $complete);

        Audit::log('Beneficiaries', 'Modified', [
            'entity_type'  => 'Beneficiary',
            'entity_id'    => $beneficiaryId,
            'entity_label' => $beneficiary['name'],
            'description'  => $complete
                ? 'Socio-economic assessment completed (assessment #' . $assessmentId . ')'
                : 'Socio-economic assessment saved as a draft',
        ]);

        if (!$complete) {
            Flash::info('Assessment saved as a draft.');
            $this->redirect('/beneficiaries/' . $beneficiaryId . '/assessment');
        }

        Flash::success('Assessment completed.');

        // Run matching straight away when configured to (section 20).
        if (Config::setting('auto_match_on_assessment', true)) {
            $engine = new EligibilityEngine();
            $results = $engine->matchAndStore($beneficiaryId, true);
            $positive = count(array_filter($results, fn($r) => $r['eligibility_status'] !== EligibilityEngine::NOT));

            Audit::log('Matching', 'Matched', [
                'entity_type'  => 'Beneficiary',
                'entity_id'    => $beneficiaryId,
                'entity_label' => $beneficiary['name'],
                'description'  => 'Scheme matching run automatically after assessment: '
                                  . $positive . ' applicable scheme(s) identified',
            ]);

            Flash::success($positive . ' potentially applicable scheme(s) identified.');
            $this->redirect('/beneficiaries/' . $beneficiaryId . '/matching');
        }

        $this->redirect('/beneficiaries/' . $beneficiaryId);
    }

    // =================================================================
    // Household roster and field visits
    // =================================================================

    public function saveMember(string $id): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->find((int)$id), 'Beneficiary');
        $this->assertAccess($beneficiary);

        $validator = Validator::make($this->request->all(), [
            'name'     => 'required|string|max:150|no_html',
            'relation' => 'nullable|string|max:60',
            'gender'   => 'nullable|in:Male,Female,Other',
            'age'      => 'nullable|integer|between:0,120',
        ], ['name' => 'Member Name']);

        if ($validator->fails()) {
            Flash::error($validator->firstError() ?? 'Please check the member details.');
            $this->back('/beneficiaries/' . (int)$id);
        }

        $this->db->insert('beneficiary_members', [
            'beneficiary_id'  => (int)$id,
            'name'            => (string)$this->request->post('name'),
            'relation'        => $this->request->post('relation') ?: null,
            'gender'          => $this->request->post('gender') ?: null,
            'age'             => $this->request->int('age'),
            'education_level' => $this->request->post('education_level') ?: null,
            'occupation'      => $this->request->post('occupation') ?: null,
            'is_disabled'     => $this->request->bool('is_disabled') ? 1 : 0,
            'is_student'      => $this->request->bool('is_student') ? 1 : 0,
            'remarks'         => $this->request->post('remarks') ?: null,
        ]);

        Audit::log('Beneficiaries', 'Modified', [
            'entity_type'  => 'Beneficiary',
            'entity_id'    => (int)$id,
            'entity_label' => $beneficiary['name'],
            'description'  => 'Household member added: ' . $this->request->post('name'),
        ]);

        Flash::success('Household member added.');
        $this->back('/beneficiaries/' . (int)$id);
    }

    public function deleteMember(string $id, string $memberId): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->find((int)$id), 'Beneficiary');
        $this->assertAccess($beneficiary);

        $this->db->execute('DELETE FROM beneficiary_members WHERE id = :m AND beneficiary_id = :b',
            ['m' => (int)$memberId, 'b' => (int)$id]);

        Flash::success('Household member removed.');
        $this->back('/beneficiaries/' . (int)$id);
    }

    public function saveVisit(string $id): void
    {
        $beneficiary = $this->findOrFail($this->beneficiaries->find((int)$id), 'Beneficiary');
        $this->assertAccess($beneficiary);

        $validator = Validator::make($this->request->all(), [
            'visit_date' => 'required|date|date_before_today',
            'visit_type' => 'required|in:Registration,Assessment,Document Collection,Follow-up,Verification,Awareness Camp,Other',
        ], ['visit_date' => 'Visit Date', 'visit_type' => 'Visit Type']);

        if ($validator->fails()) {
            Flash::error($validator->firstError() ?? 'Please check the visit details.');
            $this->back('/beneficiaries/' . (int)$id);
        }

        $this->beneficiaries->saveVisit([
            'beneficiary_id' => (int)$id,
            'visit_date'     => $this->dateOrNull('visit_date'),
            'visit_type'     => (string)$this->request->post('visit_type'),
            'field_user_id'  => Auth::id(),
            'state_id'       => (int)$beneficiary['state_id'],
            'district_id'    => (int)$beneficiary['district_id'],
            'block_id'       => $beneficiary['block_id'] ? (int)$beneficiary['block_id'] : null,
            'village_id'     => $beneficiary['village_id'] ? (int)$beneficiary['village_id'] : null,
            'households_covered' => 1,
            'purpose'        => $this->request->post('purpose') ?: null,
            'observations'   => $this->request->post('observations') ?: null,
        ]);

        Flash::success('Field visit recorded.');
        $this->back('/beneficiaries/' . (int)$id);
    }

    // =================================================================
    // Internals
    // =================================================================

    private function beneficiaryFilters(): array
    {
        $stateId    = $this->request->int('state_id');
        $districtId = $this->request->int('district_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);

        return [
            'search'            => $this->request->filled('search') ? (string)$this->request->input('search') : null,
            'state_id'          => $stateId,
            'district_id'       => $districtId,
            'block_id'          => $this->request->int('block_id'),
            'village_id'        => $this->request->int('village_id'),
            'gender'            => $this->inOrNull('gender', ['Male', 'Female', 'Other']),
            'area_type'         => $this->inOrNull('area_type', ['Rural', 'Urban']),
            'record_status'     => $this->inOrNull('record_status',
                                    ['Registered', 'Assessed', 'Matched', 'In Process', 'Benefited', 'Closed', 'Rejected']),
            'assessment_status' => $this->inOrNull('assessment_status', ['Pending', 'In Progress', 'Completed']),
            'field_user_id'     => $this->request->int('field_user_id'),
            'scheme_id'         => $this->request->int('scheme_id'),
            'age_group'         => $this->inOrNull('age_group', ['0-5', '6-14', '15-17', '18-35', '36-59', '60+']),
            'date_from'         => $this->dateOrNull('date_from'),
            'date_to'           => $this->dateOrNull('date_to'),
            'has_documents_pending' => $this->request->bool('has_documents_pending'),
        ];
    }

    private function formData(?array $beneficiary): array
    {
        $stateId    = $beneficiary['state_id'] ?? Auth::stateId();
        $districtId = $beneficiary['district_id'] ?? Auth::districtId();

        return [
            'states'     => $this->geo->stateOptions(),
            'districts'  => $this->geo->districtOptions($stateId ? (int)$stateId : null),
            'blocks'     => $this->geo->blocks($districtId ? (int)$districtId : null),
            'gps'        => $this->geo->gramPanchayats($beneficiary['block_id'] ?? null),
            'villages'   => $this->geo->villages($beneficiary['gram_panchayat_id'] ?? null),
            'fieldUsers' => (new User())->fieldUsers($stateId ? (int)$stateId : null, $districtId ? (int)$districtId : null),
            'members'    => $beneficiary['members'] ?? [],

            // The profile questionnaire is captured during registration, from
            // the same definition the Assessment screen uses.
            'questionSections' => $this->masters->assessmentForm(),
            'answers'          => $beneficiary
                ? $this->beneficiaries->answerMap((int)$beneficiary['id'])
                : [],
        ];
    }

    /** Validate the registration form and build the beneficiary row. */
    private function validateBeneficiary(?int $ignoreId): array
    {
        $rules = [
            'name'              => 'required|string|max:150|no_html',
            'relation_name'     => 'nullable|string|max:150|no_html',
            'relation_type'     => 'nullable|in:Father,Husband,Mother,Guardian,Other',
            'father_name'       => 'nullable|string|max:150|no_html',
            'mother_name'       => 'nullable|string|max:150|no_html',
            'spouse_name'       => 'nullable|string|max:150|no_html',
            'post_office'       => 'nullable|string|max:120|no_html',
            'gender'            => 'required|in:Male,Female,Other',
            'dob'               => 'nullable|date|date_before_today',
            'age'               => 'nullable|integer|between:0,120',
            'mobile'            => 'nullable|mobile',
            'alt_mobile'        => 'nullable|mobile',
            'aadhaar_number'    => 'nullable|aadhaar',
            'state_id'          => 'required|integer|exists:states,id',
            'district_id'       => 'required|integer|exists:districts,id',
            'block_id'          => 'nullable|integer|exists:blocks,id',
            'gram_panchayat_id' => 'nullable|integer|exists:gram_panchayats,id',
            'village_id'        => 'nullable|integer|exists:villages,id',
            'pincode'           => 'nullable|pincode',
            'area_type'         => 'required|in:Rural,Urban',
            'registration_date' => 'required|date|date_before_today',
            'field_user_id'     => 'nullable|integer|exists:users,id',
        ];

        $validator = Validator::make($this->request->all(), $rules, [
            'name'              => 'Beneficiary Name',
            'aadhaar_number'    => 'Aadhaar Number',
            'state_id'          => 'State',
            'district_id'       => 'District',
            'registration_date' => 'Registration Date',
        ]);

        $fallback = $ignoreId ? '/beneficiaries/' . $ignoreId . '/edit' : '/beneficiaries/create';

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), $fallback);
        }

        $stateId    = (int)$this->request->post('state_id');
        $districtId = (int)$this->request->post('district_id');

        // The geography chain must hang together, and must sit inside the
        // signed-in user's own scope.
        $chainErrors = $this->geo->validateChain(
            $stateId, $districtId,
            $this->request->int('block_id'),
            $this->request->int('gram_panchayat_id'),
            $this->request->int('village_id')
        );
        if ($chainErrors) {
            $this->backWithErrors($chainErrors, $fallback);
        }
        Scope::assertAccess($stateId, $districtId, Auth::id());

        // Age and date of birth must agree; the date of birth wins.
        $dob = $this->dateOrNull('dob');
        $age = $this->request->int('age');
        if ($dob) {
            $derived = age_from_dob($dob);
            if ($derived !== null) {
                $age = $derived;
            }
        }
        if ($age === null) {
            $this->backWithErrors(['age' => 'Enter either the date of birth or the age.'], $fallback);
        }

        // Aadhaar: only the last four digits and a peppered hash are stored.
        $aadhaarRaw   = (string)($this->request->post('aadhaar_number') ?? '');
        $aadhaarDigits = preg_replace('/\D/', '', $aadhaarRaw);
        $hasAadhaar   = $this->request->bool('has_aadhaar') || $aadhaarDigits !== '';
        $aadhaarLast4 = $aadhaarDigits !== '' ? substr($aadhaarDigits, -4) : null;
        $aadhaarHash  = $aadhaarDigits !== '' ? hash_aadhaar($aadhaarDigits) : null;

        // Editing without re-entering Aadhaar keeps whatever is already held.
        if ($ignoreId && $aadhaarDigits === '') {
            $existing = $this->beneficiaries->find($ignoreId, ['aadhaar_last4', 'aadhaar_hash', 'has_aadhaar']);
            if ($existing) {
                $aadhaarLast4 = $existing['aadhaar_last4'];
                $aadhaarHash  = $existing['aadhaar_hash'];
                $hasAadhaar   = (int)$existing['has_aadhaar'] === 1 || $hasAadhaar;
            }
        }

        $data = [
            'registration_date' => $this->dateOrNull('registration_date') ?: date('Y-m-d'),
            'name'              => (string)$this->request->post('name'),
            'relation_type'     => $this->request->post('relation_type') ?: 'Father',
            'relation_name'     => $this->request->post('relation_name') ?: null,
            'father_name'       => $this->request->post('father_name') ?: null,
            'mother_name'       => $this->request->post('mother_name') ?: null,
            'spouse_name'       => $this->request->post('spouse_name') ?: null,
            'gender'            => (string)$this->request->post('gender'),
            'dob'               => $dob,
            'age'               => $age,
            'mobile'            => $this->normaliseMobile($this->request->post('mobile')),
            'alt_mobile'        => $this->normaliseMobile($this->request->post('alt_mobile')),
            'aadhaar_last4'     => $aadhaarLast4,
            'aadhaar_hash'      => $aadhaarHash,
            'has_aadhaar'       => $hasAadhaar ? 1 : 0,
            'state_id'          => $stateId,
            'district_id'       => $districtId,
            'block_id'          => $this->request->int('block_id'),
            'gram_panchayat_id' => $this->request->int('gram_panchayat_id'),
            'village_id'        => $this->request->int('village_id'),
            'village_name'      => $this->request->post('village_name') ?: null,
            'post_office'       => $this->request->post('post_office') ?: null,
            'address_line'      => $this->request->post('address_line') ?: null,
            'pincode'           => $this->request->post('pincode') ?: null,
            'area_type'         => (string)$this->request->post('area_type', 'Rural'),
            'consent_given'     => $this->request->bool('consent_given') ? 1 : 0,
            'consent_date'      => $this->request->bool('consent_given') ? date('Y-m-d') : null,
            'field_user_id'     => $this->request->int('field_user_id') ?: Auth::id(),
            'remarks'           => $this->request->post('remarks') ?: null,
        ];

        return $data;
    }

    private function normaliseMobile($value): ?string
    {
        if (!$value) {
            return null;
        }
        $digits = preg_replace('/\D/', '', (string)$value);
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        return $digits !== '' ? $digits : null;
    }

    /**
     * Save the profile questionnaire submitted alongside the registration form.
     *
     * Registration must not be blocked by an unanswered question, so an
     * incomplete questionnaire is stored as a draft and the caller is told how
     * many required answers are still outstanding.
     *
     * @return array{answered:int, complete:bool, missing:int}
     */
    private function saveProfileAnswers(int $beneficiaryId): array
    {
        $answers = $this->request->post('answers');
        if (!is_array($answers)) {
            return ['answered' => 0, 'complete' => false, 'missing' => 0];
        }

        $given = array_filter($answers, function ($value) {
            if (is_array($value)) {
                return array_filter($value, fn($v) => $v !== '' && $v !== null) !== [];
            }
            return $value !== '' && $value !== null;
        });

        if ($given === []) {
            return ['answered' => 0, 'complete' => false, 'missing' => 0];
        }

        $missing = 0;
        foreach ($this->masters->assessmentQuestions() as $question) {
            if ((int)$question['is_required'] !== 1) {
                continue;
            }
            // A required question that was never asked (its condition was not
            // met) cannot be outstanding.
            if (!empty($question['depends_on_key']) && !isset($given[$question['depends_on_key']])) {
                continue;
            }
            if (!isset($given[$question['question_key']])) {
                $missing++;
            }
        }

        $complete = $missing === 0;
        $this->beneficiaries->saveAssessment($beneficiaryId, $answers, Auth::id(), $complete);

        return ['answered' => count($given), 'complete' => $complete, 'missing' => $missing];
    }

    private function groupAnswers(array $answers): array
    {
        $grouped = [];
        foreach ($answers as $answer) {
            $section = $answer['section'] ?: 'Other Information';
            $grouped[$section][] = $answer;
        }
        return $grouped;
    }

    private function assertAccess(array $beneficiary): void
    {
        Scope::assertAccess(
            (int)$beneficiary['state_id'],
            (int)$beneficiary['district_id'],
            $beneficiary['field_user_id'] ? (int)$beneficiary['field_user_id'] : null
        );
    }

    private function describeFilters(array $filters): array
    {
        $out = [];
        if (!empty($filters['search']))      $out['Search'] = $filters['search'];
        if (!empty($filters['state_id']))    $out['State'] = $this->geo->stateName((int)$filters['state_id']);
        if (!empty($filters['district_id'])) $out['District'] = $this->geo->districtName((int)$filters['district_id']);
        if (!empty($filters['gender']))      $out['Gender'] = $filters['gender'];
        if (!empty($filters['record_status'])) $out['Status'] = $filters['record_status'];
        if (!empty($filters['date_from']))   $out['From'] = fmt_date($filters['date_from']);
        if (!empty($filters['date_to']))     $out['To'] = fmt_date($filters['date_to']);
        return $out;
    }
}
