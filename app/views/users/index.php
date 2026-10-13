<?php
/**
 * User directory (specification section 16).
 *
 * @var JanKavach\Core\Paginator $paginator
 * @var array $filters, $roles, $states, $districts
 */
$pageSubtitle = 'User accounts you may manage in ' . ($scopeLabel ?: 'the programme');

ob_start(); ?>
    <?php if (can('user.create')): ?>
        <a href="<?= e(url('/users/create')) ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-person-plus me-1"></i>Add User
        </a>
    <?php endif; ?>
    <?php if (can('role.manage')): ?>
        <a href="<?= e(url('/roles')) ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-shield-lock me-1"></i>Roles
        </a>
    <?php endif; ?>
    <div class="btn-group">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-download me-1"></i>Export
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item jk-export" data-format="excel" href="#"><i class="bi bi-file-earmark-excel me-2"></i>Excel</a></li>
            <li><a class="dropdown-item jk-export" data-format="csv" href="#"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
        </ul>
    </div>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<form method="get" class="jk-filter-bar">
    <div class="row g-2">
        <div class="col-12 col-md-4 col-lg-3">
            <label class="form-label" for="search">Search</label>
            <input type="search" class="form-control form-control-sm" id="search" name="search"
                   value="<?= e($filters['search'] ?? '') ?>" placeholder="Name, username, email or mobile">
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="role_id">Role</label>
            <select class="form-select form-select-sm" id="role_id" name="role_id" data-auto-submit>
                <option value="">All roles</option>
                <?php foreach ($roles as $role): ?>
                    <option value="<?= (int)$role['id'] ?>" <?= (int)($filters['role_id'] ?? 0) === (int)$role['id'] ? 'selected' : '' ?>>
                        <?= e($role['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="state_id">State</label>
            <select class="form-select form-select-sm" id="state_id" name="state_id" data-auto-submit>
                <option value="">All States</option>
                <?php foreach ($states as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['state_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="district_id">District</label>
            <select class="form-select form-select-sm" id="district_id" name="district_id"
                    data-depends-on="state_id" data-server-filled="1"
                    data-endpoint="/geo/districts/{id}" data-placeholder="All Districts" data-auto-submit>
                <option value="">All Districts</option>
                <?php foreach ($districts as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['district_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e($name) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <label class="form-label" for="is_active">Status</label>
            <select class="form-select form-select-sm" id="is_active" name="is_active" data-auto-submit>
                <option value="">All</option>
                <option value="1" <?= ($filters['is_active'] ?? null) === 1 ? 'selected' : '' ?>>Active</option>
                <option value="0" <?= ($filters['is_active'] ?? null) === 0 ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="col-6 col-lg-1 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-sm btn-primary">Go</button>
        </div>
    </div>
</form>

<div class="jk-card">
    <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-person-badge"></i>Users</h3></div>
    <div class="jk-card__body jk-card__body--flush">
        <?php if ($paginator->isEmpty()): ?>
            <div class="jk-empty">
                <i class="bi bi-person-x"></i>
                <h5>No users found</h5>
                <?php if (can('user.create')): ?>
                    <a href="<?= e(url('/users/create')) ?>" class="btn btn-sm btn-primary">Add the first user</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="jk-table-wrap">
                <table class="jk-table">
                    <thead>
                        <tr>
                            <th><?= sort_link('User ID', 'code', $sortKey, $sortDir) ?></th>
                            <th style="min-width:180px"><?= sort_link('Name', 'name', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Username', 'username', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Email', 'email', $sortKey, $sortDir) ?></th>
                            <th>Mobile</th>
                            <th><?= sort_link('Role', 'role', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('State', 'state', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('District', 'district', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Status', 'status', $sortKey, $sortDir) ?></th>
                            <th><?= sort_link('Last Login', 'login', $sortKey, $sortDir) ?></th>
                            <th class="jk-table__actions no-print"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paginator->items as $user): ?>
                            <tr<?= (int)$user['is_active'] === 0 ? ' class="opacity-75"' : '' ?>>
                                <td class="nowrap"><code class="small"><?= e($user['user_code']) ?></code></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="jk-avatar jk-avatar--sm"><?= e(initials($user['full_name'])) ?></span>
                                        <div class="min-width-0">
                                            <a href="<?= e(url('/users/' . (int)$user['id'])) ?>" class="fw-semibold">
                                                <?= e($user['full_name']) ?>
                                            </a>
                                            <?php if (!empty($user['designation'])): ?>
                                                <div class="text-muted tiny"><?= e(str_limit($user['designation'], 26)) ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td><code class="small"><?= e($user['username']) ?></code></td>
                                <td class="small text-muted"><?= e(str_limit($user['email'], 26)) ?></td>
                                <td class="small"><?= e($user['mobile'] ?? '-') ?></td>
                                <td><span class="jk-chip jk-chip--primary"><?= e($user['role_name']) ?></span></td>
                                <td class="small"><?= e($user['state_name'] ?? '-') ?></td>
                                <td class="small"><?= e($user['district_name'] ?? '-') ?></td>
                                <td>
                                    <?= (int)$user['is_active'] === 1
                                        ? '<span class="badge bg-success">Active</span>'
                                        : '<span class="badge bg-secondary">Inactive</span>' ?>
                                    <?php if ((int)$user['must_change_password'] === 1): ?>
                                        <span class="badge bg-warning text-dark" data-bs-toggle="tooltip"
                                              title="Must change password at next sign-in">PW</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted nowrap">
                                    <?= !empty($user['last_login_at']) ? time_ago($user['last_login_at']) : 'never' ?>
                                </td>
                                <td class="jk-table__actions no-print">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= e(url('/users/' . (int)$user['id'])) ?>"
                                           class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <?php if (can('user.edit')): ?>
                                            <a href="<?= e(url('/users/' . (int)$user['id'] . '/edit')) ?>"
                                               class="btn btn-outline-secondary" data-bs-toggle="tooltip" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
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
            <?php $recordNoun = 'user'; require VIEW_PATH . '/partials/pagination.php'; ?>
        </div>
    <?php endif; ?>
</div>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
  document.querySelectorAll('.jk-export').forEach(function (link) {
    link.addEventListener('click', function () {
      const params = new URLSearchParams(location.search);
      params.set('format', link.dataset.format);
      link.href = JK.url('/users/export') + '?' + params.toString();
    });
  });
</script>
<?php \JanKavach\Core\View::endSection(); ?>
