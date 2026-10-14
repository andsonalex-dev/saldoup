<?php

namespace App\Http\Requests\Auth;

class ResetPasswordRequest extends AuthRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
            'password' => $this->newPasswordRules(),
        ];
    }
}
