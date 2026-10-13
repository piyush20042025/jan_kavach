<?php
/**
 * Notification list (specification section 36).
 * @var array $notifications
 * @var bool  $unreadOnly
 * @var int   $unreadCount
 */
$pageSubtitle = 'Follow-ups due, documents pending and applications needing attention';

ob_start(); ?>
    <?php if ($unreadCount > 0): ?>
        <form method="post" action="<?= e(url('/notifications/read-all')) ?>" class="d-inline">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-secondary" type="submit">
                <i class="bi bi-check2-all me-1"></i>Mark all read
            </button>
        </form>
    <?php endif; ?>
    <?php if (can('settings.manage')): ?>
        <form method="post" action="<?= e(url('/notifications/generate')) ?>" class="d-inline">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-primary" type="submit">
                <i class="bi bi-arrow-repeat me-1"></i>Generate now
            </button>
        </form>
    <?php endif; ?>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= !$unreadOnly ? 'active' : '' ?>" href="<?= e(url('/notifications')) ?>">
            All
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $unreadOnly ? 'active' : '' ?>" href="<?= e(url('/notifications?unread=1')) ?>">
            Unread <?php if ($unreadCount > 0): ?><span class="badge bg-danger"><?= num($unreadCount) ?></span><?php endif; ?>
        </a>
    </li>
</ul>

<div class="jk-card">
    <div class="jk-card__body jk-card__body--flush">
        <?php if (empty($notifications)): ?>
            <div class="jk-empty">
                <i class="bi bi-bell-slash"></i>
                <h5><?= $unreadOnly ? 'Nothing unread' : 'No notifications' ?></h5>
                <p>
                    <?= $unreadOnly
                        ? 'You are up to date.'
                        : 'Notifications are raised for overdue follow-ups, pending documents, stalled applications and scheme changes.' ?>
                </p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $notification): ?>
                    <?php
                    $seen = (int)$notification['seen'] === 1;
                    $priorityVariant = match ($notification['priority']) {
                        'Critical' => 'danger',
                        'High'     => 'warning',
                        'Low'      => 'secondary',
                        default    => 'primary',
                    };
                    ?>
                    <div class="list-group-item d-flex gap-3 py-3 <?= $seen ? '' : 'bg-light' ?>">
                        <span class="jk-notif-item__icon flex-shrink-0">
                            <i class="bi <?= e($notification['icon'] ?: 'bi-bell') ?>"></i>
                        </span>

                        <div class="flex-grow-1 min-width-0">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <div class="fw-semibold" style="font-size:.88rem">
                                    <?php if (!empty($notification['link'])): ?>
                                        <a href="<?= e(url($notification['link'])) ?>" class="text-decoration-none">
                                            <?= e($notification['title']) ?>
                                        </a>
                                    <?php else: ?>
                                        <?= e($notification['title']) ?>
                                    <?php endif; ?>
                                    <?php if (!$seen): ?>
                                        <span class="badge bg-primary ms-1">New</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-muted small nowrap">
                                    <?= time_ago($notification['created_at']) ?>
                                </div>
                            </div>

                            <?php if (!empty($notification['message'])): ?>
                                <div class="text-muted small"><?= e($notification['message']) ?></div>
                            <?php endif; ?>

                            <div class="d-flex flex-wrap gap-2 align-items-center mt-2">
                                <span class="badge bg-<?= e($priorityVariant) ?>-subtle text-<?= e($priorityVariant) ?>">
                                    <?= e($notification['priority']) ?>
                                </span>
                                <span class="jk-chip" style="font-size:.7rem">
                                    <?= e(ucwords(str_replace('_', ' ', $notification['type']))) ?>
                                </span>

                                <?php if (!empty($notification['link'])): ?>
                                    <a href="<?= e(url($notification['link'])) ?>" class="btn btn-sm btn-outline-primary py-0 px-2"
                                       style="font-size:.74rem">Open</a>
                                <?php endif; ?>

                                <?php if (!$seen): ?>
                                    <form method="post"
                                          action="<?= e(url('/notifications/' . (int)$notification['id'] . '/read')) ?>"
                                          class="d-inline">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-sm btn-link p-0 text-muted" type="submit"
                                                style="font-size:.74rem">Mark read</button>
                                    </form>
                                <?php endif; ?>

                                <?php
                                // Show which external channels were requested, so the
                                // scaffolding is visible without pretending it delivered.
                                $channels = [];
                                if ((int)$notification['channel_email'] === 1)    $channels[] = 'Email';
                                if ((int)$notification['channel_sms'] === 1)      $channels[] = 'SMS';
                                if ((int)$notification['channel_whatsapp'] === 1) $channels[] = 'WhatsApp';
                                ?>
                                <?php if ($channels): ?>
                                    <span class="jk-chip" style="font-size:.7rem"
                                          data-bs-toggle="tooltip"
                                          title="Queued for <?= e(implode(', ', $channels)) ?> - awaiting a gateway integration">
                                        <i class="bi bi-hourglass-split"></i><?= e($notification['dispatch_status']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<p class="text-muted small">
    <i class="bi bi-info-circle me-1"></i>
    Notifications are addressed to you directly, or broadcast to your role within your State or District.
    SMS, WhatsApp and email delivery is scaffolded but not connected &mdash; see
    <?php if (can('settings.manage')): ?>
        <a href="<?= e(url('/settings')) ?>">Settings &rsaquo; Notification Channels</a>.
    <?php else: ?>
        Settings &rsaquo; Notification Channels.
    <?php endif; ?>
</p>
