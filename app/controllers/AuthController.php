<?php
/**
 * Registration, login, logout, forgot/reset password.
 */

declare(strict_types=1);

final class AuthController extends Controller
{
    // ------------------------------------------------------------------
    // Registration
    // ------------------------------------------------------------------

    public function showRegister(): void
    {
        $this->view('auth/register', ['title' => 'Create account', 'old' => [], 'errors' => []]);
    }

    public function register(): void
    {
        $data = [
            'name'                  => $this->input('name'),
            'email'                 => User::normalizeEmail($this->input('email')),
            'student_id'            => $this->input('student_id'),
            'phone'                 => $this->input('phone'),
            'password'              => $this->rawInput('password'),
            'password_confirmation' => $this->rawInput('password_confirmation'),
        ];

        $v = Validator::make($data, [
            'name'       => User::NAME_RULES,
            'email'      => User::EMAIL_RULES,
            'student_id' => ['nullable', 'max:50', 'regex:/^[A-Za-z0-9\/\-]+$/'],
            'phone'      => ['nullable', 'regex:/^\+?[0-9\s\-()]{7,20}$/'],
            'password'   => array_merge(User::PASSWORD_RULES, ['same:password_confirmation']),
        ], ['student_id' => 'Student ID', 'phone' => 'Phone number']);

        $errors = $v->errors();
        if (!isset($errors['email']) && User::emailExists($data['email'])) {
            $errors['email'] = 'An account with this email already exists.';
        }

        if ($errors) {
            $this->renderRegisterErrors($data, $errors);
            return;
        }

        try {
            $id = User::create($data);
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) === 1062) {   // duplicate email (concurrent sign-up)
                $this->renderRegisterErrors($data, ['email' => 'An account with this email already exists.']);
                return;
            }
            throw $e;
        }

        Auth::login(User::findById($id));
        Session::flash('success', 'Welcome to CampusFind, ' . $data['name'] . '! Your account has been created.');
        redirect('/dashboard');
    }

    private function renderRegisterErrors(array $data, array $errors): void
    {
        unset($data['password'], $data['password_confirmation']);   // never echo passwords back
        $this->view('auth/register', ['title' => 'Create account', 'old' => $data, 'errors' => $errors], 422);
    }

    // ------------------------------------------------------------------
    // Login / logout
    // ------------------------------------------------------------------

    public function showLogin(): void
    {
        $this->view('auth/login', ['title' => 'Log in', 'old' => [], 'errors' => []]);
    }

    public function login(): void
    {
        $email    = User::normalizeEmail($this->input('email'));
        $password = $this->rawInput('password');

        $v = Validator::make(['email' => $email, 'password' => $password], [
            'email'    => ['required', 'email', 'max:190'],
            'password' => ['required', 'max_bytes:4096'],
        ]);
        if ($v->fails()) {
            $this->view('auth/login', ['title' => 'Log in', 'old' => ['email' => $email], 'errors' => $v->errors()], 422);
            return;
        }

        $result = Auth::attempt($email, $password, client_ip());

        switch ($result['status']) {
            case 'ok':
                $intended = safe_redirect_path(Session::pull('intended'), '/dashboard');
                Auth::login($result['user']);
                Session::flash('success', 'Welcome back, ' . $result['user']['name'] . '!');
                redirect($intended);

            case 'locked':
                $minutes = (int) ceil($result['retry_after'] / 60);
                header('Retry-After: ' . $result['retry_after']);
                $this->view('auth/login', ['title' => 'Log in', 'old' => ['email' => $email], 'errors' => [],
                    'alert' => "Too many failed login attempts. Please try again in $minutes minute" . ($minutes === 1 ? '' : 's') . '.'], 429);
                return;

            case 'inactive':
                $this->view('auth/login', ['title' => 'Log in', 'old' => ['email' => $email], 'errors' => [],
                    'alert' => 'This account has been deactivated. Please contact the CampusFind administrator.'], 403);
                return;

            default:
                // Same message whether the email or the password was wrong.
                $this->view('auth/login', ['title' => 'Log in', 'old' => ['email' => $email], 'errors' => [],
                    'alert' => 'Invalid email or password.'], 422);
        }
    }

    public function logout(): void
    {
        Auth::logout();
        Session::flash('success', 'You have been logged out.');
        redirect('/');
    }

    // ------------------------------------------------------------------
    // Forgot / reset password
    // ------------------------------------------------------------------

    public function showForgot(): void
    {
        $this->view('auth/forgot', ['title' => 'Forgot password', 'old' => [], 'errors' => []]);
    }

    public function forgot(): void
    {
        $email = User::normalizeEmail($this->input('email'));
        $v = Validator::make(['email' => $email], ['email' => User::EMAIL_RULES]);
        if ($v->fails()) {
            $this->view('auth/forgot', ['title' => 'Forgot password', 'old' => ['email' => $email], 'errors' => $v->errors()], 422);
            return;
        }

        $user = User::findByEmail($email);
        if ($user && $user['is_active'] && !PasswordReset::recentlyRequested((int) $user['id'])) {
            $token = PasswordReset::create((int) $user['id']);
            $link  = absolute_url('/reset-password?token=' . $token);
            Mailer::send($user['email'], 'Reset your CampusFind password',
                "Hi {$user['name']},\n\n"
                . "We received a request to reset your CampusFind password.\n"
                . "Open this link within " . PasswordReset::TTL_MINUTES . " minutes to choose a new one:\n\n"
                . "$link\n\n"
                . "If you didn't ask for this, you can ignore this email; your password won't change.");
        }

        // Identical response whether or not the account exists (no email enumeration).
        Session::flash('info', 'If an account exists for that email, we have sent a password reset link. Please check your inbox.');
        redirect('/forgot-password');
    }

    public function showReset(): void
    {
        $token = $this->query('token');
        $valid = PasswordReset::findValid($token) !== null;
        $this->view('auth/reset', ['title' => 'Reset password', 'token' => $valid ? $token : '',
            'valid' => $valid, 'errors' => []], $valid ? 200 : 400);
    }

    public function reset(): void
    {
        $token = $this->input('token');
        $reset = PasswordReset::findValid($token);
        if ($reset === null) {
            $this->view('auth/reset', ['title' => 'Reset password', 'token' => '', 'valid' => false, 'errors' => []], 400);
            return;
        }

        $data = ['password' => $this->rawInput('password'), 'password_confirmation' => $this->rawInput('password_confirmation')];
        $v = Validator::make($data, ['password' => array_merge(User::PASSWORD_RULES, ['same:password_confirmation'])]);
        if ($v->fails()) {
            $this->view('auth/reset', ['title' => 'Reset password', 'token' => $token, 'valid' => true, 'errors' => $v->errors()], 422);
            return;
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            User::updatePassword((int) $reset['user_id'], $data['password']);
            PasswordReset::consume((int) $reset['id'], (int) $reset['user_id']);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        RateLimiter::clear($reset['email']);

        Session::flash('success', 'Your password has been reset. You can now log in with your new password.');
        redirect('/login');
    }
}
