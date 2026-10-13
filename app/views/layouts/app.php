<?php
/**
 * Main application layout: sidebar + header + content.
 *
 * @var string $content   rendered page body
 * @var array  $authUser
 * @var array  $flashMessages
 */
use JanKavach\Core\Csrf;
use JanKavach\Core\View;

$unreadCount = 0;
if (\JanKavach\Core\Auth::check()) {
    $unreadCount = \JanKavach\Libraries\Notifier::unreadCount(\JanKavach\Core\Auth::id());
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="<?= e($appName ?? 'Jan Kavach') ?> - <?= e($appTagline ?? '') ?>">
<?= Csrf::metaTag() ?>
<title><?= e(($pageTitle ?? 'Dashboard') . ' | ' . ($appName ?? 'Jan Kavach')) ?></title>

<link rel="icon" href="<?= e(asset('images/favicon-32.png')) ?>" sizes="32x32" type="image/png">
<link rel="icon" href="<?= e(asset('images/favicon-16.png')) ?>" sizes="16x16" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('images/apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?= View::section('styles') ?>
</head>
<body>

<div class="jk-backdrop" aria-hidden="true"></div>

<?php require VIEW_PATH . '/partials/sidebar.php'; ?>

<div class="jk-main">

    <?php require VIEW_PATH . '/partials/header.php'; ?>

    <main class="jk-content" id="jk-content">
        <?php require VIEW_PATH . '/partials/flash.php'; ?>
        <?= $content ?>
    </main>

    <footer class="jk-footer">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
            <span>
                <strong><?= e($appName ?? 'Jan Kavach') ?></strong>
                &mdash; <?= e($appTagline ?? '') ?>
            </span>
            <span class="text-subtle">
                Version <?= e($appVersion ?? '1.0.0') ?>
                <?php if (!empty($authUser)): ?>
                    &middot; Signed in as <?= e($authUser['full_name']) ?> (<?= e($authUser['role_name']) ?>)
                <?php endif; ?>
            </span>
        </div>
    </footer>
</div>

<script>
  window.JK_CONFIG = {
    baseUrl:   <?= ejs(base_url()) ?>,
    csrfToken: <?= ejs(Csrf::token()) ?>,
    csrfName:  <?= ejs(config('security.csrf_token_name', '_token')) ?>
  };
</script>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/chartjs/chart.umd.min.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?= View::section('scripts') ?>
</body>
</html>
