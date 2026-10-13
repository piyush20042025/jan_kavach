<?php
namespace JanKavach\Controllers;

use JanKavach\Core\Auth;
use JanKavach\Core\Controller;
use JanKavach\Core\Paginator;
use JanKavach\Core\Scope;
use JanKavach\Libraries\Exporter;
use JanKavach\Models\AuditLog;
use JanKavach\Models\User;

/**
 * Audit trail viewer (specification section 29).
 */
class AuditController extends Controller
{
    private AuditLog $logs;

    public function __construct($request = null)
    {
        parent::__construct($request);
        $this->logs = new AuditLog();
    }

    public function index(): void
    {
        $filters = $this->auditFilters();
        [$column, $dir, $sortKey] = $this->sort(AuditLog::SORTS, 'time', 'DESC');

        $paginator = $this->logs->paginate(
            $filters,
            Paginator::pageFrom($this->request),
            Paginator::perPageFrom($this->request),
            $column, $dir, $_GET ?? []
        );

        $this->view('audit/index', [
            'pageTitle'   => 'Audit Logs',
            'breadcrumbs' => [['label' => 'Audit Logs']],
            'paginator'   => $paginator,
            'filters'     => $filters,
            'sortKey'     => $sortKey,
            'sortDir'     => $dir,
            'modules'     => $this->logs->modules(),
            'actions'     => $this->logs->actions(),
            'entityTypes' => $this->logs->entityTypes(),
            'users'       => (new User())->allFiltered([], 500),
            'summary'     => $this->logs->summary($filters),
            'failedLogins'=> Auth::isSuperAdmin() ? $this->logs->recentFailedLogins(10) : [],
        ]);
    }

    public function show(string $id): void
    {
        $entry = $this->findOrFail($this->logs->findDetailed((int)$id), 'Audit entry');

        // Re-apply the same visibility rule the listing uses, so a direct
        // link cannot reveal an entry outside the user's scope.
        if (!Auth::isSuperAdmin() && (int)($entry['user_id'] ?? 0) !== Auth::id()) {
            $visible = (int)$this->db->scalar(
                'SELECT COUNT(*) FROM users u WHERE u.id = :uid AND ('
                . (Auth::dataScope() === 'state' ? 'u.state_id = :scope' : 'u.district_id = :scope')
                . ')',
                ['uid' => (int)($entry['user_id'] ?? 0),
                 'scope' => Auth::dataScope() === 'state' ? (Auth::stateId() ?? 0) : (Auth::districtId() ?? 0)]
            );
            if ($visible === 0) {
                \JanKavach\Core\Response::abort(403, 'That audit entry is outside your data scope.');
            }
        }

        $this->view('audit/show', [
            'pageTitle'   => 'Audit Entry',
            'breadcrumbs' => [['label' => 'Audit Logs', 'url' => '/audit-logs'], ['label' => '#' . (int)$id]],
            'entry'       => $entry,
            'related'     => $entry['entity_type'] && $entry['entity_id']
                ? $this->db->select(
                    'SELECT id, action, user_name, description, created_at FROM audit_logs
                      WHERE entity_type = :t AND entity_id = :i AND id <> :id
                      ORDER BY created_at DESC LIMIT 20',
                    ['t' => $entry['entity_type'], 'i' => (int)$entry['entity_id'], 'id' => (int)$id])
                : [],
        ]);
    }

    public function export(): void
    {
        $format  = $this->inOrNull('format', ['excel', 'csv', 'pdf']) ?? 'excel';
        $filters = $this->auditFilters();
        $rows = $this->logs->allFiltered($filters);

        Exporter::send($format, [
            'Date & Time' => fn($r) => fmt_datetime($r['created_at']),
            'User'        => fn($r) => $r['user_name'] ?? 'System',
            'Role'        => fn($r) => $r['role_code'] ?? '-',
            'IP Address'  => fn($r) => $r['ip_address'] ?? '-',
            'Module'      => 'module',
            'Action'      => 'action',
            'Record Type' => fn($r) => $r['entity_type'] ?? '-',
            'Record'      => fn($r) => $r['entity_label'] ?? ($r['entity_id'] ? '#' . $r['entity_id'] : '-'),
            'Description' => fn($r) => $r['description'] ?? '-',
            'Changed From'=> fn($r) => $this->flatten($r['old_values'] ?? null),
            'Changed To'  => fn($r) => $this->flatten($r['new_values'] ?? null),
        ], $rows, 'audit-trail', [
            'title'       => 'Audit Trail',
            'subtitle'    => 'Who changed what, when, and from where',
            'module'      => 'Audit',
            'filters'     => $this->describeFilters($filters),
            'orientation' => 'landscape',
        ]);
    }

    // =================================================================
    // Internals
    // =================================================================

    private function auditFilters(): array
    {
        return [
            'search'       => $this->request->filled('search') ? (string)$this->request->input('search') : null,
            'user_id'      => $this->request->int('user_id'),
            'module'       => $this->request->filled('module') ? (string)$this->request->input('module') : null,
            'action'       => $this->inOrNull('action', $this->logs->actions()),
            'entity_type'  => $this->request->filled('entity_type') ? (string)$this->request->input('entity_type') : null,
            'entity_id'    => $this->request->int('entity_id'),
            'ip_address'   => $this->request->filled('ip_address') ? (string)$this->request->input('ip_address') : null,
            'date_from'    => $this->dateOrNull('date_from'),
            'date_to'      => $this->dateOrNull('date_to'),
            'changes_only' => $this->request->bool('changes_only'),
        ];
    }

    /** Render a JSON change snapshot as readable "field: value" text. */
    private function flatten($json): string
    {
        $values = json_list($json);
        if ($values === []) {
            return '';
        }
        $parts = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            $parts[] = ucwords(str_replace('_', ' ', (string)$key)) . ': ' . str_limit((string)$value, 120);
        }
        return implode(' | ', $parts);
    }

    private function describeFilters(array $filters): array
    {
        $out = [];
        if (!empty($filters['search']))      $out['Search'] = $filters['search'];
        if (!empty($filters['module']))      $out['Module'] = $filters['module'];
        if (!empty($filters['action']))      $out['Action'] = $filters['action'];
        if (!empty($filters['entity_type'])) $out['Record Type'] = $filters['entity_type'];
        if (!empty($filters['user_id'])) {
            $out['User'] = $this->db->scalar('SELECT full_name FROM users WHERE id = :id',
                ['id' => (int)$filters['user_id']]);
        }
        if (!empty($filters['date_from'])) $out['From'] = fmt_date($filters['date_from']);
        if (!empty($filters['date_to']))   $out['To'] = fmt_date($filters['date_to']);
        $out['Data Scope'] = Scope::label() ?: 'National';
        return $out;
    }
}
