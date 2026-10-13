<?php
/**
 * Flash messages queued by the controllers.
 * @var array $flashMessages [['type' => 'success', 'message' => '...'], ...]
 */
$icons = [
    'success' => 'bi-check-circle',
    'danger'  => 'bi-exclamation-octagon',
    'warning' => 'bi-exclamation-triangle',
    'info'    => 'bi-info-circle',
];
?>
<?php foreach (($flashMessages ?? []) as $flash): ?>
    <?php $type = $flash['type'] ?? 'info'; ?>
    <div class="alert alert-<?= e($type) ?> alert-dismissible fade show d-flex align-items-start gap-2"
         role="alert"
         <?= $type === 'success' || $type === 'info' ? 'data-auto-dismiss="1"' : '' ?>>
        <i class="bi <?= e($icons[$type] ?? 'bi-info-circle') ?> mt-1"></i>
        <div class="flex-grow-1"><?= nl2br_e($flash['message'] ?? '') ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endforeach; ?>

<?php
// Field-level validation errors that were not attached to a visible input.
if (!empty($errors) && is_array($errors) && count($errors) > 0):
    $listed = array_slice($errors, 0, 8);
?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <div class="d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-octagon mt-1"></i>
            <div class="flex-grow-1">
                <strong>Please correct the following:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    <?php foreach ($listed as $field => $message): ?>
                        <li><?= e(is_array($message) ? ($message[0] ?? '') : $message) ?></li>
                    <?php endforeach; ?>
                    <?php if (count($errors) > 8): ?>
                        <li class="text-muted"><?= count($errors) - 8 ?> more&hellip;</li>
                    <?php endif; ?>
                </ul>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    </div>
<?php endif; ?>
