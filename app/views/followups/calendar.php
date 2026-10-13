<?php
/**
 * What is due: overdue, today and the coming week (specification section 22).
 *
 * @var array $due, $overdue, $today, $upcoming, $filters, $states, $districts
 */
$pageSubtitle = 'Applications waiting on a follow-up in ' . ($scopeLabel ?: 'the programme');

ob_start(); ?>
    <a href="<?= e(url('/followups')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-journal-check me-1"></i>Follow-up Register
    </a>
    <?php if (can('followup.create')): ?>
        <a href="<?= e(url('/followups/create')) ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Record Follow-up
        </a>
    <?php endif; ?>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<form method="get" class="jk-filter-bar">
    <div class="row g-2">
        <div class="col-6 col-md-4 col-lg-3">
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
        <div class="col-6 col-md-4 col-lg-3">
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
        <div class="col-6 col-lg-2 d-flex align-items-end">
            <a href="<?= e(url('/followups/calendar')) ?>" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
        </div>
    </div>
</form>

<div class="row g-3">
    <?php
    $buckets = [
        ['title' => 'Overdue', 'icon' => 'bi-calendar-x', 'variant' => 'danger',
         'rows' => $overdue, 'count' => $due['overdue'],
         'empty' => 'Nothing is overdue in your area.'],
        ['title' => 'Due Today', 'icon' => 'bi-calendar-event', 'variant' => 'warning',
         'rows' => $today, 'count' => $due['today'],
         'empty' => 'Nothing is scheduled for today.'],
        ['title' => 'Next 7 Days', 'icon' => 'bi-calendar-week', 'variant' => 'primary',
         'rows' => $upcoming, 'count' => $due['upcoming'],
         'empty' => 'Nothing is coming up this week.'],
    ];
    foreach ($buckets as $bucket): ?>
        <div class="col-lg-4">
            <div class="jk-card h-100">
                <div class="jk-card__head">
                    <h3 class="jk-card__title">
                        <i class="bi <?= e($bucket['icon']) ?> text-<?= e($bucket['variant']) ?>"></i>
                        <?= e($bucket['title']) ?>
                        <span class="badge bg-<?= e($bucket['variant']) ?>"><?= num($bucket['count']) ?></span>
                    </h3>
                </div>
                <div class="jk-card__body jk-card__body--flush">
                    <?php if (empty($bucket['rows'])): ?>
                        <p class="text-muted text-center small py-4 mb-0">
                            <i class="bi bi-check2-circle d-block fs-3 mb-2 text-success"></i>
                            <?= e($bucket['empty']) ?>
                        </p>
                    <?php else: ?>
                        <div class="list-group list-group-flush jk-scroll-y" style="max-height:540px">
                            <?php foreach ($bucket['rows'] as $item): ?>
                                <div class="list-group-item px-3 py-2">
                                    <div class="d-flex justify-content-between gap-2 align-items-start">
                                        <div class="min-width-0">
                                            <a href="<?= e(url('/beneficiaries/' . (int)$item['beneficiary_id'])) ?>"
                                               class="fw-semibold d-block" style="font-size:.85rem">
                                                <?= e(str_limit($item['beneficiary_name'], 26)) ?>
                                            </a>
                                            <div class="text-muted tiny">
                                                <?= e(str_limit($item['scheme_name'], 30)) ?>
                                            </div>
                                            <div class="text-muted tiny">
                                                <?= e($item['district_name'] ?? '') ?>
                                                <?php if (!empty($item['assigned_to_name'])): ?>
                                                    &middot; <?= e($item['assigned_to_name']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="text-end flex-shrink-0">
                                            <div class="small"><?= fmt_date($item['next_followup_date']) ?></div>
                                            <?php if ((int)$item['days_overdue'] > 0): ?>
                                                <span class="badge bg-danger"><?= (int)$item['days_overdue'] ?>d late</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="d-flex gap-1 mt-2">
                                        <?php if (!empty($item['mobile'])): ?>
                                            <a href="tel:<?= e($item['mobile']) ?>" class="btn btn-sm btn-outline-secondary py-0 px-2"
                                               data-bs-toggle="tooltip" title="Call <?= e(mask_mobile($item['mobile'])) ?>">
                                                <i class="bi bi-telephone"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= e(url('/applications/' . (int)$item['id'])) ?>"
                                           class="btn btn-sm btn-outline-secondary py-0 px-2 flex-grow-1"
                                           style="font-size:.74rem">Open application</a>
                                        <?php if (can('followup.create')): ?>
                                            <a href="<?= e(url('/followups/create?application_id=' . (int)$item['id'])) ?>"
                                               class="btn btn-sm btn-primary py-0 px-2" style="font-size:.74rem">Log</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ((int)$bucket['count'] > count($bucket['rows'])): ?>
                    <div class="jk-card__foot text-center">
                        <a href="<?= e(url('/applications?followup_due=' . strtolower(str_replace([' ', '7'], ['', ''], $bucket['title'] === 'Next 7 Days' ? 'upcoming' : $bucket['title'])))) ?>"
                           class="small">
                            View all <?= num($bucket['count']) ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>
