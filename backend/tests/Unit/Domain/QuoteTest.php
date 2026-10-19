<?php

namespace App\Tests\Unit\Domain;

use App\Entity\Quote;
use App\Enum\QuoteStatus;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

/** §9.2, §16.4 : estimation ≠ devis accepté, hausse justifiée et acceptée explicitement, version conservée. */
final class QuoteTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-19 10:42:00', new \DateTimeZone('UTC'));
    }

    public function testTotalIsTheSumOfLines(): void
    {
        $quote = new Quote(Factory::intervention(), 1, [
            ['label' => 'Déplacement', 'amountMillimes' => 20_000],
            ['label' => 'Main-d’œuvre', 'amountMillimes' => 50_000],
            ['label' => 'Pièce de remplacement', 'amountMillimes' => 15_000],
        ], $this->now);

        self::assertSame(85_000, $quote->getTotalMillimes());
        self::assertSame('TND', $quote->getCurrency());
        self::assertSame(QuoteStatus::Proposed, $quote->getStatus());
    }

    public function testAQuoteIsOnlyAcceptedByAnExplicitDecision(): void
    {
        $intervention = Factory::intervention();
        $quote = new Quote($intervention, 1, [['label' => 'Diagnostic', 'amountMillimes' => 20_000]], $this->now);

        self::assertNull($intervention->getAcceptedQuote());

        $quote->accept($this->now);
        self::assertSame($quote, $intervention->getAcceptedQuote());
        self::assertEquals($this->now, $quote->getDecidedAt());
    }

    public function testAnAcceptedQuoteCannotBeDecidedAgain(): void
    {
        $quote = new Quote(Factory::intervention(), 1, [['label' => 'Diagnostic', 'amountMillimes' => 20_000]], $this->now);
        $quote->accept($this->now);

        $this->expectException(\DomainException::class);
        $quote->reject($this->now);
    }

    public function testAPriceIncreaseMustBeJustified(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Quote(Factory::intervention(), 2, [['label' => 'Pièce', 'amountMillimes' => 40_000]], $this->now);
    }

    public function testNegativeLinesAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Quote(Factory::intervention(), 1, [['label' => 'Remise', 'amountMillimes' => -5_000]], $this->now);
    }
}
