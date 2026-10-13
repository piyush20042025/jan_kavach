<?php
/**
 * Top bar: sidebar toggle, global scheme search, geography context,
 * notifications and the user menu.
 *
 * @var array|null $authUser
 * @var string     $scopeLabel
 * @var int        $unreadCount
 */
?>
<header class="jk-header">

    <button class="jk-icon-btn" id="jk-sidebar-toggle" type="button" aria-label="Toggle navigation">
        <i class="bi bi-list fs-5"></i>
    </button>

    <!-- Global scheme search (specification section 34) -->
    <?php if (can('scheme.view')): ?>
        <div class="jk-header__search d-none d-sm-block">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-sm" id="jk-global-search"
                   placeholder="Search government schemes&hellip;" autocomplete="off"
                   aria-label="Search government schemes">
            <div class="jk-search-results" id="jk-search-results"></div>
        </div>
    <?php else: ?>
        <div class="flex-grow-1"></div>
    <?php endif; ?>

    <div class="ms-auto d-flex align-items-center gap-1 gap-sm-2">

        <!-- State / District context -->
        <?php if (!empty($scopeLabel)): ?>
            <span class="jk-scope-chip d-none d-md-inline-flex"
                  data-bs-toggle="tooltip"
                  title="You are seeing data for this area only">
                <i class="bi bi-geo-alt"></i><?= e($scopeLabel) ?>
            </span>
        <?php endif; ?>

        <!-- Notifications -->
        <div class="dropdown">
            <button class="jk-icon-btn" type="button" id="jk-notif-trigger"
                    data-bs-toggle="dropdown" data-bs-auto-close="outside"
                    aria-expanded="false" aria-label="Notifications">
                <i class="bi bi-bell fs-5"></i>
                <?php if (!empty($unreadCount)): ?>
                    <span class="jk-dot" id="jk-notif-count"><?= (int)$unreadCount > 99 ? '99+' : (int)$unreadCount ?></span>
                <?php endif; ?>
            </button>
            <div class="dropdown-menu dropdown-menu-end jk-notif-menu shadow">
                <div class="jk-notif-menu__head">
                    <span>Notifications</span>
                    <a href="#" id="jk-notif-mark-all" class="small">Mark all read</a>
                </div>
                <div class="jk-notif-menu__list" id="jk-notif-list">
                    <div class="p-4 text-center text-muted small">
                        <span class="spinner-border spinner-border-sm me-1"></span>Loading&hellip;
                    </div>
                </div>
                <div class="jk-notif-menu__head border-top border-bottom-0 justify-content-center">
                    <a href="<?= e(url('/notifications')) ?>" class="small">View all notifications</a>
                </div>
            </div>
        </div>

        <!-- User menu -->
        <div class="dropdown">
            <button class="btn btn-sm d-flex align-items-center gap-2 px-2" type="button"
                    data-bs-toggle="dropdown" aria-expanded="false">
                <span class="jk-avatar"><?= e(initials($authUser['full_name'] ?? '')) ?></span>
                <span class="d-none d-lg-block text-start lh-sm">
                    <span class="d-block fw-semibold" style="font-size:.82rem">
                        <?= e(str_limit($authUser['full_name'] ?? '', 22)) ?>
                    </span>
                    <span class="d-block text-muted" style="font-size:.7rem">
                        <?= e($authUser['role_name'] ?? '') ?>
                    </span>
                </span>
                <i class="bi bi-chevron-down small text-muted"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow" style="min-width:250px">
                <li class="px-3 py-2 border-bottom">
                    <div class="fw-semibold"><?= e($authUser['full_name'] ?? '') ?></div>
                    <div class="small text-muted"><?= e($authUser['email'] ?? '') ?></div>
                    <div class="mt-1">
                        <span class="badge bg-primary-subtle text-primary"><?= e($authUser['role_name'] ?? '') ?></span>
                        <?php if (!empty($authUser['state_name'])): ?>
                            <span class="badge bg-secondary-subtle text-secondary"><?= e($authUser['state_name']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($authUser['district_name'])): ?>
                            <span class="badge bg-secondary-subtle text-secondary"><?= e($authUser['district_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </li>
                <li><a class="dropdown-item" href="<?= e(url('/profile')) ?>">
                    <i class="bi bi-person me-2"></i>My Profile</a></li>
                <li><a class="dropdown-item" href="<?= e(url('/profile/password')) ?>">
                    <i class="bi bi-key me-2"></i>Change Password</a></li>
                <li><a class="dropdown-item" href="<?= e(url('/profile/activity')) ?>">
                    <i class="bi bi-clock-history me-2"></i>My Activity</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('/logout')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
