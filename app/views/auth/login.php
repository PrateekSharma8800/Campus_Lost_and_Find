<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-7 col-lg-5 col-xl-4">
        <div class="card auth-card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-1 text-center">Welcome back</h1>
                <p class="text-muted text-center mb-4">Log in to your CampusFind account.</p>

                <?php if (!empty($alert)): ?>
                    <div class="alert alert-danger d-flex gap-2" role="alert">
                        <i class="bi bi-exclamation-octagon flex-shrink-0 mt-1" aria-hidden="true"></i>
                        <div><?= e($alert) ?></div>
                    </div>
                <?php endif; ?>

                <form method="post" action="/login" novalidate data-once>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="f_email" class="form-label">Email address</label>
                        <input type="email" class="form-control<?= invalid($errors, 'email') ?>" id="f_email" name="email"
                               value="<?= e($old['email'] ?? '') ?>" maxlength="190" autocomplete="username" required autofocus>
                        <?= feedback($errors, 'email') ?>
                    </div>

                    <?php View::partial('password-input', ['name' => 'password', 'label' => 'Password', 'errors' => $errors,
                        'autocomplete' => 'current-password']) ?>

                    <div class="text-end mb-3">
                        <a href="/forgot-password" class="small">Forgot your password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2">Log in</button>
                </form>

                <p class="text-center text-muted mt-4 mb-0">
                    New to CampusFind? <a href="/register">Create an account</a>
                </p>
            </div>
        </div>
    </div>
</div>
