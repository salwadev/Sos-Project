<?php

namespace App\Storage;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Stockage sur disque local, hors de public/ (dev et test). Répertoire : app.documents.storage_dir.
 * Les clés sont générées par l'application (jamais le nom du fichier envoyé) et contrôlées ici
 * pour qu'aucune ne sorte du répertoire.
 */
final class LocalDocumentStorage implements DocumentStorage
{
    private const KEY_PATTERN = '~^[a-z0-9][a-z0-9_-]*(/[a-z0-9][a-z0-9_-]*)*$~';

    public function __construct(
        #[Autowire('%app.documents.storage_dir%')]
        private readonly string $root,
    ) {
    }

    public function put(string $key, string $sourcePath): void
    {
        $target = $this->path($key);
        $directory = \dirname($target);
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Répertoire de stockage inaccessible.');
        }
        if (!@copy($sourcePath, $target)) {
            throw new \RuntimeException('Écriture du document impossible.');
        }
        @chmod($target, 0600);
    }

    public function read(string $key)
    {
        $path = $this->path($key);
        $stream = is_file($path) ? @fopen($path, 'r') : false;
        if (false === $stream) {
            throw new \RuntimeException('Document introuvable dans le stockage.');
        }

        return $stream;
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function path(string $key): string
    {
        if (!preg_match(self::KEY_PATTERN, $key)) {
            throw new \InvalidArgumentException('Clé de stockage invalide.');
        }

        return rtrim($this->root, '/\\').'/'.$key;
    }
}
