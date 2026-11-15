<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use App\Services\AuditLogger;
use App\Support\AdminTwoFactor;
use App\Support\LoginLockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /**
     * Which dashboard each role lands on (mirrors the legacy login.php cascade).
     */
    private function dashboardFor(string $guard): string
    {
        return match ($guard) {
            'admin' => route('admin.dashboard'),
            'dept' => route('department.dashboard'),
            default => route('user.dashboard'),
        };
    }

    /**
     * If any guard is already logged in, bounce to its dashboard (legacy behavior).
     */
    private function alreadyLoggedIn(): ?RedirectResponse
    {
        foreach (['admin', 'dept', 'user'] as $guard) {
            if (auth($guard)->check()) {
                return redirect($this->dashboardFor($guard));
            }
        }

        return null;
    }

    public function show(): View|RedirectResponse
    {
        return $this->alreadyLoggedIn() ?? view('auth.login', [
            'registered' => request()->boolean('registered'),
            'deactivated' => request()->query('error') === 'deactivated',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($redirect = $this->alreadyLoggedIn()) {
            return $redirect;
        }

        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $input = trim($credentials['email']);
        $password = $credentials['password'];
        $remember = $request->boolean('remember');

        // Too many wrong passwords for this email/username: refused for a
        // while, even with the right password (App\Support\LoginLockout).
        if (LoginLockout::isLocked($input)) {
            $minutes = LoginLockout::minutesLeft($input);

            throw ValidationException::withMessages([
                'email' => 'Too many failed sign-in attempts for this account. Try again in '
                    . $minutes . ' ' . ($minutes === 1 ? 'minute' : 'minutes')
                    . ', or reset your password with "Forgot password?".',
            ]);
        }

        // 1. User accounts (Student / Faculty) — matched by email.
        $user = UserAccount::where('email', $input)->first();
        if ($user && Hash::check($password, $user->password)) {
            if (strtolower((string) $user->status) === 'deactivated') {
                $this->logSignInProblem('Blocked login (deactivated account)', $user, $input, $request);

                throw ValidationException::withMessages([
                    'email' => 'Your account has been deactivated by the Administrator.',
                ]);
            }
            $user->forceFill(['last_online' => now()])->save();
            LoginLockout::clear($input);

            return $this->logInto('user', $user, $remember);
        }

        // 2. Admin accounts — matched by username OR email.
        $admin = Admin::where('username', $input)->orWhere('email', $input)->first();
        if ($admin && Hash::check($password, $admin->password)) {
            if (strtolower((string) $admin->status) === 'deactivated') {
                $this->logSignInProblem('Blocked login (deactivated account)', $admin, $input, $request);

                throw ValidationException::withMessages([
                    'email' => 'This administrator account has been deactivated.',
                ]);
            }
            // Two-step sign-in: the password was right; a code is emailed and
            // the admin is signed in only after entering it (codeForm/verifyCode).
            if (AdminTwoFactor::enabled()) {
                try {
                    AdminTwoFactor::start($request, $admin, $remember);
                } catch (\Throwable $e) {
                    report($e);

                    throw ValidationException::withMessages([
                        'email' => "We couldn't email your sign-in code. Please try again in a few minutes.",
                    ]);
                }

                return redirect()->route('login.code');
            }

            $admin->forceFill(['last_online' => now()])->save();
            LoginLockout::clear($input, $admin->username, $admin->email);

            return $this->logInto('admin', $admin, $remember);
        }

        // 3. Department accounts — matched by email.
        $dept = DepartmentAccount::where('email', $input)->first();
        if ($dept && Hash::check($password, $dept->password)) {
            if (strtolower((string) $dept->status) === 'deactivated') {
                $this->logSignInProblem('Blocked login (deactivated account)', $dept, $input, $request);

                throw ValidationException::withMessages([
                    'email' => 'This department account has been deactivated by the Administrator.',
                ]);
            }
            $dept->forceFill(['last_online' => now()])->save();
            LoginLockout::clear($input);

            return $this->logInto('dept', $dept, $remember);
        }

        // Wrong password, or no account with this email/username. Recorded in
        // the Audit Trail (and once more when it locks the account).
        $target = $user ?? $admin ?? $dept;
        $justLocked = LoginLockout::recordFailure($input);
        $this->logSignInProblem(
            $target ? 'Failed login (wrong password)' : 'Failed login (unknown account)',
            $target,
            $input,
            $request,
        );
        if ($justLocked) {
            $this->logSignInProblem('Account locked', $target, $input, $request);
        }

        throw ValidationException::withMessages([
            'email' => 'Invalid email/username or password.',
        ]);
    }

    /**
     * Audit Trail entry for a sign-in that didn't succeed. No account is
     * recorded as the actor (nobody signed in, and the targeted account must
     * not count as "active"): the Admin sees "Not signed in", the targeted
     * account under Affected Record, and its email in the details. For a name
     * that matches no account only a masked form is kept, because people
     * sometimes type their password into the email box.
     */
    private function logSignInProblem(string $action, $account, string $input, Request $request): void
    {
        [$entity, $who] = match (true) {
            $account instanceof UserAccount => ['user_account:' . $account->user_id, $account->email],
            $account instanceof Admin => ['admin:' . $account->admin_id, $account->email ?: $account->username],
            $account instanceof DepartmentAccount => ['department_account:' . $account->dept_acc_id, $account->email],
            default => ['auth:unknown', self::mask($input)],
        };

        $details = match ($action) {
            'Account locked' => 'Sign-in for ' . $who . ' locked for ' . LoginLockout::LOCK_MINUTES
                . ' minutes after ' . LoginLockout::MAX_FAILURES . ' failed attempts',
            'Blocked login (deactivated account)' => 'Correct password for ' . $who . ', but the account is deactivated',
            'Failed login (unknown account)' => 'No account matches ' . $who,
            'Failed login (wrong sign-in code)' => 'Right password but wrong emailed code for ' . $who,
            default => 'Wrong password for ' . $who,
        };

        AuditLogger::log($action, $entity, $details, [], $request);
    }

    /** "jdelacruz@gmail.com" -> "jd***@gmail.com", "someone" -> "so***". */
    private static function mask(string $input): string
    {
        $input = trim($input);
        $at = mb_strrpos($input, '@');
        $name = $at === false ? $input : mb_substr($input, 0, $at);
        $domain = $at === false ? '' : mb_substr($input, $at);

        return mb_substr(mb_substr($name, 0, 2) . '***' . $domain, 0, 80);
    }

    // ------------------------------------------------------------------
    // Admin sign-in code (App\Support\AdminTwoFactor)
    // ------------------------------------------------------------------

    /** "Enter your sign-in code" page, after an Admin's correct password. */
    public function codeForm(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->alreadyLoggedIn()) {
            return $redirect;
        }

        $admin = AdminTwoFactor::pendingAdmin($request);
        if (!$admin) {
            return redirect()->route('login');
        }

        return view('auth.admin-code', [
            'email' => self::maskEmail((string) $admin->email),
            'minutes' => AdminTwoFactor::CODE_MINUTES,
            'resendWait' => AdminTwoFactor::resendWait($request),
        ]);
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        if ($redirect = $this->alreadyLoggedIn()) {
            return $redirect;
        }

        $admin = AdminTwoFactor::pendingAdmin($request);
        if (!$admin) {
            return redirect()->route('login');
        }

        $request->validate(['code' => ['required', 'string', 'max:20']], [
            'code.required' => 'Enter the 6-digit code from your email.',
        ]);

        // A lockout from wrong passwords or codes also stops the code step.
        if (LoginLockout::isLocked((string) $admin->email) || LoginLockout::isLocked((string) $admin->username)) {
            AdminTwoFactor::clear($request);

            return redirect()->route('login')->withErrors([
                'email' => 'Too many failed sign-in attempts for this account. Try again in '
                    . LoginLockout::minutesLeft((string) ($admin->email ?: $admin->username)) . ' minutes.',
            ]);
        }

        $remember = AdminTwoFactor::remember($request);
        $result = AdminTwoFactor::check($request, (string) $request->input('code'));

        if ($result === 'ok') {
            // Deactivated while the code was on its way: still refused.
            if (strtolower((string) $admin->status) === 'deactivated') {
                return redirect()->route('login')->withErrors(['email' => 'This administrator account has been deactivated.']);
            }

            $admin->forceFill(['last_online' => now()])->save();
            LoginLockout::clear($admin->username, $admin->email);

            return $this->logInto('admin', $admin, $remember, 'with an emailed code');
        }

        if ($result === 'none' || $result === 'expired') {
            AdminTwoFactor::clear($request);

            return redirect()->route('login')->withErrors([
                'email' => $result === 'expired'
                    ? 'Your sign-in code has expired. Sign in again to get a new one.'
                    : 'Please sign in again.',
            ]);
        }

        // Wrong code: counts towards the account lockout like a wrong password.
        $justLocked = false;
        foreach (array_unique(array_filter([mb_strtolower((string) $admin->email), mb_strtolower((string) $admin->username)])) as $name) {
            $justLocked = LoginLockout::recordFailure($name) || $justLocked;
        }
        $this->logSignInProblem('Failed login (wrong sign-in code)', $admin, (string) $admin->email, $request);
        if ($justLocked) {
            $this->logSignInProblem('Account locked', $admin, (string) $admin->email, $request);
        }

        if ($result === 'too_many' || $justLocked) {
            AdminTwoFactor::clear($request);

            return redirect()->route('login')->withErrors([
                'email' => 'Too many wrong codes. Sign in again to get a new code.',
            ]);
        }

        $left = AdminTwoFactor::attemptsLeft($request);

        return back()->withErrors([
            'code' => 'That code is not correct. ' . $left . ' ' . ($left === 1 ? 'try' : 'tries') . ' left.',
        ]);
    }

    /** Emails a new code (at most once every RESEND_SECONDS). */
    public function resendCode(Request $request): RedirectResponse
    {
        $admin = AdminTwoFactor::pendingAdmin($request);
        if (!$admin) {
            return redirect()->route('login');
        }

        $wait = AdminTwoFactor::resendWait($request);
        if ($wait > 0) {
            return back()->withErrors(['code' => 'Please wait ' . $wait . ' seconds before asking for a new code.']);
        }

        try {
            AdminTwoFactor::sendCode($request, $admin);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['code' => "We couldn't email a new code. Please try again in a few minutes."]);
        }

        return back()->with('status', 'A new code was sent. Codes sent earlier no longer work.');
    }

    /** "Use a different account": drops the waiting sign-in. */
    public function cancelCode(Request $request): RedirectResponse
    {
        AdminTwoFactor::clear($request);

        return redirect()->route('login');
    }

    /** "admin@school.edu" -> "ad•••@school.edu" (shown on the code page). */
    private static function maskEmail(string $email): string
    {
        $at = mb_strrpos($email, '@');
        if ($at === false) {
            return $email;
        }

        return mb_substr($email, 0, min(2, $at)) . '•••' . mb_substr($email, $at);
    }

    private function logInto(string $guard, $account, bool $remember, ?string $how = null): RedirectResponse
    {
        // Only one identity per session (legacy auth_check cleared the others).
        foreach (['user', 'admin', 'dept'] as $other) {
            if ($other !== $guard) {
                auth($other)->logout();
            }
        }

        auth($guard)->login($account, $remember); // regenerates session id

        AuditLogger::log(
            'Login',
            'auth:' . $guard,
            'Signed in as ' . match ($guard) {
                'admin' => 'Administrator',
                'dept' => 'Department account',
                default => ($account->role ?: 'User'),
            } . ($how ? ' ' . $how : ''),
            AuditLogger::actorFor($guard, $account),
        );

        return redirect()->intended($this->dashboardFor($guard));
    }

    public function destroy(Request $request): RedirectResponse
    {
        foreach (['user', 'admin', 'dept'] as $guard) {
            if ($account = auth($guard)->user()) {
                AuditLogger::log('Logout', 'auth:' . $guard, 'Signed out', AuditLogger::actorFor($guard, $account), $request);
            }
            auth($guard)->logout();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
