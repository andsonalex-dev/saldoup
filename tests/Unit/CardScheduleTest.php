<?php

namespace Tests\Unit;

use App\Services\Finance\CardSchedule;
use PHPUnit\Framework\TestCase;

class CardScheduleTest extends TestCase
{
    public function test_installment_remainder_is_allocated_to_first_parts_and_total_is_exact(): void
    {
        $parts = (new CardSchedule)->installments('100.00', 3);
        $this->assertSame(['33.34', '33.33', '33.33'], $parts);
        $sum = '0.00';
        foreach ($parts as $part) {
            $sum = bcadd($sum, $part, 2);
        }$this->assertSame('100.00', $sum);
    }

    public function test_closing_day_and_short_months_follow_explicit_configuration(): void
    {
        $s = new CardSchedule;
        $this->assertSame(['2026-10-10', '2026-11-05'], $s->dates('2026-10-10', 10, 5, 1, false));
        $this->assertSame(['2026-11-10', '2026-12-05'], $s->dates('2026-10-10', 10, 5, 1, true));
        $this->assertSame(['2027-02-28', '2027-03-31'], $s->dates('2027-02-28', 31, 31, 1, false));
        $this->assertSame(['2027-03-31', '2027-04-30'], $s->dates('2027-02-28', 31, 31, 1, false, 1));
        $this->assertSame(['2028-02-29', '2028-03-31'], $s->dates('2028-02-29',31,31,1,false));
    }
}
