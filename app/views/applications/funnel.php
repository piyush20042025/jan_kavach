<?php
/**
 * Application funnel view (specification section 5).
 *
 * @var array $funnel, $filters, $states, $districts, $schemes
 */
$pageSubtitle = 'From identification through to benefit received';
require VIEW_PATH . '/partials/page-head.php';

$stages = array_values(array_filter($funnel, fn($s) => empty($s['aside'])));
$top    = $stages[0]['count'] ?? 0;
$bottom = end($stages)['count'] ?? 0;
?>

<form method="get" class="jk-filter-bar">
    <div class="row g-2">
        <div class="col-6 col-md-3 col-lg-2">
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
        <div class="col-6 col-md-3 col-lg-2">
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
        <div class="col-6 col-md-3 col-lg-3">
            <label class="form-label" for="scheme_id">Scheme</label>
            <select class="form-select form-select-sm" id="scheme_id" name="scheme_id" data-auto-submit>
                <option value="">All schemes</option>
                <?php foreach ($schemes as $id => $name): ?>
                    <option value="<?= (int)$id ?>" <?= (int)($filters['scheme_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                        <?= e(str_limit($name, 40)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="date_from">From</label>
            <input type="date" class="form-control form-control-sm" id="date_from" name="date_from"
                   value="<?= e($filters['date_from'] ?? '') ?>" data-auto-submit>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
            <label class="form-label" for="date_to">To</label>
            <input type="date" class="form-control form-control-sm" id="date_to" name="date_to"
                   value="<?= e($filters['date_to'] ?? '') ?>" data-date-min-from="date_from" data-auto-submit>
        </div>
        <div class="col-6 col-lg-1 d-flex align-items-end">
            <a href="<?= e(url('/applications/funnel')) ?>" class="btn btn-sm btn-outline-secondary w-100">Clear</a>
        </div>
    </div>
</form>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="jk-card">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-funnel"></i>Application Funnel</h3></div>
            <div class="jk-card__body">
                <?php require VIEW_PATH . '/partials/funnel.php'; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="jk-card h-100">
            <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-percent"></i>Conversion</h3></div>
            <div class="jk-card__body">
                <div class="text-center mb-3">
                    <div class="display-5 fw-bold text-primary"><?= pct(percent_of($bottom, $top), 1) ?></div>
                    <div class="text-muted small">of identified applications reached benefit received</div>
                </div>

                <div class="jk-dl">
                    <?php
                    $previous = null;
                    foreach ($stages as $stage):
                        $drop = ($previous !== null && $previous > 0)
                            ? round((1 - $stage['count'] / $previous) * 100, 1) : null;
                    ?>
                        <div class="jk-dl__row">
                            <div class="jk-dl__key"><?= e($stage['label']) ?></div>
                            <div class="jk-dl__val">
                                <strong><?= num($stage['count']) ?></strong>
                                <?php if ($drop !== null): ?>
                                    <span class="text-<?= $drop > 40 ? 'danger' : ($drop > 0 ? 'warning' : 'success') ?> small ms-1">
                                        <?= $drop > 0 ? '&minus;' . $drop . '%' : 'no drop' ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php $previous = $stage['count']; endforeach; ?>
                </div>

                <hr>
                <p class="text-muted small mb-0">
                    The largest single drop shows where the programme loses the most people. Follow it up in
                    <a href="<?= e(url('/reports/applications-pending')) ?>">Applications Pending</a>
                    to see which records are stuck and for how long.
                </p>
            </div>
        </div>
    </div>
</div>
