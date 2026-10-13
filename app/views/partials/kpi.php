<?php
/**
 * One KPI tile.
 *
 * @var string      $kpiValue
 * @var string      $kpiLabel
 * @var string      $kpiIcon
 * @var string      $kpiColor  hex accent
 * @var string      $kpiSoft   hex tint for the icon background
 * @var string|null $kpiHint
 * @var string|null $kpiUrl
 */
$tag  = !empty($kpiUrl) ? 'a' : 'div';
$href = !empty($kpiUrl) ? ' href="' . e(url($kpiUrl)) . '"' : '';
?>
<<?= $tag ?><?= $href ?> class="jk-kpi"
    style="--jk-kpi-color:<?= e($kpiColor ?? '#0f4c81') ?>;--jk-kpi-soft:<?= e($kpiSoft ?? '#e8f0f8') ?>">
    <span class="jk-kpi__icon"><i class="bi <?= e($kpiIcon ?? 'bi-bar-chart') ?>"></i></span>
    <span class="jk-kpi__body">
        <span class="jk-kpi__value d-block"><?= e($kpiValue ?? '0') ?></span>
        <span class="jk-kpi__label d-block"><?= e($kpiLabel ?? '') ?></span>
        <?php if (!empty($kpiHint)): ?>
            <span class="jk-kpi__hint d-block"><?= e($kpiHint) ?></span>
        <?php endif; ?>
    </span>
</<?= $tag ?>>
