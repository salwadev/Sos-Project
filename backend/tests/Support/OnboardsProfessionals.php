<?php

namespace App\Tests\Support;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/** Inscription professionnelle et dépôt de justificatifs via l'API, avec des fichiers d'exemple jetables. */
trait OnboardsProfessionals
{
    /** PNG 1×1 transparent. */
    private const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    /** @var list<string> */
    private array $sampleFiles = [];

    /** @return array<string, mixed> corps valide de POST /api/professional/profile (service « plumbing », zone « la_marsa ») */
    protected function profilePayload(array $overrides = []): array
    {
        return [
            'displayName' => 'Sami B.',
            'legalStatus' => 'individual',
            'services' => [['serviceCode' => 'plumbing', 'travelFeeMillimes' => 15_000]],
            'serviceAreaCodes' => ['la_marsa'],
            'baseLatitude' => 36.885,
            'baseLongitude' => 10.33,
            ...$overrides,
        ];
    }

    /** @return array<string, mixed>|null */
    protected function submitProfile(string $token, array $overrides = []): ?array
    {
        return $this->api('POST', '/api/professional/profile', $this->profilePayload($overrides), $token);
    }

    /** Petit PDF valide (détecté application/pdf d'après son contenu). */
    protected function samplePdf(): string
    {
        return $this->sampleFile('pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
    }

    protected function samplePng(): string
    {
        return $this->sampleFile('png', (string) base64_decode(self::PNG_BASE64, true));
    }

    protected function sampleFile(string $extension, string $content): string
    {
        $path = \sprintf('%s/sos-doc-%s.%s', sys_get_temp_dir(), bin2hex(random_bytes(6)), $extension);
        file_put_contents($path, $content);
        $this->sampleFiles[] = $path;

        return $path;
    }

    /**
     * POST /api/professional/documents en multipart/form-data.
     *
     * @param array<string, string> $fields champs du formulaire en plus de « type »
     *
     * @return array<string, mixed>|null
     */
    protected function uploadDocument(string $token, string $type, ?string $path, array $fields = []): ?array
    {
        $files = null === $path ? [] : ['file' => new UploadedFile($path, basename($path), null, null, true)];
        $this->browser()->request('POST', '/api/professional/documents', ['type' => $type, ...$fields], $files, [
            'CONTENT_TYPE' => 'multipart/form-data',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ]);
        $content = $this->browser()->getResponse()->getContent();

        return '' === $content || false === $content ? null : json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
    }

    /** GET du fichier d'un justificatif ; renvoie [statut HTTP, contenu]. */
    protected function downloadDocument(string $documentId, ?string $token): array
    {
        $server = ['HTTP_ACCEPT' => '*/*'];
        if (null !== $token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $this->browser()->request('GET', \sprintf('/api/professional/documents/%s/file', $documentId), server: $server);

        return [$this->browser()->getResponse()->getStatusCode(), (string) $this->browser()->getInternalResponse()->getContent()];
    }

    protected function documentStorageDir(): string
    {
        return static::getContainer()->getParameter('app.documents.storage_dir');
    }

    /** À appeler dans tearDown : fichiers d'exemple et stockage de test. */
    protected function removeSampleFiles(): void
    {
        (new Filesystem())->remove([...$this->sampleFiles, $this->documentStorageDir()]);
        $this->sampleFiles = [];
    }

    private function browser(): KernelBrowser
    {
        return $this->client;
    }
}
