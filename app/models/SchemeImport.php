<?php
namespace JanKavach\Models;

use JanKavach\Core\Auth;
use JanKavach\Core\Database;
use JanKavach\Core\Model;
use JanKavach\Libraries\XlsxReader;

/**
 * Excel scheme import (specification section 37).
 *
 * The pipeline is: upload -> parse & validate -> preview -> import.
 * Each stage is persisted, so a large workbook can be reviewed before any
 * scheme row is written, and the rejected rows remain inspectable afterwards.
 *
 * Column mapping is automatic for the headings the supplied workbook uses and
 * can be overridden on the preview screen.
 */
class SchemeImport extends Model
{
    protected string $table = 'scheme_import_batches';

    /** Target field => the header spellings that map onto it. */
    public const COLUMN_ALIASES = [
        'source_row_no'     => ['sl.no.', 'sl no', 'slno', 's.no', 'sr no', 'serial', 'sl. no.'],
        'name'              => ['name of the scheme', 'scheme name', 'name', 'scheme'],
        'priority'          => ['priority ranking', 'priority ranking (for fxbis)', 'priority'],
        'natstate'          => ['national/state-specific', 'national/state specific', 'national / state',
                                'scheme type', 'level', 'national/state'],
        'feasibility'       => ['feasibility ranking', 'feasibility'],
        'beneficiary_type'  => ['beneficiary type', 'beneficiary types', 'beneficiary'],
        'target_group'      => ['target group', 'target groups', 'target population'],
        'theme'             => ['theme', 'themes', 'sector'],
        'eligibility'       => ['eligibility criteria', 'eligibility'],
        'benefits'          => ['benefits', 'benefit', 'benefit details'],
        'how_to_apply'      => ['how to apply', 'application process', 'how to apply?'],
        'service_approach'  => ['service approach', 'approach'],
        'source_link'       => ['source link', 'source', 'source url', 'official link', 'link'],
        'state'             => ['state'],
        'scheme_code'       => ['scheme code', 'code'],
        'short_name'        => ['short name', 'acronym', 'scheme short name'],
        'ministry'          => ['implementing ministry', 'ministry'],
        'department'        => ['implementing department', 'department'],
        'scheme_status'     => ['scheme status', 'status'],
        'active_enrollment' => ['active enrollment', 'active enrolment'],
        'helpline'          => ['helpline', 'helpline number'],
        'portal_url'        => ['portal url', 'official portal', 'portal'],
    ];

    /** Fields the importer understands, with their screen labels. */
    public const TARGET_FIELDS = [
        'source_row_no'     => 'SL. No.',
        'scheme_code'       => 'Scheme Code',
        'name'              => 'Name of the Scheme',
        'short_name'        => 'Short Name / Acronym',
        'natstate'          => 'National / State-Specific',
        'state'             => 'State',
        'priority'          => 'Priority Ranking',
        'feasibility'       => 'Feasibility Ranking',
        'beneficiary_type'  => 'Beneficiary Type',
        'target_group'      => 'Target Group',
        'theme'             => 'Theme',
        'eligibility'       => 'Eligibility Criteria',
        'benefits'          => 'Benefits',
        'how_to_apply'      => 'How to Apply',
        'service_approach'  => 'Service Approach',
        'source_link'       => 'Source Link',
        'ministry'          => 'Implementing Ministry',
        'department'        => 'Implementing Department',
        'scheme_status'     => 'Scheme Status',
        'active_enrollment' => 'Active Enrollment',
        'helpline'          => 'Helpline Number',
        'portal_url'        => 'Official Portal URL',
    ];

    // =================================================================
    // Batch lifecycle
    // =================================================================

    public function createBatch(array $file, ?string $sheetName): int
    {
        return $this->db->insert('scheme_import_batches', [
            'batch_code'    => $this->nextCode('batch_code', 'JK-IMP-', 5),
            'original_name' => $file['original_name'],
            'stored_path'   => $file['stored_name'],
            'sheet_name'    => $sheetName,
            'status'        => 'Uploaded',
            'imported_by'   => Auth::id(),
        ]);
    }

    public function batch(int $id): ?array
    {
        return $this->db->selectOne(
            'SELECT b.*, u.full_name AS imported_by_name
               FROM scheme_import_batches b
               LEFT JOIN users u ON u.id = b.imported_by
              WHERE b.id = :id',
            ['id' => $id]
        );
    }

    public function batches(int $limit = 25): array
    {
        return $this->db->select(
            'SELECT b.*, u.full_name AS imported_by_name
               FROM scheme_import_batches b
               LEFT JOIN users u ON u.id = b.imported_by
              ORDER BY b.created_at DESC LIMIT ' . (int)$limit
        );
    }

    public function batchRows(int $batchId, ?string $status = null, int $limit = 1000): array
    {
        $sql = 'SELECT * FROM scheme_import_rows WHERE batch_id = :id';
        $bindings = ['id' => $batchId];
        if ($status !== null) {
            $sql .= ' AND status = :status';
            $bindings['status'] = $status;
        }
        return $this->db->select($sql . ' ORDER BY row_no LIMIT ' . (int)$limit, $bindings);
    }

    public function rowCounts(int $batchId): array
    {
        $rows = $this->db->select(
            'SELECT status, COUNT(*) AS n FROM scheme_import_rows WHERE batch_id = :id GROUP BY status',
            ['id' => $batchId]
        );
        $out = ['Valid' => 0, 'Duplicate' => 0, 'Error' => 0, 'Inserted' => 0, 'Updated' => 0, 'Skipped' => 0];
        foreach ($rows as $row) {
            $out[$row['status']] = (int)$row['n'];
        }
        return $out;
    }

    // =================================================================
    // Parse and validate
    // =================================================================

    /** Guess the column mapping from the workbook's own headings. */
    public function autoMap(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $header) {
            $normalised = $this->normaliseHeader($header);
            if ($normalised === '') {
                continue;
            }
            foreach (self::COLUMN_ALIASES as $field => $aliases) {
                if (isset($map[$field])) {
                    continue;
                }
                foreach ($aliases as $alias) {
                    // Exact match first, then a contains match, so
                    // "Priority Ranking (For FXBIS)" still lands on priority.
                    if ($normalised === $alias || str_starts_with($normalised, $alias)) {
                        $map[$field] = $index;
                        continue 3;
                    }
                }
            }
        }
        return $map;
    }

    private function normaliseHeader(string $header): string
    {
        $header = mb_strtolower(trim($header));
        $header = preg_replace('/\s+/', ' ', $header);
        return (string)$header;
    }

    /**
     * Read the workbook, group merged multi-row schemes, validate, and store
     * one scheme_import_rows record per prospective scheme.
     */
    public function parseAndValidate(int $batchId, string $filePath, ?string $sheetName, array $columnMap): array
    {
        $reader = new XlsxReader($filePath);
        $rows   = $reader->rows($sheetName, 20000, true);   // merges expanded

        if (count($rows) < 2) {
            throw new \RuntimeException('The sheet has no data rows below the header.');
        }

        $this->db->execute('DELETE FROM scheme_import_rows WHERE batch_id = :id', ['id' => $batchId]);

        $get = function (array $row, string $field) use ($columnMap) {
            if (!isset($columnMap[$field])) {
                return null;
            }
            $value = $row[$columnMap[$field]] ?? null;
            if ($value === null) {
                return null;
            }
            $value = trim((string)$value);
            return $value === '' ? null : $value;
        };

        // Group by SL.No. where present, otherwise by scheme name, so a scheme
        // whose columns are merged across several rows becomes one record and
        // its multiple Beneficiary Types / Themes are collected together.
        $groups = [];
        $order  = [];
        foreach (array_slice($rows, 1) as $offset => $row) {
            $rowNo = $offset + 2;                       // 1-based, header is row 1
            $name  = $get($row, 'name');
            $sl    = $get($row, 'source_row_no');

            if ($name === null && $sl === null) {
                continue;                               // genuinely blank line
            }
            $key = $sl !== null ? 'sl:' . $sl : 'nm:' . mb_strtolower((string)$name);

            if (!isset($groups[$key])) {
                $groups[$key] = ['first_row' => $rowNo, 'rows' => []];
                $order[] = $key;
            }
            $groups[$key]['rows'][] = $row;
        }

        $multi = ['beneficiary_type', 'target_group', 'theme', 'service_approach', 'natstate'];
        $counts = ['total' => 0, 'valid' => 0, 'duplicate' => 0, 'error' => 0];

        foreach ($order as $key) {
            $group = $groups[$key];
            $first = $group['rows'][0];
            $counts['total']++;

            $record = [];
            foreach (array_keys(self::TARGET_FIELDS) as $field) {
                if (in_array($field, $multi, true)) {
                    // Collect the distinct values across every row of the merge.
                    $values = [];
                    foreach ($group['rows'] as $r) {
                        $v = $get($r, $field);
                        if ($v !== null && !in_array($v, $values, true)) {
                            $values[] = $v;
                        }
                    }
                    $record[$field] = $values;
                } else {
                    $record[$field] = $get($first, $field);
                }
            }

            [$status, $message, $schemeId, $action] = $this->validateRecord($record);

            if ($status === 'Valid') {
                $counts['valid']++;
            } elseif ($status === 'Duplicate') {
                $counts['duplicate']++;
            } else {
                $counts['error']++;
            }

            $this->db->insert('scheme_import_rows', [
                'batch_id'    => $batchId,
                'row_no'      => $group['first_row'],
                'scheme_name' => $record['name'] !== null ? mb_substr((string)$record['name'], 0, 300) : null,
                'raw_data'    => json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'status'      => $status,
                'message'     => $message !== null ? mb_substr($message, 0, 500) : null,
                'scheme_id'   => $schemeId,
                'action'      => $action,
            ]);
        }

        $this->db->update('scheme_import_batches', [
            'column_map' => json_encode($columnMap),
            'sheet_name' => $sheetName,
            'total_rows' => $counts['total'],
            'valid_rows' => $counts['valid'],
            'failed_rows'=> $counts['error'],
            'skipped_rows'=> $counts['duplicate'],
            'status'     => 'Validated',
            'message'    => sprintf('%d scheme record(s) read: %d ready, %d possible duplicate(s), %d with errors.',
                                    $counts['total'], $counts['valid'], $counts['duplicate'], $counts['error']),
        ], ['id' => $batchId]);

        return $counts;
    }

    /**
     * Validate one prospective scheme.
     * @return array [status, message, existingSchemeId, action]
     */
    private function validateRecord(array $record): array
    {
        $name = trim((string)($record['name'] ?? ''));

        if ($name === '') {
            return ['Error', 'Scheme name is missing.', null, 'skip'];
        }
        if (mb_strlen($name) > 300) {
            return ['Error', 'Scheme name exceeds 300 characters.', null, 'skip'];
        }

        // The level is what decides which State a scheme belongs to, so an
        // unrecognisable value is an error rather than a silent default.
        [$level, $stateId, $stateIds, $levelNote] = $this->resolveLevel($record);
        if ($level === null) {
            return ['Error', $levelNote ?: 'The National/State-Specific column could not be interpreted.', null, 'skip'];
        }

        $schemeModel = new Scheme();
        $duplicates = $schemeModel->findDuplicates($name, $record['scheme_code'] ?? null, $level, $stateId);

        if ($duplicates) {
            $existing = $duplicates[0];
            return [
                'Duplicate',
                sprintf('Matches existing scheme %s (%s). Choose Update to overwrite it, or Skip to leave it unchanged.',
                        $existing['scheme_code'], $existing['name']),
                (int)$existing['id'],
                'skip',
            ];
        }

        $warnings = [];
        if (empty($record['theme'])) {
            $warnings[] = 'no Theme supplied';
        }
        if (empty($record['beneficiary_type'])) {
            $warnings[] = 'no Beneficiary Type supplied';
        }
        if (empty($record['source_link'])) {
            $warnings[] = 'no Source Link supplied';
        }
        if ($levelNote) {
            $warnings[] = $levelNote;
        }

        return ['Valid', $warnings ? 'Will be imported (' . implode('; ', $warnings) . ').' : 'Ready to import.', null, 'insert'];
    }

    /**
     * Interpret the National/State column, which in the supplied workbook
     * holds either "National", a State name, or "National + <State>".
     *
     * @return array [level|null, primaryStateId, stateIds, note]
     */
    public function resolveLevel(array $record): array
    {
        $raw = $record['natstate'] ?? null;
        $text = is_array($raw) ? implode(' / ', $raw) : (string)($raw ?? '');
        $explicitState = $record['state'] ?? null;
        if (is_array($explicitState)) {
            $explicitState = $explicitState[0] ?? null;
        }
        $haystack = mb_strtolower(trim($text . ' ' . (string)$explicitState));

        if ($haystack === '') {
            return [null, null, [], 'The National/State-Specific column is blank.'];
        }

        $states = $this->db->select('SELECT id, name, state_code FROM states');
        $found = [];
        foreach ($states as $state) {
            if (str_contains($haystack, mb_strtolower($state['name']))) {
                $found[(int)$state['id']] = (int)$state['id'];
            }
        }
        $hasNational = str_contains($haystack, 'national') || str_contains($haystack, 'centrally sponsored');

        if ($hasNational && $found) {
            return ['National+State', reset($found), array_values($found), null];
        }
        if ($hasNational) {
            return ['National', null, [], null];
        }
        if ($found) {
            return ['State', reset($found), array_values($found), null];
        }
        return [null, null, [], 'The value "' . mb_substr($text, 0, 60) . '" does not match any State in the master.'];
    }

    // =================================================================
    // Import
    // =================================================================

    /**
     * Write the rows the user approved.
     *
     * @param array $actions rowId => 'insert'|'update'|'skip'
     */
    public function process(int $batchId, array $actions): array
    {
        $batch = $this->batch($batchId);
        if (!$batch) {
            throw new \RuntimeException('Import batch not found.');
        }

        $rows = $this->db->select(
            "SELECT * FROM scheme_import_rows WHERE batch_id = :id AND status IN ('Valid','Duplicate')",
            ['id' => $batchId]
        );

        $result = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];
        $schemeModel = new Scheme();
        $userId = Auth::id();

        foreach ($rows as $row) {
            $rowId  = (int)$row['id'];
            $action = $actions[$rowId] ?? $row['action'];

            if ($action === 'skip') {
                $this->db->update('scheme_import_rows', ['status' => 'Skipped', 'action' => 'skip'], ['id' => $rowId]);
                $result['skipped']++;
                continue;
            }

            $record = json_list($row['raw_data']);

            try {
                // Each scheme is written in its own transaction, so one bad
                // row cannot roll back the rest of the import.
                $schemeId = $this->db->transaction(function () use ($record, $action, $row, $schemeModel, $userId) {
                    return $this->writeScheme($record, $action, $row['scheme_id'] ? (int)$row['scheme_id'] : null, $schemeModel, $userId);
                });

                $this->db->update('scheme_import_rows', [
                    'status'    => $action === 'update' ? 'Updated' : 'Inserted',
                    'scheme_id' => $schemeId,
                    'action'    => $action,
                    'message'   => $action === 'update' ? 'Existing scheme updated.' : 'Scheme created.',
                ], ['id' => $rowId]);

                $result[$action === 'update' ? 'updated' : 'inserted']++;
            } catch (\Throwable $e) {
                $this->db->update('scheme_import_rows', [
                    'status'  => 'Error',
                    'message' => mb_substr($e->getMessage(), 0, 500),
                ], ['id' => $rowId]);
                $result['failed']++;
                $result['errors'][] = sprintf('Row %d (%s): %s', (int)$row['row_no'], $row['scheme_name'], $e->getMessage());
            }
        }

        $this->db->update('scheme_import_batches', [
            'inserted_rows' => $result['inserted'],
            'updated_rows'  => $result['updated'],
            'skipped_rows'  => $result['skipped'],
            'failed_rows'   => $result['failed'],
            'status'        => $result['failed'] > 0 && $result['inserted'] + $result['updated'] === 0 ? 'Failed' : 'Imported',
            'message'       => sprintf('%d inserted, %d updated, %d skipped, %d failed.',
                                       $result['inserted'], $result['updated'], $result['skipped'], $result['failed']),
            'completed_at'  => date('Y-m-d H:i:s'),
        ], ['id' => $batchId]);

        return $result;
    }

    /** Create or update one scheme plus its relations from an import record. */
    private function writeScheme(array $record, string $action, ?int $existingId, Scheme $schemeModel, ?int $userId): int
    {
        $name = trim((string)$record['name']);
        [$level, $stateId, $stateIds] = $this->resolveLevel($record);
        if ($level === null) {
            throw new \RuntimeException('The scheme level could not be resolved.');
        }

        $eligibility = $this->joinMulti($record['eligibility'] ?? null);
        $benefits    = $this->joinMulti($record['benefits'] ?? null);
        $apply       = $this->joinMulti($record['how_to_apply'] ?? null);
        $targetGroup = $this->joinMulti($record['target_group'] ?? null);

        $data = [
            'name'                 => mb_substr($name, 0, 300),
            'short_name'           => $this->extractShortName($record['short_name'] ?? null, $name),
            'scheme_level'         => $level,
            'state_id'             => $stateId,
            'target_group_summary' => $targetGroup,
            'eligibility_summary'  => $eligibility,
            'benefit_description'  => $benefits,
            'application_process'  => $apply,
            'source_url'           => $this->extractUrl($record['source_link'] ?? null),
            'portal_url'           => $this->extractUrl($record['portal_url'] ?? null) ?: $this->extractUrl($apply),
            'helpline_number'      => $record['helpline'] ?? null,
            'ministry_name'        => $record['ministry'] ?? null,
            'department_name'      => $record['department'] ?? null,
            'scheme_status'        => $this->normaliseStatus($record['scheme_status'] ?? null),
            'active_enrollment'    => $this->normaliseYesNo($record['active_enrollment'] ?? null, 1),
            'application_mode'     => $this->guessApplicationMode($apply),
            'benefit_type'         => null,
            'service_approach_id'  => $this->resolveServiceApproach($record['service_approach'] ?? null),
            'priority_id'          => $this->resolvePriority($record['priority'] ?? null),
            'feasibility_id'       => $this->resolveFeasibility($record['feasibility'] ?? null),
            'source_row_no'        => isset($record['source_row_no']) && is_numeric($record['source_row_no'])
                                      ? (int)$record['source_row_no'] : null,
            'is_active'            => 1,
            'updated_by'           => $userId,
        ];
        if ($host = parse_url((string)$data['source_url'], PHP_URL_HOST)) {
            $data['source_website'] = mb_substr($host, 0, 200);
        }

        $themeIds = $this->resolveThemes($record['theme'] ?? null);
        $data['theme_id'] = $themeIds[0] ?? null;

        if ($action === 'update' && $existingId) {
            $schemeId = $existingId;
            $data['slug'] = $schemeModel->uniqueSlug($name, $schemeId);
            $this->db->update('schemes', $data, ['id' => $schemeId]);
        } else {
            $data['scheme_code'] = $record['scheme_code'] ?: $schemeModel->nextSchemeCode();
            $data['slug']        = $schemeModel->uniqueSlug($name);
            $data['created_by']  = $userId;
            $schemeId = $this->db->insert('schemes', $data);
        }

        $schemeModel->syncRelations(
            $schemeId,
            $themeIds,
            $this->resolveBeneficiaryTypes($record['beneficiary_type'] ?? null),
            $this->resolveTargetGroups($targetGroup, $eligibility),
            $stateIds
        );

        // Documents named in the scheme's own text.
        $documentIds = $this->resolveDocuments($eligibility . ' ' . $apply);
        $schemeModel->syncDocuments($schemeId, array_map(
            fn($id) => ['document_type_id' => $id, 'is_mandatory' => 1,
                        'remarks' => 'Named in the imported workbook for this scheme'],
            $documentIds
        ));

        if ($benefits) {
            $schemeModel->syncBenefits($schemeId, [[
                'benefit_type' => null,
                'description'  => $benefits,
                'amount'       => null,
                'mode'         => null,
                'frequency'    => null,
            ]]);
        }

        return $schemeId;
    }

    // =================================================================
    // Value resolution helpers
    // =================================================================

    private function joinMulti($value): ?string
    {
        if (is_array($value)) {
            $value = implode("\n", array_filter($value, fn($v) => $v !== null && $v !== ''));
        }
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function extractShortName($given, string $name): ?string
    {
        if ($given) {
            return mb_substr(trim((string)$given), 0, 80);
        }
        if (preg_match_all('/\(([^()]{2,40})\)/', $name, $matches)) {
            foreach (array_reverse($matches[1]) as $candidate) {
                $letters = preg_replace('/[^A-Za-z]/', '', $candidate);
                if ($letters !== '' && strlen($letters) <= 20 && $letters === strtoupper($letters)) {
                    return mb_substr(trim($candidate), 0, 80);
                }
            }
        }
        return null;
    }

    private function extractUrl($text): ?string
    {
        if (!$text) {
            return null;
        }
        if (preg_match('#https?://[^\s,;)"\']+#i', (string)$text, $m)) {
            return mb_substr(rtrim($m[0], '.,);'), 0, 500);
        }
        return null;
    }

    private function normaliseStatus($value): string
    {
        $text = mb_strtolower(trim((string)$value));
        if (str_contains($text, 'discontinu')) {
            return 'Discontinued';
        }
        if (str_contains($text, 'dormant')) {
            return 'Dormant';
        }
        return 'Active';
    }

    private function normaliseYesNo($value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }
        $text = mb_strtolower(trim((string)$value));
        if (in_array($text, ['no', '0', 'false', 'n'], true)) {
            return 0;
        }
        if (in_array($text, ['yes', '1', 'true', 'y', 'active enrolment', 'active enrollment'], true)) {
            return 1;
        }
        return $default;
    }

    private function guessApplicationMode(?string $text): string
    {
        $t = mb_strtolower((string)$text);
        $online  = str_contains($t, 'online') || str_contains($t, 'portal') || str_contains($t, 'website');
        $offline = str_contains($t, 'visit') || str_contains($t, 'office') || str_contains($t, 'anganwadi')
                || str_contains($t, 'csc') || str_contains($t, 'in person');
        if ($online && $offline) return 'Both';
        if ($online) return 'Online';
        return 'Offline';
    }

    private function resolveThemes($value): array
    {
        $values = is_array($value) ? $value : ($value ? [$value] : []);
        if (!$values) {
            return [];
        }
        $ids = [];
        foreach ($values as $raw) {
            // Composite headings such as "Health and Education" or
            // "Social Security / Livelihood Support" map onto several themes.
            foreach (preg_split('#\s*(?:/|,|\band\b|&)\s*#i', (string)$raw) as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }
                $id = $this->db->scalar(
                    'SELECT id FROM scheme_themes WHERE LOWER(name) = :n OR LOWER(code) = :c LIMIT 1',
                    ['n' => mb_strtolower($part), 'c' => mb_strtolower($part)]
                );
                if (!$id) {
                    $id = $this->db->scalar(
                        'SELECT id FROM scheme_themes WHERE LOWER(name) LIKE :n ORDER BY CHAR_LENGTH(name) LIMIT 1',
                        ['n' => '%' . mb_strtolower($part) . '%']
                    );
                }
                if ($id && !in_array((int)$id, $ids, true)) {
                    $ids[] = (int)$id;
                }
            }
        }
        return $ids;
    }

    private function resolveBeneficiaryTypes($value): array
    {
        $values = is_array($value) ? $value : ($value ? [$value] : []);
        $ids = [];
        foreach ($values as $raw) {
            $raw = trim((string)$raw);
            if ($raw === '') {
                continue;
            }
            $id = $this->db->scalar(
                'SELECT id FROM beneficiary_types WHERE LOWER(name) = :n OR LOWER(code) = :c LIMIT 1',
                ['n' => mb_strtolower($raw), 'c' => mb_strtolower($raw)]
            );
            if (!$id) {
                // "PwD/Chronic Diseases" and similar: match on the first word.
                $first = preg_split('#[\s/,]+#', $raw)[0] ?? $raw;
                $id = $this->db->scalar(
                    'SELECT id FROM beneficiary_types WHERE LOWER(name) LIKE :n ORDER BY CHAR_LENGTH(name) LIMIT 1',
                    ['n' => mb_strtolower($first) . '%']
                );
            }
            if ($id && !in_array((int)$id, $ids, true)) {
                $ids[] = (int)$id;
            }
        }
        return $ids;
    }

    /** Keyword-match the curated target groups against the scheme's text. */
    private function resolveTargetGroups(?string $targetText, ?string $eligibilityText): array
    {
        $blob = mb_strtolower(trim((string)$targetText . ' ' . (string)$eligibilityText));
        if ($blob === '') {
            return [];
        }
        $groups = $this->db->select('SELECT id, name FROM target_groups WHERE is_active = 1');
        $ids = [];
        foreach ($groups as $group) {
            // Match on each significant word of the group's own name.
            $words = array_filter(preg_split('/\s+/', mb_strtolower($group['name'])),
                                  fn($w) => mb_strlen($w) > 4);
            foreach ($words as $word) {
                if (str_contains($blob, $word)) {
                    $ids[] = (int)$group['id'];
                    break;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    private function resolveServiceApproach($value): ?int
    {
        $values = is_array($value) ? $value : ($value ? [$value] : []);
        foreach ($values as $raw) {
            $raw = trim((string)$raw);
            if ($raw === '') {
                continue;
            }
            $id = $this->db->scalar(
                'SELECT id FROM service_approaches WHERE LOWER(name) = :n LIMIT 1',
                ['n' => mb_strtolower($raw)]
            );
            if (!$id) {
                $id = $this->db->scalar(
                    'SELECT id FROM service_approaches WHERE LOWER(name) LIKE :n LIMIT 1',
                    ['n' => '%' . mb_strtolower($raw) . '%']
                );
            }
            if ($id) {
                return (int)$id;
            }
        }
        return null;
    }

    private function resolvePriority($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $text = trim((string)$value);
        if (preg_match('/(\d+)/', $text, $m)) {
            $id = $this->db->scalar('SELECT id FROM priority_levels WHERE rank_value = :r', ['r' => (int)$m[1]]);
            if ($id) {
                return (int)$id;
            }
        }
        $id = $this->db->scalar('SELECT id FROM priority_levels WHERE LOWER(name) LIKE :n LIMIT 1',
            ['n' => '%' . mb_strtolower($text) . '%']);
        return $id ? (int)$id : null;
    }

    private function resolveFeasibility($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (preg_match('/([1-5])/', (string)$value, $m)) {
            $id = $this->db->scalar('SELECT id FROM feasibility_levels WHERE rank_value = :r', ['r' => (int)$m[1]]);
            return $id ? (int)$id : null;
        }
        return null;
    }

    /** Document types whose name appears in the scheme's text. */
    private function resolveDocuments(string $text): array
    {
        $blob = mb_strtolower($text);
        if (trim($blob) === '') {
            return [];
        }

        // Reuse the same phrase list the seed generator used, so an imported
        // scheme picks up documents the same way the seeded ones did.
        $phrases = [
            'AADHAAR' => ['aadhaar', 'aadhar'],
            'VOTER_ID' => ['voter id', 'epic'],
            'PAN' => ['pan card'],
            'PHOTO' => ['photograph', 'passport size'],
            'BIRTH_CERT' => ['birth certificate'],
            'AGE_PROOF' => ['age proof', 'proof of age'],
            'DEATH_CERT' => ['death certificate'],
            'RESIDENCE' => ['residence proof', 'domicile', 'residence certificate'],
            'ADDRESS_PROOF' => ['address proof'],
            'RATION_CARD' => ['ration card'],
            'BPL_CERT' => ['bpl certificate'],
            'INCOME_CERT' => ['income certificate', 'income proof'],
            'CASTE_CERT' => ['caste certificate'],
            'MINORITY_CERT' => ['minority certificate'],
            'BANK_PASSBOOK' => ['passbook'],
            'BANK_DETAILS' => ['bank account', 'bank details', 'cancelled cheque'],
            'DISABILITY_CERT' => ['disability certificate'],
            'UDID' => ['udid'],
            'MEDICAL_RECORD' => ['medical record', 'medical report', 'prescription'],
            'PREGNANCY_PROOF' => ['proof of pregnancy', 'pregnancy test', 'mcp card'],
            'ABHA' => ['abha'],
            'LAND_RECORD' => ['land record', 'land document', 'khatian', 'patta'],
            'KISAN_CARD' => ['kisan credit card'],
            'SOIL_CARD' => ['soil health card'],
            'SCHOOL_ID' => ['school id', 'college id', 'student id'],
            'MARKSHEET' => ['marksheet', 'mark sheet', 'educational certificate'],
            'BONAFIDE' => ['bonafide', 'bona fide'],
            'FEE_RECEIPT' => ['fee receipt'],
            'MARRIAGE_CERT' => ['marriage certificate'],
            'WIDOW_CERT' => ['widow certificate'],
            'WEAVER_ID' => ['weaver id', 'handloom card'],
            'WORKER_ID' => ['labour card', 'bocw', 'welfare board registration'],
            'ESHRAM' => ['e-shram', 'eshram'],
            'JOB_CARD' => ['job card'],
            'ELECTRICITY' => ['electricity bill'],
            'PPO' => ['pension payment order'],
            'SELF_DECL' => ['self declaration', 'self-declaration'],
            'GUARDIAN_CONSENT' => ['guardian consent'],
            'FIR_COPY' => ['police report', 'police complaint'],
            'ENTERPRISE_DOC' => ['udyam', 'enterprise registration'],
        ];

        $codes = [];
        foreach ($phrases as $code => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($blob, $needle)) {
                    $codes[] = $code;
                    break;
                }
            }
        }
        if (!$codes) {
            return [];
        }

        [$holders, $bindings] = Database::inClause($codes, 'dc');
        $rows = $this->db->select("SELECT id FROM document_types WHERE code IN ({$holders})", $bindings);
        return array_map('intval', array_column($rows, 'id'));
    }

    public function cancel(int $batchId): void
    {
        $this->db->update('scheme_import_batches',
            ['status' => 'Cancelled', 'message' => 'Cancelled by the user before import.', 'completed_at' => date('Y-m-d H:i:s')],
            ['id' => $batchId]);
    }
}
