<?php

namespace App\Http\Requests\Auth;

class LoginRequest extends AuthRequest
{
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string', 'max:1024']];
    }
}
