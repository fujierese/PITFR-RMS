<?php
namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Mail\RegistrationOtp;
use App\Models\AuditLog;
use App\Models\College;
use App\Models\Department;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectByRole(Auth::user()->role);
        }

        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $email = trim((string) $request->input('email', $request->input('username', '')));
        $accountKey = 'login:account:' . hash('sha256', mb_strtolower($email));
        $ipKey = 'login:ip:' . hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($accountKey, 5) || RateLimiter::tooManyAttempts($ipKey, 20)) {
            return back()
                ->withErrors(['email' => 'Too many login attempts. Please try again later.'])
                ->withInput($request->except('password'));
        }

        $credentials = [
            'username' => $email,
            'password' => $request->input('password'),
        ];

        $hasUsablePassword = User::query()
            ->where('username', $email)
            ->whereNotNull('password')
            ->exists();

        if (!$hasUsablePassword || !Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($accountKey, 60);
            RateLimiter::hit($ipKey, 60);
            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput($request->except('password'));
        }

        RateLimiter::clear($accountKey);
        RateLimiter::clear($ipKey);
        $user = Auth::user();
        if ($user->is_active === false) {
            Auth::logout();
            return back()->withErrors(['email' => 'This account is deactivated. Contact an administrator.']);
        }
        if ($user->role === 'requestor' && in_array($user->requestor_type, ['student', 'faculty', 'staff', 'outsider'], true) && !$user->email_verified_at) {
            Auth::logout();
            $request->session()->put('registration_user_id', $user->id);
            return redirect()->route('register.verify');
        }

        $request->session()->regenerate();
        return $this->redirectByRole(Auth::user()->role);
    }

    public function showRegister(Request $request)
    {
        if (Auth::check()) return redirect()->route('home');
        return view('auth.register', [
            'colleges' => College::with('departments')->orderBy('name')->get(),
            'googleProfile' => $request->session()->get('google_registration_profile'),
            'googleType' => $request->session()->get('google_registration_type', 'student'),
        ]);
    }

    public function redirectToGoogle(Request $request)
    {
        $clientId = (string) config('services.google.client_id');
        $clientSecret = (string) config('services.google.client_secret');
        if ($clientId === '' || $clientSecret === '' || str_starts_with($clientId, 'your-') || str_starts_with($clientSecret, 'your-')) {
            return redirect()->route('login')->withErrors([
                'google' => 'Google sign-in is not configured. Please use your PITFR account or contact an administrator.',
            ]);
        }

        $requestedType = strtolower((string) ($request->input('type', 'outsider')));
        $allowedTypes = ['student', 'faculty', 'outsider', 'clinic'];

        $request->validate(['type' => ['nullable', 'in:' . implode(',', $allowedTypes)]]);
        $request->session()->put('google_registration_type', in_array($requestedType, $allowedTypes, true) ? $requestedType : 'outsider');

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('login')->withErrors(['username' => 'Google authentication was not completed. Please try again.']);
        }

        $googleId = trim((string) ($googleUser->getId() ?? ''));
        $email = strtolower(trim((string) $googleUser->getEmail()));

        if ($googleId === '' || $email === '') {
            return redirect()->route('login')->withErrors(['username' => 'Google authentication could not be completed because the required account information was unavailable.']);
        }

        $googleProfile = $googleUser->user ?? [];
        $emailVerified = $googleProfile['email_verified'] ?? $googleProfile['verified_email'] ?? null;
        if ($emailVerified === null || filter_var($emailVerified, FILTER_VALIDATE_BOOLEAN) === false) {
            return redirect()->route('login')->withErrors(['username' => 'Your Google email address must be verified before you can continue.']);
        }

        $existing = User::where('google_id', $googleId)->first();
        if ($existing) {
            if (strtolower(trim((string) $existing->username)) !== $email) {
                return redirect()->route('login')->withErrors(['username' => 'Google authentication could not be completed because the account information does not match.']);
            }

            if (! $this->isGoogleEligibleRequestor($existing)) {
                return redirect()->route('login')->withErrors(['username' => 'This PITFR account cannot be linked to Google authentication. Please use the available account login method or contact the administrator.']);
            }

            if (! $existing->is_active) {
                return redirect()->route('login')->withErrors(['username' => 'This PITFR account is currently inactive. Please contact the appropriate administrator.']);
            }

            if ($existing->password === null) {
                return redirect()->route('login')->withErrors(['username' => 'This PITFR account must complete initial password setup before Google sign-in is available.']);
            }

            Auth::login($existing);
            return $this->redirectByRole($existing->role);
        }

        $existing = User::where('username', $email)->first();
        if ($existing) {
            if ($existing->google_id && $existing->google_id !== $googleId) {
                return redirect()->route('login')->withErrors(['username' => 'Google authentication could not be completed because of an account identity conflict. Please contact the administrator.']);
            }

            if (! $this->isGoogleEligibleRequestor($existing)) {
                $message = ($existing->role === 'requestor' && $existing->requestor_type === 'outsider')
                    ? 'This Google account cannot be linked to the existing PITFR account. Please use the available account login method or contact the administrator.'
                    : 'This PITFR account cannot be linked to Google authentication. Please use the available account login method or contact the administrator.';

                return redirect()->route('login')->withErrors(['username' => $message]);
            }

            if (! $existing->is_active) {
                return redirect()->route('login')->withErrors(['username' => 'This PITFR account is currently inactive. Please contact the appropriate administrator.']);
            }

            if ($existing->password === null) {
                return redirect()->route('login')->withErrors(['username' => 'This PITFR account must complete initial password setup before Google sign-in is available.']);
            }

            $existing->forceFill(['google_id' => $googleId, 'email_verified_at' => $existing->email_verified_at ?: now()])->save();
            Auth::login($existing);
            return $this->redirectByRole($existing->role);
        }

        $request->session()->put('google_registration_profile', [
            'google_id' => $googleId,
            'email' => $email,
            'first_name' => $googleProfile['given_name'] ?? '',
            'last_name' => $googleProfile['family_name'] ?? '',
            'name' => $googleUser->getName() ?: $email,
        ]);

        return redirect()->route('register');
    }

    public function showForgotPassword()
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $username = strtolower(trim($data['email']));
        $user = User::query()->where('username', $username)->where('is_active', true)->first();

        if ($user) {
            try {
                Password::sendResetLink(['username' => $username]);
            } catch (\Throwable $exception) {
                Log::warning('Unable to deliver a password reset link.', [
                    'exception' => $exception::class,
                ]);
            }
        }

        return back()->with('status', 'If an active account matches that email address, a password reset link will be sent.');
    }

    public function showResetPassword(string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => request()->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);
        $inactiveAccount = false;
        $changedUserId = null;
        $status = Password::reset(
            ['username' => strtolower(trim($data['email'])), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password) use (&$inactiveAccount, &$changedUserId): void {
                DB::transaction(function () use ($user, $password, &$inactiveAccount, &$changedUserId): void {
                    $currentUser = User::query()->lockForUpdate()->findOrFail($user->getKey());
                    if (! $currentUser->is_active) {
                        $inactiveAccount = true;
                        return;
                    }

                    $wasPasswordless = $currentUser->password === null;
                    $currentUser->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();
                    $currentUser->tokens()->delete();
                    if (config('session.driver') === 'database') {
                        DB::table(config('session.table', 'sessions'))
                            ->where('user_id', $currentUser->id)
                            ->delete();
                    }
                    AuditLog::create([
                        'actor_id' => null,
                        'target_user_id' => $currentUser->id,
                        'action' => $wasPasswordless ? 'password_setup_completed' : 'password_recovered',
                        'details' => $wasPasswordless
                            ? 'Initial password setup completed through the password broker.'
                            : 'Password recovered through the password broker.',
                        'old_values' => [],
                        'new_values' => [],
                    ]);
                    Password::broker()->deleteToken($currentUser);
                    $changedUserId = $currentUser->id;
                });
            }
        );

        if ($inactiveAccount) {
            return back()->withErrors(['email' => 'This account is inactive. Contact an administrator.']);
        }

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email' => __($status)]);
        }

        try {
            User::findOrFail($changedUserId)->notify(new PasswordChangedNotification());
        } catch (\Throwable $exception) {
            Log::warning('Unable to deliver password reset confirmation.', [
                'user_id' => $changedUserId,
                'exception' => $exception::class,
            ]);
        }

        return redirect()->route('login')->with('status', __($status));
    }

    public function register(Request $request)
    {
        $googleProfile = $request->session()->get('google_registration_profile');
        $isGoogleRegistration = is_array($googleProfile);
        if ($isGoogleRegistration) {
            $request->merge(['username' => strtolower(trim((string) ($googleProfile['email'] ?? '')))]);
        }

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'surname' => ['required', 'string', 'max:100'],
            'username' => ['required', 'email', 'max:50', 'unique:users,username'],
            'password' => [$isGoogleRegistration ? 'nullable' : 'required', 'string', 'min:12', 'confirmed'],
            'office_or_organization' => ['required', 'string', 'max:191'],
            'organization_acronym' => ['nullable', 'string', 'max:50'],
            'organization_type' => ['required', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:50'],
        ], [
            'username.unique' => 'Unable to complete registration with the provided details.',
            'school_id_number.regex' => 'Student ID must be in format: 23-0098-635 (2 digits - 4 digits - 3 digits)',
            'college_id.required_if' => 'College is required for student registration',
            'department_id.required_if' => 'Department is required for student registration',
            'office_or_organization.required_if' => 'Organization name is required for this account type.',
        ]);

        $fullName = User::formatFullName(
            $data['surname'],
            $data['first_name'],
            $data['middle_name'] ?? null,
        );

        // Normalize organization / purpose: treat common 'Individual' markers and empty strings as null
        $org = $data['office_or_organization'] ?? null;
        if (is_string($org)) {
            $org = trim($org);
            $lower = strtolower($org);
            if ($org === '' || in_array($lower, ['individual','personal','individual / personal','n/a','none'], true)) {
                $org = null;
            }
        }

        // Fetch department to get college-related info if needed
        $department = null;
        if (!empty($data['department_id'])) {
            $department = Department::find($data['department_id']);
            if ($department && !empty($data['college_id']) && (int) $department->college_id !== (int) $data['college_id']) {
                return back()->withErrors(['department_id' => 'Please select a department under the selected college.'])->withInput();
            }
        }

        // Get department name for storage
        $departmentName = $department ? $department->name : null;

        $user = DB::transaction(function () use ($data, $fullName, $org, $departmentName, $googleProfile, $isGoogleRegistration): User {
            $user = User::create([
                'username' => strtolower(trim($data['username'])),
                'password' => Hash::make($data['password'] ?? bin2hex(random_bytes(24))),
                'name' => $fullName,
                'role' => 'requestor',
                'requestor_type' => 'outsider',
                'is_active' => true,
                'school_id_number' => $data['school_id_number'] ?? null,
                'office_or_organization' => $org,
                'organization_acronym' => $data['organization_acronym'] ?? null,
                'organization_type' => $data['organization_type'],
                'contact_number' => $data['contact_number'] ?? null,
                'department' => $departmentName,
                'college_id' => $data['college_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'google_id' => $isGoogleRegistration ? ($googleProfile['google_id'] ?? null) : null,
                'email_verified_at' => $isGoogleRegistration ? now() : null,
            ]);

            $this->recordUserAudit(null, $user, 'user_created', 'Created a new outsider account.', [], [
                'name' => $user->name,
                'username' => $user->username,
                'role' => $user->role,
                'requestor_type' => $user->requestor_type,
            ]);

            return $user;
        });

        if ($isGoogleRegistration) {
            $request->session()->forget(['google_registration_profile', 'google_registration_type']);
            Auth::login($user);
            return redirect()->route('requestor.index');
        }

        $request->session()->put('registration_user_id', $user->id);
        $this->sendOtp($user, $request);

        return redirect()->route('register.verify');
    }

    public function showVerify(Request $request)
    {
        $user = $this->pendingRegistrationUser($request);
        abort_unless($user, 404);

        return view('auth.verify-otp', ['email' => $user->username]);
    }

    public function verify(Request $request)
    {
        $user = $this->pendingRegistrationUser($request);
        abort_unless($user, 404);

        $data = $request->validate(['otp' => ['required', 'digits:6']]);
        if ($user->otp_expires_at?->isPast() || !$user->otp_hash) {
            return back()->withErrors(['otp' => 'This code has expired. Request a new code.']);
        }

        if ($user->otp_attempts >= 5) {
            return back()->withErrors(['otp' => 'Too many attempts. Request a new code.']);
        }

        if (!Hash::check($data['otp'], $user->otp_hash)) {
            $user->increment('otp_attempts');
            return back()->withErrors(['otp' => 'The verification code is invalid.']);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'otp_hash' => null,
            'otp_expires_at' => null,
            'otp_attempts' => 0,
        ])->save();
        $request->session()->forget('registration_user_id');
        Auth::login($user);

        return redirect()->route('requestor.index')->with('success', 'Your email has been verified.');
    }

    private function recordUserAudit(?User $actor, User $targetUser, string $action, string $details, array $oldValues, array $newValues): void
    {
        AuditLog::create([
            'actor_id' => $actor?->id,
            'target_user_id' => $targetUser->id,
            'action' => $action,
            'details' => $details,
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    public function resendOtp(Request $request)
    {
        $user = $this->pendingRegistrationUser($request);
        abort_unless($user, 404);

        $key = 'registration-otp:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['otp' => 'Please wait before requesting another code.']);
        }
        RateLimiter::hit($key, 60);
        $this->sendOtp($user, $request);

        return back()->with('status', 'A new verification code was sent.');
    }

    public function departments(College $college)
    {
        return response()->json($college->departments()->orderBy('name')->get(['id', 'name']));
    }

    private function pendingRegistrationUser(Request $request): ?User
    {
        return User::whereKey($request->session()->get('registration_user_id'))
            ->whereNull('email_verified_at')
            ->first();
    }

    private function sendOtp(User $user, Request $request): void
    {
        $otp = (string) random_int(100000, 999999);
        $user->forceFill([
            'otp_hash' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
            'otp_attempts' => 0,
            'otp_last_sent_at' => now(),
        ])->save();
        Mail::to($user->username)->send(new RegistrationOtp($otp));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    private function isGoogleEligibleRequestor(?User $user): bool
    {
        return $user instanceof User
            && $user->role === 'requestor'
            && in_array($user->requestor_type, ['student', 'faculty', 'staff', 'outsider'], true);
    }

    private function redirectByRole(string $role)
    {
        return match (true) {
            in_array($role, ['admin', 'facility_admin'], true) || $role === 'supply_office' => redirect()->route('supply-office.index'),
            str_starts_with($role, 'custodian') => redirect()->route('custodian.index'),
            default => redirect()->route('requestor.index'),
        };
    }
}