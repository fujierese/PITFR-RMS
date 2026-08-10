<?php
namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        $credentials = $request->only('username', 'password');

        if (!Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['username' => 'Invalid username or password.'])->withInput();
        }

        $request->session()->regenerate();
        return $this->redirectByRole(Auth::user()->role);
    }

    public function showRegister()
    {
        if (Auth::check()) return redirect()->route('home');
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'name'     => ['required', 'string', 'max:191'],
            'requestor_type' => ['required', 'in:student,faculty,outsider'],
            'school_id_number' => ['required_if:requestor_type,student|string|max:50'],
            // For external requestors we allow an optional organization or purpose; empty or 'Individual' is acceptable
            'office_or_organization' => ['nullable', 'string', 'max:191'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'department' => ['nullable', 'string', 'max:191'],
        ]);

        // Normalize organization / purpose: treat common 'Individual' markers and empty strings as null
        $org = $data['office_or_organization'] ?? null;
        if (is_string($org)) {
            $org = trim($org);
            $lower = strtolower($org);
            if ($org === '' || in_array($lower, ['individual','personal','individual / personal','n/a','none'], true)) {
                $org = null;
            }
        }

        $user = User::create([
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'name'     => $data['name'],
            'role'     => 'requestor',
            'requestor_type' => $data['requestor_type'],
            'school_id_number' => $data['school_id_number'] ?? null,
            'office_or_organization' => $org,
            'contact_number' => $data['contact_number'] ?? null,
            'department' => $data['department'] ?? null,
        ]);

        Auth::login($user);
        return redirect()->route('requestor.index');
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