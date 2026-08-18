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
        $email = trim((string) $request->input('email', $request->input('username', '')));
        $credentials = [
            'username' => $email,
            'password' => $request->input('password'),
        ];

        if (!Auth::guard('web')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Invalid email or password.'])->withInput($request->except('password'));
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
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'requestor_type' => ['required', 'in:student,outsider'],
            'college_id' => ['required_if:requestor_type,student', 'nullable', 'exists:colleges,id'],
            'department_id' => ['required_if:requestor_type,student', 'nullable', 'exists:departments,id'],
            'school_id_number' => ['required_if:requestor_type,student', 'nullable', 'string', 'regex:/^\d{2}-\d{4}-\d{3}$/'],
            'office_or_organization' => ['nullable', 'string', 'max:191'],
            'contact_number' => ['nullable', 'string', 'max:50'],
        ], [
            'school_id_number.regex' => 'Student ID must be in format: 23-0098-635 (2 digits - 4 digits - 3 digits)',
            'college_id.required_if' => 'College is required for student registration',
            'department_id.required_if' => 'Department is required for student registration',
        ]);

        // Combine name fields
        $fullName = trim($data['first_name']);
        if (!empty($data['middle_name'])) {
            $fullName .= ' ' . trim($data['middle_name']);
        }
        $fullName .= ' ' . trim($data['last_name']);

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
            $department = \App\Models\Department::find($data['department_id']);
        }

        // Get department name for storage
        $departmentName = $department ? $department->name : null;

        $user = User::create([
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'name' => $fullName,
            'role' => 'requestor',
            'requestor_type' => $data['requestor_type'],
            'school_id_number' => $data['school_id_number'] ?? null,
            'office_or_organization' => $org,
            'contact_number' => $data['contact_number'] ?? null,
            'department' => $departmentName,
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