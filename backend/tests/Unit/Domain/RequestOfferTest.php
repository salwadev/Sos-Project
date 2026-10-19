<?php

namespace App\Tests\Unit\Domain;

use App\Entity\RequestOffer;
use App\Enum\OfferStatus;
use App\Tests\Support\Factory;
use PHPUnit\Framework\TestCase;

final class RequestOfferTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-10-19 09:30:00', new \DateTimeZone('UTC'));
    }

    private function offer(): RequestOffer
    {
        return new RequestOffer(Factory::request(), Factory::professional(), 1, 20_000, $this->now, $this->now->modify('+42 seconds'));
    }

    public function testAProfessionalReservesWithItsOwnTravelFee(): void
    {
        $offer = $this->offer();
        $offer->reserve($this->now, $this->now->modify('+3 minutes'), 25_000);

        self::assertSame(OfferStatus::Reserved, $offer->getStatus());
        self::assertSame(25_000, $offer->getTravelFeeMillimes());
    }

    public function testADeclinedOfferCannotBeReserved(): void
    {
        $offer = $this->offer();
        $offer->decline($this->now);

        $this->expectException(\DomainException::class);
        $offer->reserve($this->now, $this->now->modify('+3 minutes'), 20_000);
    }

    public function testTheClientCanOnlyConfirmAReservedOffer(): void
    {
        $offer = $this->offer();

        $this->expectException(\DomainException::class);
        $offer->confirmByClient();
    }

    public function testAnExpiredOfferCannotBeConfirmed(): void
    {
        $offer = $this->offer();
        $offer->reserve($this->now, $this->now->modify('+3 minutes'), 20_000);
        $offer->expire();

        $this->expectException(\DomainException::class);
        $offer->confirmByClient();
    }
}
