<?php
/**
 * Left navigation.  Every entry is gated by the permission that controls
 * the screen it links to, so the menu never offers a page the user would
 * then be refused.
 */
?>
<aside class="jk-sidebar" id="jk-sidebar">

    <div class="jk-sidebar__brand">
        <span class="jk-logo">
            <img src="<?= e(asset('images/logo-mark-sm.png')) ?>" alt="Jan Kavach"
                 width="96" height="96">
        </span>
        <span class="jk-sidebar__brand-text">
            <span class="jk-sidebar__title d-block">Jan Kavach</span>
            <span class="jk-sidebar__subtitle">Social Protection MIS</span>
        </span>
    </div>

    <nav class="jk-sidebar__nav" aria-label="Main navigation">

        <a class="jk-nav-link <?= nav_active('/dashboard') ?>" href="<?= e(url('/dashboard')) ?>">
            <i class="bi bi-speedometer2"></i><span>Dashboard</span>
        </a>

        <!-- ---------------------------------------------------------- -->
        <?php if (can('scheme.view')): ?>
            <div class="jk-nav-section">Scheme Management</div>

            <a class="jk-nav-link <?= is_active_path('/schemes', true) || is_active_path('/schemes') && !is_active_path('/schemes/create') ? 'active' : '' ?>"
               href="<?= e(url('/schemes')) ?>">
                <i class="bi bi-journal-text"></i><span>All Schemes</span>
            </a>

            <?php if (can('scheme.create')): ?>
                <a class="jk-nav-link <?= nav_active('/schemes/create', true) ?>" href="<?= e(url('/schemes/create')) ?>">
                    <i class="bi bi-plus-square"></i><span>Add Scheme</span>
                </a>
            <?php endif; ?>

            <a class="jk-nav-link <?= nav_active('/search') ?>" href="<?= e(url('/search')) ?>">
                <i class="bi bi-search"></i><span>Search Schemes</span>
            </a>
        <?php endif; ?>

        <!-- ---------------------------------------------------------- -->
        <?php if (can_any(['beneficiary.view', 'beneficiary.create'])): ?>
            <div class="jk-nav-section">Beneficiaries</div>

            <?php if (can('beneficiary.view')): ?>
                <a class="jk-nav-link <?= is_active_path('/beneficiaries') && !is_active_path('/beneficiaries/create') ? 'active' : '' ?>"
                   href="<?= e(url('/beneficiaries')) ?>">
                    <i class="bi bi-people"></i><span>All Beneficiaries</span>
                </a>
            <?php endif; ?>

            <?php if (can('beneficiary.create')): ?>
                <a class="jk-nav-link <?= nav_active('/beneficiaries/create', true) ?>" href="<?= e(url('/beneficiaries/create')) ?>">
                    <i class="bi bi-person-plus"></i><span>Register Beneficiary</span>
                </a>
            <?php endif; ?>

            <?php if (can('beneficiary.match')): ?>
                <a class="jk-nav-link <?= nav_active('/matching') ?>" href="<?= e(url('/matching')) ?>">
                    <i class="bi bi-check2-square"></i><span>Scheme Matching</span>
                </a>
            <?php endif; ?>

            <?php if (can('beneficiary.document')): ?>
                <a class="jk-nav-link <?= nav_active('/documents/pending', true) ?>" href="<?= e(url('/documents/pending')) ?>">
                    <i class="bi bi-folder-check"></i><span>Document Verification</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ---------------------------------------------------------- -->
        <?php if (can_any(['application.view', 'followup.view'])): ?>
            <div class="jk-nav-section">Delivery</div>

            <?php if (can('application.view')): ?>
                <a class="jk-nav-link <?= nav_active('/applications') ?>" href="<?= e(url('/applications')) ?>">
                    <i class="bi bi-file-earmark-text"></i><span>Applications</span>
                </a>
            <?php endif; ?>

            <?php if (can('followup.view')): ?>
                <a class="jk-nav-link <?= nav_active('/followups') ?>" href="<?= e(url('/followups')) ?>">
                    <i class="bi bi-telephone"></i><span>Follow-ups</span>
                </a>
            <?php endif; ?>

            <?php if (can('fielddata.view')): ?>
                <a class="jk-nav-link <?= nav_active('/field-data') ?>" href="<?= e(url('/field-data')) ?>">
                    <i class="bi bi-clipboard-data"></i><span>Field Data</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <!-- ---------------------------------------------------------- -->
        <?php if (can('report.view')): ?>
            <div class="jk-nav-section">Analysis</div>
            <a class="jk-nav-link <?= nav_active('/reports') ?>" href="<?= e(url('/reports')) ?>">
                <i class="bi bi-file-bar-graph"></i><span>Reports</span>
            </a>
        <?php endif; ?>

        <!-- ---------------------------------------------------------- -->
        <?php
        $showAdmin = can_any(['master.view', 'user.view', 'role.manage', 'scheme.import',
                              'audit.view', 'settings.manage']);
        ?>
        <?php if ($showAdmin): ?>
            <div class="jk-nav-section">Administration</div>

            <?php if (can('master.view')): ?>
                <a class="jk-nav-link <?= nav_active('/masters') ?>" href="<?= e(url('/masters')) ?>">
                    <i class="bi bi-sliders"></i><span>Masters</span>
                </a>
            <?php endif; ?>

            <?php if (can('user.view')): ?>
                <a class="jk-nav-link <?= nav_active('/users') ?>" href="<?= e(url('/users')) ?>">
                    <i class="bi bi-person-badge"></i><span>User Management</span>
                </a>
            <?php endif; ?>

            <?php if (can('role.manage')): ?>
                <a class="jk-nav-link <?= nav_active('/roles') ?>" href="<?= e(url('/roles')) ?>">
                    <i class="bi bi-shield-lock"></i><span>Roles &amp; Permissions</span>
                </a>
            <?php endif; ?>

            <?php if (can('scheme.import')): ?>
                <a class="jk-nav-link <?= nav_active('/import') ?>" href="<?= e(url('/import/schemes')) ?>">
                    <i class="bi bi-upload"></i><span>Import Schemes</span>
                </a>
            <?php endif; ?>

            <?php if (can('audit.view')): ?>
                <a class="jk-nav-link <?= nav_active('/audit-logs') ?>" href="<?= e(url('/audit-logs')) ?>">
                    <i class="bi bi-clock-history"></i><span>Audit Logs</span>
                </a>
            <?php endif; ?>

            <?php if (can('settings.manage')): ?>
                <a class="jk-nav-link <?= nav_active('/settings') ?>" href="<?= e(url('/settings')) ?>">
                    <i class="bi bi-gear"></i><span>Settings</span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

    </nav>
</aside>
