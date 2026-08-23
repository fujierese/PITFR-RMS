<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    public function authenticate(): bool
    {
        $email = trim((string) $this->input('email', $this->input('username', '')));

        return Auth::guard('web')->attempt([
            'username' => $email,
            'password' => $this->input('password'),
        ], $this->boolean('remember'));
    }
}