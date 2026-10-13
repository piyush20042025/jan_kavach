<?php
/**
 * Print / PDF layout.  The browser's own print-to-PDF produces a faithful,
 * Unicode-correct document without adding a PDF binary dependency.
 * @var string $content
 */
use JanKavach\Core\View;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(($pageTitle ?? 'Print') . ' | ' . ($appName ?? 'Jan Kavach')) ?></title>
<link rel="icon" href="<?= e(asset('images/favicon-32.png')) ?>" sizes="32x32" type="image/png">
<link rel="icon" href="<?= e(asset('images/favicon-16.png')) ?>" sizes="16x16" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('images/apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<style>
  body { background: #fff; }
  .jk-print-page { max-width: 1120px; margin: 0 auto; padding: 1.25rem; }
  .jk-print-head {
    border-bottom: 2px solid var(--jk-primary);
    padding-bottom: .7rem; margin-bottom: 1rem;
    display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem;
  }
  .jk-print-head h1 { font-size: 1.22rem; margin: 0 0 .15rem; color: var(--jk-primary); }
  .jk-print-meta { font-size: .75rem; color: var(--jk-text-muted); text-align: right; }
  .jk-print-bar {
    position: sticky; top: 0; z-index: 20;
    background: var(--jk-primary); color: #fff;
    padding: .5rem 1rem; display: flex; gap: .5rem;
    align-items: center; justify-content: space-between;
  }
  @media print { .jk-print-bar { display: none !important; } .jk-print-page { padding: 0; max-width: none; } }
</style>
<?= View::section('styles') ?>
</head>
<body>

<div class="jk-print-bar no-print">
    <span class="small">Use your browser's print dialogue and choose <strong>Save as PDF</strong> for a PDF copy.</span>
    <span class="d-flex gap-2">
        <button class="btn btn-sm btn-light" onclick="window.print()">
            <i class="bi bi-printer me-1"></i>Print / Save as PDF
        </button>
        <button class="btn btn-sm btn-outline-light" onclick="window.close()">Close</button>
    </span>
</div>

<div class="jk-print-page">
    <?= $content ?>
</div>

<?php if (!empty($autoPrint)): ?>
<script>
  // Give the layout a moment to settle before opening the dialogue.
  window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 350); });
</script>
<?php endif; ?>
<?= View::section('scripts') ?>
</body>
</html>
