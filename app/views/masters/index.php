<?php
/**
 * Master data hub (specification section 28).
 * @var array $cards, $geography
 */
$pageSubtitle = 'Configurable lists that drive the scheme master, the assessment form and the eligibility engine';
require VIEW_PATH . '/partials/page-head.php';
?>

<h2 class="h6 text-muted text-uppercase mb-2" style="font-size:.72rem;letter-spacing:.06em">Geography</h2>
<div class="row g-3 mb-3">
    <?php
    $geoCards = [
        ['url' => '/masters/states',          'label' => 'States & UTs',      'icon' => 'bi-map',     'count' => $geography['states']],
        ['url' => '/masters/districts',       'label' => 'Districts',         'icon' => 'bi-geo-alt', 'count' => $geography['districts']],
        ['url' => '/masters/blocks',          'label' => 'Blocks',            'icon' => 'bi-grid',    'count' => $geography['blocks']],
        ['url' => '/masters/gram-panchayats', 'label' => 'Gram Panchayats',   'icon' => 'bi-diagram-3','count' => $geography['gps']],
        ['url' => '/masters/villages',        'label' => 'Villages',          'icon' => 'bi-houses',  'count' => $geography['villages']],
    ];
    foreach ($geoCards as $card): ?>
        <div class="col-6 col-md-4 col-xl-2">
            <a href="<?= e(url($card['url'])) ?>" class="jk-card h-100 mb-0 text-decoration-none d-block">
                <div class="jk-card__body text-center">
                    <i class="bi <?= e($card['icon']) ?> d-block mb-2" style="font-size:1.6rem;color:var(--jk-primary)"></i>
                    <div class="h5 mb-0" style="color:var(--jk-text)"><?= num($card['count']) ?></div>
                    <div class="text-muted small"><?= e($card['label']) ?></div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="h6 text-muted text-uppercase mb-2" style="font-size:.72rem;letter-spacing:.06em">Domain Masters</h2>
<div class="row g-3">
    <?php foreach ($cards as $key => $card): ?>
        <div class="col-md-6 col-xl-4">
            <a href="<?= e(url('/masters/' . $key)) ?>" class="jk-card h-100 mb-0 text-decoration-none d-block">
                <div class="jk-card__body d-flex gap-3">
                    <span class="jk-kpi__icon" style="background:var(--jk-primary-soft);color:var(--jk-primary)">
                        <i class="bi <?= e($card['icon']) ?>"></i>
                    </span>
                    <span class="flex-grow-1 min-width-0">
                        <span class="d-flex justify-content-between align-items-start gap-2">
                            <span class="fw-semibold" style="color:var(--jk-text)"><?= e($card['label']) ?></span>
                            <span class="badge bg-secondary"><?= num($card['active']) ?></span>
                        </span>
                        <span class="d-block text-muted small mt-1"><?= e($card['description']) ?></span>
                        <?php if ($card['count'] > $card['active']): ?>
                            <span class="d-block text-subtle tiny mt-1">
                                <?= num($card['count'] - $card['active']) ?> inactive
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>

<div class="jk-card mt-3">
    <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-lightbulb"></i>Extending the System</h3></div>
    <div class="jk-card__body small text-muted">
        <div class="row g-3">
            <div class="col-md-4">
                <strong class="d-block text-dark mb-1">A new dropdown</strong>
                Add rows to <a href="<?= e(url('/masters/lookups')) ?>">Dropdown Options</a> under a new
                <em>dropdown key</em>. No schema change is needed.
            </div>
            <div class="col-md-4">
                <strong class="d-block text-dark mb-1">A new assessment question</strong>
                Add it under <a href="<?= e(url('/settings/assessment-questions')) ?>">Settings &rsaquo;
                Assessment Questions</a>; it appears on the field form immediately.
            </div>
            <div class="col-md-4">
                <strong class="d-block text-dark mb-1">A new eligibility condition</strong>
                Declare it under <a href="<?= e(url('/settings/eligibility-attributes')) ?>">Settings &rsaquo;
                Eligibility Attributes</a>, then use it in any scheme's rule builder.
            </div>
        </div>
    </div>
</div>
