<?php
/**
 * Page title, breadcrumb trail and the action buttons for a screen.
 *
 * @var string     $pageTitle
 * @var array      $breadcrumbs  [['label' => 'Schemes', 'url' => '/schemes'], ...]
 * @var string     $pageSubtitle optional
 * @var string     $pageActions  optional pre-rendered HTML
 */
?>
<div class="jk-page-head">
    <div>
        <?php if (!empty($breadcrumbs)): ?>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="<?= e(url('/dashboard')) ?>"><i class="bi bi-house-door"></i></a>
                    </li>
                    <?php foreach ($breadcrumbs as $index => $crumb): ?>
                        <?php $isLast = $index === array_key_last($breadcrumbs); ?>
                        <li class="breadcrumb-item <?= $isLast ? 'active' : '' ?>"
                            <?= $isLast ? 'aria-current="page"' : '' ?>>
                            <?php if (!$isLast && !empty($crumb['url'])): ?>
                                <a href="<?= e(url($crumb['url'])) ?>"><?= e($crumb['label']) ?></a>
                            <?php else: ?>
                                <?= e($crumb['label']) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        <?php endif; ?>

        <h1><?= e($pageTitle ?? '') ?></h1>
        <?php if (!empty($pageSubtitle)): ?>
            <p class="text-muted mb-0 small"><?= e($pageSubtitle) ?></p>
        <?php endif; ?>
    </div>

    <?php if (!empty($pageActions)): ?>
        <div class="d-flex flex-wrap gap-2 no-print"><?= $pageActions ?></div>
    <?php endif; ?>
</div>
