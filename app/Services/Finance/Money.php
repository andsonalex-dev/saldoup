<?php

namespace App\Services\Finance;

use Illuminate\Validation\ValidationException;

class Money
{
    public static function normalize(mixed $value, bool $positive = false): string
    {
        if (! is_string($value) || ! preg_match('/^(-?)(\d{1,13})(?:[.,](\d{1,2}))?$/D', $value)) {
            throw ValidationException::withMessages(['amount' => 'Informe um valor válido com até duas casas decimais, sem separador de milhar.']);
        }
        $value = str_replace(',', '.', $value);
        if ($positive && bccomp($value, '0', 2) <= 0) {
            throw ValidationException::withMessages(['amount' => 'O valor deve ser maior que zero.']);
        }

        return bcadd($value, '0', 2);
    }

    public static function format(string $value): string
    {
        [$whole,$fraction] = explode('.', bcadd($value, '0', 2));

        return $whole.','.$fraction;
    }
}
