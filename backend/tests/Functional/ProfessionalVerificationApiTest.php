<?php

namespace App\Tests\Functional;

use App\Audit\AuditLogger;
use App\Entity\AuditLog;
use App\Entity\ProfessionalProfile;
use App\Entity\Service;
use App\Entity\ServiceArea;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Enum\UserRole;
use App\Matching\ProfessionalEligibility;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\DrivesMatching;
use App\Tests\Support\Factory;
use App\Tests\Support\OnboardsProfessionals;

/** Vérification des professionnels par l'administration, jusqu'au matching — §6.2, §7.2, §8.1, §14.5, §16.6. */
final class ProfessionalVerificationApiTest extends ApiTestCase
{
    use DrivesMatching;
    use OnboardsProfessionals;

    private const MERGE_PATCH = ['Content-Type' => 'application/merge-patch+json'];

    private Service $plumbing;
    private Service $electricity;
    private ServiceArea $laMarsa;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $home = Factory::category($this->em(), 'home');
        $this->plumbing = Factory::service($this->em(), $home, 'plumbing');
        $this->electricity = Factory::service($this->em(), $home, 'electricity');
        $this->electricity->setRequiresQualification(true);
        $this->laMarsa = Factory::laMarsa($this->em());
        $this->em()->flush();
        $this->adminToken = $this->tokenFor(Factory::user($this->em(), UserRole::Admin));
    }

    protected function tearDown(): void
    {
        $this->removeSampleFiles();
        parent::tearDown();
    }

    /** @return array{User, string, string} professionnel inscrit par l'API : [compte, jeton, id du profil] */
    private function onboardedProfessional(array $overrides = []): array
    {
        $user = Factory::client($this->em());
        $token = $this->tokenFor($user);
        $profile = $this->submitProfile($token, $overrides);
        self::assertSame(200, $this->httpStatus());

        return [$user, $token, $profile['id']];
    }

    /** @return array<string, mixed>|null */
    private function decide(string $profileId, array $body, ?string $token = null): ?array
    {
        return $this->api('PATCH', \sprintf('/api/admin/professionals/%s/verification', $profileId), $body, $token ?? $this->adminToken, self::MERGE_PATCH);
    }

    /** Les appels HTTP redémarrent le noyau : on relit le catalogue dans l'EntityManager courant. */
    private function reloadCatalog(): void
    {
        $this->plumbing = $this->em()->find(Service::class, $this->plumbing->getId());
        $this->electricity = $this->em()->find(Service::class, $this->electricity->getId());
        $this->laMarsa = $this->em()->find(ServiceArea::class, $this->laMarsa->getId());
    }

    private function profile(string $id): ProfessionalProfile
    {
        $this->em()->clear();

        return $this->em()->find(ProfessionalProfile::class, $id);
    }

    public function testAdminEndpointsAreReservedToAdmins(): void
    {
        [, $proToken, $profileId] = $this->onboardedProfessional();
        $support = $this->tokenFor(Factory::user($this->em(), UserRole::Support));
        $client = $this->tokenFor(Factory::client($this->em()));
        $body = ['action' => 'reject', 'reason' => 'Test'];

        foreach (['professionnel' => $proToken, 'support' => $support, 'client' => $client] as $who => $token) {
            $this->api('GET', '/api/admin/professionals', token: $token);
            self::assertSame(403, $this->httpStatus(), $who);
            $this->api('GET', '/api/admin/professionals/'.$profileId, token: $token);
            self::assertSame(403, $this->httpStatus(), $who);
            $this->decide($profileId, $body, $token);
            self::assertSame(403, $this->httpStatus(), $who.' ne peut pas statuer (§16.6).');
        }
        $this->api('GET', '/api/admin/professionals');
        self::assertSame(401, $this->httpStatus());

        self::assertSame('pending', $this->profile($profileId)->getVerificationStatus()->value, 'Aucune décision n\'est passée.');
        self::assertNull($this->em()->getRepository(AuditLog::class)->findOneBy(['action' => 'professional.verification_changed']));
    }

    public function testTheAdminQueueListsPendingProfilesWithContactDetails(): void
    {
        [$user, , $profileId] = $this->onboardedProfessional();
        [, , $otherId] = $this->onboardedProfessional(['displayName' => 'Ines K.']);
        $this->reloadCatalog();
        Factory::availableProfessional($this->em(), $this->plumbing, $this->laMarsa);

        $queue = $this->api('GET', '/api/admin/professionals', token: $this->adminToken);
        self::assertSame(200, $this->httpStatus());
        self::assertSame([$profileId, $otherId], array_column($queue, 'id'), 'Profils « pending », plus anciens d\'abord.');
        self::assertSame($user->getPhone(), $queue[0]['phone'], 'L\'administration peut joindre le professionnel.');

        self::assertCount(1, $this->api('GET', '/api/admin/professionals?verificationStatus=verified', token: $this->adminToken));
        $this->api('GET', '/api/admin/professionals?verificationStatus=whatever', token: $this->adminToken);
        self::assertSame(400, $this->httpStatus());

        self::assertSame($profileId, $this->api('GET', '/api/admin/professionals/'.$profileId, token: $this->adminToken)['id']);
        $this->api('GET', '/api/admin/professionals/0192f0c4-0000-7000-8000-000000000000', token: $this->adminToken);
        self::assertSame(404, $this->httpStatus());
    }

    public function testApproveRejectSuspendAndReactivateWithAReasonAndAnAuditTrail(): void
    {
        [, $token, $profileId] = $this->onboardedProfessional();
        $identity = $this->uploadDocument($token, 'identity', $this->samplePdf())['id'];

        $this->decide($profileId, ['action' => 'approve']);
        self::assertSame(422, $this->httpStatus(), 'Motif obligatoire.');
        $this->decide($profileId, ['reason' => 'Rien à faire']);
        self::assertSame(422, $this->httpStatus(), 'Une action ou un justificatif.');
        $this->decide($profileId, ['action' => 'promote', 'reason' => 'x']);
        self::assertSame(422, $this->httpStatus());

        $refused = $this->decide($profileId, ['action' => 'approve', 'reason' => 'Dossier complet']);
        self::assertSame(409, $this->httpStatus(), 'Pas d\'approbation sans pièce d\'identité approuvée (§6.2).');
        self::assertStringContainsString('identité', $refused['detail']);
        $this->decide($profileId, ['action' => 'suspend', 'reason' => 'x']);
        self::assertSame(409, $this->httpStatus(), 'On ne suspend qu\'un profil vérifié.');

        $rejected = $this->decide($profileId, [
            'action' => 'reject',
            'reason' => 'Pièce d\'identité illisible',
            'documents' => [['id' => $identity, 'status' => 'rejected', 'rejectionReason' => 'Photo floue']],
        ]);
        self::assertSame(200, $this->httpStatus());
        self::assertSame('rejected', $rejected['verificationStatus']);
        self::assertSame('Pièce d\'identité illisible', $rejected['verificationNote']);
        self::assertSame('rejected', $rejected['documents'][0]['status']);
        self::assertSame('Photo floue', $rejected['documents'][0]['rejectionReason']);

        // Le professionnel corrige : nouveau dépôt, le profil repart en vérification.
        $newIdentity = $this->uploadDocument($token, 'identity', $this->samplePng(), ['expiresAt' => '2030-12-31'])['id'];
        self::assertSame('pending', $this->submitProfile($token)['verificationStatus']);

        $approved = $this->decide($profileId, [
            'action' => 'approve',
            'reason' => 'Identité contrôlée',
            'documents' => [['id' => $newIdentity, 'status' => 'approved', 'expiresAt' => '2030-06-30']],
        ]);
        self::assertSame(200, $this->httpStatus(), 'Justificatif et profil approuvés dans la même requête.');
        self::assertSame('verified', $approved['verificationStatus']);
        self::assertArrayHasKey('verifiedAt', $approved);
        $document = array_values(array_filter($approved['documents'], static fn (array $d) => $d['id'] === $newIdentity))[0];
        self::assertSame('approved', $document['status']);
        self::assertSame('2030-06-30', $document['expiresAt'], 'L\'administration fixe l\'expiration (§7.2).');

        $this->api('PATCH', '/api/professional/availability', ['available' => true], $token, self::MERGE_PATCH);
        $suspended = $this->decide($profileId, ['action' => 'suspend', 'reason' => 'Réclamations répétées']);
        self::assertSame('suspended', $suspended['verificationStatus']);
        self::assertFalse($this->api('GET', '/api/professional/availability', token: $token)['available'], 'La suspension coupe la disponibilité.');

        $this->decide($profileId, ['action' => 'approve', 'reason' => 'x']);
        self::assertSame(409, $this->httpStatus(), 'Un profil suspendu se réactive, il ne se réapprouve pas.');
        self::assertSame('verified', $this->decide($profileId, ['action' => 'reactivate', 'reason' => 'Médiation conclue'])['verificationStatus']);

        $audit = $this->em()->getRepository(AuditLog::class);
        $changes = $audit->findBy(['action' => 'professional.verification_changed'], ['createdAt' => 'ASC', 'id' => 'ASC']);
        self::assertSame(
            [['reject', 'pending', 'rejected'], ['approve', 'pending', 'verified'], ['suspend', 'verified', 'suspended'], ['reactivate', 'suspended', 'verified']],
            array_map(static fn (AuditLog $l) => [$l->getContext()['action'], $l->getContext()['from'], $l->getContext()['to']], $changes),
        );
        self::assertSame('Réclamations répétées', $changes[2]->getContext()['reason']);
        self::assertCount(2, $audit->findBy(['action' => 'professional.document_reviewed']));
        self::assertSame([], static::getContainer()->get(AuditLogger::class)->verifyChain());
    }

    public function testDocumentReviewsAreValidated(): void
    {
        [, $token, $profileId] = $this->onboardedProfessional(['legalStatus' => 'company']);
        [, $otherToken] = $this->onboardedProfessional();
        $identity = $this->uploadDocument($token, 'identity', $this->samplePdf())['id'];
        $foreign = $this->uploadDocument($otherToken, 'identity', $this->samplePdf())['id'];

        $this->decide($profileId, ['reason' => 'x', 'documents' => [['id' => $foreign, 'status' => 'approved']]]);
        self::assertSame(422, $this->httpStatus(), 'Justificatif d\'un autre professionnel.');
        $this->decide($profileId, ['reason' => 'x', 'documents' => [['id' => $identity, 'status' => 'rejected']]]);
        self::assertSame(422, $this->httpStatus(), 'Motif de rejet obligatoire.');
        $this->decide($profileId, ['reason' => 'x', 'documents' => [['id' => $identity, 'status' => 'approved', 'expiresAt' => '2020-01-01']]]);
        self::assertSame(409, $this->httpStatus(), 'Un justificatif expiré ne peut pas être approuvé.');
        self::assertSame('pending', $this->profile($profileId)->findDocument($identity)->getStatus()->value, 'Rien n\'a été écrit.');

        $refused = $this->decide($profileId, [
            'action' => 'approve',
            'reason' => 'Identité OK',
            'documents' => [['id' => $identity, 'status' => 'approved']],
        ]);
        self::assertSame(409, $this->httpStatus());
        self::assertStringContainsString('registre', $refused['detail'], 'Une entreprise doit fournir son extrait du registre.');
        self::assertSame('pending', $this->profile($profileId)->findDocument($identity)->getStatus()->value, 'Décision entière annulée.');
        self::assertNull($this->em()->getRepository(AuditLog::class)->findOneBy(['action' => 'professional.document_reviewed']), 'Ni trace sans décision.');
    }

    public function testAnOnboardedProfessionalBecomesEligibleForMatchingOnceVerified(): void
    {
        [$user, $token, $profileId] = $this->onboardedProfessional([
            'displayName' => 'Nouveau Pro',
            'services' => [
                ['serviceCode' => 'plumbing', 'travelFeeMillimes' => 12_000],
                ['serviceCode' => 'electricity', 'travelFeeMillimes' => 18_000],
            ],
        ]);
        $this->api('PATCH', '/api/professional/availability', ['available' => true, 'durationMinutes' => 120], $token, self::MERGE_PATCH);
        self::assertSame(200, $this->httpStatus());
        $identity = $this->uploadDocument($token, 'identity', $this->samplePdf())['id'];
        $qualification = $this->uploadDocument($token, 'qualification', $this->samplePdf(), ['expiresAt' => '2030-01-01'])['id'];

        $eligibility = static::getContainer()->get(ProfessionalEligibility::class);
        $this->reloadCatalog();
        $before =$this->submittedRequest($this->plumbing, $this->laMarsa);
        self::assertSame('profil non vérifié', $eligibility->exclusionReason($this->profile($profileId), $before, new \DateTimeImmutable()));

        $pending = $this->api('GET', '/api/admin/professionals', token: $this->adminToken);
        self::assertSame([$profileId], array_column($pending, 'id'));
        $this->decide($profileId, [
            'action' => 'approve',
            'reason' => 'Identité contrôlée en agence',
            'documents' => [['id' => $identity, 'status' => 'approved']],
        ]);
        self::assertSame(200, $this->httpStatus());

        $this->reloadCatalog();
        $request = $this->submittedRequest($this->plumbing, $this->laMarsa);
        $this->dispatch($request);
        self::assertSame(['Nouveau Pro' => 'sent'], $this->solicitations($request), 'Le professionnel vérifié est sollicité.');
        $offers = $this->api('GET', '/api/professional/requests', token: $token);
        self::assertSame(200, $this->httpStatus());
        self::assertSame($request->getReference(), $offers[0]['request']['reference']);
        self::assertSame(12_000, $offers[0]['travelFeeMillimes'], 'Frais déclarés à l\'inscription.');

        // Métier réglementé : il faut en plus la qualification approuvée (§8.1).
        $this->reloadCatalog();
        $electrical = $this->submittedRequest($this->electricity, $this->laMarsa);
        self::assertSame('qualification manquante ou expirée', $eligibility->exclusionReason($this->profile($profileId), $electrical, new \DateTimeImmutable()));
        $this->decide($profileId, ['reason' => 'Diplôme vérifié', 'documents' => [['id' => $qualification, 'status' => 'approved']]]);
        self::assertSame(200, $this->httpStatus());
        self::assertNull($eligibility->exclusionReason($this->profile($profileId), $electrical, new \DateTimeImmutable()));

        $electrical = $this->em()->find(ServiceRequest::class, $electrical->getId());
        $this->dispatch($electrical);
        self::assertSame(['Nouveau Pro' => 'sent'], $this->solicitations($electrical));
        self::assertTrue($this->em()->find(User::class, $user->getId())->hasRole(UserRole::Client), 'Il reste client par ailleurs.');
    }
}
