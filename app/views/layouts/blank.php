<?php
/**
 * Minimal chrome-free layout, used where a page supplies its own shell.
 * @var string $content
 */
use JanKavach\Core\Csrf;
use JanKavach\Core\View;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= Csrf::metaTag() ?>
<title><?= e(($pageTitle ?? '') . ' | ' . ($appName ?? 'Jan Kavach')) ?></title>
<link rel="icon" href="<?= e(asset('images/favicon-32.png')) ?>" sizes="32x32" type="image/png">
<link rel="icon" href="<?= e(asset('images/favicon-16.png')) ?>" sizes="16x16" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('images/apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?= View::section('styles') ?>
</head>
<body>
<main class="container py-4">
    <?php require VIEW_PATH . '/partials/flash.php'; ?>
    <?= $content ?>
</main>

<script>
  window.JK_CONFIG = {
    baseUrl:   <?= ejs(base_url()) ?>,
    csrfToken: <?= ejs(Csrf::token()) ?>,
    csrfName:  <?= ejs(config('security.csrf_token_name', '_token')) ?>
  };
</script>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?= View::section('scripts') ?>
</body>
</html>
