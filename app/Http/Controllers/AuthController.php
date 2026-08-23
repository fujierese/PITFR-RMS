<?php
namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Mail\RegistrationOtp;
use App\Models\College;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
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
        $credentials = [
            'username' => $email,
            'password' => $request->input('password'),
        ];

        if (!Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput($request->except('password'));
        }

        $user = Auth::user();
        if ($user->role === 'requestor' && in_array($user->requestor_type, ['student', 'outsider'], true) && !$user->email_verified_at) {
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

        $type = $request->validate(['type' => ['nullable', 'in:student,outsider']])['type'] ?? 'student';
        $request->session()->put('google_registration_type', $type);

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable) {
            return redirect()->route('register')->withErrors(['username' => 'Google authentication could not be completed.']);
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));
        if ($email === '') {
            return redirect()->route('register')->withErrors(['username' => 'Google did not provide an email address.']);
        }

        $existing = User::where('google_id', $googleUser->getId())->first();
        if ($existing) {
            Auth::login($existing);
            return $this->redirectByRole($existing->role);
        }

        $existing = User::where('username', $email)->first();
        if ($existing) {
            if ($existing->role !== 'requestor') {
                return redirect()->route('register')->withErrors(['username' => 'This email belongs to an administrator-managed account.']);
            }

            $existing->forceFill(['google_id' => $googleUser->getId(), 'email_verified_at' => $existing->email_verified_at ?: now()])->save();
            Auth::login($existing);
            return $this->redirectByRole($existing->role);
        }

        $request->session()->put('google_registration_profile', [
            'google_id' => $googleUser->getId(),
            'email' => $email,
            'first_name' => $googleUser->user['given_name'] ?? '',
            'last_name' => $googleUser->user['family_name'] ?? '',
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
        $status = Password::sendResetLink(['username' => strtolower(trim($data['email']))]);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
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
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);
        $status = Password::reset(
            ['username' => strtolower(trim($data['email'])), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password): void {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function register(Request $request)
    {
        $googleProfile = $request->session()->get('google_registration_profile');
        $isGoogleRegistration = is_array($googleProfile);
        $data = $request->validate([
            'requestor_type' => ['required', 'in:student,outsider'],
            'first_name' => ['required_if:requestor_type,student', 'nullable', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required_if:requestor_type,student', 'nullable', 'string', 'max:100'],
            'contact_person' => ['required_if:requestor_type,outsider', 'nullable', 'string', 'max:100'],
            'username' => ['required', 'email', 'max:255', 'unique:users,username'],
            'password' => [$isGoogleRegistration ? 'nullable' : 'required', 'string', 'min:6', 'confirmed'],
            'college_id' => ['required_if:requestor_type,student', 'nullable', 'exists:colleges,id'],
            'department_id' => ['required_if:requestor_type,student', 'nullable', 'exists:departments,id'],
            'school_id_number' => ['required_if:requestor_type,student', 'nullable', 'string', 'regex:/^\d{2}-\d{4}-\d{3}$/'],
            'office_or_organization' => ['required_if:requestor_type,outsider', 'nullable', 'string', 'max:191'],
            'contact_number' => ['nullable', 'string', 'max:50'],
        ], [
            'school_id_number.regex' => 'Student ID must be in format: 23-0098-635 (2 digits - 4 digits - 3 digits)',
            'college_id.required_if' => 'College is required for student registration',
            'department_id.required_if' => 'Department is required for student registration',
            'office_or_organization.required_if' => 'Organization name is required for external registration',
        ]);

        if ($isGoogleRegistration) {
            $data['username'] = $googleProfile['email'];
        }

        $fullName = $data['requestor_type'] === 'outsider'
            ? trim($data['contact_person'])
            : trim(implode(' ', array_filter([$data['first_name'], $data['middle_name'] ?? null, $data['last_name']])));

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

        $user = DB::transaction(fn () => User::create([
            'username' => strtolower(trim($data['username'])),
            'password' => Hash::make($data['password'] ?? bin2hex(random_bytes(24))),
            'name' => $fullName,
            'role' => 'requestor',
            'requestor_type' => $data['requestor_type'],
            'school_id_number' => $data['school_id_number'] ?? null,
            'office_or_organization' => $data['requestor_type'] === 'outsider' ? $org : null,
            'contact_number' => $data['contact_number'] ?? null,
            'department' => $departmentName,
            'college_id' => $data['college_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'google_id' => $googleProfile['google_id'] ?? null,
            'email_verified_at' => $isGoogleRegistration ? now() : null,
        ]));

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

    private function redirectByRole(string $role)
    {
        return match (true) {
            in_array($role, ['admin', 'facility_admin'], true) || $role === 'supply_office' => redirect()->route('supply-office.index'),
            str_starts_with($role, 'custodian') => redirect()->route('custodian.index'),
            default => redirect()->route('requestor.index'),
        };
    }
}