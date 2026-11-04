<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="card auth-card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-1 text-center">Choose a new password</h1>

                <?php if (!$valid): ?>
                    <div class="alert alert-warning mt-4 d-flex gap-2" role="alert">
                        <i class="bi bi-exclamation-triangle flex-shrink-0 mt-1" aria-hidden="true"></i>
                        <div>This password reset link is invalid or has expired. Reset links work once and last
                            <?= PasswordReset::TTL_MINUTES ?> minutes.</div>
                    </div>
                    <a href="/forgot-password" class="btn btn-primary w-100 py-2">Request a new link</a>
                <?php else: ?>
                    <p class="text-muted text-center mb-4">Pick a strong password that you don't use on other sites.</p>
                    <form method="post" action="/reset-password" novalidate data-once>
                        <?= csrf_field() ?>
                        <input type="hidden" name="token" value="<?= e($token) ?>">
                        <?php View::partial('password-input', ['name' => 'password', 'label' => 'New password', 'errors' => $errors,
                            'autocomplete' => 'new-password', 'help' => 'At least 8 characters, including a letter and a number.']) ?>
                        <?php View::partial('password-input', ['name' => 'password_confirmation', 'label' => 'Confirm new password',
                            'errors' => $errors, 'autocomplete' => 'new-password']) ?>
                        <button type="submit" class="btn btn-primary w-100 py-2 mt-2">Reset password</button>
                    </form>
                <?php endif; ?>

                <p class="text-center mt-4 mb-0"><a href="/login"><i class="bi bi-arrow-left me-1"></i>Back to log in</a></p>
            </div>
        </div>
    </div>
</div>
