<?php

namespace App\Storage;

/**
 * Stockage privé des justificatifs et pièces jointes (§11.4, §12.6). Rien n'y est jamais servi directement :
 * la lecture passe par un contrôleur qui vérifie les droits (§16.6). En production, une implémentation
 * « stockage objet » (bucket privé, liens signés à durée courte) remplacera LocalDocumentStorage.
 */
interface DocumentStorage
{
    /** Copie le fichier local $sourcePath sous la clé donnée. */
    public function put(string $key, string $sourcePath): void;

    /**
     * @return resource flux en lecture seule, à fermer par l'appelant
     *
     * @throws \RuntimeException si la clé est inconnue
     */
    public function read(string $key);

    public function delete(string $key): void;
}
