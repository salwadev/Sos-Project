<?php

namespace App\Tests\Functional;

use App\Entity\AuditLog;
use App\Entity\ProfessionalDocument;
use App\Entity\ProfessionalProfile;
use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\VerificationStatus;
use App\Tests\Support\ApiTestCase;
use App\Tests\Support\Factory;
use App\Tests\Support\OnboardsProfessionals;

/** Inscription professionnelle et justificatifs — §6.1, §6.2, §12.6, §14.4, §16.6. */
final class ProfessionalOnboardingApiTest extends ApiTestCase
{
    use OnboardsProfessionals;

    protected function setUp(): void
    {
        parent::setUp();
        $home = Factory::category($this->em(), 'home');
        Factory::service($this->em(), $home, 'plumbing');
        Factory::service($this->em(), $home, 'electricity');
        Factory::laMarsa($this->em());
        Factory::area($this->em(), 'carthage', [10.31, 36.84, 10.35, 36.86], active: false);
    }

    protected function tearDown(): void
    {
        $this->removeSampleFiles();
        parent::tearDown();
    }

    private function profileOf(User $user): ProfessionalProfile
    {
        $this->em()->clear();

        return $this->em()->find(User::class, $user->getId())->getProfessionalProfile();
    }

    /** @return array{User, string} professionnel inscrit par l'API, et son jeton */
    private function onboardedProfessional(): array
    {
        $user = Factory::client($this->em());
        $token = $this->tokenFor($user);
        $this->submitProfile($token);
        self::assertSame(200, $this->httpStatus());

        return [$user, $token];
    }

    public function testAClientBecomesAPendingProfessional(): void
    {
        $user = Factory::client($this->em());
        $token = $this->tokenFor($user);

        $profile = $this->submitProfile($token, [
            'services' => [
                ['serviceCode' => 'plumbing', 'travelFeeMillimes' => 15_000, 'diagnosticFeeMillimes' => 10_000],
                ['serviceCode' => 'electricity', 'travelFeeMillimes' => 20_000],
            ],
            'serviceAreaCodes' => ['la_marsa', 'carthage'],
        ]);

        self::assertSame(200, $this->httpStatus());
        self::assertSame('pending', $profile['verificationStatus'], 'En attente tant que les contrôles ne sont pas faits (§6.1).');
        self::assertSame('Sami B.', $profile['displayName']);
        self::assertSame('individual', $profile['legalStatus']);
        self::assertSame(['electricity', 'plumbing'], array_column($profile['services'], 'serviceCode'));
        self::assertSame(10_000, $profile['services'][1]['diagnosticFeeMillimes']);
        self::assertSame(['carthage', 'la_marsa'], array_column($profile['serviceAreas'], 'code'), 'Une zone pas encore ouverte peut être couverte.');
        self::assertEqualsWithDelta(36.885, $profile['baseLatitude'], 1e-6);
        self::assertEqualsWithDelta(10.33, $profile['baseLongitude'], 1e-6);
        self::assertSame([], $profile['documents']);
        self::assertArrayNotHasKey('phone', $profile, 'Le téléphone n\'est renvoyé qu\'à l\'administration.');

        $stored = $this->profileOf($user);
        self::assertTrue($stored->getUser()->hasRole(UserRole::Professional));
        self::assertTrue($stored->getUser()->hasRole(UserRole::Client), 'Les rôles se cumulent (§13.2).');
        self::assertSame(VerificationStatus::Pending, $stored->getVerificationStatus());

        $read = $this->api('GET', '/api/professional/profile', token: $token);
        self::assertSame(200, $this->httpStatus(), 'Le rôle professionnel s\'applique dès la requête suivante.');
        self::assertSame($profile['id'], $read['id']);

        $audit = $this->em()->getRepository(AuditLog::class)->findOneBy(['action' => 'professional.profile_created']);
        self::assertNotNull($audit);
        self::assertSame($profile['id'], $audit->getSubjectId());
    }

    public function testTheProfileIsValidated(): void
    {
        $token = $this->tokenFor(Factory::client($this->em()));

        $this->submitProfile($token, ['services' => [['serviceCode' => 'tiling', 'travelFeeMillimes' => 1]]]);
        self::assertSame(422, $this->httpStatus(), 'Service inconnu.');
        $this->submitProfile($token, ['serviceAreaCodes' => ['sfax']]);
        self::assertSame(422, $this->httpStatus(), 'Zone inconnue.');
        $this->submitProfile($token, ['services' => []]);
        self::assertSame(422, $this->httpStatus(), 'Au moins un métier.');
        $this->submitProfile($token, ['services' => [
            ['serviceCode' => 'plumbing', 'travelFeeMillimes' => 1],
            ['serviceCode' => 'plumbing', 'travelFeeMillimes' => 2],
        ]]);
        self::assertSame(422, $this->httpStatus(), 'Service en double.');
        $this->submitProfile($token, ['services' => [['serviceCode' => 'plumbing', 'travelFeeMillimes' => -1]]]);
        self::assertSame(422, $this->httpStatus(), 'Frais négatifs.');
        $this->submitProfile($token, ['legalStatus' => 'association']);
        self::assertSame(422, $this->httpStatus());
        $this->submitProfile($token, ['baseLatitude' => null]);
        self::assertSame(422, $this->httpStatus(), 'Point de départ obligatoire.');

        self::assertNull($this->em()->getRepository(ProfessionalProfile::class)->findOneBy([]), 'Rien n\'a été créé.');

        $this->api('POST', '/api/professional/profile', $this->profilePayload());
        self::assertSame(401, $this->httpStatus());
        $this->api('GET', '/api/professional/profile', token: $token);
        self::assertSame(403, $this->httpStatus(), 'Un client sans profil n\'a pas accès aux endpoints professionnels.');
    }

    public function testUpdatingReplacesServicesAndAreasAndControlsTheVerificationStatus(): void
    {
        [$user, $token] = $this->onboardedProfessional();
        $profile = $this->profileOf($user);
        $profile->setVerificationStatus(VerificationStatus::Verified, 'Contrôlé', new \DateTimeImmutable());
        $this->em()->flush();

        $updated = $this->submitProfile($token, [
            'services' => [['serviceCode' => 'electricity', 'travelFeeMillimes' => 25_000]],
            'serviceAreaCodes' => ['carthage'],
        ]);
        self::assertSame(200, $this->httpStatus());
        self::assertSame('verified', $updated['verificationStatus'], 'Métiers, zones et tarifs ne remettent pas en cause la vérification.');
        self::assertSame(['electricity'], array_column($updated['services'], 'serviceCode'));
        self::assertSame(['carthage'], array_column($updated['serviceAreas'], 'code'));

        $updated = $this->submitProfile($token, ['services' => [['serviceCode' => 'electricity', 'travelFeeMillimes' => 30_000]]]);
        self::assertSame(30_000, $updated['services'][0]['travelFeeMillimes'], 'Tarif mis à jour en place (contrainte d\'unicité).');

        $renamed = $this->submitProfile($token, ['displayName' => 'Sami Ben Ali']);
        self::assertSame('pending', $renamed['verificationStatus'], 'Le nom affiché est un élément contrôlé : retour en vérification.');

        $profile = $this->profileOf($user);
        $profile->setVerificationStatus(VerificationStatus::Rejected, 'Pièce illisible', new \DateTimeImmutable());
        $this->em()->flush();
        self::assertSame('pending', $this->submitProfile($token)['verificationStatus'], 'Un profil rejeté et corrigé repart en vérification.');

        $profile = $this->profileOf($user);
        $profile->setVerificationStatus(VerificationStatus::Suspended, 'Fraude', new \DateTimeImmutable());
        $this->em()->flush();
        $this->submitProfile($token);
        self::assertSame(409, $this->httpStatus(), 'Seule l\'administration réactive un profil suspendu (§7.2).');
    }

    public function testAProfessionalUploadsADocumentKeptInPrivateStorage(): void
    {
        [$user, $token] = $this->onboardedProfessional();
        $pdf = $this->samplePdf();

        $document = $this->uploadDocument($token, 'identity', $pdf, ['expiresAt' => '2031-05-01']);

        self::assertSame(201, $this->httpStatus());
        self::assertSame('identity', $document['type']);
        self::assertSame('pending', $document['status']);
        self::assertSame('application/pdf', $document['mimeType']);
        self::assertSame(filesize($pdf), $document['sizeBytes']);
        self::assertSame('2031-05-01', $document['expiresAt']);
        self::assertSame(\sprintf('/api/professional/documents/%s/file', $document['id']), $document['fileUrl']);
        self::assertArrayNotHasKey('storageKey', $document, 'L\'emplacement du fichier n\'est jamais exposé.');

        $stored = $this->em()->find(ProfessionalDocument::class, $document['id']);
        self::assertStringNotContainsString(basename($pdf), $stored->getStorageKey(), 'Le nom d\'origine n\'est pas réutilisé.');
        $path = $this->documentStorageDir().'/'.$stored->getStorageKey();
        self::assertFileEquals($pdf, $path);
        self::assertStringNotContainsString('public', str_replace('\\', '/', realpath($path)), 'Rien sous public/.');

        $profile = $this->api('GET', '/api/professional/profile', token: $token);
        self::assertSame([$document['id']], array_column($profile['documents'], 'id'));

        [$status, $content] = $this->downloadDocument($document['id'], $token);
        self::assertSame(200, $status);
        self::assertSame(file_get_contents($pdf), $content);
        $headers = $this->client->getResponse()->headers;
        self::assertSame('application/pdf', $headers->get('Content-Type'));
        self::assertStringStartsWith('attachment;', (string) $headers->get('Content-Disposition'));
        self::assertSame('nosniff', $headers->get('X-Content-Type-Options'));
        self::assertStringContainsString('no-store', (string) $headers->get('Cache-Control'));

        self::assertNotNull($this->em()->getRepository(AuditLog::class)->findOneBy(['action' => 'professional.document_uploaded']));
    }

    public function testNobodyButTheOwnerAndAdminsCanReadADocument(): void
    {
        [, $token] = $this->onboardedProfessional();
        $document = $this->uploadDocument($token, 'qualification', $this->samplePng());
        self::assertSame(201, $this->httpStatus());
        self::assertSame('image/png', $document['mimeType']);

        [, $otherToken] = $this->onboardedProfessional();
        self::assertSame(403, $this->downloadDocument($document['id'], $otherToken)[0], 'Jamais un autre professionnel (§16.6).');
        self::assertSame(403, $this->downloadDocument($document['id'], $this->tokenFor(Factory::client($this->em())))[0]);
        self::assertSame(403, $this->downloadDocument($document['id'], $this->tokenFor(Factory::user($this->em(), UserRole::Support)))[0], 'Données sensibles masquées au support (§4.4).');
        self::assertSame(401, $this->downloadDocument($document['id'], null)[0]);
        self::assertSame(404, $this->downloadDocument('0192f0c4-0000-7000-8000-000000000000', $token)[0]);

        $admin = Factory::user($this->em(), UserRole::Admin);
        self::assertSame(200, $this->downloadDocument($document['id'], $this->tokenFor($admin))[0]);
        $viewed = $this->em()->getRepository(AuditLog::class)->findOneBy(['action' => 'professional.document_viewed']);
        self::assertNotNull($viewed, 'Les consultations de l\'administration sont tracées.');
        self::assertSame($admin->getUserIdentifier(), $viewed->getActorId());
    }

    public function testUploadsAreRestrictedByTypeSizeAndExpiry(): void
    {
        [$user, $token] = $this->onboardedProfessional();

        $this->uploadDocument($token, 'identity', $this->sampleFile('pdf', "Ceci n'est pas un PDF.\n"));
        self::assertSame(422, $this->httpStatus(), 'Le type est vérifié sur le contenu, pas sur l\'extension.');
        $this->uploadDocument($token, 'identity', $this->sampleFile('pdf', '%PDF-1.4'.str_repeat(' ', 10 * 1024 * 1024)));
        self::assertSame(422, $this->httpStatus(), 'Au-delà de 10 Mio.');
        $this->uploadDocument($token, 'passport', $this->samplePdf());
        self::assertSame(422, $this->httpStatus(), 'Type de justificatif inconnu.');
        $this->uploadDocument($token, 'insurance', $this->samplePdf(), ['expiresAt' => '2020-01-01']);
        self::assertSame(422, $this->httpStatus(), 'Justificatif déjà expiré.');
        $this->uploadDocument($token, 'insurance', $this->samplePdf(), ['expiresAt' => 'demain']);
        self::assertSame(422, $this->httpStatus());
        $this->uploadDocument($token, 'identity', null);
        self::assertSame(422, $this->httpStatus(), 'Fichier manquant.');

        self::assertCount(0, $this->profileOf($user)->getDocuments());
        self::assertDirectoryDoesNotExist($this->documentStorageDir().'/professionals', 'Aucun fichier refusé n\'est conservé.');

        $this->uploadDocument($this->tokenFor(Factory::client($this->em())), 'identity', $this->samplePdf());
        self::assertSame(403, $this->httpStatus(), 'Profil professionnel requis.');
    }
}
