<?php

namespace App\Tests\Unit\Domain;

use App\Entity\Commission;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class CommissionTest extends TestCase
{
    public function testTenPercentOfEightyFiveDinars(): void
    {
        $commission = new Commission(Factory::intervention(), 85_000, 1_000);

        self::assertSame(8_500, $commission->getAmountMillimes());
    }

    public function testRoundsToTheNearestMillime(): void
    {
        // 12 345 millimes × 12,5 % = 1 543,125 → 1 543
        self::assertSame(1_543, Commission::compute(12_345, 1_250));
        // 999 millimes × 15 % = 149,85 → 150
        self::assertSame(150, Commission::compute(999, 1_500));
    }

    public function testRejectsAnInvalidRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Commission(Factory::intervention(), 85_000, 10_001);
    }
}
