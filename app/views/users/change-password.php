<?php
/**
 * Change password.
 *
 * Rendered inside the auth layout when a change is being forced (the account
 * was created or reset by an administrator), and inside the application
 * layout when the user chose to change it themselves.
 *
 * @var bool  $mustChange
 * @var array $user
 */
?>
<?php if (!empty($mustChange)): ?>
    <div class="jk-auth__card">
        <h2>Set a New Password</h2>
        <p class="subtitle">
            Your password was issued by an administrator, so it must be changed before you continue.
        </p>
<?php else: ?>
    <?php
    $pageTitle   = 'Change Password';
    $breadcrumbs = [['label' => 'My Profile', 'url' => '/profile'], ['label' => 'Change Password']];
    require VIEW_PATH . '/partials/page-head.php';
    ?>
    <div class="row justify-content-center">
    <div class="col-lg-6">
    <div class="jk-card">
        <div class="jk-card__head"><h3 class="jk-card__title"><i class="bi bi-key"></i>Change Password</h3></div>
        <div class="jk-card__body">
<?php endif; ?>

    <form method="post" action="<?= e(url('/profile/password')) ?>" data-guard novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label class="form-label" for="current_password">Current Password <span class="req">*</span></label>
            <div class="input-group jk-password-wrap">
                <input type="password" class="form-control border-end-0" id="current_password"
                       name="current_password" required autocomplete="current-password">
                <span class="input-group-text bg-white p-0 border-start-0">
                    <button class="jk-password-toggle" type="button"
                            style="position:static !important;transform:none !important"
                            aria-label="Show password"><i class="bi bi-eye"></i></button>
                </span>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="new_password">New Password <span class="req">*</span></label>
            <div class="input-group jk-password-wrap">
                <input type="password" class="form-control border-end-0" id="new_password"
                       name="new_password" required autocomplete="new-password">
                <span class="input-group-text bg-white p-0 border-start-0">
                    <button class="jk-password-toggle" type="button"
                            style="position:static !important;transform:none !important"
                            aria-label="Show password"><i class="bi bi-eye"></i></button>
                </span>
            </div>
            <div class="jk-progress mt-2"><span id="strengthBar" style="width:0%"></span></div>
            <div class="form-text" id="strengthText">
                At least 8 characters, with both letters and numbers.
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="new_password_confirmation">
                Confirm New Password <span class="req">*</span>
            </label>
            <input type="password" class="form-control" id="new_password_confirmation"
                   name="new_password_confirmation" required autocomplete="new-password">
            <div class="form-text" id="matchText"></div>
        </div>

        <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-check-lg me-1"></i>Change Password
        </button>

        <?php if (empty($mustChange)): ?>
            <a href="<?= e(url('/profile')) ?>" class="btn btn-outline-secondary w-100 mt-2">Cancel</a>
        <?php endif; ?>
    </form>

    <?php if (!empty($mustChange)): ?>
        <form method="post" action="<?= e(url('/logout')) ?>" class="mt-2">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-link w-100 text-muted small">Sign out instead</button>
        </form>
    <?php endif; ?>

<?php if (!empty($mustChange)): ?>
    </div>
<?php else: ?>
        </div>
    </div>
    </div>
    </div>
<?php endif; ?>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
(function () {
  const password = document.getElementById('new_password');
  const confirm  = document.getElementById('new_password_confirmation');
  const bar      = document.getElementById('strengthBar');
  const text     = document.getElementById('strengthText');
  const match    = document.getElementById('matchText');

  // Advisory only; the server applies the real rule.
  function strength(value) {
    let score = 0;
    if (value.length >= 8)  score += 30;
    if (value.length >= 12) score += 15;
    if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score += 20;
    if (/[0-9]/.test(value)) score += 20;
    if (/[^A-Za-z0-9]/.test(value)) score += 15;
    return Math.min(100, score);
  }

  password.addEventListener('input', function () {
    const score = strength(password.value);
    bar.style.width = score + '%';
    bar.style.background = score < 45 ? 'var(--jk-danger)'
                         : (score < 75 ? 'var(--jk-warning)' : 'var(--jk-success)');
    text.textContent = password.value === ''
      ? 'At least 8 characters, with both letters and numbers.'
      : (score < 45 ? 'Too weak - add length, digits or mixed case.'
      : (score < 75 ? 'Acceptable, but could be stronger.' : 'Strong password.'));
  });

  confirm.addEventListener('input', function () {
    if (confirm.value === '') { match.textContent = ''; return; }
    const same = confirm.value === password.value;
    match.textContent = same ? 'Passwords match.' : 'The passwords do not match yet.';
    match.className = 'form-text ' + (same ? 'text-success' : 'text-danger');
  });
})();
</script>
<?php \JanKavach\Core\View::endSection(); ?>
