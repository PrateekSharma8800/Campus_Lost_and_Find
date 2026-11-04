<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="card auth-card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-3"><i class="bi bi-key display-6 text-primary" aria-hidden="true"></i></div>
                <h1 class="h3 mb-1 text-center">Forgot password?</h1>
                <p class="text-muted text-center mb-4">Enter your account email and we'll send you a link to reset your password.</p>

                <form method="post" action="/forgot-password" novalidate data-once>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="f_email" class="form-label">Email address</label>
                        <input type="email" class="form-control<?= invalid($errors, 'email') ?>" id="f_email" name="email"
                               value="<?= e($old['email'] ?? '') ?>" maxlength="190" autocomplete="email" required autofocus>
                        <?= feedback($errors, 'email') ?>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2">Send reset link</button>
                </form>

                <p class="text-center mt-4 mb-0"><a href="/login"><i class="bi bi-arrow-left me-1"></i>Back to log in</a></p>
            </div>
        </div>
    </div>
</div>
