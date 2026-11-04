<div class="row justify-content-center">
    <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
        <div class="card auth-card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-1 text-center">Create your account</h1>
                <p class="text-muted text-center mb-4">Join CampusFind to report and recover lost items.</p>

                <form method="post" action="/register" novalidate data-once>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="f_name" class="form-label">Full name</label>
                        <input type="text" class="form-control<?= invalid($errors, 'name') ?>" id="f_name" name="name"
                               value="<?= e($old['name'] ?? '') ?>" maxlength="100" autocomplete="name" required autofocus>
                        <?= feedback($errors, 'name') ?>
                    </div>

                    <div class="mb-3">
                        <label for="f_email" class="form-label">Email address</label>
                        <input type="email" class="form-control<?= invalid($errors, 'email') ?>" id="f_email" name="email"
                               value="<?= e($old['email'] ?? '') ?>" maxlength="190" autocomplete="email" required>
                        <?= feedback($errors, 'email') ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label for="f_student_id" class="form-label">Student ID <span class="text-muted small">(optional)</span></label>
                            <input type="text" class="form-control<?= invalid($errors, 'student_id') ?>" id="f_student_id" name="student_id"
                                   value="<?= e($old['student_id'] ?? '') ?>" maxlength="50">
                            <?= feedback($errors, 'student_id') ?>
                        </div>
                        <div class="col-sm-6">
                            <label for="f_phone" class="form-label">Phone <span class="text-muted small">(optional)</span></label>
                            <input type="tel" class="form-control<?= invalid($errors, 'phone') ?>" id="f_phone" name="phone"
                                   value="<?= e($old['phone'] ?? '') ?>" maxlength="20" autocomplete="tel">
                            <?= feedback($errors, 'phone') ?>
                        </div>
                    </div>

                    <?php View::partial('password-input', ['name' => 'password', 'label' => 'Password', 'errors' => $errors,
                        'autocomplete' => 'new-password', 'help' => 'At least 8 characters, including a letter and a number.']) ?>
                    <?php View::partial('password-input', ['name' => 'password_confirmation', 'label' => 'Confirm password',
                        'errors' => $errors, 'autocomplete' => 'new-password']) ?>

                    <button type="submit" class="btn btn-primary w-100 py-2 mt-2">Create account</button>
                </form>

                <p class="text-center text-muted mt-4 mb-0">
                    Already have an account? <a href="/login">Log in</a>
                </p>
            </div>
        </div>
    </div>
</div>
