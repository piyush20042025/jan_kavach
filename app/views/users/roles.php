<?php
/**
 * Roles overview.
 * @var array $roles
 * @var int $permissionCount
 */
$pageSubtitle = 'Roles decide what a user can do and how much data they can see';

ob_start(); ?>
    <a href="<?= e(url('/users')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Users
    </a>
    <a href="<?= e(url('/roles/create')) ?>" class="btn btn-sm btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Add Role
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<div class="alert alert-info d-flex gap-2 align-items-start">
    <i class="bi bi-info-circle mt-1"></i>
    <div>
        <strong>Roles are data, not code.</strong>
        A new role is a row plus a set of permissions, and the permission gate picks it up immediately.
        The <em>data scope</em> is what restricts a user to their State or District &mdash; it is applied to
        every query in the application, not just the menu.
    </div>
</div>

<div class="row g-3">
    <?php foreach ($roles as $role): ?>
        <div class="col-md-6 col-xl-4">
            <div class="jk-card h-100 mb-0">
                <div class="jk-card__body d-flex flex-column h-100">
                    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                        <div class="min-width-0">
                            <h3 class="h6 mb-1"><?= e($role['name']) ?></h3>
                            <code class="tiny text-muted"><?= e($role['code']) ?></code>
                        </div>
                        <div class="text-end flex-shrink-0">
                            <?= (int)$role['is_active'] === 1
                                ? '<span class="badge bg-success">Active</span>'
                                : '<span class="badge bg-secondary">Inactive</span>' ?>
                            <?php if ((int)$role['is_system'] === 1): ?>
                                <span class="badge bg-light text-muted border d-block mt-1">Built-in</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <p class="text-muted small flex-grow-1"><?= e($role['description'] ?? '') ?></p>

                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="jk-chip" data-bs-toggle="tooltip"
                              title="<?= e(match ($role['data_scope']) {
                                  'all'      => 'Sees every State and District',
                                  'state'    => 'Sees only the assigned State',
                                  'district' => 'Sees only the assigned District',
                                  default    => 'Sees only the records they collected',
                              }) ?>">
                            <i class="bi bi-shield-lock"></i><?= e(ucfirst($role['data_scope'])) ?> scope
                        </span>
                        <span class="jk-chip">
                            <i class="bi bi-key"></i>
                            <?= $role['code'] === 'SUPER_ADMIN'
                                ? 'All ' . $permissionCount
                                : num($role['permission_count']) . ' / ' . $permissionCount ?>
                        </span>
                        <span class="jk-chip">
                            <i class="bi bi-people"></i><?= num($role['user_count']) ?> user(s)
                        </span>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="<?= e(url('/roles/' . (int)$role['id'] . '/edit')) ?>"
                           class="btn btn-sm btn-outline-primary flex-grow-1">
                            <i class="bi bi-pencil me-1"></i>Permissions
                        </a>
                        <a href="<?= e(url('/users?role_id=' . (int)$role['id'])) ?>"
                           class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip" title="View users">
                            <i class="bi bi-people"></i>
                        </a>
                        <?php if ($role['code'] !== 'SUPER_ADMIN'): ?>
                            <form method="post" action="<?= e(url('/roles/' . (int)$role['id'] . '/toggle')) ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-outline-secondary" type="submit"
                                        data-bs-toggle="tooltip"
                                        title="<?= (int)$role['is_active'] === 1 ? 'Deactivate' : 'Activate' ?>">
                                    <i class="bi bi-<?= (int)$role['is_active'] === 1 ? 'pause' : 'play' ?>-circle"></i>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
