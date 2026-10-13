<?php
/**
 * The signed-in user's own profile.
 * @var array $user, $permissions, $recentActivity
 * @var array|null $performance
 */
$pageSubtitle = $user['role_name'] . ' &middot; ' . ($user['user_code'] ?? '');
$old    = $old ?? [];
$errors = $errors ?? [];

$value = function (string $field, $default = '') use ($user, $old) {
    return $old[$field] ?? ($user[$field] ?? $default);
};
$invalid  = fn(string $field) => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = fn(string $field) => isset($errors[$field])
    ? '<div class="invalid-feedback">' . e($errors[$field]) . '</div>' : '';

ob_start(); ?>
    <a href="<?= e(url('/profile/password')) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-key me-1"></i>Change Password
    </a>
    <a href="<?= e(url('/profile/activity')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-clock-history me-1"></i>My Activity
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<div class="row g-3">
<div class="col-lg-5">
    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-person"></i>My Details</h3></div>
        <div class="jk-card__body">
            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="jk-avatar jk-avatar--lg"><?= e(initials($user['full_name'])) ?></span>
                <div>
                    <h2 class="h5 mb-1"><?= e($user['full_name']) ?></h2>
                    <span class="jk-chip jk-chip--primary"><?= e($user['role_name']) ?></span>
                </div>
            </div>

            <form method="post" action="<?= e(url('/profile')) ?>" data-guard novalidate>
                <?= csrf_field() ?>

                <div class="mb-2">
                    <label class="form-label" for="full_name">Full Name <span class="req">*</span></label>
                    <input type="text" class="form-control<?= $invalid('full_name') ?>" id="full_name"
                           name="full_name" maxlength="150" required value="<?= e($value('full_name')) ?>">
                    <?= $feedback('full_name') ?>
                </div>

                <div class="mb-2">
                    <label class="form-label" for="email">Email <span class="req">*</span></label>
                    <input type="email" class="form-control<?= $invalid('email') ?>" id="email" name="email"
                           maxlength="150" required value="<?= e($value('email')) ?>">
                    <?= $feedback('email') ?>
                </div>

                <div class="mb-2">
                    <label class="form-label" for="mobile">Mobile</label>
                    <input type="tel" class="form-control<?= $invalid('mobile') ?>" id="mobile" name="mobile"
                           maxlength="15" inputmode="numeric" value="<?= e($value('mobile')) ?>">
                    <?= $feedback('mobile') ?>
                </div>

                <div class="mb-2">
                    <label class="form-label" for="designation">Designation</label>
                    <input type="text" class="form-control" id="designation" name="designation"
                           maxlength="120" value="<?= e($value('designation')) ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label" for="organization">Organization</label>
                    <input type="text" class="form-control" id="organization" name="organization"
                           maxlength="150" value="<?= e($value('organization')) ?>">
                </div>

                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-check-lg me-1"></i>Save Profile
                </button>
            </form>
        </div>
    </div>

    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-shield-lock"></i>My Access</h3></div>
        <div class="jk-card__body">
            <div class="jk-dl">
                <div class="jk-dl__row"><div class="jk-dl__key">Username</div>
                    <div class="jk-dl__val"><code><?= e($user['username']) ?></code></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Role</div>
                    <div class="jk-dl__val"><?= e($user['role_name']) ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Data Scope</div>
                    <div class="jk-dl__val">
                        <?= e(ucfirst($user['data_scope'])) ?>
                        <span class="d-block text-subtle tiny">
                            <?= e(match ($user['data_scope']) {
                                'all'      => 'You see data for every State and District',
                                'state'    => 'You see data for your assigned State only',
                                'district' => 'You see data for your assigned District only',
                                default    => 'You see only the records you collected',
                            }) ?>
                        </span>
                    </div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">State</div>
                    <div class="jk-dl__val"><?= e($user['state_name'] ?? 'Not assigned') ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">District</div>
                    <div class="jk-dl__val"><?= e($user['district_name'] ?? 'Not assigned') ?></div></div>
                <div class="jk-dl__row"><div class="jk-dl__key">Last Sign-in</div>
                    <div class="jk-dl__val">
                        <?= !empty($user['last_login_at']) ? fmt_datetime($user['last_login_at']) : 'This is your first session' ?>
                        <?php if (!empty($user['last_login_ip'])): ?>
                            <span class="d-block text-subtle tiny">from <?= e($user['last_login_ip']) ?></span>
                        <?php endif; ?>
                    </div></div>
            </div>
            <p class="text-muted small mb-0 mt-2">
                <i class="bi bi-info-circle me-1"></i>
                Your role and area of work can only be changed by an administrator.
            </p>
        </div>
    </div>
</div>

<div class="col-lg-7">
    <?php if ($performance && (int)$performance['beneficiaries_registered'] > 0): ?>
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-speedometer2"></i>My Work</h3></div>
            <div class="jk-card__body">
                <div class="jk-kpi-grid" style="grid-template-columns:repeat(auto-fill,minmax(140px,1fr))">
                    <?php
                    $stats = [
                        ['value' => $performance['beneficiaries_registered'], 'label' => 'Registered',    'icon' => 'bi-person-plus',     'color' => '#0f4c81', 'soft' => '#e8f0f8'],
                        ['value' => $performance['assessments_completed'],    'label' => 'Assessed',      'icon' => 'bi-clipboard-check', 'color' => '#2a9d8f', 'soft' => '#e3f3f1'],
                        ['value' => $performance['schemes_identified'],       'label' => 'Schemes Found', 'icon' => 'bi-check2-square',   'color' => '#198754', 'soft' => '#e6f4ec'],
                        ['value' => $performance['applications_initiated'],   'label' => 'Applications',  'icon' => 'bi-file-earmark',    'color' => '#1a6bb0', 'soft' => '#e8f0f8'],
                        ['value' => $performance['benefits_received'],        'label' => 'Benefited',     'icon' => 'bi-award',           'color' => '#146c43', 'soft' => '#e3f2e9'],
                        ['value' => $performance['followups_done'],           'label' => 'Follow-ups',    'icon' => 'bi-telephone',       'color' => '#f59e0b', 'soft' => '#fdf3e0'],
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

    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-key"></i>What I Can Do</h3>
        </div>
        <div class="jk-card__body">
            <?php if (empty($permissions)): ?>
                <p class="text-muted mb-0">No permissions are recorded for your role.</p>
            <?php else: ?>
                <?php foreach ($permissions as $module => $items): ?>
                    <div class="mb-2">
                        <div class="text-muted small fw-semibold mb-1"><?= e($module) ?></div>
                        <div class="d-flex flex-wrap gap-1">
                            <?php foreach ($items as $permission): ?>
                                <span class="jk-chip"><i class="bi bi-check2 text-success"></i><?= e($permission['name']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="jk-card">
        <div class="jk-card__head">
            <h3 class="jk-card__title"><i class="bi bi-clock-history"></i>Recent Activity</h3>
            <a href="<?= e(url('/profile/activity')) ?>" class="small">See more</a>
        </div>
        <div class="jk-card__body jk-card__body--flush">
            <?php if (empty($recentActivity)): ?>
                <div class="jk-empty"><i class="bi bi-clock-history"></i><h5>Nothing recorded yet</h5></div>
            <?php else: ?>
                <div class="jk-table-wrap">
                    <table class="jk-table jk-table--compact">
                        <thead><tr><th>When</th><th>Module</th><th>Action</th><th>Description</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentActivity as $entry): ?>
                                <tr>
                                    <td class="nowrap small"><?= time_ago($entry['created_at']) ?></td>
                                    <td><span class="jk-chip"><?= e($entry['module']) ?></span></td>
                                    <td><?= badge($entry['action'], 'secondary') ?></td>
                                    <td class="text-muted small"><?= e(str_limit($entry['description'] ?? '-', 80)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>
