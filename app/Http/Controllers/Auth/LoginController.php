<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
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

        // 1. User accounts (Student / Faculty) — matched by email.
        $user = UserAccount::where('email', $input)->first();
        if ($user && Hash::check($password, $user->password)) {
            if (strtolower((string) $user->status) === 'deactivated') {
                throw ValidationException::withMessages([
                    'email' => 'Your account has been deactivated by the Administrator.',
                ]);
            }
            $user->forceFill(['last_online' => now()])->save();

            return $this->logInto('user', $user, $remember);
        }

        // 2. Admin accounts — matched by username OR email.
        $admin = Admin::where('username', $input)->orWhere('email', $input)->first();
        if ($admin && Hash::check($password, $admin->password)) {
            return $this->logInto('admin', $admin, $remember);
        }

        // 3. Department accounts — matched by email.
        $dept = DepartmentAccount::where('email', $input)->first();
        if ($dept && Hash::check($password, $dept->password)) {
            if (strtolower((string) $dept->status) === 'deactivated') {
                throw ValidationException::withMessages([
                    'email' => 'This department account has been deactivated by the Administrator.',
                ]);
            }
            $dept->forceFill(['last_online' => now()])->save();

            return $this->logInto('dept', $dept, $remember);
        }

        throw ValidationException::withMessages([
            'email' => 'Invalid email/username or password.',
        ]);
    }

    private function logInto(string $guard, $account, bool $remember): RedirectResponse
    {
        // Only one identity per session (legacy auth_check cleared the others).
        foreach (['user', 'admin', 'dept'] as $other) {
            if ($other !== $guard) {
                auth($other)->logout();
            }
        }

        auth($guard)->login($account, $remember); // regenerates session id

        return redirect()->intended($this->dashboardFor($guard));
    }

    public function destroy(Request $request): RedirectResponse
    {
        foreach (['user', 'admin', 'dept'] as $guard) {
            auth($guard)->logout();
        }
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
