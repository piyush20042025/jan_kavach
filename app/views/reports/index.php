<?php
/**
 * Report catalogue (specification section 26).
 * @var array $grouped  group => [key => definition]
 */
$pageSubtitle = 'Every report respects your assigned geography: ' . ($scopeLabel ?: 'National');
require VIEW_PATH . '/partials/page-head.php';
?>

<div class="alert alert-info d-flex gap-2 align-items-start">
    <i class="bi bi-info-circle mt-1"></i>
    <div>
        Each report opens with its own filters and can be downloaded as <strong>Excel</strong> or
        <strong>CSV</strong>, or opened as a print-ready page you can save as a <strong>PDF</strong>.
        Personal identifiers stay masked in every export.
    </div>
</div>

<?php foreach ($grouped as $group => $reports): ?>
    <h2 class="h6 text-muted text-uppercase mb-2 mt-3" style="font-size:.72rem;letter-spacing:.06em">
        <?= e($group) ?>
    </h2>
    <div class="row g-3 mb-2">
        <?php foreach ($reports as $key => $report): ?>
            <div class="col-md-6 col-xl-4">
                <a href="<?= e(url('/reports/' . $key)) ?>" class="jk-card h-100 mb-0 text-decoration-none d-block">
                    <div class="jk-card__body d-flex gap-3">
                        <span class="jk-kpi__icon" style="background:var(--jk-primary-soft);color:var(--jk-primary)">
                            <i class="bi <?= e($report['icon']) ?>"></i>
                        </span>
                        <span class="flex-grow-1 min-width-0">
                            <span class="d-block fw-semibold" style="color:var(--jk-text)">
                                <?= e($report['title']) ?>
                            </span>
                            <span class="d-block text-muted small mt-1"><?= e($report['description']) ?></span>
                        </span>
                        <i class="bi bi-chevron-right text-subtle align-self-center"></i>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endforeach; ?>
