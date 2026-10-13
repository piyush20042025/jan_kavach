<?php
/**
 * Global scheme search (specification section 34).
 *
 * @var string $term
 * @var array  $results, $filters, $states, $themes, $beneficiaryTypes
 */
$pageSubtitle = 'Find schemes by name, keyword, State, theme or beneficiary type';
require VIEW_PATH . '/partials/page-head.php';
?>

<div class="jk-card">
    <div class="jk-card__body">
        <form method="get" action="<?= e(url('/search')) ?>">
            <div class="row g-2">
                <div class="col-12 col-lg-5">
                    <label class="form-label" for="q">Search Government Schemes</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control" id="q" name="q" value="<?= e($term) ?>"
                               placeholder="e.g. Senior Citizen, pension, pregnant women, weaver" autofocus>
                    </div>
                    <div class="form-text">
                        Searches the scheme name, acronym, theme, beneficiary type, target group,
                        eligibility text and benefit description.
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="state_id">State</label>
                    <select class="form-select" id="state_id" name="state_id">
                        <option value="">All States</option>
                        <?php foreach ($states as $id => $name): ?>
                            <option value="<?= (int)$id ?>" <?= (int)($filters['state_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                                <?= e($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="theme_id">Theme</label>
                    <select class="form-select" id="theme_id" name="theme_id">
                        <option value="">All Themes</option>
                        <?php foreach ($themes as $id => $name): ?>
                            <option value="<?= (int)$id ?>" <?= (int)($filters['theme_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                                <?= e($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="form-label" for="beneficiary_type_id">Beneficiary Type</label>
                    <select class="form-select" id="beneficiary_type_id" name="beneficiary_type_id">
                        <option value="">All</option>
                        <?php foreach ($beneficiaryTypes as $id => $name): ?>
                            <option value="<?= (int)$id ?>" <?= (int)($filters['beneficiary_type_id'] ?? 0) === (int)$id ? 'selected' : '' ?>>
                                <?= e($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </div>
        </form>

        <?php if ($term === '' && !array_filter($filters)): ?>
            <div class="mt-3 pt-3 border-top">
                <div class="text-muted small mb-2">Common searches</div>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach (['Senior Citizen', 'Pregnant Women', 'Widow', 'Disability', 'Farmer',
                                    'Scholarship', 'Housing', 'Insurance', 'Pension', 'Weaver',
                                    'Construction Worker', 'Girl Child'] as $suggestion): ?>
                        <a href="<?= e(url('/search?q=' . urlencode($suggestion))) ?>" class="jk-chip">
                            <i class="bi bi-search"></i><?= e($suggestion) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($term !== '' || array_filter($filters)): ?>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 mb-0">
            <?= count($results) ?> scheme<?= count($results) === 1 ? '' : 's' ?> found
            <?php if ($term !== ''): ?>
                for &ldquo;<span class="text-primary"><?= e($term) ?></span>&rdquo;
            <?php endif; ?>
        </h2>
        <a href="<?= e(url('/search')) ?>" class="small">Clear search</a>
    </div>

    <?php if (empty($results)): ?>
        <div class="jk-card">
            <div class="jk-empty">
                <i class="bi bi-search"></i>
                <h5>Nothing matched that search</h5>
                <p>Try a shorter term, a different spelling, or remove one of the filters.
                   Searching for a theme such as &ldquo;Health&rdquo; or a group such as
                   &ldquo;Senior Citizen&rdquo; usually gives the widest result.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($results as $scheme): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="jk-card h-100 mb-0">
                        <div class="jk-card__body d-flex flex-column h-100">
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                <?php if ($scheme['scheme_level'] === 'National'): ?>
                                    <span class="badge bg-primary">National</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#2a9d8f"><?= e($scheme['scheme_level']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($scheme['state_name'])): ?>
                                    <span class="badge bg-light text-dark border"><?= e($scheme['state_name']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($scheme['theme_name'])): ?>
                                    <?= badge_color($scheme['theme_name'], $scheme['theme_color'] ?: '#6c757d') ?>
                                <?php endif; ?>
                                <?php if (!empty($scheme['priority_rank'])): ?>
                                    <span class="badge bg-danger">P<?= (int)$scheme['priority_rank'] ?></span>
                                <?php endif; ?>
                            </div>

                            <h3 class="h6 mb-1">
                                <a href="<?= e(url('/schemes/' . (int)$scheme['id'])) ?>">
                                    <?= e(str_limit($scheme['name'], 78)) ?>
                                </a>
                            </h3>

                            <?php if (!empty($scheme['beneficiary_types'])): ?>
                                <div class="text-muted small mb-2">
                                    <i class="bi bi-people me-1"></i><?= e(str_limit($scheme['beneficiary_types'], 56)) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($scheme['target_group_summary'])): ?>
                                <p class="text-muted small mb-2" style="font-size:.79rem">
                                    <?= e(str_limit($scheme['target_group_summary'], 130)) ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!empty($scheme['benefit_description'])): ?>
                                <p class="small mb-2" style="font-size:.79rem">
                                    <i class="bi bi-gift text-success me-1"></i>
                                    <?= e(str_limit($scheme['benefit_description'], 110)) ?>
                                </p>
                            <?php endif; ?>

                            <div class="mt-auto pt-2 d-flex gap-2">
                                <a href="<?= e(url('/schemes/' . (int)$scheme['id'])) ?>"
                                   class="btn btn-sm btn-outline-primary flex-grow-1">View details</a>
                                <?php if (can('beneficiary.match')): ?>
                                    <a href="<?= e(url('/matching/eligibility-check?scheme_id=' . (int)$scheme['id'])) ?>"
                                       class="btn btn-sm btn-outline-secondary" data-bs-toggle="tooltip"
                                       title="Check eligibility">
                                        <i class="bi bi-check2-square"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
