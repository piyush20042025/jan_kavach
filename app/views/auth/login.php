<?php
/**
 * Sign-in screen (specification section 3).
 * @var bool  $timedOut
 * @var array $old
 */
?>
<div class="jk-auth__card">

    <h2>Sign In</h2>
    <p class="subtitle">Use the account issued to you by your programme administrator.</p>

    <?php if (!empty($timedOut)): ?>
        <div class="alert alert-warning d-flex gap-2 align-items-start">
            <i class="bi bi-clock-history mt-1"></i>
            <div>Your session timed out after a period of inactivity. Please sign in again.</div>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('/login')) ?>" data-guard novalidate>
        <?= csrf_field() ?>

        <div class="mb-3">
            <label for="username" class="form-label">
                Username or Email <span class="req">*</span>
            </label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-person text-muted"></i></span>
                <input type="text" class="form-control" id="username" name="username"
                       value="<?= e(old('username', '', $old ?? [])) ?>"
                       required autofocus autocomplete="username"
                       placeholder="e.g. admin" maxlength="150">
            </div>
        </div>

        <div class="mb-3">
            <label for="password" class="form-label">
                Password <span class="req">*</span>
            </label>
            <div class="input-group jk-password-wrap">
                <span class="input-group-text bg-white"><i class="bi bi-lock text-muted"></i></span>
                <input type="password" class="form-control border-end-0" id="password" name="password"
                       required autocomplete="current-password" placeholder="Enter your password" maxlength="200">
                <span class="input-group-text bg-white p-0 border-start-0">
                    <button class="jk-password-toggle position-static translate-middle-y-0" type="button"
                            style="position:static !important;transform:none !important"
                            aria-label="Show password">
                        <i class="bi bi-eye"></i>
                    </button>
                </span>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
            <a href="<?= e(url('/forgot-password')) ?>" class="small">Forgot password?</a>
        </div>

        <button type="submit" class="btn btn-primary w-100 btn-lg">
            <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
        </button>
    </form>

    <?php if (config('app.env') !== 'production'): ?>
        <div class="jk-demo-creds">
            <div class="d-flex align-items-center gap-1 fw-semibold mb-1">
                <i class="bi bi-info-circle"></i> Demonstration accounts
            </div>
            <table>
                <tr>
                    <td><code>admin</code></td>
                    <td><code>Admin@123</code></td>
                    <td>Super Administrator</td>
                    <td><button type="button" class="jk-fill" data-user="admin" data-pass="Admin@123">use</button></td>
                </tr>
                <tr>
                    <td><code>wb.admin</code></td>
                    <td><code>State@123</code></td>
                    <td>State Admin &ndash; West Bengal</td>
                    <td><button type="button" class="jk-fill" data-user="wb.admin" data-pass="State@123">use</button></td>
                </tr>
                <tr>
                    <td><code>wb.purulia</code></td>
                    <td><code>District@123</code></td>
                    <td>District Coordinator</td>
                    <td><button type="button" class="jk-fill" data-user="wb.purulia" data-pass="District@123">use</button></td>
                </tr>
                <tr>
                    <td><code>wb.field1</code></td>
                    <td><code>Field@123</code></td>
                    <td>Field User</td>
                    <td><button type="button" class="jk-fill" data-user="wb.field1" data-pass="Field@123">use</button></td>
                </tr>
            </table>
            <div class="mt-1" style="font-size:.7rem;opacity:.85">
                Each account shows a different slice of the same data, which is how the
                State and District restrictions can be seen in practice.
            </div>
        </div>
    <?php endif; ?>
</div>

<?php \JanKavach\Core\View::startSection('scripts'); ?>
<script>
  document.querySelectorAll('.jk-fill').forEach(function (button) {
    button.addEventListener('click', function () {
      document.getElementById('username').value = button.dataset.user;
      document.getElementById('password').value = button.dataset.pass;
      document.getElementById('username').focus();
    });
  });
</script>
<?php \JanKavach\Core\View::endSection(); ?>
