<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

abstract class AuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    protected function newPasswordRules(): array
    {
        return ['required', 'string', 'confirmed', Password::min(12), function ($attribute, $value, $fail): void {
            if (strlen($value) > 72) {
                $fail('A senha é muito longa. Escolha uma frase menor.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'required' => 'Preencha o campo :attribute.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Não foi possível cadastrar este e-mail. Tente entrar ou recuperar sua senha.',
            'password.confirmed' => 'As senhas não coincidem.',
            'password.min' => 'A senha deve ter pelo menos 12 caracteres.',
            'max' => 'O campo :attribute excede o limite permitido.',
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha', 'token' => 'token'];
    }
}
