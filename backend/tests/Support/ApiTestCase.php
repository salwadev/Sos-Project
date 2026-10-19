<?php

namespace App\Tests\Support;

use App\Entity\User;
use App\Sms\InMemorySmsSender;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** Base des tests HTTP : base de test vidée, requêtes JSON, connexion OTP de bout en bout. */
abstract class ApiTestCase extends WebTestCase
{
    use ResetsDatabase;

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        self::resetDatabase($this->em());
        static::getContainer()->get('cache.otp_limiter')->clear();
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string>      $headers en-têtes HTTP (ex. ['Idempotency-Key' => 'abc'], ['Content-Type' => 'application/merge-patch+json'])
     *
     * @return array<string, mixed>|null corps JSON décodé
     */
    protected function api(string $method, string $uri, ?array $body = null, ?string $token = null, array $headers = []): ?array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            $server['CONTENT_TYPE' === $key ? $key : 'HTTP_'.$key] = $value;
        }

        $this->client->request($method, $uri, server: $server, content: null === $body ? null : json_encode($body, \JSON_THROW_ON_ERROR));
        $content = $this->client->getResponse()->getContent();

        return '' === $content || false === $content ? null : json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }

    protected function httpStatus(): int
    {
        return $this->client->getResponse()->getStatusCode();
    }

    protected function lastSmsCode(string $phoneE164): ?string
    {
        return static::getContainer()->get(InMemorySmsSender::class)->lastCodeFor($phoneE164);
    }

    /**
     * Connexion complète par OTP.
     *
     * @return array<string, mixed> réponse de /api/auth/verify-otp
     */
    protected function loginWithOtp(string $phoneE164): array
    {
        $this->api('POST', '/api/auth/request-otp', ['phone' => $phoneE164]);
        self::assertSame(202, $this->httpStatus());
        $code = $this->lastSmsCode($phoneE164);
        self::assertNotNull($code);

        $tokens = $this->api('POST', '/api/auth/verify-otp', ['phone' => $phoneE164, 'code' => $code]);
        self::assertSame(200, $this->httpStatus());

        return $tokens;
    }

    /** Jeton d'accès direct pour un utilisateur déjà en base (évite le parcours OTP). */
    protected function tokenFor(User $user): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
    }
}
