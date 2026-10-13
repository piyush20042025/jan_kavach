<?php
/**
 * Application settings.
 * @var array $grouped, $groupLabels
 */
$pageSubtitle = 'Behaviour that can be changed without touching the code';

$groupIcons = [
    'general'      => 'bi-gear',
    'matching'     => 'bi-check2-square',
    'followup'     => 'bi-telephone',
    'security'     => 'bi-shield-lock',
    'upload'       => 'bi-upload',
    'notification' => 'bi-bell',
    'report'       => 'bi-file-bar-graph',
];

ob_start(); ?>
    <a href="<?= e(url('/settings/assessment-questions')) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-clipboard-data me-1"></i>Assessment Questions
    </a>
    <a href="<?= e(url('/settings/eligibility-attributes')) ?>" class="btn btn-sm btn-outline-primary">
        <i class="bi bi-sliders me-1"></i>Eligibility Attributes
    </a>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<form method="post" action="<?= e(url('/settings')) ?>" data-guard>
<?= csrf_field() ?>

<div class="row g-3">
    <?php foreach ($grouped as $group => $settings): ?>
        <div class="col-lg-6">
            <div class="jk-card h-100">
                <div class="jk-card__head">
                    <h3 class="jk-card__title">
                        <i class="bi <?= e($groupIcons[$group] ?? 'bi-gear') ?>"></i>
                        <?= e($groupLabels[$group] ?? ucfirst($group)) ?>
                    </h3>
                </div>
                <div class="jk-card__body">
                    <?php foreach ($settings as $setting): ?>
                        <div class="mb-3">
                            <?php if ($setting['value_type'] === 'bool'): ?>
                                <div class="form-check form-switch">
                                    <input type="hidden" name="settings[<?= e($setting['setting_key']) ?>]" value="0">
                                    <input class="form-check-input" type="checkbox"
                                           id="s_<?= e($setting['setting_key']) ?>"
                                           name="settings[<?= e($setting['setting_key']) ?>]" value="1"
                                           <?= $setting['setting_value'] === '1' ? 'checked' : '' ?>
                                           <?= (int)$setting['is_editable'] === 0 ? 'disabled' : '' ?>>
                                    <label class="form-check-label" for="s_<?= e($setting['setting_key']) ?>">
                                        <?= e($setting['label']) ?>
                                    </label>
                                </div>
                                <?php if (!empty($setting['description'])): ?>
                                    <div class="form-text ms-4"><?= e($setting['description']) ?></div>
                                <?php endif; ?>

                            <?php elseif ($setting['value_type'] === 'text' || $setting['value_type'] === 'json'): ?>
                                <label class="form-label" for="s_<?= e($setting['setting_key']) ?>">
                                    <?= e($setting['label']) ?>
                                </label>
                                <textarea class="form-control form-control-sm" rows="3"
                                          id="s_<?= e($setting['setting_key']) ?>"
                                          name="settings[<?= e($setting['setting_key']) ?>]"
                                          <?= (int)$setting['is_editable'] === 0 ? 'readonly' : '' ?>><?= e($setting['setting_value']) ?></textarea>
                                <?php if (!empty($setting['description'])): ?>
                                    <div class="form-text"><?= e($setting['description']) ?></div>
                                <?php endif; ?>

                            <?php else: ?>
                                <label class="form-label" for="s_<?= e($setting['setting_key']) ?>">
                                    <?= e($setting['label']) ?>
                                </label>
                                <input type="<?= $setting['value_type'] === 'int' ? 'number' : 'text' ?>"
                                       class="form-control form-control-sm"
                                       id="s_<?= e($setting['setting_key']) ?>"
                                       name="settings[<?= e($setting['setting_key']) ?>]"
                                       value="<?= e($setting['setting_value']) ?>"
                                       <?= $setting['value_type'] === 'int' ? 'min="0"' : '' ?>
                                       <?= (int)$setting['is_editable'] === 0 ? 'readonly' : '' ?>>
                                <?php if (!empty($setting['description'])): ?>
                                    <div class="form-text"><?= e($setting['description']) ?></div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="jk-card">
    <div class="jk-card__body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span class="text-muted small">
            <i class="bi bi-info-circle me-1"></i>
            Changes are written to the audit trail and take effect immediately for every user.
        </span>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Save Settings
        </button>
    </div>
</div>
</form>

<div class="jk-card">
    <div class="jk-card__head">
        <h3 class="jk-card__title"><i class="bi bi-bell"></i>Notification Channels</h3>
    </div>
    <div class="jk-card__body">
        <p class="text-muted small">
            In-app notifications work today. Email, SMS and WhatsApp are scaffolded but not connected:
            every notification row already carries channel flags and a dispatch status, so a delivery worker
            can pick up pending rows once a gateway is configured &mdash; no schema change is needed.
            Switching a channel on here has no effect until that integration exists.
        </p>
        <?php if (can('settings.manage')): ?>
            <form method="post" action="<?= e(url('/notifications/generate')) ?>" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-arrow-repeat me-1"></i>Generate notifications now
                </button>
            </form>
            <span class="text-muted small ms-2">
                Sweeps for overdue follow-ups, pending documents and stalled applications.
                In production this would run on a schedule.
            </span>
        <?php endif; ?>
    </div>
</div>
