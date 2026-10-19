<?php

namespace App\Tests\Unit\Domain;

use App\Entity\Quote;
use App\Intervention\CommissionPolicy;
use App\Intervention\InterventionConflict;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

/** §9.4 : assiette = devis accepté qui fait foi, taux par défaut ou par catégorie. */
final class CommissionPolicyTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-19 11:30:00', new \DateTimeZone('UTC'));
    }

    public function testTheBaseIsTheLatestAcceptedQuote(): void
    {
        $intervention = Factory::intervention();
        (new Quote($intervention, 1, [['label' => 'Diagnostic', 'amountMillimes' => 20_000]], $this->now))->accept($this->now);
        (new Quote($intervention, 2, [['label' => 'Pièce', 'amountMillimes' => 65_000]], $this->now, 'Pièce cassée.'))->accept($this->now);
        new Quote($intervention, 3, [['label' => 'Option', 'amountMillimes' => 99_000]], $this->now, 'Pas encore acceptée.');

        $commission = (new CommissionPolicy(1_000))->commissionFor($intervention);

        self::assertSame(65_000, $commission->getBaseMillimes());
        self::assertSame(6_500, $commission->getAmountMillimes());
    }

    public function testTheRateCanBeSetPerCategory(): void
    {
        $intervention = Factory::intervention(); // catégorie « home » (Factory::category)

        self::assertSame(1_500, (new CommissionPolicy(1_000, ['home' => 1_500]))->rateFor($intervention));
        self::assertSame(1_000, (new CommissionPolicy(1_000, ['locksmith' => 1_500]))->rateFor($intervention));
    }

    public function testNoCommissionWithoutAnAcceptedQuote(): void
    {
        $intervention = Factory::intervention();
        new Quote($intervention, 1, [['label' => 'Diagnostic', 'amountMillimes' => 20_000]], $this->now);

        $this->expectException(InterventionConflict::class);
        (new CommissionPolicy(1_000))->commissionFor($intervention);
    }
}
