<?php
/**
 * One audit entry, with the before/after values where they were captured.
 * @var array $entry, $related
 */
$pageSubtitle = $entry['module'] . ' &middot; ' . $entry['action'];

$old = $entry['old_decoded'] ?? [];
$new = $entry['new_decoded'] ?? [];
$keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));

ob_start(); ?>
    <a href="<?= e(url('/audit-logs')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Audit Trail
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<div class="row g-3">
<div class="col-lg-5">
    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-info-circle"></i>Entry Details</h3>
            <?= badge($entry['action'], match ($entry['action']) {
                'Created', 'Approved' => 'success',
                'Deleted', 'Rejected' => 'danger',
                'Failed Login'        => 'warning',
                default               => 'secondary',
            }) ?>
        </div>
        <div class="jk-card__body">
            <div class="jk-dl">
                <div class="jk-dl__row"><div class="jk-dl__key">Entry</div>
                    <div class="jk-dl__val"><code>#<?= (int)$entry['id'] ?></code></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Date &amp; Time</div>
                    <div class="jk-dl__val">
                        <?= fmt_datetime($entry['created_at']) ?>
                        <span class="d-block text-subtle tiny"><?= time_ago($entry['created_at']) ?></span>
                    </div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">User</div>
                    <div class="jk-dl__val">
                        <?php if (!empty($entry['user_id'])): ?>
                            <a href="<?= e(url('/users/' . (int)$entry['user_id'])) ?>">
                                <?= e($entry['user_name'] ?? 'Unknown') ?>
                            </a>
                        <?php else: ?>
                            <?= e($entry['user_name'] ?? 'System') ?>
                        <?php endif; ?>
                        <?php if (!empty($entry['role_name'])): ?>
                            <span class="d-block text-subtle tiny"><?= e($entry['role_name']) ?></span>
                        <?php endif; ?>
                    </div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Module</div>
                    <div class="jk-dl__val"><?= e($entry['module']) ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Action</div>
                    <div class="jk-dl__val"><?= e($entry['action']) ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Record</div>
                    <div class="jk-dl__val">
                        <?php if (!empty($entry['entity_type'])): ?>
                            <?= e($entry['entity_type']) ?>
                            <?php if (!empty($entry['entity_id'])): ?>
                                <code>#<?= (int)$entry['entity_id'] ?></code>
                            <?php endif; ?>
                            <?php if (!empty($entry['entity_label'])): ?>
                                <span class="d-block text-subtle tiny"><?= e($entry['entity_label']) ?></span>
                            <?php endif; ?>
                        <?php else: ?>-<?php endif; ?>
                    </div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">IP Address</div>
                    <div class="jk-dl__val"><code><?= e($entry['ip_address'] ?? '-') ?></code></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Browser</div>
                    <div class="jk-dl__val" style="word-break:break-all">
                        <span class="text-muted tiny"><?= e($entry['user_agent'] ?? '-') ?></span>
                    </div></div>
            </div>

            <?php if (!empty($entry['description'])): ?>
                <div class="mt-3">
                    <div class="text-muted small fw-semibold mb-1">Description</div>
                    <div class="jk-prose"><?= nl2br_e($entry['description']) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="col-lg-7">
    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-file-diff"></i>What Changed</h3>
            <?php if ($keys): ?>
                <span class="badge bg-secondary"><?= count($keys) ?> field(s)</span>
            <?php endif; ?>
        </div>
        <div class="jk-card__body <?= empty($keys) ? '' : 'jk-card__body--flush' ?>">
            <?php if (empty($keys)): ?>
                <p class="text-muted mb-0">
                    No before/after values were captured for this action. Field-level snapshots are kept for
                    record changes; views, logins and exports record only the event itself.
                </p>
            <?php else: ?>
                <div class="jk-table-wrap">
                    <table class="jk-table jk-table--compact">
                        <thead><tr><th style="width:26%">Field</th><th style="width:37%">Before</th><th style="width:37%">After</th></tr></thead>
                        <tbody>
                            <?php foreach ($keys as $key): ?>
                                <?php
                                $before = $old[$key] ?? null;
                                $after  = $new[$key] ?? null;
                                $render = function ($value) {
                                    if ($value === null) return '<span class="text-subtle">(not set)</span>';
                                    if ($value === '')   return '<span class="text-subtle">(empty)</span>';
                                    if (is_array($value)) {
                                        return '<code class="small">' . e(json_encode($value, JSON_UNESCAPED_UNICODE)) . '</code>';
                                    }
                                    if (is_bool($value)) return $value ? 'Yes' : 'No';
                                    return e(str_limit((string)$value, 220));
                                };
                                ?>
                                <tr>
                                    <td class="fw-semibold"><?= e(ucwords(str_replace('_', ' ', (string)$key))) ?></td>
                                    <td class="text-danger small"><?= $render($before) ?></td>
                                    <td class="text-success small"><?= $render($after) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($related)): ?>
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-list-ul"></i>Other Activity on This Record</h3>
                <?php if (!empty($entry['entity_type']) && !empty($entry['entity_id'])): ?>
                    <a href="<?= e(query_url(['entity_type' => $entry['entity_type'],
                                              'entity_id' => (int)$entry['entity_id']], ['edit'])) ?>"
                       class="small">Filter the trail</a>
                <?php endif; ?>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <div class="jk-table-wrap" style="max-height:380px">
                    <table class="jk-table jk-table--compact">
                        <thead><tr><th>When</th><th>User</th><th>Action</th><th>Description</th><th></th></tr></thead>
                        <tbody>
                            <?php foreach ($related as $other): ?>
                                <tr>
                                    <td class="nowrap small"><?= fmt_datetime($other['created_at']) ?></td>
                                    <td class="small"><?= e($other['user_name'] ?? 'System') ?></td>
                                    <td><?= badge($other['action'], 'secondary') ?></td>
                                    <td class="text-muted small"><?= e(str_limit($other['description'] ?? '-', 70)) ?></td>
                                    <td class="text-end">
                                        <a href="<?= e(url('/audit-logs/' . (int)$other['id'])) ?>" class="small">Open</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>
