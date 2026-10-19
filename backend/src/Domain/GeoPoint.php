<?php

namespace App\Domain;

/** Conversion latitude/longitude ↔ EWKT pour les colonnes PostGIS `geography`. */
final class GeoPoint
{
    public const SRID = 4326;

    public static function toEwkt(float $latitude, float $longitude): string
    {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException('Coordonnées hors limites.');
        }

        // PostGIS attend l'ordre longitude, latitude.
        return \sprintf('SRID=%d;POINT(%F %F)', self::SRID, $longitude, $latitude);
    }

    /** @return array{float, float}|null [latitude, longitude] d'un POINT EWKT, ou null s'il n'y en a pas */
    public static function fromEwkt(?string $ewkt): ?array
    {
        if (null === $ewkt || !preg_match('/POINT\s*\(\s*(-?[\d.]+)\s+(-?[\d.]+)\s*\)/i', $ewkt, $m)) {
            return null;
        }

        return [(float) $m[2], (float) $m[1]];
    }
}
