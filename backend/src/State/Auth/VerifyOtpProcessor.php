<?php

namespace App\State\Auth;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Auth\OtpVerification;
use App\Audit\AuditLogger;
use App\Auth\OtpService;
use App\Auth\PhoneNumber;
use App\Auth\TokenIssuer;
use App\Auth\TokenPair;
use App\Domain\Clock;
use App\Entity\ClientProfile;
use App\Entity\User;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Vérifie le code ; au premier passage, crée un compte client (§5.1).
 * Les échecs renvoient toujours le même message, sans indiquer si le numéro existe.
 *
 * @implements ProcessorInterface<OtpVerification, TokenPair>
 */
final class VerifyOtpProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly TokenIssuer $tokens,
        private readonly AuditLogger $audit,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): TokenPair
    {
        \assert($data instanceof OtpVerification);
        $phone = PhoneNumber::normalizeTunisian((string) $data->phone);

        if (null === $phone || !$this->otp->verify($phone, (string) $data->code)) {
            throw new HttpException(401, 'Code invalide ou expiré.');
        }

        $user = $this->users->findOneBy(['phone' => $phone]);
        $newAccount = null === $user;
        if ($newAccount) {
            $user = new User($phone, UserRole::Client);
            new ClientProfile($user);
            $this->em->persist($user);
        }
        if (!$user->isActive()) {
            throw new AccessDeniedHttpException('Compte suspendu. Contactez le support.');
        }

        $user->markLoggedIn(Clock::now());
        $this->em->flush();

        $this->audit->record($newAccount ? 'auth.account_created' : 'auth.login', $user, 'User', $user->getUserIdentifier());

        return $this->tokens->issue($user, $newAccount);
    }
}
