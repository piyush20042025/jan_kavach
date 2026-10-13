<?php
/**
 * Choose a new password against a valid reset token
 * (specification section 31 - "secure password reset mechanism").
 *
 * @var string $token     single-use token, already validated by the controller
 * @var string $fullName  whose account this is
 */
$minLength = (int)(config('security.password_min_length') ?? 8);
?>
<div class="jk-auth__card">

    <h2>Choose a New Password</h2>
    <p class="subtitle">
        Setting the password for <strong><?= e($fullName) ?></strong>.
    </p>

    <form method="post" action="<?= e(url('/reset-password')) ?>" data-guard novalidate id="resetForm">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <div class="mb-3">
            <label for="password" class="form-label">
                New Password <span class="req">*</span>
            </label>
            <div class="input-group jk-password-wrap">
                <span class="input-group-text bg-white"><i class="bi bi-lock text-muted"></i></span>
                <input type="password" class="form-control border-end-0" id="password" name="password"
                       required autofocus autocomplete="new-password"
                       minlength="<?= $minLength ?>" maxlength="200"
                       placeholder="At least <?= $minLength ?> characters">
                <span class="input-group-text bg-white p-0 border-start-0">
                    <button class="jk-password-toggle position-static" type="button"
                            style="position:static !important;transform:none !important"
                            aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </span>
            </div>
            <ul class="mt-2 mb-0 ps-3 text-muted" style="font-size:.76rem" id="pwRules">
                <li data-rule="length">At least <?= $minLength ?> characters</li>
                <li data-rule="mix">Contains both letters and numbers</li>
                <li data-rule="common">Not an obvious password such as <code>password1</code></li>
            </ul>
        </div>

        <div class="mb-3">
            <label for="password_confirmation" class="form-label">
                Confirm New Password <span class="req">*</span>
            </label>
            <div class="input-group jk-password-wrap">
                <span class="input-group-text bg-white"><i class="bi bi-lock-fill text-muted"></i></span>
                <input type="password" class="form-control border-end-0"
                       id="password_confirmation" name="password_confirmation"
                       required autocomplete="new-password" maxlength="200"
                       placeholder="Type it again">
                <span class="input-group-text bg-white p-0 border-start-0">
                    <button class="jk-password-toggle position-static" type="button"
                            style="position:static !important;transform:none !important"
                            aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </span>
            </div>
            <div class="form-text" id="matchHint"></div>
        </div>

        <button type="submit" class="btn btn-primary w-100 btn-lg">
            <i class="bi bi-check-lg me-1"></i>Set New Password
        </button>
    </form>

    <div class="text-center mt-3">
        <a href="<?= e(url('/login')) ?>" class="small">
            <i class="bi bi-arrow-left me-1"></i>Back to sign in
        </a>
    </div>

    <hr class="my-3">

    <p class="text-muted mb-0" style="font-size:.76rem">
        <i class="bi bi-shield-lock me-1"></i>
        This link can only be used once. Every session signed in on your account is ended when
        the password changes, so you will need to sign in again everywhere.
    </p>
</div>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
(function () {
  // Live feedback only. The same rules are enforced again on the server,
  // which is what actually decides whether the password is accepted.
  var MIN    = <?= (int)$minLength ?>;
  var COMMON = ['password', 'password1', '12345678', 'admin123', 'qwerty123', 'jankavach'];

  var password = document.getElementById('password');
  var confirm  = document.getElementById('password_confirmation');
  var hint     = document.getElementById('matchHint');
  var rules    = document.getElementById('pwRules');

  function mark(name, ok) {
    var li = rules.querySelector('[data-rule="' + name + '"]');
    if (!li) return;
    li.classList.toggle('text-success', ok);
    li.classList.toggle('text-muted', !ok);
  }

  function check() {
    var value = password.value;
    mark('length', value.length >= MIN);
    mark('mix', /[A-Za-z]/.test(value) && /\d/.test(value));
    mark('common', value.length > 0 && COMMON.indexOf(value.toLowerCase()) === -1);

    if (!confirm.value) {
      hint.textContent = '';
      hint.className = 'form-text';
      return;
    }
    var same = confirm.value === value;
    hint.textContent = same ? 'Both entries match.' : 'The two entries do not match yet.';
    hint.className = 'form-text ' + (same ? 'text-success' : 'text-danger');
  }

  password.addEventListener('input', check);
  confirm.addEventListener('input', check);
})();
</script>
<?php \JanKavach\Core\View::endSection(); ?>
