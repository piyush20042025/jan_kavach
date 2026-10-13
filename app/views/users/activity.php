<?php
/**
 * The signed-in user's own audit trail.
 * @var array $activity
 */
$pageSubtitle = 'Everything you have done in Jan Kavach, most recent first';

ob_start(); ?>
    <a href="<?= e(url('/profile')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>My Profile
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<div class="jk-card">
    <div class="jk-card__head">
        <h3 class="jk-card__title"><i class="bi bi-clock-history"></i>My Activity</h3>
        <span class="text-muted small"><?= count($activity) ?> recent entries</span>
    </div>
    <div class="jk-card__body jk-card__body--flush">
        <?php if (empty($activity)): ?>
            <div class="jk-empty">
                <i class="bi bi-clock-history"></i>
                <h5>Nothing recorded yet</h5>
                <p>Your actions are written to the audit trail as you work.</p>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap">
                <table class="jk-table jk-table--compact">
                    <thead>
                        <tr><th>When</th><th>Module</th><th>Action</th><th>Record</th>
                            <th>Description</th><th>IP Address</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activity as $entry): ?>
                            <tr>
                                <td class="nowrap small">
                                    <?= fmt_datetime($entry['created_at']) ?>
                                    <div class="text-subtle tiny"><?= time_ago($entry['created_at']) ?></div>
                                </td>
                                <td><span class="jk-chip"><?= e($entry['module']) ?></span></td>
                                <td>
                                    <?= badge($entry['action'],
                                        $entry['action'] === 'Deleted' ? 'danger'
                                        : ($entry['action'] === 'Created' ? 'success'
                                        : ($entry['action'] === 'Failed Login' ? 'warning' : 'secondary'))) ?>
                                </td>
                                <td class="small">
                                    <?php if (!empty($entry['entity_type'])): ?>
                                        <span class="text-muted"><?= e($entry['entity_type']) ?></span>
                                        <?php if (!empty($entry['entity_label'])): ?>
                                            <div class="tiny"><?= e(str_limit($entry['entity_label'], 34)) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-subtle">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= e(str_limit($entry['description'] ?? '-', 100)) ?></td>
                                <td class="text-subtle tiny nowrap"><?= e($entry['ip_address'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
