<?php
/**
 * Single follow-up record.
 * @var array $followup
 */
$pageSubtitle = fmt_date($followup['followup_date']) . ' &middot; ' . $followup['beneficiary_name'];

ob_start(); ?>
    <a href="<?= e(url('/followups')) ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Register
    </a>
    <?php if (can('followup.edit')): ?>
        <a href="<?= e(url('/followups/' . (int)$followup['id'] . '/edit')) ?>" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
    <?php endif; ?>
    <?php if (can('followup.delete')): ?>
        <form method="post" action="<?= e(url('/followups/' . (int)$followup['id'] . '/delete')) ?>" class="d-inline">
            <?= csrf_field() ?>
            <button class="btn btn-sm btn-outline-danger" type="submit"
                    data-confirm="Delete this follow-up record permanently?">
                <i class="bi bi-trash me-1"></i>Delete
            </button>
        </form>
    <?php endif; ?>
<?php $pageActions = ob_get_clean();

require VIEW_PATH . '/partials/page-head.php'; ?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="jk-card">
            <div class="jk-card__head">
                <h3 class="jk-card__title"><i class="bi bi-telephone"></i><?= e($followup['followup_type']) ?></h3>
                <?= badge($followup['outcome'], status_variant($followup['outcome'])) ?>
            </div>
            <div class="jk-card__body">
                <div class="jk-dl mb-3">
                    <div class="jk-dl__row"><div class="jk-dl__key">Follow-up Date</div>
                        <div class="jk-dl__val"><?= fmt_date($followup['followup_date']) ?></div></div>
                    <div class="jk-dl__row"><div class="jk-dl__key">Type</div>
                        <div class="jk-dl__val"><?= e($followup['followup_type']) ?></div></div>
                    <div class="jk-dl__row"><div class="jk-dl__key">Outcome</div>
                        <div class="jk-dl__val"><?= badge($followup['outcome'], status_variant($followup['outcome'])) ?></div></div>
                    <div class="jk-dl__row"><div class="jk-dl__key">Application Status After</div>
                        <div class="jk-dl__val">
                            <?php if (!empty($followup['status_name'])): ?>
                                <span class="badge" style="background:<?= e($followup['status_color'] ?: '#6c757d') ?>">
                                    <?= e($followup['status_name']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Unchanged</span>
                            <?php endif; ?>
                        </div></div>
                    <div class="jk-dl__row"><div class="jk-dl__key">Next Follow-up</div>
                        <div class="jk-dl__val <?= !empty($followup['next_followup_date']) && strtotime($followup['next_followup_date']) < strtotime('today') ? 'text-danger fw-semibold' : '' ?>">
                            <?= fmt_date($followup['next_followup_date']) ?>
                        </div></div>
                    <div class="jk-dl__row"><div class="jk-dl__key">Responsible</div>
                        <div class="jk-dl__val"><?= e($followup['responsible_name'] ?? '-') ?></div></div>
                    <div class="jk-dl__row"><div class="jk-dl__key">Recorded</div>
                        <div class="jk-dl__val">
                            <?= fmt_datetime($followup['created_at']) ?>
                            <?php if (!empty($followup['created_by_name'])): ?>
                                <span class="d-block text-subtle tiny">by <?= e($followup['created_by_name']) ?></span>
                            <?php endif; ?>
                        </div></div>
                </div>

                <div class="text-muted small fw-semibold mb-1">Discussion</div>
                <div class="jk-prose"><?= nl2br_e($followup['discussion'] ?: 'No discussion was recorded.') ?></div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-person"></i>Beneficiary</h3></div>
            <div class="jk-card__body">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="jk-avatar"><?= e(initials($followup['beneficiary_name'])) ?></span>
                    <div class="min-width-0">
                        <a href="<?= e(url('/beneficiaries/' . (int)$followup['beneficiary_id'])) ?>" class="fw-semibold">
                            <?= e($followup['beneficiary_name']) ?>
                        </a>
                        <div class="text-muted tiny"><?= e($followup['beneficiary_code']) ?></div>
                    </div>
                </div>
                <div class="jk-dl">
                    <div class="jk-dl__row"><div class="jk-dl__key">Mobile</div>
                        <div class="jk-dl__val">
                            <?php if (!empty($followup['beneficiary_mobile'])): ?>
                                <a href="tel:<?= e($followup['beneficiary_mobile']) ?>">
                                    <?= e($followup['beneficiary_mobile']) ?>
                                </a>
                            <?php else: ?>-<?php endif; ?>
                        </div></div>
                    <div class="jk-dl__row"><div class="jk-dl__key">District</div>
                        <div class="jk-dl__val"><?= e($followup['district_name'] ?? '-') ?></div></div>
                    <div class="jk-dl__row"><div class="jk-dl__key">State</div>
                        <div class="jk-dl__val"><?= e($followup['state_name'] ?? '-') ?></div></div>
                </div>
            </div>
        </div>

        <?php if (!empty($followup['application_id'])): ?>
            <div class="jk-card">
                <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-file-earmark-text"></i>Application</h3></div>
                <div class="jk-card__body">
                    <a href="<?= e(url('/applications/' . (int)$followup['application_id'])) ?>" class="fw-semibold d-block mb-1">
                        <?= e($followup['application_code'] ?? '') ?>
                    </a>
                    <div class="text-muted small"><?= e($followup['scheme_name'] ?? '') ?></div>
                    <a href="<?= e(url('/applications/' . (int)$followup['application_id'])) ?>"
                       class="btn btn-sm btn-outline-primary w-100 mt-2">Open application</a>
                </div>
            </div>
        <?php endif; ?>

        <?php if (can('followup.create')): ?>
            <div class="jk-card">
                <div class="jk-card__body">
                    <a href="<?= e(url('/followups/create?beneficiary_id=' . (int)$followup['beneficiary_id']
                            . (!empty($followup['application_id']) ? '&application_id=' . (int)$followup['application_id'] : ''))) ?>"
                       class="btn btn-primary w-100">
                        <i class="bi bi-plus-lg me-1"></i>Record the next follow-up
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
