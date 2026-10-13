<?php
/**
 * Fallback error page. Used for any status without a dedicated template.
 * @var int    $status
 * @var string $title
 * @var string $message
 */
$icons = [400 => 'bi-exclamation-diamond', 401 => 'bi-shield-lock', 403 => 'bi-slash-circle',
          404 => 'bi-compass', 405 => 'bi-sign-stop', 419 => 'bi-clock-history',
          422 => 'bi-exclamation-triangle', 429 => 'bi-hourglass-split', 500 => 'bi-bug'];
$icon = $icons[$status ?? 500] ?? 'bi-exclamation-circle';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= (int)($status ?? 500) ?> <?= htmlspecialchars($title ?? 'Error', ENT_QUOTES, 'UTF-8') ?> | Jan Kavach</title>
<link rel="stylesheet" href="<?= htmlspecialchars(asset('vendor/bootstrap/bootstrap.min.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(asset('vendor/bootstrap-icons/bootstrap-icons.css'), ENT_QUOTES, 'UTF-8') ?>">
<link rel="stylesheet" href="<?= htmlspecialchars(asset('css/app.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="d-flex align-items-center justify-content-center" style="min-height:100vh;padding:1.5rem">
  <div class="jk-card text-center" style="max-width:520px;width:100%">
    <div class="jk-card__body p-4 p-md-5">
      <i class="bi <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" style="font-size:3.2rem;color:var(--jk-primary)"></i>
      <div class="display-5 fw-bold mt-2" style="color:var(--jk-primary)"><?= (int)($status ?? 500) ?></div>
      <h1 class="h5 mb-2"><?= htmlspecialchars($title ?? 'Error', ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="text-muted">
        <?= htmlspecialchars($message ?: 'Something went wrong while handling your request.', ENT_QUOTES, 'UTF-8') ?>
      </p>
      <div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
        <a href="<?= htmlspecialchars(url('/dashboard'), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-primary">
          <i class="bi bi-house-door me-1"></i>Go to Dashboard
        </a>
        <button class="btn btn-outline-secondary" onclick="history.back()">
          <i class="bi bi-arrow-left me-1"></i>Go Back
        </button>
      </div>
    </div>
  </div>
</div>
</body>
</html>
