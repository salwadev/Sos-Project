<?php

namespace App\Tests\Unit\Auth;

use App\Auth\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function validNumbers(): iterable
    {
        yield 'national avec espaces' => ['22 123 456', '+21622123456'];
        yield 'international' => ['+216 22-123-456', '+21622123456'];
        yield 'préfixe 00' => ['0021698765432', '+21698765432'];
        yield 'préfixe sans plus' => ['21650111222', '+21650111222'];
        yield 'fixe' => ['71 234 567', '+21671234567'];
    }

    #[DataProvider('validNumbers')]
    public function testNormalizesTunisianNumbers(string $raw, string $expected): void
    {
        self::assertSame($expected, PhoneNumber::normalizeTunisian($raw));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidNumbers(): iterable
    {
        yield 'trop court' => ['2212345'];
        yield 'trop long' => ['221234567'];
        yield 'commence par 0' => ['02123456'];
        yield 'autre pays' => ['+33612345678'];
        yield 'lettres' => ['22ABC456'];
        yield 'vide' => [''];
    }

    #[DataProvider('invalidNumbers')]
    public function testRejectsInvalidNumbers(string $raw): void
    {
        self::assertNull(PhoneNumber::normalizeTunisian($raw));
    }
}
