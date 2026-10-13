<?php
/**
 * Print-ready report page, used for the "PDF" export route.
 *
 * Rendered without a layout by Exporter::printable(), so it carries its own
 * document shell.
 *
 * @var array  $headers, $rows, $filters
 * @var string $title, $subtitle, $orientation, $generatedBy, $generatedAt, $footerNote
 * @var bool   $truncated, $autoPrint
 */
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | <?= e($appName ?? 'Jan Kavach') ?></title>
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>
  @page { size: A4 <?= e($orientation === 'landscape' ? 'landscape' : 'portrait') ?>; margin: 12mm; }
  body { background: #fff; }
  .jk-report { max-width: <?= $orientation === 'landscape' ? '1400px' : '1000px' ?>; margin: 0 auto; padding: 1.25rem; }
  .jk-report-head { border-bottom: 2px solid var(--jk-primary); padding-bottom: .7rem; margin-bottom: .9rem;
                    display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
  .jk-report-head h1 { font-size: 1.2rem; margin: 0 0 .1rem; color: var(--jk-primary); }
  .jk-report-meta { font-size: .72rem; color: #64748b; text-align: right; white-space: nowrap; }
  .jk-report-logo { height: 40px; width: auto; display: block; margin: 0 0 .3rem auto; }
  .jk-report-filters { background: #f4f6fa; border: 1px solid #e2e8f0; border-radius: 6px;
                       padding: .5rem .7rem; font-size: .74rem; margin-bottom: .8rem; }
  .jk-report table { font-size: .72rem; width: 100%; }
  .jk-report th { background: #eef2f7 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  .jk-report td, .jk-report th { padding: .3rem .4rem; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
  .jk-report thead { display: table-header-group; }
  .jk-report tr { page-break-inside: avoid; }
  .jk-print-bar { position: sticky; top: 0; z-index: 20; background: var(--jk-primary); color: #fff;
                  padding: .5rem 1rem; display: flex; gap: .5rem; align-items: center; justify-content: space-between; }
  @media print { .jk-print-bar { display: none !important; } .jk-report { padding: 0; max-width: none; } }
</style>
</head>
<body>

<div class="jk-print-bar no-print">
    <span class="small">
        Use your browser's print dialogue and choose <strong>Save as PDF</strong> to keep a PDF copy.
    </span>
    <span class="d-flex gap-2">
        <button class="btn btn-sm btn-light" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print / Save as PDF
        </button>
        <button class="btn btn-sm btn-outline-light" onclick="window.close()">Close</button>
    </span>
</div>

<div class="jk-report">
    <div class="jk-report-head">
        <div>
            <h1><?= e($title) ?></h1>
            <?php if (!empty($subtitle)): ?>
                <div class="text-muted" style="font-size:.76rem"><?= e($subtitle) ?></div>
            <?php endif; ?>
        </div>
        <div class="jk-report-meta">
            <img class="jk-report-logo" src="<?= e(asset('images/logo-sm.png')) ?>"
                 alt="<?= e($appName ?? 'Jan Kavach') ?>" height="40">
            <div><strong><?= e($appName ?? 'Jan Kavach') ?></strong></div>
            <div><?= e($generatedAt) ?></div>
            <div>by <?= e($generatedBy) ?></div>
            <div><?= num(count($rows)) ?> row<?= count($rows) === 1 ? '' : 's' ?></div>
        </div>
    </div>

    <?php $shown = array_filter($filters, fn($v) => $v !== null && $v !== ''); ?>
    <?php if ($shown): ?>
        <div class="jk-report-filters">
            <strong>Filters:</strong>
            <?php $parts = [];
            foreach ($shown as $label => $value) {
                $parts[] = '<span class="text-muted">' . e($label) . ':</span> <strong>' . e($value) . '</strong>';
            }
            echo implode(' &nbsp;&middot;&nbsp; ', $parts); ?>
        </div>
    <?php endif; ?>

    <?php if ($truncated): ?>
        <div class="alert alert-warning py-1 px-2" style="font-size:.74rem">
            <i class="bi bi-exclamation-triangle me-1"></i>
            This report was capped at the configured export limit; some rows are not shown.
        </div>
    <?php endif; ?>

    <?php if (empty($rows)): ?>
        <p class="text-center text-muted py-5">No data matched the filters for this report.</p>
    <?php else: ?>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th style="width:34px">#</th>
                    <?php foreach ($headers as $header): ?>
                        <th><?= e($header) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $index => $row): ?>
                    <tr>
                        <td class="text-muted"><?= $index + 1 ?></td>
                        <?php foreach ($row as $cell): ?>
                            <td>
                                <?php
                                if ($cell === null || $cell === '') {
                                    echo '-';
                                } elseif (is_float($cell)) {
                                    echo e(rtrim(rtrim(number_format($cell, 2), '0'), '.'));
                                } elseif (is_int($cell)) {
                                    echo num($cell);
                                } else {
                                    echo e(str_limit((string)$cell, 200));
                                }
                                ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <hr>
    <p class="text-muted" style="font-size:.68rem">
        <?= e($footerNote) ?>.
        Personal identifiers such as Aadhaar are masked or omitted in every report.
        Handle this document in line with your organisation's data protection policy.
    </p>
</div>

<?php if (!empty($autoPrint)): ?>
<script>
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 400); });
</script>
<?php endif; ?>
</body>
</html>
