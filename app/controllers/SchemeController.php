<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Flash;
use JanKavach\Core\Paginator;
use JanKavach\Core\Response;
use JanKavach\Core\Scope;
use JanKavach\Core\Validator;
use JanKavach\Libraries\EligibilityEngine;
use JanKavach\Libraries\Exporter;
use JanKavach\Libraries\Notifier;
use JanKavach\Models\Geography;
use JanKavach\Models\Master;
use JanKavach\Models\Scheme;

/**
 * Scheme master: listing, CRUD, the eligibility rule builder, required
 * documents, benefits, global search and exports.
 * (Specification sections 6-15, 34, 35, 38.)
 */
class SchemeController extends Controller
{
    private Scheme $schemes;
    private Master $masters;
    private Geography $geo;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->schemes = new Scheme();
        $this->masters = new Master();
        $this->geo     = new Geography();
    }

    // =================================================================
    // Listing (section 15)
    // =================================================================

    public function index(): void
    {
        $filters = $this->schemeFilters();
        [$column, $dir, $sortKey] = $this->sort(Scheme::SORTS, 'name');

        $page    = Paginator::pageFrom($this->request);
        $perPage = Paginator::perPageFrom($this->request);

        $paginator = $this->schemes->paginate($filters, $page, $perPage, $column, $dir, $_GET ?? []);

        $this->view('schemes/index', [
            'pageTitle'   => 'All Schemes',
            'breadcrumbs' => [['label' => 'Schemes', 'url' => '/schemes'], ['label' => 'All Schemes']],
            'paginator'   => $paginator,
            'filters'     => $filters,
            'sortKey'     => $sortKey,
            'sortDir'     => $dir,
            'options'     => $this->filterOptions($filters),
            'unrankedCount' => $this->unrankedCount(),
        ]);
    }

    public function export(?string $forceFormat = null): void
    {
        // printList() passes the format in; Request snapshots the query string
        // at construction, so mutating $_GET here would be silently ignored.
        $format  = $forceFormat ?? $this->inOrNull('format', ['excel', 'csv', 'pdf']) ?? 'excel';
        $filters = $this->schemeFilters();
        [$column, $dir] = $this->sort(Scheme::SORTS, 'name');

        $rows = $this->schemes->allFiltered($filters, $column, $dir);

        Exporter::send($format, [
            'Scheme Code'        => 'scheme_code',
            'Scheme Name'        => 'name',
            'Short Name'         => 'short_name',
            'National / State'   => 'scheme_level',
            'State'              => fn($r) => $r['state_name'] ?? '-',
            'Theme'              => fn($r) => $r['theme_name'] ?? '-',
            'Beneficiary Types'  => fn($r) => $r['beneficiary_types'] ?? '-',
            'Priority'           => fn($r) => $r['priority_name'] ?? 'Not Ranked',
            'Feasibility'        => fn($r) => $r['feasibility_name'] ?? 'Not Ranked',
            'Service Approach'   => fn($r) => $r['service_approach'] ?? '-',
            'Scheme Status'      => 'scheme_status',
            'Active Enrollment'  => fn($r) => (int)$r['active_enrollment'] === 1 ? 'Yes' : 'No',
            'Record Active'      => fn($r) => (int)$r['is_active'] === 1 ? 'Yes' : 'No',
            'Required Documents' => fn($r) => (int)$r['document_count'],
            'Eligibility Rules'  => fn($r) => (int)$r['rule_count'],
            'Last Verified'      => fn($r) => fmt_date($r['last_verified_date'] ?? null),
            'Source Link'        => 'source_url',
            'Last Updated'       => fn($r) => fmt_datetime($r['updated_at']),
        ], $rows, 'scheme-master', [
            'title'    => 'Scheme Master Report',
            'subtitle' => 'Government social security schemes recorded in Jan Kavach',
            'module'   => 'Schemes',
            'filters'  => $this->describeFilters($filters),
            'orientation' => 'landscape',
        ]);
    }

    public function printList(): void
    {
        $this->export('pdf');
    }

    // =================================================================
    // Scheme detail (section 35)
    // =================================================================

    public function show(string $id): void
    {
        $scheme = $this->findOrFail($this->schemes->findDetailed((int)$id), 'Scheme');
        $this->assertSchemeVisible($scheme);

        $this->schemes->incrementViews((int)$scheme['id']);
        Audit::viewed('Schemes', 'Scheme', (int)$scheme['id'], $scheme['name']);

        $engine = new EligibilityEngine();

        $this->view('schemes/show', [
            'pageTitle'   => $scheme['short_name'] ?: str_limit($scheme['name'], 60),
            'breadcrumbs' => [
                ['label' => 'Schemes', 'url' => '/schemes'],
                ['label' => str_limit($scheme['name'], 48)],
            ],
            'scheme'  => $scheme,
            'related' => $this->schemes->related((int)$scheme['id'], $scheme['theme_id'] ? (int)$scheme['theme_id'] : null, $scheme['state_id'] ? (int)$scheme['state_id'] : null),
            'engine'  => $engine,
            'usage'   => $this->schemes->usageCount((int)$scheme['id']),
        ]);
    }

    public function printOne(string $id): void
    {
        $scheme = $this->findOrFail($this->schemes->findDetailed((int)$id), 'Scheme');
        $this->assertSchemeVisible($scheme);

        $this->bare('schemes/print', [
            'pageTitle' => $scheme['name'],
            'scheme'    => $scheme,
            'engine'    => new EligibilityEngine(),
            'autoPrint' => true,
        ], 'layouts/print');
    }

    // =================================================================
    // Create / edit
    // =================================================================

    public function create(): void
    {
        $this->view('schemes/form', array_merge($this->formData(null), [
            'pageTitle'   => 'Add Scheme',
            'breadcrumbs' => [['label' => 'Schemes', 'url' => '/schemes'], ['label' => 'Add Scheme']],
            'scheme'      => null,
            'suggestedCode' => $this->schemes->nextSchemeCode(),
        ]));
    }

    public function edit(string $id): void
    {
        $scheme = $this->findOrFail($this->schemes->findDetailed((int)$id), 'Scheme');
        $this->assertSchemeEditable($scheme);

        $this->view('schemes/form', array_merge($this->formData($scheme), [
            'pageTitle'   => 'Edit Scheme',
            'breadcrumbs' => [
                ['label' => 'Schemes', 'url' => '/schemes'],
                ['label' => str_limit($scheme['name'], 40), 'url' => '/schemes/' . (int)$scheme['id']],
                ['label' => 'Edit'],
            ],
            'scheme' => $scheme,
        ]));
    }

    public function store(): void
    {
        $data = $this->validateScheme(null);

        // Duplicate guard (section 38) - unless the user has confirmed.
        if (!$this->request->bool('confirm_duplicate')) {
            $duplicates = $this->schemes->findDuplicates(
                $data['name'], $data['scheme_code'] ?? null, $data['scheme_level'],
                $data['state_id'] ?? null
            );
            if ($duplicates) {
                Flash::warning('A similar scheme already exists: '
                    . implode('; ', array_map(fn($d) => $d['scheme_code'] . ' - ' . $d['name'], $duplicates))
                    . '. Tick "Save anyway" to create this scheme regardless.');
                $this->backWithErrors(['name' => 'Possible duplicate scheme.'], '/schemes/create');
            }
        }

        $schemeId = $this->db->transaction(function () use ($data) {
            $data['scheme_code'] = $data['scheme_code'] ?: $this->schemes->nextSchemeCode();
            $data['slug']        = $this->schemes->uniqueSlug($data['name']);

            $id = $this->schemes->create($data);
            $this->syncFromRequest($id);
            return $id;
        });

        Audit::created('Schemes', 'Scheme', $schemeId, $data['name'], $data);
        Notifier::send(Notifier::SCHEME_ADDED, 'New scheme added: ' . $data['name'], [
            'message'     => 'A new scheme has been added to the scheme master.',
            'link'        => '/schemes/' . $schemeId,
            'entity_type' => 'Scheme',
            'entity_id'   => $schemeId,
        ]);

        Flash::success('Scheme created. Next, configure its eligibility rules so beneficiary matching can use it.');
        $this->redirect('/schemes/' . $schemeId . '/rules');
    }

    public function update(string $id): void
    {
        $scheme = $this->findOrFail($this->schemes->findDetailed((int)$id), 'Scheme');
        $this->assertSchemeEditable($scheme);

        $schemeId = (int)$scheme['id'];
        $data = $this->validateScheme($schemeId);

        $before = $this->schemes->find($schemeId);

        $this->db->transaction(function () use ($schemeId, $data) {
            $data['slug'] = $this->schemes->uniqueSlug($data['name'], $schemeId);
            $this->schemes->update($schemeId, $data);
            $this->syncFromRequest($schemeId);
        });

        $after = $this->schemes->find($schemeId);
        Audit::modified('Schemes', 'Scheme', $schemeId, $data['name'], $before ?: [], $after ?: []);

        Notifier::send(Notifier::SCHEME_UPDATED, 'Scheme updated: ' . $data['name'], [
            'message'     => 'Scheme details were updated. Review the eligibility rules if the criteria changed.',
            'link'        => '/schemes/' . $schemeId,
            'entity_type' => 'Scheme',
            'entity_id'   => $schemeId,
            'priority'    => 'Low',
        ]);

        Flash::success('Scheme updated.');
        $this->redirect('/schemes/' . $schemeId);
    }

    public function toggleStatus(string $id): void
    {
        $scheme = $this->findOrFail($this->schemes->find((int)$id), 'Scheme');
        $this->assertSchemeEditable($scheme);

        $new = $this->schemes->toggleActive((int)$id);

        Audit::log('Schemes', 'Modified', [
            'entity_type'  => 'Scheme',
            'entity_id'    => (int)$id,
            'entity_label' => $scheme['name'],
            'description'  => $new === 1 ? 'Scheme activated' : 'Scheme deactivated',
            'old_values'   => ['is_active' => $scheme['is_active']],
            'new_values'   => ['is_active' => $new],
        ]);

        if ($this->request->isAjax()) {
            $this->jsonSuccess(['is_active' => $new], $new === 1 ? 'Scheme activated.' : 'Scheme deactivated.');
        }
        Flash::success($new === 1 ? 'Scheme activated.' : 'Scheme deactivated.');
        $this->back('/schemes');
    }

    public function copy(string $id): void
    {
        $scheme = $this->findOrFail($this->schemes->find((int)$id), 'Scheme');
        $this->assertSchemeVisible($scheme);

        $newId = $this->schemes->duplicate((int)$id, Auth::id());
        if (!$newId) {
            Flash::error('The scheme could not be copied.');
            $this->back('/schemes');
        }

        Audit::created('Schemes', 'Scheme', $newId, 'Copy of ' . $scheme['name'], [],
            'Duplicated scheme #' . (int)$id);

        Flash::success('Scheme copied. The copy is inactive until you review and activate it.');
        $this->redirect('/schemes/' . $newId . '/edit');
    }

    public function destroy(string $id): void
    {
        $scheme = $this->findOrFail($this->schemes->find((int)$id), 'Scheme');
        $this->assertSchemeEditable($scheme);

        $usage = $this->schemes->usageCount((int)$id);
        $linked = $usage['matches'] + $usage['applications'] + $usage['benefits'];

        // Field data must never be orphaned by an administrative delete.
        if ($linked > 0) {
            $message = sprintf(
                'This scheme cannot be deleted: %d beneficiary match(es), %d application(s) and %d benefit record(s) reference it. Deactivate it instead.',
                $usage['matches'], $usage['applications'], $usage['benefits']);

            if ($this->request->isAjax()) {
                $this->jsonError($message, null, 409);
            }
            Flash::error($message);
            $this->back('/schemes/' . (int)$id);
        }

        $this->schemes->delete((int)$id);
        Audit::deleted('Schemes', 'Scheme', (int)$id, $scheme['name'], $scheme);

        Flash::success('Scheme deleted.');
        $this->redirect('/schemes');
    }

    /** AJAX duplicate check used while the scheme name is being typed. */
    public function checkDuplicate(): void
    {
        $name  = (string)$this->request->post('name', '');
        $code  = $this->request->post('scheme_code');
        $level = (string)$this->request->post('scheme_level', 'National');
        $stateId = $this->request->int('state_id');
        $ignoreId = $this->request->int('id');

        if (trim($name) === '') {
            $this->jsonSuccess(['duplicates' => []], 'Nothing to check.');
        }

        $duplicates = $this->schemes->findDuplicates($name, $code, $level, $stateId, $ignoreId);

        $this->jsonSuccess([
            'duplicates' => array_map(fn($d) => [
                'id'    => (int)$d['id'],
                'code'  => $d['scheme_code'],
                'name'  => $d['name'],
                'level' => $d['scheme_level'],
                'state' => $d['state_name'],
                'url'   => url('/schemes/' . (int)$d['id']),
            ], $duplicates),
        ], $duplicates ? count($duplicates) . ' possible duplicate(s) found.' : 'No duplicates found.');
    }

    // =================================================================
    // Eligibility rule builder (section 9)
    // =================================================================

    public function rules(string $id): void
    {
        $scheme = $this->findOrFail($this->schemes->findDetailed((int)$id), 'Scheme');
        $this->assertSchemeEditable($scheme);

        $engine = new EligibilityEngine();

        $this->view('schemes/rules', [
            'pageTitle'   => 'Eligibility Rules',
            'breadcrumbs' => [
                ['label' => 'Schemes', 'url' => '/schemes'],
                ['label' => str_limit($scheme['name'], 40), 'url' => '/schemes/' . (int)$scheme['id']],
                ['label' => 'Eligibility Rules'],
            ],
            'scheme'      => $scheme,
            'rules'       => $scheme['rules'],
            'attributes'  => $engine->attributesByGroup(),
            'operators'   => $engine->operators(),
            'lookups'     => $this->lookupPayload($engine),
            'states'      => $this->geo->stateOptions(true, false),
            'districts'   => $this->geo->allDistrictsInScope(),
        ]);
    }

    public function saveRules(string $id): void
    {
        $scheme = $this->findOrFail($this->schemes->findDetailed((int)$id), 'Scheme');
        $this->assertSchemeEditable($scheme);
        $schemeId = (int)$scheme['id'];

        $posted = $this->request->array('rules');
        $engine = new EligibilityEngine();
        $known  = $engine->attributes();
        $validOperators = array_keys($engine->operators());

        $rules = [];
        $errors = [];

        foreach ($posted as $index => $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $attribute = trim((string)($rule['attribute_key'] ?? ''));
            if ($attribute === '') {
                continue;   // an empty template row
            }
            if (!isset($known[$attribute])) {
                $errors[] = sprintf('Row %d: "%s" is not a recognised eligibility attribute.', $index + 1, $attribute);
                continue;
            }
            $operator = trim((string)($rule['operator'] ?? ''));
            if (!in_array($operator, $validOperators, true)) {
                $errors[] = sprintf('Row %d: "%s" is not a valid operator.', $index + 1, $operator);
                continue;
            }

            // The operator dictates which value fields must be present.
            $needsOne  = !in_array($operator, ['is_set', 'not_set'], true);
            $needsTwo  = $operator === 'between';
            $needsList = in_array($operator, ['any_of', 'not_in'], true);

            $value1 = trim((string)($rule['value1'] ?? ''));
            $value2 = trim((string)($rule['value2'] ?? ''));
            $list   = $rule['value_list'] ?? '';
            if (is_array($list)) {
                $list = implode(',', array_map('trim', $list));
            }
            $list = trim((string)$list);

            if ($needsList && $list === '') {
                $errors[] = sprintf('Row %d (%s): select at least one value for "%s".',
                    $index + 1, $known[$attribute]['label'], $engine->operators()[$operator]);
                continue;
            }
            if (!$needsList && $needsOne && $value1 === '') {
                $errors[] = sprintf('Row %d (%s): a comparison value is required.', $index + 1, $known[$attribute]['label']);
                continue;
            }
            if ($needsTwo && $value2 === '') {
                $errors[] = sprintf('Row %d (%s): "Between" needs both an upper and a lower value.',
                    $index + 1, $known[$attribute]['label']);
                continue;
            }
            if ($needsTwo && is_numeric($value1) && is_numeric($value2) && (float)$value2 < (float)$value1) {
                $errors[] = sprintf('Row %d (%s): the upper value must not be below the lower value.',
                    $index + 1, $known[$attribute]['label']);
                continue;
            }

            $rules[] = [
                'rule_group'    => max(1, (int)($rule['rule_group'] ?? 1)),
                'attribute_key' => $attribute,
                'operator'      => $operator,
                'value1'        => $needsList ? null : ($value1 !== '' ? $value1 : null),
                'value2'        => $needsTwo ? $value2 : null,
                'value_list'    => $needsList ? $list : null,
                'weight'        => min(10, max(1, (int)($rule['weight'] ?? 1))),
                'is_mandatory'  => !empty($rule['is_mandatory']),
                'description'   => trim((string)($rule['description'] ?? '')),
            ];
        }

        if ($errors) {
            Flash::error('The rules were not saved: ' . implode(' ', array_slice($errors, 0, 5)));
            $this->back('/schemes/' . $schemeId . '/rules');
        }

        $before = $scheme['rules'];
        $saved  = $this->schemes->syncRules($schemeId, $rules, Auth::id());

        Audit::log('Schemes', 'Modified', [
            'entity_type'  => 'Scheme',
            'entity_id'    => $schemeId,
            'entity_label' => $scheme['name'],
            'description'  => sprintf('Eligibility rules updated: %d rule(s) saved (previously %d).', $saved, count($before)),
            'old_values'   => ['rule_count' => count($before)],
            'new_values'   => ['rule_count' => $saved],
        ]);

        Flash::success($saved === 0
            ? 'All eligibility rules were removed. This scheme will now report "Needs Verification" for every beneficiary.'
            : $saved . ' eligibility rule(s) saved. Re-run scheme matching to apply them to existing beneficiaries.');

        $this->redirect('/schemes/' . $schemeId . '/rules');
    }

    public function deleteRule(string $id, string $ruleId): void
    {
        $scheme = $this->findOrFail($this->schemes->find((int)$id), 'Scheme');
        $this->assertSchemeEditable($scheme);

        $deleted = $this->db->execute(
            'DELETE FROM scheme_eligibility_rules WHERE id = :r AND scheme_id = :s',
            ['r' => (int)$ruleId, 's' => (int)$id]
        );

        if ($deleted) {
            Audit::log('Schemes', 'Modified', [
                'entity_type'  => 'Scheme',
                'entity_id'    => (int)$id,
                'entity_label' => $scheme['name'],
                'description'  => 'Deleted eligibility rule #' . (int)$ruleId,
            ]);
        }

        if ($this->request->isAjax()) {
            $this->jsonSuccess(null, $deleted ? 'Rule deleted.' : 'Rule not found.');
        }
        Flash::success($deleted ? 'Rule deleted.' : 'Rule not found.');
        $this->back('/schemes/' . (int)$id . '/rules');
    }

    /**
     * Try the rules against a hypothetical profile, so an administrator can
     * see the effect before saving.
     */
    public function testRules(string $id): void
    {
        $scheme = $this->findOrFail($this->schemes->find((int)$id), 'Scheme');
        $this->assertSchemeVisible($scheme);

        $engine  = new EligibilityEngine();
        $profile = [];

        foreach ($this->request->array('profile') as $key => $value) {
            if ($value !== '' && $value !== null) {
                $profile[$key] = $value;
            }
        }
        // The posted array arrives keyed by attribute when sent as profile[age]=62.
        $raw = $_POST['profile'] ?? [];
        if (is_array($raw)) {
            foreach ($raw as $key => $value) {
                if (is_string($key) && $value !== '' && $value !== null && !is_array($value)) {
                    $profile[$key] = $value;
                }
            }
        }

        $result = $engine->evaluateScheme($engine->applyDerived($profile), (int)$id);

        $this->jsonSuccess([
            'match_percent'      => $result['match_percent'],
            'eligibility_status' => $result['eligibility_status'],
            'reason_text'        => $result['reason_text'],
            'passed'             => $result['passed_reasons'],
            'failed'             => $result['failed_reasons'],
            'unknown'            => $result['unknown_reasons'],
            'counts'             => [
                'total'   => $result['rules_total'],
                'passed'  => $result['rules_passed'],
                'failed'  => $result['rules_failed'],
                'unknown' => $result['rules_unknown'],
            ],
        ], 'Rules evaluated.');
    }

    // =================================================================
    // Required documents (section 10) and benefits (section 11)
    // =================================================================

    public function documents(string $id): void
    {
        $scheme = $this->findOrFail($this->schemes->findDetailed((int)$id), 'Scheme');
        $this->assertSchemeEditable($scheme);

        $this->view('schemes/documents', [
            'pageTitle'   => 'Required Documents',
            'breadcrumbs' => [
                ['label' => 'Schemes', 'url' => '/schemes'],
                ['label' => str_limit($scheme['name'], 40), 'url' => '/schemes/' . (int)$scheme['id']],
                ['label' => 'Required Documents'],
            ],
            'scheme'        => $scheme,
            'documentTypes' => $this->masters->documentTypes(),
            'selected'      => array_column($scheme['documents'], null, 'document_type_id'),
        ]);
    }

    public function saveDocuments(string $id): void
    {
        $scheme = $this->findOrFail($this->schemes->find((int)$id), 'Scheme');
        $this->assertSchemeEditable($scheme);
        $schemeId = (int)$id;

        $selected  = array_map('intval', $this->request->array('document_type_id'));
        $mandatory = array_map('intval', $this->request->array('is_mandatory'));
        $validity  = array_map('intval', $this->request->array('validity_required'));
        $remarks   = $_POST['remarks'] ?? [];

        $documents = [];
        foreach ($selected as $typeId) {
            if ($typeId <= 0) {
                continue;
            }
            $documents[] = [
                'document_type_id'  => $typeId,
                // Anything explicitly ticked is mandatory; the rest optional.
                'is_mandatory'      => in_array($typeId, $mandatory, true) ? 1 : 0,
                'validity_required' => in_array($typeId, $validity, true) ? 1 : 0,
                'remarks'           => isset($remarks[$typeId]) ? mb_substr(trim((string)$remarks[$typeId]), 0, 500) : null,
            ];
        }

        $before = count($this->schemes->documents($schemeId));
        $this->schemes->syncDocuments($schemeId, $documents);

        Audit::log('Schemes', 'Modified', [
            'entity_type'  => 'Scheme',
            'entity_id'    => $schemeId,
            'entity_label' => $scheme['name'],
            'description'  => sprintf('Required documents updated: %d document(s) linked (previously %d).',
                                      count($documents), $before),
        ]);

        Flash::success(count($documents) . ' document requirement(s) saved.');
        $this->redirect('/schemes/' . $schemeId . '/documents');
    }

    public function saveBenefits(string $id): void
    {
        $scheme = $this->findOrFail($this->schemes->find((int)$id), 'Scheme');
        $this->assertSchemeEditable($scheme);
        $schemeId = (int)$id;

        $raw = $_POST['benefits'] ?? [];
        $benefits = [];
        if (is_array($raw)) {
            foreach ($raw as $benefit) {
                if (!is_array($benefit)) {
                    continue;
                }
                $benefits[] = [
                    'benefit_type' => isset($benefit['benefit_type']) ? mb_substr(trim((string)$benefit['benefit_type']), 0, 120) : null,
                    'description'  => isset($benefit['description']) ? trim((string)$benefit['description']) : null,
                    'amount'       => isset($benefit['amount']) && $benefit['amount'] !== '' ? (float)$benefit['amount'] : null,
                    'amount_text'  => isset($benefit['amount_text']) ? mb_substr(trim((string)$benefit['amount_text']), 0, 255) : null,
                    'mode'         => in_array($benefit['mode'] ?? '', ['One-time', 'Recurring', 'Both', 'In-kind', 'Service'], true)
                                      ? $benefit['mode'] : null,
                    'frequency'    => isset($benefit['frequency']) ? mb_substr(trim((string)$benefit['frequency']), 0, 80) : null,
                    'conditions'   => isset($benefit['conditions']) ? trim((string)$benefit['conditions']) : null,
                ];
            }
        }

        $this->schemes->syncBenefits($schemeId, $benefits);

        Audit::log('Schemes', 'Modified', [
            'entity_type'  => 'Scheme',
            'entity_id'    => $schemeId,
            'entity_label' => $scheme['name'],
            'description'  => count($benefits) . ' benefit record(s) saved',
        ]);

        Flash::success('Benefits saved.');
        $this->redirect('/schemes/' . $schemeId);
    }

    // =================================================================
    // Global search (section 34)
    // =================================================================

    public function globalSearch(): void
    {
        $term = trim((string)$this->request->query('q', ''));
        $filters = [
            'state_id'            => $this->request->int('state_id'),
            'theme_id'            => $this->request->int('theme_id'),
            'beneficiary_type_id' => $this->request->int('beneficiary_type_id'),
        ];
        [$filters['state_id']] = Scope::constrainFilters($filters['state_id'], null);

        $results = ($term !== '' || array_filter($filters))
            ? $this->schemes->search($term, $filters, 60)
            : [];

        if ($this->request->isAjax()) {
            $this->jsonSuccess([
                'count'   => count($results),
                'results' => array_map(fn($r) => [
                    'id'    => (int)$r['id'],
                    'name'  => $r['name'],
                    'short' => $r['short_name'],
                    'level' => $r['scheme_level'],
                    'state' => $r['state_name'],
                    'theme' => $r['theme_name'],
                    'url'   => url('/schemes/' . (int)$r['id']),
                ], array_slice($results, 0, 12)),
            ], count($results) . ' scheme(s) found.');
        }

        $this->view('schemes/search', [
            'pageTitle'   => 'Search Government Schemes',
            'breadcrumbs' => [['label' => 'Search Schemes']],
            'term'        => $term,
            'results'     => $results,
            'filters'     => $filters,
            'states'      => $this->geo->stateOptions(),
            'themes'      => $this->masters->optionsFor('scheme_themes'),
            'beneficiaryTypes' => $this->masters->optionsFor('beneficiary_types'),
        ]);
    }

    // =================================================================
    // Internals
    // =================================================================

    private function schemeFilters(): array
    {
        $stateId = $this->request->int('state_id');
        [$stateId] = Scope::constrainFilters($stateId, null);

        return [
            'search'              => $this->request->filled('search') ? (string)$this->request->input('search') : null,
            'state_id'            => $stateId,
            'scheme_level'        => $this->inOrNull('scheme_level', ['National', 'State', 'National+State']),
            'theme_id'            => $this->request->int('theme_id'),
            'beneficiary_type_id' => $this->request->int('beneficiary_type_id'),
            'target_group_id'     => $this->request->int('target_group_id'),
            'priority_id'         => $this->request->int('priority_id'),
            'feasibility_id'      => $this->request->int('feasibility_id'),
            'service_approach_id' => $this->request->int('service_approach_id'),
            'scheme_status'       => $this->inOrNull('scheme_status', ['Active', 'Dormant', 'Discontinued']),
            'is_active'           => $this->request->filled('is_active') ? $this->request->int('is_active') : null,
            'active_enrollment'   => $this->request->filled('active_enrollment') ? $this->request->int('active_enrollment') : null,
            'has_rules'           => $this->inOrNull('has_rules', ['yes', 'no']),
        ];
    }

    private function filterOptions(array $filters): array
    {
        return [
            'states'             => $this->geo->stateOptions(),
            'themes'             => $this->masters->optionsFor('scheme_themes'),
            'beneficiary_types'  => $this->masters->optionsFor('beneficiary_types'),
            'target_groups'      => $this->masters->optionsFor('target_groups'),
            'priorities'         => $this->masters->optionsFor('priority_levels', 'name', 'rank_value'),
            'feasibility'        => $this->masters->optionsFor('feasibility_levels', 'name', 'rank_value'),
            'service_approaches' => $this->masters->optionsFor('service_approaches'),
        ];
    }

    /** Everything the create/edit form needs. */
    private function formData(?array $scheme): array
    {
        return [
            'options'       => array_merge($this->filterOptions([]), [
                'document_types' => $this->masters->documentTypes(),
                'agencies'       => $this->masters->optionsFor('implementing_agencies', 'name', 'name'),
            ]),
            'allStates'     => $this->geo->stateOptions(true, false),
            'selectedThemes'      => $scheme ? array_column($scheme['themes'], 'id') : [],
            'selectedTypes'       => $scheme ? array_column($scheme['beneficiary_types'], 'id') : [],
            'selectedTargets'     => $scheme ? array_column($scheme['target_groups'], 'id') : [],
            'selectedStates'      => $scheme ? array_column($scheme['states'], 'id') : [],
        ];
    }

    /** Validate and normalise the scheme form. */
    private function validateScheme(?int $ignoreId): array
    {
        $rules = [
            'name'               => 'required|string|max:300|no_html',
            'short_name'         => 'nullable|string|max:80',
            'scheme_code'        => 'nullable|string|max:30|unique:schemes,scheme_code,' . ($ignoreId ?? 0),
            'scheme_level'       => 'required|in:National,State,National+State',
            'state_id'           => 'nullable|integer|exists:states,id',
            'theme_id'           => 'nullable|integer|exists:scheme_themes,id',
            'service_approach_id'=> 'nullable|integer|exists:service_approaches,id',
            'priority_id'        => 'nullable|integer|exists:priority_levels,id',
            'feasibility_id'     => 'nullable|integer|exists:feasibility_levels,id',
            'ministry_id'        => 'nullable|integer|exists:implementing_agencies,id',
            'department_id'      => 'nullable|integer|exists:implementing_agencies,id',
            'scheme_status'      => 'required|in:Active,Dormant,Discontinued',
            'launch_year'        => 'nullable|integer|between:1900,2100',
            'benefit_amount'     => 'nullable|numeric|min:0|max:999999999999',
            'benefit_mode'       => 'nullable|in:One-time,Recurring,Both,In-kind,Service',
            'application_mode'   => 'nullable|in:Online,Offline,Both,Not Applicable',
            'portal_url'         => 'nullable|url|max:500',
            'source_url'         => 'nullable|url|max:500',
            'last_verified_date' => 'nullable|date',
            'helpline_number'    => 'nullable|string|max:80',
            'verified_by'        => 'nullable|string|max:150',
        ];

        $validator = Validator::make($this->request->all(), $rules, [
            'name'         => 'Scheme Name',
            'scheme_level' => 'Scheme Type',
            'state_id'     => 'State',
            'theme_id'     => 'Theme',
        ]);

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(),
                $ignoreId ? '/schemes/' . $ignoreId . '/edit' : '/schemes/create');
        }

        $level   = (string)$this->request->post('scheme_level');
        $stateId = $this->request->int('state_id');

        // A State scheme must name its State; a National one must not.
        if (in_array($level, ['State', 'National+State'], true) && !$stateId) {
            $this->backWithErrors(['state_id' => 'A State-specific scheme must have a State selected.'],
                $ignoreId ? '/schemes/' . $ignoreId . '/edit' : '/schemes/create');
        }
        if ($level === 'National') {
            $stateId = null;
        }

        // A State Administrator may only create schemes for their own State.
        if (!Auth::isSuperAdmin() && $level !== 'National' && Auth::stateId() && $stateId !== Auth::stateId()) {
            $this->backWithErrors(['state_id' => 'You can only manage schemes for your own State.'],
                $ignoreId ? '/schemes/' . $ignoreId . '/edit' : '/schemes/create');
        }

        return [
            'name'               => (string)$this->request->post('name'),
            'short_name'         => $this->request->post('short_name') ?: null,
            'scheme_code'        => $this->request->post('scheme_code') ?: null,
            'description'        => $this->request->post('description') ?: null,
            'scheme_level'       => $level,
            'state_id'           => $stateId,
            'theme_id'           => $this->request->int('theme_id'),
            'service_approach_id'=> $this->request->int('service_approach_id'),
            'priority_id'        => $this->request->int('priority_id'),
            'feasibility_id'     => $this->request->int('feasibility_id'),
            'ministry_id'        => $this->request->int('ministry_id'),
            'department_id'      => $this->request->int('department_id'),
            'ministry_name'      => $this->request->post('ministry_name') ?: null,
            'department_name'    => $this->request->post('department_name') ?: null,
            'scheme_status'      => (string)$this->request->post('scheme_status', 'Active'),
            'active_enrollment'  => $this->request->bool('active_enrollment') ? 1 : 0,
            'launch_year'        => $this->request->int('launch_year'),
            'target_group_summary' => $this->request->post('target_group_summary') ?: null,
            'eligibility_summary'  => $this->request->post('eligibility_summary') ?: null,
            'benefit_type'       => $this->request->post('benefit_type') ?: null,
            'benefit_description'=> $this->request->post('benefit_description') ?: null,
            'benefit_amount'     => $this->request->filled('benefit_amount') ? (float)$this->request->post('benefit_amount') : null,
            'benefit_amount_text'=> $this->request->post('benefit_amount_text') ?: null,
            'benefit_mode'       => $this->request->post('benefit_mode') ?: null,
            'benefit_frequency'  => $this->request->post('benefit_frequency') ?: null,
            'benefit_conditions' => $this->request->post('benefit_conditions') ?: null,
            'application_mode'   => $this->request->post('application_mode') ?: 'Offline',
            'application_process'=> $this->request->post('application_process') ?: null,
            'application_instructions' => $this->request->post('application_instructions') ?: null,
            'portal_url'         => $this->request->post('portal_url') ?: null,
            'csc_available'      => $this->request->bool('csc_available') ? 1 : 0,
            'physical_office'    => $this->request->post('physical_office') ?: null,
            'helpline_number'    => $this->request->post('helpline_number') ?: null,
            'source_url'         => $this->request->post('source_url') ?: null,
            'source_website'     => $this->deriveWebsite($this->request->post('source_url')),
            'last_verified_date' => $this->dateOrNull('last_verified_date'),
            'verified_by'        => $this->request->post('verified_by') ?: null,
            'keywords'           => $this->request->post('keywords') ?: null,
            'is_active'          => $this->request->bool('is_active', true) ? 1 : 0,
        ];
    }

    private function syncFromRequest(int $schemeId): void
    {
        $this->schemes->syncRelations(
            $schemeId,
            array_map('intval', $this->request->array('theme_ids')),
            array_map('intval', $this->request->array('beneficiary_type_ids')),
            array_map('intval', $this->request->array('target_group_ids')),
            array_map('intval', $this->request->array('state_ids'))
        );
    }

    private function deriveWebsite($url): ?string
    {
        if (!$url) {
            return null;
        }
        $host = parse_url((string)$url, PHP_URL_HOST);
        return $host ? mb_substr($host, 0, 200) : null;
    }

    /** Lookup option lists, so the rule builder can render value pickers. */
    private function lookupPayload(EligibilityEngine $engine): array
    {
        $payload = [];
        foreach ($engine->attributes() as $key => $attr) {
            if (!empty($attr['lookup_key']) && !isset($payload[$attr['lookup_key']])) {
                $payload[$attr['lookup_key']] = $this->masters->lookup($attr['lookup_key']);
            }
        }
        return $payload;
    }

    private function unrankedCount(): int
    {
        return (int)$this->db->scalar(
            'SELECT COUNT(*) FROM schemes WHERE deleted_at IS NULL AND priority_id IS NULL'
        );
    }

    /** 403 unless the scheme is inside the user's scheme scope. */
    private function assertSchemeVisible(array $scheme): void
    {
        if (Auth::dataScope() === 'all') {
            return;
        }
        if ($scheme['scheme_level'] === 'National') {
            return;
        }
        $stateId = $scheme['state_id'] !== null ? (int)$scheme['state_id'] : null;
        if ($stateId !== null && $stateId === Auth::stateId()) {
            return;
        }
        // Also allow a scheme explicitly mapped to the user's State.
        $mapped = (int)$this->db->scalar(
            'SELECT COUNT(*) FROM scheme_states WHERE scheme_id = :s AND state_id = :st',
            ['s' => (int)$scheme['id'], 'st' => Auth::stateId() ?? 0]
        );
        if ($mapped > 0) {
            return;
        }
        Response::abort(403, 'This scheme belongs to a different State.');
    }

    private function assertSchemeEditable(array $scheme): void
    {
        $this->assertSchemeVisible($scheme);

        // A State Administrator must not edit the national catalogue.
        if (!Auth::isSuperAdmin() && $scheme['scheme_level'] === 'National') {
            Response::abort(403, 'National schemes can only be edited by a Super Administrator.');
        }
    }

    private function describeFilters(array $filters): array
    {
        $out = [];
        if (!empty($filters['search']))       $out['Search'] = $filters['search'];
        if (!empty($filters['state_id']))     $out['State'] = $this->geo->stateName((int)$filters['state_id']);
        if (!empty($filters['scheme_level'])) $out['Level'] = $filters['scheme_level'];
        if (!empty($filters['theme_id'])) {
            $out['Theme'] = $this->db->scalar('SELECT name FROM scheme_themes WHERE id = :id', ['id' => (int)$filters['theme_id']]);
        }
        if (!empty($filters['scheme_status'])) $out['Status'] = $filters['scheme_status'];
        return $out;
    }
}
