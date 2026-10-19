<?php

namespace App\Tests\Support;

use App\Domain\GeoPoint;
use App\Entity\Availability;
use App\Entity\ClientProfile;
use App\Entity\Intervention;
use App\Entity\ProfessionalProfile;
use App\Entity\ProfessionalService;
use App\Entity\RequestOffer;
use App\Entity\Service;
use App\Entity\ServiceArea;
use App\Entity\ServiceCategory;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Enum\Urgency;
use App\Enum\UserRole;
use App\Enum\VerificationStatus;
use Doctrine\ORM\EntityManagerInterface;

/** Construction d'objets métier pour les tests (persistés seulement si un EntityManager est fourni). */
final class Factory
{
    private static int $phoneSeq = 0;

    public static function phone(): string
    {
        return \sprintf('+2162%07d', ++self::$phoneSeq);
    }

    public static function client(?EntityManagerInterface $em = null, ?string $phone = null): User
    {
        $user = new User($phone ?? self::phone(), UserRole::Client);
        new ClientProfile($user);

        return self::save($em, $user);
    }

    public static function user(?EntityManagerInterface $em, UserRole ...$roles): User
    {
        return self::save($em, new User(self::phone(), ...$roles));
    }

    public static function professional(?EntityManagerInterface $em = null): ProfessionalProfile
    {
        $user = new User(self::phone(), UserRole::Professional);
        $profile = new ProfessionalProfile($user, 'Sami B.');
        self::save($em, $user);

        return self::save($em, $profile);
    }

    /**
     * Professionnel éligible (§8.1) : vérifié, disponible 2 h, couvrant la zone et proposant le service.
     * Chaque test retire ensuite le critère qu'il veut éprouver.
     *
     * @param array{float, float} $base [latitude, longitude] du point de départ habituel
     */
    public static function availableProfessional(
        EntityManagerInterface $em,
        Service $service,
        ServiceArea $area,
        array $base = [36.885, 10.33],
        int $travelFeeMillimes = 20_000,
        string $name = 'Sami B.',
    ): ProfessionalProfile {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $user = new User(self::phone(), UserRole::Professional);
        $profile = new ProfessionalProfile($user, $name);
        $profile->setVerificationStatus(VerificationStatus::Verified, null, $now);
        $profile->setBaseLocation(GeoPoint::toEwkt(...$base));
        $profile->addServiceArea($area);
        new ProfessionalService($profile, $service, $travelFeeMillimes);
        (new Availability($profile))->goAvailable($now, $now->modify('+2 hours'));
        $em->persist($user);

        return self::save($em, $profile);
    }

    public static function category(?EntityManagerInterface $em = null, string $code = 'home', bool $sensitive = false): ServiceCategory
    {
        return self::save($em, new ServiceCategory($code, ucfirst($code), $code, 0, $sensitive));
    }

    public static function service(?EntityManagerInterface $em = null, ?ServiceCategory $category = null, string $code = 'plumbing'): Service
    {
        return self::save($em, new Service($category ?? self::category($em), $code, ucfirst($code), $code));
    }

    /** Zone rectangulaire autour de La Marsa (lon 10.30–10.36, lat 36.86–36.91). */
    public static function laMarsa(?EntityManagerInterface $em = null, bool $active = true): ServiceArea
    {
        return self::area($em, 'la_marsa', [10.30, 36.86, 10.36, 36.91], $active);
    }

    /** @param array{float, float, float, float} $box [lonMin, latMin, lonMax, latMax] */
    public static function area(?EntityManagerInterface $em, string $code, array $box, bool $active = true): ServiceArea
    {
        [$lonMin, $latMin, $lonMax, $latMax] = $box;
        $area = new ServiceArea($code, ucfirst($code), \sprintf(
            'SRID=4326;MULTIPOLYGON(((%1$F %2$F,%3$F %2$F,%3$F %4$F,%1$F %4$F,%1$F %2$F)))',
            $lonMin, $latMin, $lonMax, $latMax,
        ));
        $area->setActive($active);

        return self::save($em, $area);
    }

    public static function request(?EntityManagerInterface $em = null, ?User $client = null, ?Service $service = null): ServiceRequest
    {
        $request = new ServiceRequest(
            $client ?? self::client($em),
            $service ?? self::service($em),
            Urgency::Asap,
            'Une fuite sous l’évier de la cuisine.',
            'Rue des Orangers, La Marsa',
        );

        return self::save($em, $request);
    }

    public static function intervention(): Intervention
    {
        $request = self::request();
        $now = new \DateTimeImmutable('2026-10-19 10:00:00', new \DateTimeZone('UTC'));
        $offer = new RequestOffer($request, self::professional(), 1, 20_000, $now, $now->modify('+42 seconds'));

        return new Intervention($request, $offer);
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private static function save(?EntityManagerInterface $em, object $entity): object
    {
        if (null !== $em) {
            $em->persist($entity);
            $em->flush();
        }

        return $entity;
    }
}
