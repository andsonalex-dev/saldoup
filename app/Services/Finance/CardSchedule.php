<?php

namespace App\Services\Finance;

use Carbon\CarbonImmutable;

class CardSchedule
{
    public function installments(string $amount, int $count): array
    {
        $base = bcdiv($amount, (string) $count, 2);
        $extra = (int) bcmul(bcsub($amount, bcmul($base, (string) $count, 2), 2), '100', 0);

        return array_map(fn ($i) => $i <= $extra ? bcadd($base, '0.01', 2) : $base, range(1, $count));
    }

    public function dates(string $purchaseDate, int $closingDay, int $dueDay, int $offset, bool $onClosingNext, int $installment = 0): array
    {
        $date = CarbonImmutable::parse($purchaseDate)->startOfDay();
        $month = $date->startOfMonth();
        $close = $month->day(min($closingDay, $month->daysInMonth));
        if ($date->gt($close) || ($date->eq($close) && $onClosingNext)) {
            $month = $month->addMonth();
        }
        $month = $month->addMonths($installment);
        $close = $month->day(min($closingDay, $month->daysInMonth));
        $dueMonth = $month->addMonths($offset);
        $due = $dueMonth->day(min($dueDay, $dueMonth->daysInMonth));

        return [$close->toDateString(), $due->toDateString()];
    }
}
