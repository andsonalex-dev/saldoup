<?php

namespace App\Http\Requests\Memberships;

use Illuminate\Foundation\Http\FormRequest;

class WorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:100'], 'timezone' => ['required', 'string', 'timezone', 'max:64']];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do espaço financeiro.',
            'name.max' => 'O nome deve ter no máximo 100 caracteres.',
            'timezone.required' => 'Escolha o fuso horário.',
            'timezone.timezone' => 'Escolha um fuso horário válido.',
        ];
    }
}
