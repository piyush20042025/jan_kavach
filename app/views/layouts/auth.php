<?php
/**
 * Two-panel layout for the sign-in and password screens.
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
<meta name="description" content="<?= e($appName ?? 'Jan Kavach') ?> - <?= e($appTagline ?? '') ?>">
<?= Csrf::metaTag() ?>
<title><?= e(($pageTitle ?? 'Sign In') . ' | ' . ($appName ?? 'Jan Kavach')) ?></title>

<link rel="icon" href="<?= e(asset('images/favicon-32.png')) ?>" sizes="32x32" type="image/png">
<link rel="icon" href="<?= e(asset('images/favicon-16.png')) ?>" sizes="16x16" type="image/png">
<link rel="apple-touch-icon" href="<?= e(asset('images/apple-touch-icon.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?= View::section('styles') ?>
</head>
<body>

<div class="jk-auth">

    <!-- Brand panel: the artwork fills it edge to edge -->
    <section class="jk-auth__brand">
        <?php $tagline = $appTagline ?? 'Connecting Citizens with Social Security Schemes'; ?>

        <!-- The artwork carries the logo, the name and the tagline, so the panel
             carries no text of its own. The heading stays in the document for
             screen readers and for the page outline. -->
        <h1 class="visually-hidden"><?= e($appName ?? 'Jan Kavach') ?> &mdash; <?= e($tagline) ?></h1>

        <img class="jk-auth__hero"
             src="<?= e(asset('images/login-hero.jpg')) ?>"
             srcset="<?= e(asset('images/login-hero-sm.jpg')) ?> 760w, <?= e(asset('images/login-hero.jpg')) ?> 1254w"
             sizes="(max-width: 991px) 100vw, 55vw"
             width="1254" height="1254" fetchpriority="high"
             alt="<?= e($appName ?? 'Jan Kavach') ?> &mdash; <?= e($tagline) ?>. A field worker shows a tablet to a rural family gathered outside their home.">
    </section>

    <!-- Form panel -->
    <section class="jk-auth__form">
        <div class="w-100" style="max-width:400px">
            <?php require VIEW_PATH . '/partials/flash.php'; ?>
            <?= $content ?>

            <p class="text-center text-muted mt-3 mb-0" style="font-size:.73rem">
                <?= e($appName ?? 'Jan Kavach') ?> v<?= e($appVersion ?? '1.0.0') ?>
                &middot; Social Protection Management Information System
            </p>
        </div>
    </section>
</div>

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
