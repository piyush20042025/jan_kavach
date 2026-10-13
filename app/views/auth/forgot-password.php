<?php
/**
 * Request a password reset link (specification section 31 - "secure password
 * reset mechanism").
 *
 * The controller answers identically whether or not the address is registered,
 * so this page cannot be used to work out who holds an account. The wording
 * here has to match that: it never confirms or denies that an address exists.
 */
?>
<div class="jk-auth__card">

    <h2>Forgot Your Password?</h2>
    <p class="subtitle">
        Enter the email address on your account and we will send you a link to choose a new password.
    </p>

    <form method="post" action="<?= e(url('/forgot-password')) ?>" data-guard novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="email" class="form-label">
                Email Address <span class="req">*</span>
            </label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-envelope text-muted"></i></span>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= e(old('email', '', $old ?? [])) ?>"
                       required autofocus autocomplete="email"
                       placeholder="you@department.gov.in" maxlength="150">
            </div>
            <div class="form-text">
                This must be the address your programme administrator recorded against your account.
            </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 btn-lg">
            <i class="bi bi-send me-1"></i>Send Reset Link
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
        The link is valid for one hour and can be used once. Requesting a new link cancels any
        earlier one. If you no longer have access to the address on your account, ask your
        programme administrator to reset the password for you.
    </p>

    <?php if (config('app.env') !== 'production' && !config('mail.enabled')): ?>
        <div class="alert alert-warning mt-3 mb-0 py-2" style="font-size:.78rem">
            <i class="bi bi-info-circle me-1"></i>
            Email delivery is switched off in this environment, so the reset link is shown on
            screen after you submit instead of being sent. See
            <code>mail.enabled</code> in <code>config/config.php</code>.
        </div>
    <?php endif; ?>
</div>
