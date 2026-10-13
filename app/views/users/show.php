<?php
/**
 * User detail.
 * @var array $user, $usage, $activity
 * @var array|null $performance
 */
$pageSubtitle = $user['user_code'] . ' &middot; ' . $user['role_name'];
$isSelf = (int)$user['id'] === (int)($authUser['id'] ?? 0);

ob_start(); ?>
    <a href="<?= e(url('/users')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>All Users
    </a>
    <?php if (can('user.edit')): ?>
        <a href="<?= e(url('/users/' . (int)$user['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
    <?php endif; ?>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<div class="row g-3">
<div class="col-lg-5">
    <div class="jk-card">
        <div class="jk-card__body">
            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="jk-avatar jk-avatar--lg"><?= e(initials($user['full_name'])) ?></span>
                <div class="min-width-0">
                    <h2 class="h5 mb-1"><?= e($user['full_name']) ?></h2>
                    <div class="d-flex flex-wrap gap-1">
                        <span class="jk-chip jk-chip--primary"><?= e($user['role_name']) ?></span>
                        <?= (int)$user['is_active'] === 1
                            ? '<span class="badge bg-success">Active</span>'
                            : '<span class="badge bg-secondary">Inactive</span>' ?>
                        <?php if ((int)$user['must_change_password'] === 1): ?>
                            <span class="badge bg-warning text-dark">Password change pending</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="jk-dl">
                <div class="jk-dl__row"><div class="jk-dl__key">User ID</div>
                    <div class="jk-dl__val"><code><?= e($user['user_code']) ?></code></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Username</div>
                    <div class="jk-dl__val"><code><?= e($user['username']) ?></code></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Email</div>
                    <div class="jk-dl__val"><?= e($user['email']) ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Mobile</div>
                    <div class="jk-dl__val"><?= e($user['mobile'] ?? '-') ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Designation</div>
                    <div class="jk-dl__val"><?= e($user['designation'] ?? '-') ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Organization</div>
                    <div class="jk-dl__val"><?= e($user['organization'] ?? '-') ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Data Scope</div>
                    <div class="jk-dl__val">
                        <?= e(ucfirst($user['data_scope'])) ?>
                        <span class="d-block text-subtle tiny">
                            <?= e(match ($user['data_scope']) {
                                'all'      => 'Sees every State and District',
                                'state'    => 'Sees only the assigned State',
                                'district' => 'Sees only the assigned District',
                                default    => 'Sees only the records they collected',
                            }) ?>
                        </span>
                    </div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">State</div>
                    <div class="jk-dl__val"><?= e($user['state_name'] ?? 'Not assigned') ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">District</div>
                    <div class="jk-dl__val"><?= e($user['district_name'] ?? 'Not assigned') ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Block</div>
                    <div class="jk-dl__val"><?= e($user['block_name'] ?? 'Not assigned') ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Created</div>
                    <div class="jk-dl__val">
                        <?= fmt_datetime($user['created_at']) ?>
                        <?php if (!empty($user['created_by_name'])): ?>
                            <span class="d-block text-subtle tiny">by <?= e($user['created_by_name']) ?></span>
                        <?php endif; ?>
                    </div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Last Login</div>
                    <div class="jk-dl__val">
                        <?= !empty($user['last_login_at']) ? fmt_datetime($user['last_login_at']) : 'Never signed in' ?>
                        <?php if (!empty($user['last_login_ip'])): ?>
                            <span class="d-block text-subtle tiny">from <?= e($user['last_login_ip']) ?></span>
                        <?php endif; ?>
                    </div></div>
            </div>
        </div>

        <?php if (can('user.edit') && !$isSelf): ?>
            <div class="jk-card__foot d-flex flex-wrap gap-2">
                <form method="post" action="<?= e(url('/users/' . (int)$user['id'] . '/toggle')) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-<?= (int)$user['is_active'] === 1 ? 'warning' : 'success' ?>"
                            type="submit"
                            data-confirm="<?= (int)$user['is_active'] === 1
                                ? 'Deactivate this account? The user will not be able to sign in.'
                                : 'Reactivate this account?' ?>">
                        <i class="bi bi-<?= (int)$user['is_active'] === 1 ? 'pause' : 'play' ?>-circle me-1"></i>
                        <?= (int)$user['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>
                    </button>
                </form>
                <?php if (can('user.reset_password')): ?>
                    <form method="post" action="<?= e(url('/users/' . (int)$user['id'] . '/reset-password')) ?>" class="d-inline">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-secondary" type="submit"
                                data-confirm="Issue a new password for this user?">
                            <i class="bi bi-key me-1"></i>Reset Password
                        </button>
                    </form>
                <?php endif; ?>
                <?php if (can('user.delete')): ?>
                    <form method="post" action="<?= e(url('/users/' . (int)$user['id'] . '/delete')) ?>" class="d-inline ms-auto">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-outline-danger" type="submit"
                                data-confirm="Delete this user account? Accounts linked to field data cannot be deleted.">
                            <i class="bi bi-trash me-1"></i>Delete
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-link-45deg"></i>Linked Records</h3></div>
        <div class="jk-card__body">
            <div class="jk-dl">
                <div class="jk-dl__row"><div class="jk-dl__key">Beneficiaries collected</div>
                    <div class="jk-dl__val fw-semibold">
                        <?php if ($usage['beneficiaries'] > 0): ?>
                            <a href="<?= e(url('/beneficiaries?field_user_id=' . (int)$user['id'])) ?>">
                                <?= num($usage['beneficiaries']) ?>
                            </a>
                        <?php else: ?>0<?php endif; ?>
                    </div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Applications assigned</div>
                    <div class="jk-dl__val fw-semibold"><?= num($usage['applications']) ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Follow-ups recorded</div>
                    <div class="jk-dl__val fw-semibold"><?= num($usage['followups']) ?></div></div>
            </div>
            <?php if (array_sum($usage) > 0): ?>
                <p class="text-muted small mb-0 mt-2">
                    <i class="bi bi-info-circle me-1"></i>
                    An account linked to field data cannot be deleted &mdash; deactivate it instead, so the
                    work stays attributed correctly.
                </p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="col-lg-7">
    <?php if ($performance): ?>
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-speedometer2"></i>Field Performance</h3>
                <?php if (can('fielddata.performance')): ?>
                    <a href="<?= e(url('/field-data/performance?user_id=' . (int)$user['id'])) ?>" class="small">Compare</a>
                <?php endif; ?>
            </div>
            <div class="jk-card__body">
                <div class="jk-kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr))">
                    <?php
                    $stats = [
                        ['value' => $performance['beneficiaries_registered'], 'label' => 'Registered',   'icon' => 'bi-person-plus',     'color' => '#0f4c81', 'soft' => '#e8f0f8'],
                        ['value' => $performance['assessments_completed'],    'label' => 'Assessed',     'icon' => 'bi-clipboard-check', 'color' => '#2a9d8f', 'soft' => '#e3f3f1'],
                        ['value' => $performance['schemes_identified'],       'label' => 'Schemes Found','icon' => 'bi-check2-square',   'color' => '#198754', 'soft' => '#e6f4ec'],
                        ['value' => $performance['applications_initiated'],   'label' => 'Applications', 'icon' => 'bi-file-earmark',    'color' => '#1a6bb0', 'soft' => '#e8f0f8'],
                        ['value' => $performance['applications_submitted'],   'label' => 'Submitted',    'icon' => 'bi-send',            'color' => '#6610f2', 'soft' => '#eee6fd'],
                        ['value' => $performance['benefits_received'],        'label' => 'Benefited',    'icon' => 'bi-award',           'color' => '#146c43', 'soft' => '#e3f2e9'],
                        ['value' => $performance['followups_done'],           'label' => 'Follow-ups',   'icon' => 'bi-telephone',       'color' => '#f59e0b', 'soft' => '#fdf3e0'],
                        ['value' => $performance['field_visits'],             'label' => 'Visits',       'icon' => 'bi-geo',             'color' => '#6a4c93', 'soft' => '#efeaf6'],
                    ];
                    foreach ($stats as $stat) {
                        extract(['kpiValue' => num($stat['value']), 'kpiLabel' => $stat['label'],
                                 'kpiIcon' => $stat['icon'], 'kpiColor' => $stat['color'],
                                 'kpiSoft' => $stat['soft'], 'kpiUrl' => null, 'kpiHint' => null]);
                        include VIEW_PATH . '/partials/kpi.php';
                    }
                    ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (can('audit.view')): ?>
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-clock-history"></i>Recent Activity</h3>
                <a href="<?= e(url('/audit-logs?user_id=' . (int)$user['id'])) ?>" class="small">Full audit trail</a>
            </div>
            <div class="jk-card__body jk-card__body--flush">
                <?php if (empty($activity)): ?>
                    <div class="jk-empty"><i class="bi bi-clock-history"></i><h5>No recorded activity</h5></div>
                <?php else: ?>
                    <div class="jk-table-wrap" style="max-height:480px">
                        <table class="jk-table jk-table--compact">
                            <thead><tr><th>When</th><th>Module</th><th>Action</th><th>Description</th></tr></thead>
                            <tbody>
                                <?php foreach ($activity as $entry): ?>
                                    <tr>
                                        <td class="nowrap small"><?= fmt_datetime($entry['created_at']) ?></td>
                                        <td><span class="jk-chip"><?= e($entry['module']) ?></span></td>
                                        <td><?= badge($entry['action'], $entry['action'] === 'Deleted' ? 'danger'
                                                : ($entry['action'] === 'Created' ? 'success' : 'secondary')) ?></td>
                                        <td class="text-muted small"><?= e(str_limit($entry['description'] ?? '-', 90)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
</div>
