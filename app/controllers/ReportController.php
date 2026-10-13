<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Response;
use JanKavach\Core\Scope;
use JanKavach\Libraries\Exporter;
use JanKavach\Models\Application;
use JanKavach\Models\Geography;
use JanKavach\Models\Master;
use JanKavach\Models\User;

/**
 * Reports module (specification section 26).
 *
 * Each report is described once in self::REPORTS - its title, the SQL that
 * produces it, and its columns.  The controller runs, renders, exports and
 * prints them all, so a new report is a new entry rather than new code.
 */
class ReportController extends Controller
{
    /**
     * key => [title, description, icon, permission, filters[], builder method]
     */
    public const REPORTS = [
        'scheme-master' => [
            'title' => 'Scheme Master Report',
            'description' => 'Every scheme in the master with its classification, ranking and source.',
            'icon' => 'bi-journal-text', 'group' => 'Schemes',
            'filters' => ['state_id', 'scheme_level', 'theme_id', 'beneficiary_type_id', 'scheme_status'],
        ],
        'scheme-documents' => [
            'title' => 'Scheme Document Requirements',
            'description' => 'Which documents each scheme requires, and how many are mandatory.',
            'icon' => 'bi-file-earmark-text', 'group' => 'Schemes',
            'filters' => ['state_id', 'scheme_level', 'theme_id'],
        ],
        'beneficiary-registration' => [
            'title' => 'Beneficiary Registration Report',
            'description' => 'All registered beneficiaries with their geography and collection details.',
            'icon' => 'bi-person-plus', 'group' => 'Beneficiaries',
            'filters' => ['date_from', 'date_to', 'state_id', 'district_id', 'gender', 'area_type', 'field_user_id'],
        ],
        'state-wise-beneficiary' => [
            'title' => 'State-wise Beneficiary Report',
            'description' => 'Beneficiary and application roll-up by State.',
            'icon' => 'bi-map', 'group' => 'Beneficiaries',
            'filters' => ['date_from', 'date_to'],
        ],
        'district-wise-beneficiary' => [
            'title' => 'District-wise Beneficiary Report',
            'description' => 'Beneficiary and application roll-up by District.',
            'icon' => 'bi-geo-alt', 'group' => 'Beneficiaries',
            'filters' => ['date_from', 'date_to', 'state_id'],
        ],
        'scheme-wise-beneficiary' => [
            'title' => 'Scheme-wise Beneficiary Report',
            'description' => 'How many beneficiaries were matched, applied and benefited, per scheme.',
            'icon' => 'bi-diagram-2', 'group' => 'Beneficiaries',
            'filters' => ['date_from', 'date_to', 'state_id', 'district_id', 'theme_id'],
        ],
        'eligible-schemes' => [
            'title' => 'Eligible Scheme Report',
            'description' => 'Every beneficiary-scheme match with its grade, score and missing documents.',
            'icon' => 'bi-check2-square', 'group' => 'Matching',
            'filters' => ['state_id', 'district_id', 'scheme_id', 'eligibility_status'],
        ],
        'applications-submitted' => [
            'title' => 'Applications Submitted Report',
            'description' => 'Applications that reached the implementing department.',
            'icon' => 'bi-send', 'group' => 'Applications',
            'filters' => ['date_from', 'date_to', 'state_id', 'district_id', 'scheme_id'],
        ],
        'applications-pending' => [
            'title' => 'Applications Pending Report',
            'description' => 'Applications still in progress, with how long each has been waiting.',
            'icon' => 'bi-hourglass-split', 'group' => 'Applications',
            'filters' => ['state_id', 'district_id', 'scheme_id', 'status_id'],
        ],
        'applications-approved' => [
            'title' => 'Approved Applications Report',
            'description' => 'Applications approved by the department.',
            'icon' => 'bi-check-circle', 'group' => 'Applications',
            'filters' => ['date_from', 'date_to', 'state_id', 'district_id', 'scheme_id'],
        ],
        'benefits-received' => [
            'title' => 'Benefits Received Report',
            'description' => 'Every recorded benefit, with amount, mode and reference.',
            'icon' => 'bi-award', 'group' => 'Applications',
            'filters' => ['date_from', 'date_to', 'state_id', 'district_id', 'scheme_id'],
        ],
        'followup' => [
            'title' => 'Follow-up Report',
            'description' => 'Follow-up interactions and what came of them.',
            'icon' => 'bi-telephone', 'group' => 'Follow-ups',
            'filters' => ['date_from', 'date_to', 'state_id', 'district_id', 'followup_type'],
        ],
        'documents-pending' => [
            'title' => 'Document Pending Report',
            'description' => 'Beneficiaries whose matched schemes are blocked on missing documents.',
            'icon' => 'bi-folder-x', 'group' => 'Follow-ups',
            'filters' => ['state_id', 'district_id'],
        ],
        'field-user-performance' => [
            'title' => 'Field User Performance Report',
            'description' => 'Collection and conversion figures for each field user.',
            'icon' => 'bi-speedometer2', 'group' => 'Performance',
            'filters' => ['state_id', 'district_id', 'field_user_id'],
        ],
    ];

    public function index(): void
    {
        $grouped = [];
        foreach (self::REPORTS as $key => $report) {
            $grouped[$report['group']][$key] = $report;
        }

        $this->view('reports/index', [
            'pageTitle'   => 'Reports',
            'breadcrumbs' => [['label' => 'Reports']],
            'grouped'     => $grouped,
        ]);
    }

    public function show(string $report): void
    {
        $definition = $this->definition($report);
        $filters = $this->reportFilters($definition['filters']);

        [$columns, $rows, $summary] = $this->build($report, $filters);

        $this->view('reports/show', [
            'pageTitle'   => $definition['title'],
            'breadcrumbs' => [['label' => 'Reports', 'url' => '/reports'], ['label' => $definition['title']]],
            'report'      => $report,
            'definition'  => $definition,
            'columns'     => $columns,
            'rows'        => $rows,
            'summary'     => $summary,
            'filters'     => $filters,
            'options'     => $this->filterOptions($definition['filters'], $filters),
        ]);
    }

    public function export(string $report, ?string $forceFormat = null): void
    {
        $definition = $this->definition($report);
        // The format is passed in explicitly by printReport(); Request snapshots
        // the query string at construction, so mutating $_GET here would be
        // silently ignored.
        $format  = $forceFormat ?? $this->inOrNull('format', ['excel', 'csv', 'pdf']) ?? 'excel';
        $filters = $this->reportFilters($definition['filters']);

        [$columns, $rows] = $this->build($report, $filters);

        Exporter::send($format, $columns, $rows, $report, [
            'title'       => $definition['title'],
            'subtitle'    => $definition['description'],
            'module'      => 'Reports',
            'filters'     => $this->describeFilters($filters),
            'orientation' => count($columns) > 8 ? 'landscape' : 'portrait',
        ]);
    }

    public function printReport(string $report): void
    {
        $this->export($report, 'pdf');
    }

    // =================================================================
    // Report builders
    // =================================================================

    /**
     * @return array [columns, rows, summary]
     */
    private function build(string $report, array $filters): array
    {
        return match ($report) {
            'scheme-master'             => $this->schemeMaster($filters),
            'scheme-documents'          => $this->schemeDocuments($filters),
            'beneficiary-registration'  => $this->beneficiaryRegistration($filters),
            'state-wise-beneficiary'    => $this->stateWise($filters),
            'district-wise-beneficiary' => $this->districtWise($filters),
            'scheme-wise-beneficiary'   => $this->schemeWise($filters),
            'eligible-schemes'          => $this->eligibleSchemes($filters),
            'applications-submitted'    => $this->applicationsBy($filters, 'submitted'),
            'applications-pending'      => $this->applicationsBy($filters, 'pending'),
            'applications-approved'     => $this->applicationsBy($filters, 'approved'),
            'benefits-received'         => $this->benefitsReceived($filters),
            'followup'                  => $this->followupReport($filters),
            'documents-pending'         => $this->documentsPending($filters),
            'field-user-performance'    => $this->fieldUserPerformance($filters),
            default                     => [[], [], []],
        };
    }

    private function schemeMaster(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::schemeClause('s');
        $where = [$scopeSql, 's.deleted_at IS NULL'];

        if (!empty($filters['state_id'])) {
            $where[] = "(s.state_id = :f_state OR s.scheme_level = 'National')";
            $bindings['f_state'] = (int)$filters['state_id'];
        }
        if (!empty($filters['scheme_level'])) {
            $where[] = 's.scheme_level = :f_level';
            $bindings['f_level'] = $filters['scheme_level'];
        }
        if (!empty($filters['theme_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM scheme_theme_map m WHERE m.scheme_id = s.id AND m.theme_id = :f_theme)';
            $bindings['f_theme'] = (int)$filters['theme_id'];
        }
        if (!empty($filters['beneficiary_type_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM scheme_beneficiary_types b WHERE b.scheme_id = s.id AND b.beneficiary_type_id = :f_btype)';
            $bindings['f_btype'] = (int)$filters['beneficiary_type_id'];
        }
        if (!empty($filters['scheme_status'])) {
            $where[] = 's.scheme_status = :f_status';
            $bindings['f_status'] = $filters['scheme_status'];
        }

        $rows = $this->db->select(
            'SELECT s.scheme_code, s.name, s.short_name, s.scheme_level, s.scheme_status,
                    s.active_enrollment, s.source_url, s.updated_at,
                    st.name AS state_name, th.name AS theme_name,
                    pl.name AS priority_name, fl.name AS feasibility_name,
                    sa.name AS service_approach,
                    (SELECT GROUP_CONCAT(bt.name ORDER BY bt.sort_order SEPARATOR ", ")
                       FROM scheme_beneficiary_types sbt JOIN beneficiary_types bt ON bt.id = sbt.beneficiary_type_id
                      WHERE sbt.scheme_id = s.id) AS beneficiary_types,
                    (SELECT COUNT(*) FROM scheme_documents sd WHERE sd.scheme_id = s.id) AS document_count,
                    (SELECT COUNT(*) FROM scheme_eligibility_rules r WHERE r.scheme_id = s.id AND r.is_active = 1) AS rule_count
               FROM schemes s
               LEFT JOIN states st ON st.id = s.state_id
               LEFT JOIN scheme_themes th ON th.id = s.theme_id
               LEFT JOIN priority_levels pl ON pl.id = s.priority_id
               LEFT JOIN feasibility_levels fl ON fl.id = s.feasibility_id
               LEFT JOIN service_approaches sa ON sa.id = s.service_approach_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY s.scheme_level, s.name LIMIT 20000',
            $bindings
        );

        $columns = [
            'Scheme Code'       => 'scheme_code',
            'Scheme Name'       => 'name',
            'National / State'  => 'scheme_level',
            'State'             => fn($r) => $r['state_name'] ?? '-',
            'Theme'             => fn($r) => $r['theme_name'] ?? '-',
            'Beneficiary Types' => fn($r) => $r['beneficiary_types'] ?? '-',
            'Priority'          => fn($r) => $r['priority_name'] ?? 'Not Ranked',
            'Feasibility'       => fn($r) => $r['feasibility_name'] ?? 'Not Ranked',
            'Service Approach'  => fn($r) => $r['service_approach'] ?? '-',
            'Status'            => 'scheme_status',
            'Documents'         => fn($r) => (int)$r['document_count'],
            'Rules'             => fn($r) => (int)$r['rule_count'],
            'Last Updated'      => fn($r) => fmt_date($r['updated_at']),
        ];

        return [$columns, $rows, [
            'Total schemes'      => count($rows),
            'National'           => count(array_filter($rows, fn($r) => $r['scheme_level'] === 'National')),
            'State-specific'     => count(array_filter($rows, fn($r) => $r['scheme_level'] !== 'National')),
            'With rules configured' => count(array_filter($rows, fn($r) => (int)$r['rule_count'] > 0)),
        ]];
    }

    private function schemeDocuments(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::schemeClause('s');
        $where = [$scopeSql, 's.deleted_at IS NULL'];

        if (!empty($filters['state_id'])) {
            $where[] = "(s.state_id = :f_state OR s.scheme_level = 'National')";
            $bindings['f_state'] = (int)$filters['state_id'];
        }
        if (!empty($filters['scheme_level'])) {
            $where[] = 's.scheme_level = :f_level';
            $bindings['f_level'] = $filters['scheme_level'];
        }
        if (!empty($filters['theme_id'])) {
            $where[] = 's.theme_id = :f_theme';
            $bindings['f_theme'] = (int)$filters['theme_id'];
        }

        $rows = $this->db->select(
            'SELECT s.scheme_code, s.name, s.scheme_level, st.name AS state_name, th.name AS theme_name,
                    COUNT(sd.id) AS total_docs,
                    SUM(sd.is_mandatory = 1) AS mandatory_docs,
                    GROUP_CONCAT(CASE WHEN sd.is_mandatory = 1 THEN dt.name END ORDER BY dt.sort_order SEPARATOR ", ") AS mandatory_list,
                    GROUP_CONCAT(CASE WHEN sd.is_mandatory = 0 THEN dt.name END ORDER BY dt.sort_order SEPARATOR ", ") AS optional_list
               FROM schemes s
               LEFT JOIN scheme_documents sd ON sd.scheme_id = s.id
               LEFT JOIN document_types dt ON dt.id = sd.document_type_id
               LEFT JOIN states st ON st.id = s.state_id
               LEFT JOIN scheme_themes th ON th.id = s.theme_id
              WHERE ' . implode(' AND ', $where) . '
              GROUP BY s.id ORDER BY s.name LIMIT 20000',
            $bindings
        );

        $columns = [
            'Scheme Code'        => 'scheme_code',
            'Scheme Name'        => 'name',
            'Level'              => 'scheme_level',
            'State'              => fn($r) => $r['state_name'] ?? '-',
            'Theme'              => fn($r) => $r['theme_name'] ?? '-',
            'Total Documents'    => fn($r) => (int)$r['total_docs'],
            'Mandatory'          => fn($r) => (int)$r['mandatory_docs'],
            'Mandatory Documents'=> fn($r) => $r['mandatory_list'] ?? '-',
            'Optional Documents' => fn($r) => $r['optional_list'] ?? '-',
        ];

        return [$columns, $rows, [
            'Schemes'                 => count($rows),
            'Schemes with no documents' => count(array_filter($rows, fn($r) => (int)$r['total_docs'] === 0)),
        ]];
    }

    private function beneficiaryRegistration(array $filters): array
    {
        $rows = (new \JanKavach\Models\Beneficiary())->allFiltered($filters, 'v.registration_date', 'DESC');

        $columns = [
            'Beneficiary ID'     => 'beneficiary_code',
            'Name'               => 'name',
            'Gender'             => 'gender',
            'Age'                => fn($r) => $r['age'] ?? '-',
            'Mobile'             => fn($r) => mask_mobile($r['mobile'] ?? null),
            'State'              => 'state_name',
            'District'           => 'district_name',
            'Block'              => fn($r) => $r['block_name'] ?? '-',
            'Village'            => fn($r) => $r['village_name'] ?? '-',
            'Rural / Urban'      => 'area_type',
            'Registered On'      => fn($r) => fmt_date($r['registration_date']),
            'Assessment'         => 'assessment_status',
            'Record Status'      => 'record_status',
            'Schemes Identified' => fn($r) => (int)$r['schemes_identified'],
            'Field User'         => fn($r) => $r['field_user_name'] ?? '-',
        ];

        return [$columns, $rows, [
            'Total registered'    => count($rows),
            'Assessment complete' => count(array_filter($rows, fn($r) => $r['assessment_status'] === 'Completed')),
            'Female'              => count(array_filter($rows, fn($r) => $r['gender'] === 'Female')),
            'Rural'               => count(array_filter($rows, fn($r) => $r['area_type'] === 'Rural')),
        ]];
    }

    private function stateWise(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::clause('b');
        $where = [$scopeSql, 'b.deleted_at IS NULL'];
        $this->applyDateRange($where, $bindings, $filters, 'b.registration_date');

        $rows = $this->db->select(
            'SELECT st.name AS state_name, st.state_code,
                    COUNT(b.id) AS beneficiaries,
                    SUM(b.assessment_status = "Completed") AS assessed,
                    SUM(b.gender = "Female") AS female,
                    SUM(b.area_type = "Rural") AS rural,
                    (SELECT COUNT(DISTINCT m.beneficiary_id) FROM beneficiary_scheme_matches m
                       JOIN beneficiaries b2 ON b2.id = m.beneficiary_id
                      WHERE b2.state_id = st.id AND b2.deleted_at IS NULL
                        AND m.eligibility_status IN ("Eligible","Potentially Eligible")) AS eligible,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a
                      WHERE a.state_id = st.id AND a.deleted_at IS NULL) AS applications,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a
                      WHERE a.state_id = st.id AND a.submission_date IS NOT NULL AND a.deleted_at IS NULL) AS submitted,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a
                      WHERE a.state_id = st.id AND a.approval_date IS NOT NULL AND a.deleted_at IS NULL) AS approved,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a
                      WHERE a.state_id = st.id AND a.benefit_received = 1 AND a.deleted_at IS NULL) AS benefited,
                    (SELECT COALESCE(SUM(br.amount),0) FROM benefits_received br WHERE br.state_id = st.id) AS benefit_amount
               FROM beneficiaries b
               JOIN states st ON st.id = b.state_id
              WHERE ' . implode(' AND ', $where) . '
              GROUP BY st.id ORDER BY beneficiaries DESC',
            $bindings
        );

        $columns = [
            'State'                 => 'state_name',
            'Beneficiaries'         => fn($r) => (int)$r['beneficiaries'],
            'Assessed'              => fn($r) => (int)$r['assessed'],
            'Female'                => fn($r) => (int)$r['female'],
            'Rural'                 => fn($r) => (int)$r['rural'],
            'Eligible Beneficiaries'=> fn($r) => (int)$r['eligible'],
            'Applications'          => fn($r) => (int)$r['applications'],
            'Submitted'             => fn($r) => (int)$r['submitted'],
            'Approved'              => fn($r) => (int)$r['approved'],
            'Benefits Received'     => fn($r) => (int)$r['benefited'],
            'Benefit Amount'        => fn($r) => (float)$r['benefit_amount'],
        ];

        return [$columns, $rows, [
            'States covered' => count($rows),
            'Beneficiaries'  => array_sum(array_column($rows, 'beneficiaries')),
            'Applications'   => array_sum(array_column($rows, 'applications')),
            'Benefits'       => array_sum(array_column($rows, 'benefited')),
        ]];
    }

    private function districtWise(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::clause('b');
        $where = [$scopeSql, 'b.deleted_at IS NULL'];

        if (!empty($filters['state_id'])) {
            $where[] = 'b.state_id = :f_state';
            $bindings['f_state'] = (int)$filters['state_id'];
        }
        $this->applyDateRange($where, $bindings, $filters, 'b.registration_date');

        $rows = $this->db->select(
            'SELECT st.name AS state_name, d.name AS district_name, d.id AS district_id,
                    COUNT(b.id) AS beneficiaries,
                    SUM(b.assessment_status = "Completed") AS assessed,
                    COUNT(DISTINCT b.village_id) AS villages,
                    COUNT(DISTINCT b.field_user_id) AS field_users,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a
                      WHERE a.district_id = d.id AND a.deleted_at IS NULL) AS applications,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a
                      WHERE a.district_id = d.id AND a.submission_date IS NOT NULL AND a.deleted_at IS NULL) AS submitted,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a
                      WHERE a.district_id = d.id AND a.benefit_received = 1 AND a.deleted_at IS NULL) AS benefited
               FROM beneficiaries b
               JOIN districts d ON d.id = b.district_id
               JOIN states st ON st.id = b.state_id
              WHERE ' . implode(' AND ', $where) . '
              GROUP BY d.id ORDER BY beneficiaries DESC',
            $bindings
        );

        $columns = [
            'State'             => 'state_name',
            'District'          => 'district_name',
            'Beneficiaries'     => fn($r) => (int)$r['beneficiaries'],
            'Assessed'          => fn($r) => (int)$r['assessed'],
            'Villages Covered'  => fn($r) => (int)$r['villages'],
            'Field Users'       => fn($r) => (int)$r['field_users'],
            'Applications'      => fn($r) => (int)$r['applications'],
            'Submitted'         => fn($r) => (int)$r['submitted'],
            'Benefits Received' => fn($r) => (int)$r['benefited'],
            'Conversion %'      => fn($r) => (int)$r['beneficiaries'] > 0
                                    ? round((int)$r['benefited'] * 100 / (int)$r['beneficiaries'], 1) : 0,
        ];

        return [$columns, $rows, [
            'Districts covered' => count($rows),
            'Beneficiaries'     => array_sum(array_column($rows, 'beneficiaries')),
            'Applications'      => array_sum(array_column($rows, 'applications')),
        ]];
    }

    private function schemeWise(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::clause('b');
        $where = [$scopeSql, 'b.deleted_at IS NULL'];

        foreach (['state_id', 'district_id'] as $key) {
            if (!empty($filters[$key])) {
                $where[] = "b.{$key} = :f_{$key}";
                $bindings['f_' . $key] = (int)$filters[$key];
            }
        }
        if (!empty($filters['theme_id'])) {
            $where[] = 's.theme_id = :f_theme';
            $bindings['f_theme'] = (int)$filters['theme_id'];
        }
        $this->applyDateRange($where, $bindings, $filters, 'b.registration_date');

        $rows = $this->db->select(
            'SELECT s.scheme_code, s.name AS scheme_name, s.short_name, s.scheme_level,
                    th.name AS theme_name,
                    COUNT(DISTINCT m.beneficiary_id) AS matched,
                    SUM(m.eligibility_status = "Eligible") AS eligible,
                    SUM(m.eligibility_status = "Potentially Eligible") AS potential,
                    COALESCE(SUM(m.missing_doc_count), 0) AS missing_documents,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a
                       JOIN beneficiaries ab ON ab.id = a.beneficiary_id
                      WHERE a.scheme_id = s.id AND a.deleted_at IS NULL AND ab.deleted_at IS NULL) AS applications,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a
                      WHERE a.scheme_id = s.id AND a.submission_date IS NOT NULL AND a.deleted_at IS NULL) AS submitted,
                    (SELECT COUNT(*) FROM beneficiary_scheme_applications a
                      WHERE a.scheme_id = s.id AND a.benefit_received = 1 AND a.deleted_at IS NULL) AS benefited
               FROM beneficiary_scheme_matches m
               JOIN schemes s ON s.id = m.scheme_id
               JOIN beneficiaries b ON b.id = m.beneficiary_id
               LEFT JOIN scheme_themes th ON th.id = s.theme_id
              WHERE ' . implode(' AND ', $where) . '
                AND m.eligibility_status <> "Not Eligible"
              GROUP BY s.id ORDER BY matched DESC LIMIT 500',
            $bindings
        );

        $columns = [
            'Scheme Code'         => 'scheme_code',
            'Scheme'              => 'scheme_name',
            'Level'               => 'scheme_level',
            'Theme'               => fn($r) => $r['theme_name'] ?? '-',
            'Beneficiaries Matched' => fn($r) => (int)$r['matched'],
            'Graded Eligible'     => fn($r) => (int)$r['eligible'],
            'Potentially Eligible'=> fn($r) => (int)$r['potential'],
            'Documents Missing'   => fn($r) => (int)$r['missing_documents'],
            'Applications'        => fn($r) => (int)$r['applications'],
            'Submitted'           => fn($r) => (int)$r['submitted'],
            'Benefits Received'   => fn($r) => (int)$r['benefited'],
            'Utilisation %'       => fn($r) => (int)$r['matched'] > 0
                                     ? round((int)$r['benefited'] * 100 / (int)$r['matched'], 1) : 0,
        ];

        return [$columns, $rows, [
            'Schemes with matches' => count($rows),
            'Total matches'        => array_sum(array_column($rows, 'matched')),
            'Applications'         => array_sum(array_column($rows, 'applications')),
        ]];
    }

    private function eligibleSchemes(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::clause('b');
        $where = [$scopeSql, 'b.deleted_at IS NULL'];

        foreach (['state_id', 'district_id'] as $key) {
            if (!empty($filters[$key])) {
                $where[] = "b.{$key} = :f_{$key}";
                $bindings['f_' . $key] = (int)$filters[$key];
            }
        }
        if (!empty($filters['scheme_id'])) {
            $where[] = 'm.scheme_id = :f_scheme';
            $bindings['f_scheme'] = (int)$filters['scheme_id'];
        }
        if (!empty($filters['eligibility_status'])) {
            $where[] = 'm.eligibility_status = :f_elig';
            $bindings['f_elig'] = $filters['eligibility_status'];
        } else {
            $where[] = 'm.eligibility_status <> "Not Eligible"';
        }

        $rows = $this->db->select(
            'SELECT b.beneficiary_code, b.name AS beneficiary_name, b.gender, b.age,
                    st.name AS state_name, d.name AS district_name,
                    s.scheme_code, COALESCE(s.short_name, s.name) AS scheme_name, s.scheme_level,
                    m.match_percent, m.eligibility_status, m.missing_doc_count,
                    m.missing_documents, m.reason_text, m.matched_at
               FROM beneficiary_scheme_matches m
               JOIN beneficiaries b ON b.id = m.beneficiary_id
               JOIN schemes s ON s.id = m.scheme_id
               LEFT JOIN states st ON st.id = b.state_id
               LEFT JOIN districts d ON d.id = b.district_id
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY b.name, m.match_percent DESC LIMIT 20000',
            $bindings
        );

        $columns = [
            'Beneficiary ID'    => 'beneficiary_code',
            'Beneficiary'       => 'beneficiary_name',
            'Gender'            => 'gender',
            'Age'               => fn($r) => $r['age'] ?? '-',
            'State'             => 'state_name',
            'District'          => 'district_name',
            'Scheme'            => 'scheme_name',
            'Level'             => 'scheme_level',
            'Match %'           => fn($r) => (float)$r['match_percent'],
            'Eligibility'       => 'eligibility_status',
            'Missing Documents' => fn($r) => (int)$r['missing_doc_count'],
            'Document List'     => fn($r) => implode(', ', json_list($r['missing_documents'])),
            'Reason'            => fn($r) => str_limit($r['reason_text'] ?? '', 300),
        ];

        return [$columns, $rows, [
            'Matches'              => count($rows),
            'Graded Eligible'      => count(array_filter($rows, fn($r) => $r['eligibility_status'] === 'Eligible')),
            'Blocked on documents' => count(array_filter($rows, fn($r) => (int)$r['missing_doc_count'] > 0)),
        ]];
    }

    private function applicationsBy(array $filters, string $mode): array
    {
        $applicationFilters = array_merge($filters, match ($mode) {
            'submitted' => ['funnel_stage' => null],
            'approved'  => ['funnel_stage' => 'Approved'],
            default     => ['pending_only' => true],
        });

        $rows = (new Application())->allFiltered($applicationFilters, 'v.updated_at', 'DESC');

        if ($mode === 'submitted') {
            $rows = array_values(array_filter($rows, fn($r) => !empty($r['submission_date'])));
        }
        if ($mode === 'approved') {
            $rows = array_values(array_filter($rows, fn($r) => !empty($r['approval_date'])));
        }

        $columns = [
            'Application Code' => 'application_code',
            'Beneficiary ID'   => 'beneficiary_code',
            'Beneficiary'      => 'beneficiary_name',
            'Scheme'           => 'scheme_name',
            'State'            => 'state_name',
            'District'         => 'district_name',
            'Status'           => 'status_name',
            'Department No.'   => fn($r) => $r['application_number'] ?? '-',
            'Initiated'        => fn($r) => fmt_date($r['initiated_date']),
            'Submitted'        => fn($r) => fmt_date($r['submission_date']),
            'Approved'         => fn($r) => fmt_date($r['approval_date']),
            'Days in Pipeline' => fn($r) => $r['referral_date']
                                  ? (int)((time() - strtotime($r['referral_date'])) / 86400) : '-',
            'Next Follow-up'   => fn($r) => fmt_date($r['next_followup_date']),
            'Assigned To'      => fn($r) => $r['assigned_to_name'] ?? '-',
        ];

        if ($mode === 'pending') {
            $columns['Waiting Since'] = fn($r) => fmt_date($r['updated_at']);
        }

        return [$columns, $rows, [
            'Applications' => count($rows),
            'Submitted'    => count(array_filter($rows, fn($r) => !empty($r['submission_date']))),
            'Approved'     => count(array_filter($rows, fn($r) => !empty($r['approval_date']))),
        ]];
    }

    private function benefitsReceived(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::clause('b');
        $where = [$scopeSql, 'b.deleted_at IS NULL'];

        foreach (['state_id', 'district_id'] as $key) {
            if (!empty($filters[$key])) {
                $where[] = "br.{$key} = :f_{$key}";
                $bindings['f_' . $key] = (int)$filters[$key];
            }
        }
        if (!empty($filters['scheme_id'])) {
            $where[] = 'br.scheme_id = :f_scheme';
            $bindings['f_scheme'] = (int)$filters['scheme_id'];
        }
        $this->applyDateRange($where, $bindings, $filters, 'br.received_date');

        $rows = $this->db->select(
            'SELECT br.*, b.beneficiary_code, b.name AS beneficiary_name, b.gender,
                    s.scheme_code, COALESCE(s.short_name, s.name) AS scheme_name,
                    st.name AS state_name, d.name AS district_name,
                    a.application_code, u.full_name AS recorded_by_name
               FROM benefits_received br
               JOIN beneficiaries b ON b.id = br.beneficiary_id
               JOIN schemes s ON s.id = br.scheme_id
               LEFT JOIN states st ON st.id = br.state_id
               LEFT JOIN districts d ON d.id = br.district_id
               LEFT JOIN beneficiary_scheme_applications a ON a.id = br.application_id
               LEFT JOIN users u ON u.id = br.recorded_by
              WHERE ' . implode(' AND ', $where) . '
              ORDER BY br.received_date DESC LIMIT 20000',
            $bindings
        );

        $columns = [
            'Received On'     => fn($r) => fmt_date($r['received_date']),
            'Beneficiary ID'  => 'beneficiary_code',
            'Beneficiary'     => 'beneficiary_name',
            'Scheme'          => 'scheme_name',
            'State'           => fn($r) => $r['state_name'] ?? '-',
            'District'        => fn($r) => $r['district_name'] ?? '-',
            'Benefit Type'    => fn($r) => $r['benefit_type'] ?? '-',
            'Amount'          => fn($r) => $r['amount'] !== null ? (float)$r['amount'] : '',
            'Payment Mode'    => 'payment_mode',
            'Instalment'      => fn($r) => $r['instalment_no'] ?? '-',
            'Reference'       => fn($r) => $r['reference_no'] ?? '-',
            'Application'     => fn($r) => $r['application_code'] ?? '-',
            'Recorded By'     => fn($r) => $r['recorded_by_name'] ?? '-',
        ];

        return [$columns, $rows, [
            'Benefit records' => count($rows),
            'Total amount'    => money(array_sum(array_map(fn($r) => (float)($r['amount'] ?? 0), $rows))),
            'Beneficiaries'   => count(array_unique(array_column($rows, 'beneficiary_code'))),
        ]];
    }

    private function followupReport(array $filters): array
    {
        $rows = (new \JanKavach\Models\Followup())->allFiltered($filters, 'f.followup_date', 'DESC');

        $columns = [
            'Date'           => fn($r) => fmt_date($r['followup_date']),
            'Beneficiary ID' => 'beneficiary_code',
            'Beneficiary'    => 'beneficiary_name',
            'Scheme'         => fn($r) => $r['scheme_name'] ?? '-',
            'Type'           => 'followup_type',
            'Outcome'        => 'outcome',
            'Status After'   => fn($r) => $r['status_name'] ?? '-',
            'Discussion'     => fn($r) => str_limit($r['discussion'] ?? '', 250),
            'Next Follow-up' => fn($r) => fmt_date($r['next_followup_date']),
            'Responsible'    => fn($r) => $r['responsible_name'] ?? '-',
            'District'       => fn($r) => $r['district_name'] ?? '-',
        ];

        return [$columns, $rows, [
            'Follow-ups'  => count($rows),
            'Resolved'    => count(array_filter($rows, fn($r) => $r['outcome'] === 'Resolved')),
            'Escalated'   => count(array_filter($rows, fn($r) => $r['outcome'] === 'Escalated')),
            'Not reached' => count(array_filter($rows, fn($r) => $r['outcome'] === 'Not Reachable')),
        ]];
    }

    private function documentsPending(array $filters): array
    {
        [$scopeSql, $bindings] = Scope::clause('b');
        $where = [$scopeSql, 'b.deleted_at IS NULL', 'm.missing_doc_count > 0',
                  'm.eligibility_status IN ("Eligible","Potentially Eligible")'];

        foreach (['state_id', 'district_id'] as $key) {
            if (!empty($filters[$key])) {
                $where[] = "b.{$key} = :f_{$key}";
                $bindings['f_' . $key] = (int)$filters[$key];
            }
        }

        $rows = $this->db->select(
            'SELECT b.beneficiary_code, b.name AS beneficiary_name, b.mobile,
                    st.name AS state_name, d.name AS district_name,
                    COALESCE(v.name, b.village_name) AS village_name,
                    u.full_name AS field_user_name,
                    COUNT(DISTINCT m.scheme_id) AS blocked_schemes,
                    SUM(m.missing_doc_count) AS missing_total,
                    GROUP_CONCAT(DISTINCT COALESCE(s.short_name, s.name) ORDER BY s.name SEPARATOR ", ") AS schemes
               FROM beneficiary_scheme_matches m
               JOIN beneficiaries b ON b.id = m.beneficiary_id
               JOIN schemes s ON s.id = m.scheme_id
               LEFT JOIN states st ON st.id = b.state_id
               LEFT JOIN districts d ON d.id = b.district_id
               LEFT JOIN villages v ON v.id = b.village_id
               LEFT JOIN users u ON u.id = b.field_user_id
              WHERE ' . implode(' AND ', $where) . '
              GROUP BY b.id ORDER BY missing_total DESC LIMIT 20000',
            $bindings
        );

        $columns = [
            'Beneficiary ID'      => 'beneficiary_code',
            'Beneficiary'         => 'beneficiary_name',
            'Mobile'              => fn($r) => mask_mobile($r['mobile'] ?? null),
            'State'               => 'state_name',
            'District'            => 'district_name',
            'Village'             => fn($r) => $r['village_name'] ?? '-',
            'Schemes Blocked'     => fn($r) => (int)$r['blocked_schemes'],
            'Documents Missing'   => fn($r) => (int)$r['missing_total'],
            'Affected Schemes'    => fn($r) => str_limit($r['schemes'] ?? '', 250),
            'Field User'          => fn($r) => $r['field_user_name'] ?? '-',
        ];

        return [$columns, $rows, [
            'Beneficiaries blocked' => count($rows),
            'Document gaps'         => array_sum(array_map(fn($r) => (int)$r['missing_total'], $rows)),
        ]];
    }

    private function fieldUserPerformance(array $filters): array
    {
        $rows = (new User())->performance([
            'state_id'    => $filters['state_id'] ?? null,
            'district_id' => $filters['district_id'] ?? null,
            'user_id'     => $filters['field_user_id'] ?? null,
        ]);

        $columns = [
            'User Code'              => 'user_code',
            'Field User'             => 'full_name',
            'Role'                   => 'role_name',
            'State'                  => fn($r) => $r['state_name'] ?? '-',
            'District'               => fn($r) => $r['district_name'] ?? '-',
            'Beneficiaries Registered' => fn($r) => (int)$r['beneficiaries_registered'],
            'Assessments Completed'  => fn($r) => (int)$r['assessments_completed'],
            'Schemes Identified'     => fn($r) => (int)$r['schemes_identified'],
            'Applications Initiated' => fn($r) => (int)$r['applications_initiated'],
            'Applications Submitted' => fn($r) => (int)$r['applications_submitted'],
            'Applications Approved'  => fn($r) => (int)$r['applications_approved'],
            'Benefits Received'      => fn($r) => (int)$r['benefits_received'],
            'Follow-ups'             => fn($r) => (int)$r['followups_done'],
            'Field Visits'           => fn($r) => (int)$r['field_visits'],
            'Last Login'             => fn($r) => fmt_datetime($r['last_login_at']),
        ];

        return [$columns, $rows, [
            'Field users'   => count($rows),
            'Registrations' => array_sum(array_map(fn($r) => (int)$r['beneficiaries_registered'], $rows)),
            'Applications'  => array_sum(array_map(fn($r) => (int)$r['applications_initiated'], $rows)),
        ]];
    }

    // =================================================================
    // Internals
    // =================================================================

    private function definition(string $report): array
    {
        if (!isset(self::REPORTS[$report])) {
            Response::abort(404, 'That report does not exist.');
        }
        return self::REPORTS[$report];
    }

    private function reportFilters(array $allowed): array
    {
        $stateId    = $this->request->int('state_id');
        $districtId = $this->request->int('district_id');
        [$stateId, $districtId] = Scope::constrainFilters($stateId, $districtId);

        $all = [
            'date_from'           => $this->dateOrNull('date_from'),
            'date_to'             => $this->dateOrNull('date_to'),
            'state_id'            => $stateId,
            'district_id'         => $districtId,
            'scheme_id'           => $this->request->int('scheme_id'),
            'theme_id'            => $this->request->int('theme_id'),
            'beneficiary_type_id' => $this->request->int('beneficiary_type_id'),
            'status_id'           => $this->request->int('status_id'),
            'field_user_id'       => $this->request->int('field_user_id'),
            'gender'              => $this->inOrNull('gender', ['Male', 'Female', 'Other']),
            'area_type'           => $this->inOrNull('area_type', ['Rural', 'Urban']),
            'scheme_level'        => $this->inOrNull('scheme_level', ['National', 'State', 'National+State']),
            'scheme_status'       => $this->inOrNull('scheme_status', ['Active', 'Dormant', 'Discontinued']),
            'eligibility_status'  => $this->inOrNull('eligibility_status',
                                       ['Eligible', 'Potentially Eligible', 'Needs Verification', 'Not Eligible']),
            'followup_type'       => $this->inOrNull('followup_type',
                                       ['Phone Call', 'Home Visit', 'Department Visit', 'Document Collection',
                                        'SMS/WhatsApp', 'Camp', 'Other']),
        ];

        // Geography is always applied, even when a report does not list it,
        // because it is what keeps a user inside their own data.
        $keep = array_flip(array_merge($allowed, ['state_id', 'district_id']));
        return array_intersect_key($all, $keep) + ['state_id' => $stateId, 'district_id' => $districtId];
    }

    private function filterOptions(array $allowed, array $filters): array
    {
        $geo = new Geography();
        $master = new Master();
        $options = [];

        if (in_array('state_id', $allowed, true) || true) {
            $options['states'] = $geo->stateOptions();
            $options['districts'] = $geo->districtOptions($filters['state_id'] ?? null);
        }
        if (in_array('theme_id', $allowed, true)) {
            $options['themes'] = $master->optionsFor('scheme_themes');
        }
        if (in_array('beneficiary_type_id', $allowed, true)) {
            $options['beneficiary_types'] = $master->optionsFor('beneficiary_types');
        }
        if (in_array('status_id', $allowed, true)) {
            $options['statuses'] = $master->optionsFor('application_statuses', 'name', 'stage_order');
        }
        if (in_array('scheme_id', $allowed, true)) {
            [$scopeSql, $bindings] = Scope::schemeClause('s');
            $options['schemes'] = $this->db->pluck(
                'SELECT s.id, COALESCE(s.short_name, s.name) FROM schemes s
                  WHERE ' . $scopeSql . ' AND s.deleted_at IS NULL ORDER BY s.name LIMIT 400',
                $bindings);
        }
        if (in_array('field_user_id', $allowed, true)) {
            $options['field_users'] = (new User())->fieldUsers($filters['state_id'] ?? null, $filters['district_id'] ?? null);
        }
        return $options;
    }

    private function applyDateRange(array &$where, array &$bindings, array $filters, string $column): void
    {
        if (!empty($filters['date_from'])) {
            $where[] = "{$column} >= :r_from";
            $bindings['r_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "{$column} <= :r_to";
            $bindings['r_to'] = $filters['date_to'];
        }
    }

    private function describeFilters(array $filters): array
    {
        $geo = new Geography();
        $out = [];

        if (!empty($filters['date_from']))   $out['From'] = fmt_date($filters['date_from']);
        if (!empty($filters['date_to']))     $out['To'] = fmt_date($filters['date_to']);
        if (!empty($filters['state_id']))    $out['State'] = $geo->stateName((int)$filters['state_id']);
        if (!empty($filters['district_id'])) $out['District'] = $geo->districtName((int)$filters['district_id']);
        if (!empty($filters['scheme_id'])) {
            $out['Scheme'] = $this->db->scalar('SELECT name FROM schemes WHERE id = :id', ['id' => (int)$filters['scheme_id']]);
        }
        if (!empty($filters['theme_id'])) {
            $out['Theme'] = $this->db->scalar('SELECT name FROM scheme_themes WHERE id = :id', ['id' => (int)$filters['theme_id']]);
        }
        if (!empty($filters['gender']))             $out['Gender'] = $filters['gender'];
        if (!empty($filters['eligibility_status'])) $out['Eligibility'] = $filters['eligibility_status'];
        if (!empty($filters['scheme_status']))      $out['Scheme Status'] = $filters['scheme_status'];
        if (!empty($filters['followup_type']))      $out['Follow-up Type'] = $filters['followup_type'];

        // Always state the scope, so a printed report is unambiguous.
        $out['Data Scope'] = Scope::label() ?: 'National';

        return $out;
    }
}
