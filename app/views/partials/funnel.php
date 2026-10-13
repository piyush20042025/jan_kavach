<?php
/**
 * Application funnel (specification section 5).
 * Each stage counts everything at or beyond it, so the bars can only narrow.
 *
 * @var array $funnel [['stage','label','count','aside'?], ...]
 */
$stages = array_values(array_filter($funnel ?? [], fn($s) => empty($s['aside'])));
$aside  = array_values(array_filter($funnel ?? [], fn($s) => !empty($s['aside'])));
$top    = $stages[0]['count'] ?? 0;

$colors = ['#0f4c81', '#1a6bb0', '#2a9d8f', '#4cae9e', '#e9c46a', '#198754'];
?>
<?php if ($top === 0): ?>
    <div class="jk-empty py-4">
        <i class="bi bi-funnel"></i>
        <h5>No applications yet</h5>
        <p>Once beneficiaries are matched to schemes and applications are initiated,
           the progression from identification to benefit received appears here.</p>
    </div>
<?php else: ?>
    <div class="jk-funnel">
        <?php foreach ($stages as $index => $stage): ?>
            <?php
            $count   = (int)$stage['count'];
            $width   = $top > 0 ? max(1.2, ($count / $top) * 100) : 0;
            $previous = $index > 0 ? (int)$stages[$index - 1]['count'] : null;
            $drop    = ($previous !== null && $previous > 0)
                       ? round((1 - $count / $previous) * 100)
                       : null;
            $color   = $colors[$index % count($colors)];
            ?>
            <div class="jk-funnel__row">
                <div class="jk-funnel__label"><?= e($stage['label']) ?></div>
                <div class="jk-funnel__bar-wrap">
                    <div class="jk-funnel__bar" style="width:<?= $width ?>%;background:<?= e($color) ?>">
                        <?php if ($width > 18): ?>
                            <?= $top > 0 ? round($count / $top * 100) : 0 ?>%
                        <?php endif; ?>
                    </div>
                </div>
                <div class="jk-funnel__count"><?= num($count) ?></div>
                <div class="jk-funnel__drop">
                    <?php if ($drop !== null && $drop > 0): ?>
                        <span class="text-danger" data-bs-toggle="tooltip"
                              title="<?= $drop ?>% of records did not progress past the previous stage">
                            &minus;<?= $drop ?>%
                        </span>
                    <?php elseif ($drop !== null): ?>
                        <span class="text-success">&mdash;</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($aside): ?>
        <div class="d-flex flex-wrap gap-3 mt-3 pt-2 border-top">
            <?php foreach ($aside as $stage): ?>
                <span class="jk-chip">
                    <i class="bi <?= $stage['stage'] === 'Rejected' ? 'bi-x-circle text-danger' : 'bi-archive' ?>"></i>
                    <?= e($stage['label']) ?>: <strong><?= num($stage['count']) ?></strong>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
