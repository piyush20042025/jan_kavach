<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Audit;
use JanKavach\Core\Auth;
use JanKavach\Core\Config;
use JanKavach\Core\Controller;
use JanKavach\Core\Flash;
use JanKavach\Core\Validator;
use JanKavach\Libraries\EligibilityEngine;
use JanKavach\Models\Master;

/**
 * Application settings, the assessment questionnaire and the eligibility
 * attribute vocabulary.
 *
 * These three screens are what make the platform extensible without code:
 * a new assessment question or eligibility attribute added here immediately
 * appears on the field form and in the rule builder.
 */
class SettingsController extends Controller
{
    public function index(): void
    {
        $settings = $this->db->select(
            'SELECT * FROM system_settings ORDER BY setting_group, sort_order, setting_key');

        $grouped = [];
        foreach ($settings as $setting) {
            $grouped[$setting['setting_group']][] = $setting;
        }

        $this->view('settings/index', [
            'pageTitle'   => 'Settings',
            'breadcrumbs' => [['label' => 'Settings']],
            'grouped'     => $grouped,
            'groupLabels' => [
                'general'      => 'General',
                'matching'     => 'Scheme Matching',
                'followup'     => 'Follow-ups',
                'security'     => 'Security',
                'upload'       => 'File Uploads',
                'notification' => 'Notifications',
                'report'       => 'Reports & Exports',
            ],
        ]);
    }

    public function update(): void
    {
        $posted = $_POST['settings'] ?? [];
        if (!is_array($posted)) {
            Flash::error('Nothing was submitted.');
            $this->back('/settings');
        }

        $definitions = [];
        foreach ($this->db->select('SELECT * FROM system_settings WHERE is_editable = 1') as $row) {
            $definitions[$row['setting_key']] = $row;
        }

        $changed = [];
        $errors  = [];

        foreach ($posted as $key => $value) {
            if (!isset($definitions[$key])) {
                continue;   // ignore anything not defined as editable
            }
            $definition = $definitions[$key];
            $value = is_array($value) ? json_encode($value) : trim((string)$value);

            // Type-check before storing, so a bad value cannot break a screen.
            if ($definition['value_type'] === 'int') {
                if ($value === '' || !is_numeric($value)) {
                    $errors[] = $definition['label'] . ' must be a number.';
                    continue;
                }
                $value = (string)(int)$value;

                // Thresholds must stay inside a sensible range and in order.
                if (in_array($key, ['eligible_threshold', 'potential_threshold'], true)
                    && ((int)$value < 0 || (int)$value > 100)) {
                    $errors[] = $definition['label'] . ' must be between 0 and 100.';
                    continue;
                }
            }
            if ($definition['value_type'] === 'bool') {
                $value = in_array(strtolower($value), ['1', 'yes', 'true', 'on'], true) ? '1' : '0';
            }
            if ($definition['value_type'] === 'json') {
                json_decode($value);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $errors[] = $definition['label'] . ' must be valid JSON.';
                    continue;
                }
            }

            if ((string)$definition['setting_value'] !== $value) {
                $changed[$key] = ['from' => $definition['setting_value'], 'to' => $value];
                $this->db->update('system_settings',
                    ['setting_value' => $value, 'updated_by' => Auth::id()],
                    ['setting_key' => $key]);
            }
        }

        // The eligible threshold must not sit below the potential one.
        $eligible  = (int)($this->db->scalar("SELECT setting_value FROM system_settings WHERE setting_key = 'eligible_threshold'") ?? 90);
        $potential = (int)($this->db->scalar("SELECT setting_value FROM system_settings WHERE setting_key = 'potential_threshold'") ?? 60);
        if ($eligible < $potential) {
            $errors[] = 'The "Eligible" threshold cannot be lower than the "Potentially Eligible" threshold; both were left unchanged.';
            foreach (['eligible_threshold', 'potential_threshold'] as $key) {
                if (isset($changed[$key])) {
                    $this->db->update('system_settings',
                        ['setting_value' => $changed[$key]['from']], ['setting_key' => $key]);
                    unset($changed[$key]);
                }
            }
        }

        Config::flushSettings();

        if ($changed) {
            Audit::log('Settings', 'Modified', [
                'entity_type' => 'SystemSettings',
                'description' => count($changed) . ' setting(s) changed',
                'old_values'  => array_map(fn($c) => $c['from'], $changed),
                'new_values'  => array_map(fn($c) => $c['to'], $changed),
            ]);
        }

        if ($errors) {
            Flash::error(implode(' ', $errors));
        }
        Flash::success($changed
            ? count($changed) . ' setting(s) updated.'
            : 'No changes were made.');

        $this->redirect('/settings');
    }

    // =================================================================
    // Assessment questionnaire (section 18 - "add questions later")
    // =================================================================

    public function assessmentQuestions(): void
    {
        $questions = $this->db->select(
            'SELECT q.*, ea.label AS attribute_label
               FROM assessment_questions q
               LEFT JOIN eligibility_attributes ea ON ea.attribute_key = q.question_key
              ORDER BY q.section, q.sort_order, q.id');

        $grouped = [];
        foreach ($questions as $question) {
            $grouped[$question['section']][] = $question;
        }

        $master = new Master();

        $this->view('settings/assessment-questions', [
            'pageTitle'   => 'Assessment Questions',
            'breadcrumbs' => [['label' => 'Settings', 'url' => '/settings'], ['label' => 'Assessment Questions']],
            'grouped'     => $grouped,
            'lookupKeys'  => $master->lookupKeys(),
            'attributes'  => $this->db->pluck(
                'SELECT attribute_key, label FROM eligibility_attributes WHERE is_active = 1 ORDER BY sort_order'),
            'editing'     => $this->request->filled('edit')
                ? $this->db->selectOne('SELECT * FROM assessment_questions WHERE id = :id',
                    ['id' => (int)$this->request->input('edit')])
                : null,
            'sections'    => array_keys($grouped),
        ]);
    }

    public function saveAssessmentQuestion(): void
    {
        $id = $this->request->int('id');

        $validator = Validator::make($this->request->all(), [
            'section'      => 'required|string|max:80',
            'question_key' => 'required|alpha_dash|max:60|unique:assessment_questions,question_key,' . ($id ?? 0),
            'label'        => 'required|string|max:255',
            'help_text'    => 'nullable|string|max:500',
            'input_type'   => 'required|in:text,number,decimal,select,multiselect,radio,checkbox,date,textarea',
            'lookup_key'   => 'nullable|string|max:60',
            'unit'         => 'nullable|string|max:30',
            'sort_order'   => 'nullable|integer|between:0,9999',
        ], [
            'question_key' => 'Question Key',
            'label'        => 'Question Text',
            'input_type'   => 'Input Type',
        ]);

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), '/settings/assessment-questions');
        }

        $inputType = (string)$this->request->post('input_type');
        $lookupKey = $this->request->post('lookup_key') ?: null;

        // A choice question is meaningless without an option list.
        if (in_array($inputType, ['select', 'multiselect', 'radio'], true) && !$lookupKey) {
            $this->backWithErrors(
                ['lookup_key' => 'A ' . $inputType . ' question needs a dropdown key. Create the options under Masters > Dropdown Options first.'],
                '/settings/assessment-questions');
        }
        if ($lookupKey) {
            $exists = (int)$this->db->scalar('SELECT COUNT(*) FROM lookup_options WHERE lookup_key = :k',
                ['k' => $lookupKey]);
            if ($exists === 0) {
                $this->backWithErrors(
                    ['lookup_key' => 'No options exist for the dropdown key "' . $lookupKey . '". Add them under Masters > Dropdown Options.'],
                    '/settings/assessment-questions');
            }
        }

        $questionKey = (string)$this->request->post('question_key');

        // The answer only reaches the matching engine when the key matches a
        // declared eligibility attribute - say so rather than failing quietly.
        $attributeExists = (int)$this->db->scalar(
            'SELECT COUNT(*) FROM eligibility_attributes WHERE attribute_key = :k AND is_active = 1',
            ['k' => $questionKey]);

        $data = [
            'section'      => (string)$this->request->post('section'),
            'question_key' => $questionKey,
            'label'        => (string)$this->request->post('label'),
            'help_text'    => $this->request->post('help_text') ?: null,
            'input_type'   => $inputType,
            'lookup_key'   => $lookupKey,
            'unit'         => $this->request->post('unit') ?: null,
            'min_value'    => $this->request->filled('min_value') ? (float)$this->request->post('min_value') : null,
            'max_value'    => $this->request->filled('max_value') ? (float)$this->request->post('max_value') : null,
            'is_required'  => $this->request->bool('is_required') ? 1 : 0,
            'feeds_eligibility' => $attributeExists > 0 ? 1 : 0,
            'sort_order'   => $this->request->int('sort_order', 100),
            'is_active'    => $this->request->bool('is_active', true) ? 1 : 0,
        ];

        if ($id) {
            $before = $this->db->selectOne('SELECT * FROM assessment_questions WHERE id = :id', ['id' => $id]);
            $this->db->update('assessment_questions', $data, ['id' => $id]);
            Audit::modified('Settings', 'AssessmentQuestion', $id, $data['label'], $before ?: [], $data);
            Flash::success('Question updated.');
        } else {
            $id = $this->db->insert('assessment_questions', $data);
            Audit::created('Settings', 'AssessmentQuestion', $id, $data['label'], $data);
            Flash::success('Question added. It now appears on the beneficiary assessment form.');
        }

        if ($attributeExists === 0) {
            Flash::warning('No eligibility attribute is defined with the key "' . $questionKey . '", '
                . 'so answers to this question are stored but will not be read by the matching engine. '
                . 'Add a matching attribute under Settings > Eligibility Attributes to use it in scheme rules.');
        }

        $this->redirect('/settings/assessment-questions');
    }

    public function toggleAssessmentQuestion(string $id): void
    {
        $question = $this->findOrFail(
            $this->db->selectOne('SELECT * FROM assessment_questions WHERE id = :id', ['id' => (int)$id]),
            'Question');

        $new = (int)$question['is_active'] === 1 ? 0 : 1;
        $this->db->update('assessment_questions', ['is_active' => $new], ['id' => (int)$id]);

        Audit::log('Settings', 'Modified', [
            'entity_type'  => 'AssessmentQuestion',
            'entity_id'    => (int)$id,
            'entity_label' => $question['label'],
            'description'  => $new === 1 ? 'Question activated' : 'Question removed from the assessment form',
        ]);

        Flash::success('Question ' . ($new === 1 ? 'activated.' : 'deactivated.'));
        $this->back('/settings/assessment-questions');
    }

    // =================================================================
    // Eligibility attribute vocabulary (section 9)
    // =================================================================

    public function eligibilityAttributes(): void
    {
        $engine = new EligibilityEngine();
        $master = new Master();

        $attributes = $this->db->select(
            'SELECT a.*,
                    (SELECT COUNT(*) FROM scheme_eligibility_rules r
                      WHERE r.attribute_key = a.attribute_key AND r.is_active = 1) AS rule_usage,
                    (SELECT COUNT(*) FROM beneficiary_profile_attributes p
                      WHERE p.attribute_key = a.attribute_key) AS data_coverage
               FROM eligibility_attributes a
              ORDER BY a.group_label, a.sort_order, a.label');

        $grouped = [];
        foreach ($attributes as $attribute) {
            $grouped[$attribute['group_label'] ?: 'General'][] = $attribute;
        }

        $this->view('settings/eligibility-attributes', [
            'pageTitle'   => 'Eligibility Attributes',
            'breadcrumbs' => [['label' => 'Settings', 'url' => '/settings'], ['label' => 'Eligibility Attributes']],
            'grouped'     => $grouped,
            'operators'   => $engine->operators(),
            'lookupKeys'  => $master->lookupKeys(),
            'editing'     => $this->request->filled('edit')
                ? $this->db->selectOne('SELECT * FROM eligibility_attributes WHERE id = :id',
                    ['id' => (int)$this->request->input('edit')])
                : null,
        ]);
    }

    public function saveEligibilityAttribute(): void
    {
        $id = $this->request->int('id');

        $validator = Validator::make($this->request->all(), [
            'attribute_key' => 'required|alpha_dash|max:60|unique:eligibility_attributes,attribute_key,' . ($id ?? 0),
            'label'         => 'required|string|max:150',
            'data_type'     => 'required|in:int,decimal,bool,string,enum,set,date',
            'lookup_key'    => 'nullable|string|max:60',
            'source'        => 'required|in:beneficiary,household,assessment,derived,geo',
            'source_column' => 'nullable|alpha_dash|max:60',
            'unit'          => 'nullable|string|max:30',
            'group_label'   => 'nullable|string|max:60',
            'description'   => 'nullable|string|max:500',
            'sort_order'    => 'nullable|integer|between:0,9999',
        ], [
            'attribute_key' => 'Attribute Key',
            'label'         => 'Label',
            'data_type'     => 'Data Type',
            'source'        => 'Value Source',
        ]);

        if ($validator->fails()) {
            $this->backWithErrors($validator->firstErrors(), '/settings/eligibility-attributes');
        }

        $dataType  = (string)$this->request->post('data_type');
        $lookupKey = $this->request->post('lookup_key') ?: null;

        if ($dataType === 'enum' && !$lookupKey) {
            $this->backWithErrors(
                ['lookup_key' => 'An enum attribute needs a dropdown key so the rule builder knows which values to offer.'],
                '/settings/eligibility-attributes');
        }

        $source = (string)$this->request->post('source');
        $column = $this->request->post('source_column') ?: null;

        // A column-backed attribute must point at a column that exists.
        if (in_array($source, ['beneficiary', 'household', 'geo'], true) && $column) {
            $table = $source === 'household' ? 'beneficiary_households' : 'beneficiaries';
            $exists = (int)$this->db->scalar(
                'SELECT COUNT(*) FROM information_schema.columns
                  WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c',
                ['t' => $table, 'c' => $column]);
            if ($exists === 0) {
                $this->backWithErrors(
                    ['source_column' => 'The column "' . $column . '" does not exist on ' . $table . '.'],
                    '/settings/eligibility-attributes');
            }
        }

        $data = [
            'attribute_key' => (string)$this->request->post('attribute_key'),
            'label'         => (string)$this->request->post('label'),
            'data_type'     => $dataType,
            'lookup_key'    => $lookupKey,
            'source'        => $source,
            'source_column' => $column,
            'unit'          => $this->request->post('unit') ?: null,
            'group_label'   => $this->request->post('group_label') ?: 'General',
            'description'   => $this->request->post('description') ?: null,
            'allowed_operators' => $this->request->post('allowed_operators') ?: null,
            'sort_order'    => $this->request->int('sort_order', 100),
            'is_active'     => $this->request->bool('is_active', true) ? 1 : 0,
        ];

        if ($id) {
            $before = $this->db->selectOne('SELECT * FROM eligibility_attributes WHERE id = :id', ['id' => $id]);

            // Renaming a key would silently orphan every rule using it.
            if ($before && $before['attribute_key'] !== $data['attribute_key']) {
                $inUse = (int)$this->db->scalar(
                    'SELECT COUNT(*) FROM scheme_eligibility_rules WHERE attribute_key = :k',
                    ['k' => $before['attribute_key']]);
                if ($inUse > 0) {
                    $this->backWithErrors([
                        'attribute_key' => sprintf('"%s" is used by %d eligibility rule(s), so its key cannot be changed. Create a new attribute instead.',
                            $before['attribute_key'], $inUse),
                    ], '/settings/eligibility-attributes');
                }
            }

            $this->db->update('eligibility_attributes', $data, ['id' => $id]);
            Audit::modified('Settings', 'EligibilityAttribute', $id, $data['label'], $before ?: [], $data);
            Flash::success('Eligibility attribute updated.');
        } else {
            $id = $this->db->insert('eligibility_attributes', $data);
            Audit::created('Settings', 'EligibilityAttribute', $id, $data['label'], $data);
            Flash::success('Eligibility attribute added. It is now available in the scheme rule builder.');

            if ($source === 'assessment') {
                Flash::info('Add a matching assessment question with the key "' . $data['attribute_key']
                    . '" so field teams can record this value.');
            }
        }

        $this->redirect('/settings/eligibility-attributes');
    }
}
