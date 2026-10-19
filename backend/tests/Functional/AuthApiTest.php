<?php

namespace App\Tests\Functional;

use App\Entity\AuditLog;
use App\Entity\OtpChallenge;
use App\Entity\User;
use App\Enum\UserStatus;
use App\Sms\InMemorySmsSender;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Factory;

/** §5.1, §14.1, §15.3, §16.7 : connexion OTP, jetons, protection contre les abus. */
final class AuthApiTest extends ApiTestCase
{
    private const PHONE = '+21622123456';

    public function testRequestOtpAnswersTheSameWayForKnownAndUnknownNumbers(): void
    {
        Factory::client($this->em(), '+21698765432');

        $known = $this->api('POST', '/api/auth/request-otp', ['phone' => '98 765 432']);
        $knownStatus = $this->httpStatus();
        $unknown = $this->api('POST', '/api/auth/request-otp', ['phone' => '22 123 456']);

        self::assertSame(202, $knownStatus);
        self::assertSame(202, $this->httpStatus());
        self::assertSame($known, $unknown, 'Aucune différence ne révèle si un numéro est inscrit.');
    }

    public function testTheCodeIsNeverStoredInClear(): void
    {
        $this->api('POST', '/api/auth/request-otp', ['phone' => self::PHONE]);
        $code = $this->lastSmsCode(self::PHONE);

        $challenge = $this->em()->getRepository(OtpChallenge::class)->findOneBy(['phone' => self::PHONE]);
        self::assertNotNull($challenge);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $challenge->getCodeHash());
        self::assertStringNotContainsString((string) $code, $challenge->getCodeHash());
    }

    public function testAnInvalidPhoneIsRejected(): void
    {
        $this->api('POST', '/api/auth/request-otp', ['phone' => '+33 6 12 34 56 78']);

        self::assertSame(422, $this->httpStatus());
        self::assertCount(0, static::getContainer()->get(InMemorySmsSender::class)->getMessages());
    }

    public function testFirstLoginCreatesAClientAccount(): void
    {
        $tokens = $this->loginWithOtp(self::PHONE);

        self::assertTrue($tokens['newAccount']);
        self::assertContains('ROLE_CLIENT', $tokens['roles']);
        self::assertSame(900, $tokens['expiresIn']);
        self::assertNotEmpty($tokens['token']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $tokens['refreshToken']);

        $user = $this->em()->getRepository(User::class)->findOneBy(['phone' => self::PHONE]);
        self::assertNotNull($user?->getClientProfile());
        self::assertSame($user->getUserIdentifier(), $tokens['userId']);

        $audit = $this->em()->getRepository(AuditLog::class)->findOneBy(['action' => 'auth.account_created']);
        self::assertNotNull($audit, 'La création de compte est journalisée.');
        self::assertStringNotContainsString(self::PHONE, json_encode($audit->getContext(), \JSON_THROW_ON_ERROR));
    }

    public function testSecondLoginReusesTheAccount(): void
    {
        $this->loginWithOtp(self::PHONE);
        $tokens = $this->loginWithOtp(self::PHONE);

        self::assertFalse($tokens['newAccount']);
        self::assertCount(1, $this->em()->getRepository(User::class)->findBy(['phone' => self::PHONE]));
    }

    public function testAWrongCodeIsRejectedWithAGenericMessage(): void
    {
        $this->api('POST', '/api/auth/request-otp', ['phone' => self::PHONE]);
        $wrong = '000000' === $this->lastSmsCode(self::PHONE) ? '111111' : '000000';

        $error = $this->api('POST', '/api/auth/verify-otp', ['phone' => self::PHONE, 'code' => $wrong]);

        self::assertSame(401, $this->httpStatus());
        self::assertSame('Code invalide ou expiré.', $error['detail']);
    }

    public function testTheCodeIsLockedAfterFiveFailedAttempts(): void
    {
        $this->api('POST', '/api/auth/request-otp', ['phone' => self::PHONE]);
        $code = $this->lastSmsCode(self::PHONE);
        $wrong = '000000' === $code ? '111111' : '000000';

        for ($i = 0; $i < OtpChallenge::MAX_ATTEMPTS; ++$i) {
            $this->api('POST', '/api/auth/verify-otp', ['phone' => self::PHONE, 'code' => $wrong]);
            self::assertSame(401, $this->httpStatus());
        }

        $this->api('POST', '/api/auth/verify-otp', ['phone' => self::PHONE, 'code' => $code]);
        self::assertSame(401, $this->httpStatus(), 'Même le bon code est refusé une fois les essais épuisés.');
    }

    public function testACodeCanOnlyBeUsedOnce(): void
    {
        $this->api('POST', '/api/auth/request-otp', ['phone' => self::PHONE]);
        $code = $this->lastSmsCode(self::PHONE);

        $this->api('POST', '/api/auth/verify-otp', ['phone' => self::PHONE, 'code' => $code]);
        self::assertSame(200, $this->httpStatus());

        $this->api('POST', '/api/auth/verify-otp', ['phone' => self::PHONE, 'code' => $code]);
        self::assertSame(401, $this->httpStatus());
    }

    public function testAnExpiredCodeIsRejected(): void
    {
        $this->api('POST', '/api/auth/request-otp', ['phone' => self::PHONE]);
        $code = $this->lastSmsCode(self::PHONE);
        $this->em()->getConnection()->executeStatement("UPDATE otp_challenge SET expires_at = now() - interval '1 second'");

        $this->api('POST', '/api/auth/verify-otp', ['phone' => self::PHONE, 'code' => $code]);
        self::assertSame(401, $this->httpStatus());
    }

    public function testOtpRequestsAreRateLimitedPerPhone(): void
    {
        $this->client->disableReboot();

        for ($i = 0; $i < 3; ++$i) {
            $this->api('POST', '/api/auth/request-otp', ['phone' => self::PHONE]);
            self::assertSame(202, $this->httpStatus());
        }

        $this->api('POST', '/api/auth/request-otp', ['phone' => self::PHONE]);
        self::assertSame(429, $this->httpStatus());
        self::assertTrue($this->client->getResponse()->headers->has('Retry-After'));
    }

    public function testRefreshRotatesTheToken(): void
    {
        $tokens = $this->loginWithOtp(self::PHONE);

        $renewed = $this->api('POST', '/api/auth/refresh', ['refreshToken' => $tokens['refreshToken']]);
        self::assertSame(200, $this->httpStatus());
        self::assertNotSame($tokens['refreshToken'], $renewed['refreshToken']);

        $this->api('GET', '/api/requests', token: $renewed['token']);
        self::assertSame(200, $this->httpStatus(), 'Le nouveau jeton d\'accès fonctionne.');
    }

    public function testReusingARefreshTokenRevokesTheWholeSession(): void
    {
        $tokens = $this->loginWithOtp(self::PHONE);
        $renewed = $this->api('POST', '/api/auth/refresh', ['refreshToken' => $tokens['refreshToken']]);

        $this->api('POST', '/api/auth/refresh', ['refreshToken' => $tokens['refreshToken']]);
        self::assertSame(401, $this->httpStatus(), 'L\'ancien jeton est refusé.');

        $this->api('POST', '/api/auth/refresh', ['refreshToken' => $renewed['refreshToken']]);
        self::assertSame(401, $this->httpStatus(), 'La réutilisation a révoqué aussi le jeton le plus récent.');
    }

    public function testLogoutRevokesTheRefreshToken(): void
    {
        $tokens = $this->loginWithOtp(self::PHONE);

        $this->api('POST', '/api/auth/logout', ['refreshToken' => $tokens['refreshToken']]);
        self::assertSame(204, $this->httpStatus());

        $this->api('POST', '/api/auth/refresh', ['refreshToken' => $tokens['refreshToken']]);
        self::assertSame(401, $this->httpStatus());
    }

    public function testASuspendedAccountCannotUseItsToken(): void
    {
        $user = Factory::client($this->em());
        $token = $this->tokenFor($user);
        $user->setStatus(UserStatus::Suspended);
        $this->em()->flush();

        $this->api('GET', '/api/requests', token: $token);
        self::assertSame(401, $this->httpStatus());
    }

    public function testProtectedRoutesRequireAToken(): void
    {
        $this->api('GET', '/api/requests');
        self::assertSame(401, $this->httpStatus());

        $this->api('GET', '/api/requests', token: 'not-a-jwt');
        self::assertSame(401, $this->httpStatus());
    }
}
