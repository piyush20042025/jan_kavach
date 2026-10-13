<?php
/**
 * Audit trail listing (specification section 29).
 *
 * @var JanKavach\Core\Paginator $paginator
 * @var array $filters, $modules, $actions, $entityTypes, $users, $summary, $failedLogins
 */
$pageSubtitle = 'Who changed what, when, and from where';

ob_start(); ?>
    <div class="btn-group">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i>Export
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item jk-export" data-format="excel" href="#"><i class="bi bi-file-earmark-excel me-2"></i>Excel</a></li>
            <li><a class="dropdown-item jk-export" data-format="csv" href="#"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
            <li><a class="dropdown-item jk-export" data-format="pdf" target="_blank" href="#"><i class="bi bi-file-earmark-pdf me-2"></i>PDF / Print</a></li>
        </ul>
    </div>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<?php if (!empty($summary)): ?>
    <div class="jk-card">
        <div class="jk-card__body jk-card__body--tight">
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($summary as $entry): ?>
                    <a href="<?= e(query_url(['action' => $entry['action'], 'page' => null])) ?>"
                       class="jk-chip <?= ($filters['action'] ?? '') === $entry['action'] ? 'jk-chip--primary' : '' ?>">
                        <?= e($entry['action']) ?> <strong class="ms-1"><?= num($entry['n']) ?></strong>
                    </a>
                <?php endforeach; ?>
                <?php if (!empty($filters['action'])): ?>
                    <a href="<?= e(query_url(['action' => null, 'page' => null])) ?>" class="jk-chip text-danger">
                        <i class="bi bi-x"></i>Clear
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<form method="get" class="jk-filter-bar">
    <div class="row g-2">
        <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label" for="search">Search</label>
            <input type="search" class="form-control form-control-sm" id="search" name="search"
                   value="<?= e($filters['search'] ?? '') ?>" placeholder="User, description or record">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="user_id">User</label>
            <select class="form-select form-select-sm" id="user_id" name="user_id" data-auto-submit>
                <option value="">All users</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= (int)$user['id'] ?>" <?= (int)($filters['user_id'] ?? 0) === (int)$user['id'] ? 'selected' : '' ?>>
                        <?= e($user['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="module">Module</label>
            <select class="form-select form-select-sm" id="module" name="module" data-auto-submit>
                <option value="">All modules</option>
                <?php foreach ($modules as $module): ?>
                    <option value="<?= e($module) ?>" <?= ($filters['module'] ?? '') === $module ? 'selected' : '' ?>>
                        <?= e($module) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="action">Action</label>
            <select class="form-select form-select-sm" id="action" name="action" data-auto-submit>
                <option value="">All actions</option>
                <?php foreach ($actions as $action): ?>
                    <option value="<?= e($action) ?>" <?= ($filters['action'] ?? '') === $action ? 'selected' : '' ?>>
                        <?= e($action) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="entity_type">Record Type</label>
            <select class="form-select form-select-sm" id="entity_type" name="entity_type" data-auto-submit>
                <option value="">All types</option>
                <?php foreach ($entityTypes as $type): ?>
                    <option value="<?= e($type) ?>" <?= ($filters['entity_type'] ?? '') === $type ? 'selected' : '' ?>>
                        <?= e($type) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="date_from">From</label>
            <input type="date" class="form-control form-control-sm" id="date_from" name="date_from"
                   value="<?= e($filters['date_from'] ?? '') ?>" data-auto-submit>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="date_to">To</label>
            <input type="date" class="form-control form-control-sm" id="date_to" name="date_to"
                   value="<?= e($filters['date_to'] ?? '') ?>" data-date-min-from="date_from" data-auto-submit>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="ip_address">IP Address</label>
            <input type="text" class="form-control form-control-sm" id="ip_address" name="ip_address"
                   value="<?= e($filters['ip_address'] ?? '') ?>" placeholder="e.g. 10.0.0.5">
        </div>
        <div class="col-12 col-lg-4 d-flex align-items-end gap-2">
            <div class="form-check me-2">
                <input class="form-check-input" type="checkbox" id="changes_only" name="changes_only" value="1"
                       data-auto-submit <?= !empty($filters['changes_only']) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="changes_only">With recorded changes only</label>
            </div>
            <button type="submit" class="btn btn-sm btn-primary">Search</button>
            <a href="<?= e(url('/audit-logs')) ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </div>
</form>

<div class="jk-card">
    <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-clock-history"></i>Audit Trail</h3></div>
    <div class="jk-card__body jk-card__body--flush">
        <?php if ($paginator->isEmpty()): ?>
            <div class="jk-empty">
                <i class="bi bi-clock-history"></i>
                <h5>No audit entries found</h5>
                <p>Nothing matched the current filters.</p>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap">
                <table class="jk-table">
                    <thead>
                        <tr>
                            <th><?= sort_link('Date & Time', 'time', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('User', 'user', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Module', 'module', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Action', 'action', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Record', 'entity', $sortKey, $sortDir) ?></th>
                            <th style="min-width:230px">Description</th>
                            <th><?= sort_link('IP', 'ip', $sortKey, $sortDir) ?></th>
                            <th class="no-print"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginator->items as $entry): ?>
                            <?php $hasChanges = !empty($entry['old_values']) || !empty($entry['new_values']); ?>
                            <tr>
                                <td class="nowrap small">
                                    <?= fmt_datetime($entry['created_at']) ?>
                                    <div class="text-subtle tiny"><?= time_ago($entry['created_at']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold" style="font-size:.83rem">
                                        <?= e($entry['user_name'] ?? 'System') ?>
                                    </div>
                                    <?php if (!empty($entry['role_name'])): ?>
                                        <div class="text-muted tiny"><?= e($entry['role_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="jk-chip"><?= e($entry['module']) ?></span></td>
                                <td>
                                    <?= badge($entry['action'], match ($entry['action']) {
                                        'Created', 'Approved'      => 'success',
                                        'Deleted', 'Rejected'      => 'danger',
                                        'Failed Login'             => 'warning',
                                        'Login', 'Logout'          => 'info',
                                        'Modified', 'Password Reset' => 'primary',
                                        default                    => 'secondary',
                                    }) ?>
                                </td>
                                <td class="small">
                                    <?php if (!empty($entry['entity_type'])): ?>
                                        <span class="text-muted"><?= e($entry['entity_type']) ?></span>
                                        <?php if (!empty($entry['entity_label'])): ?>
                                            <div class="tiny"><?= e(str_limit($entry['entity_label'], 30)) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-subtle">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= e(str_limit($entry['description'] ?? '-', 110)) ?></td>
                                <td class="text-subtle tiny nowrap"><?= e($entry['ip_address'] ?? '-') ?></td>
                                <td class="text-end no-print">
                                    <a href="<?= e(url('/audit-logs/' . (int)$entry['id'])) ?>"
                                       class="btn btn-sm btn-outline-secondary"
                                       data-bs-toggle="tooltip"
                                       title="<?= $hasChanges ? 'See what changed' : 'View entry' ?>">
                                        <i class="bi bi-<?= $hasChanges ? 'file-diff' : 'eye' ?>"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if (!$paginator->isEmpty()): ?>
        <div class="jk-card__foot">
            <?php $recordNoun = 'entry'; require VIEW_PATH . '/partials/pagination.php'; ?>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($failedLogins)): ?>
    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-shield-exclamation text-warning"></i>Recent Failed Sign-in Attempts</h3>
        </div>
        <div class="jk-card__body jk-card__body--flush">
            <div class="jk-table-wrap">
                <table class="jk-table jk-table--compact">
                    <thead><tr><th>When</th><th>Username Tried</th><th>IP Address</th><th>Reason</th></tr></thead>
                    <tbody>
                        <?php foreach ($failedLogins as $attempt): ?>
                            <tr>
                                <td class="nowrap small"><?= fmt_datetime($attempt['attempted_at']) ?></td>
                                <td><code class="small"><?= e($attempt['username']) ?></code></td>
                                <td class="small"><?= e($attempt['ip_address']) ?></td>
                                <td class="text-muted small"><?= e($attempt['reason'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="jk-card__foot text-muted small">
            <i class="bi bi-info-circle me-1"></i>
            Accounts lock automatically after <?= (int)(setting('max_login_attempts') ?? 5) ?> failed attempts
            for <?= (int)(setting('lockout_minutes') ?? 15) ?> minutes. Attempts are counted per username and
            per IP address.
        </div>
    </div>
<?php endif; ?>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
  document.querySelectorAll('.jk-export').forEach(function (link) {
    link.addEventListener('click', function () {
      const params = new URLSearchParams(location.search);
      params.set('format', link.dataset.format);
      link.href = JK.url('/audit-logs/export') + '?' + params.toString();
    });
  });
</script>
<?php \JanKavach\Core\View::endSection(); ?>
