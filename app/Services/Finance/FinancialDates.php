<?php
namespace App\Services\Finance;
use App\Models\Workspace;
use Illuminate\Validation\ValidationException;
class FinancialDates {
    public static function assertActual(string $date,Workspace $workspace): void {
        if($date>now($workspace->timezone)->toDateString()) throw ValidationException::withMessages(['date'=>'A data efetiva não pode ser futura. Para operações futuras, use um lançamento previsto.']);
    }
}
