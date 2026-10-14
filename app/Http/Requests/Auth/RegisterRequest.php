<?php

namespace App\Http\Requests\Auth;

class RegisterRequest extends AuthRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => $this->newPasswordRules(),
        ];
    }
}
