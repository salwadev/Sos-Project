<?php

namespace App\Command;

use App\Entity\Service;
use App\Entity\ServiceArea;
use App\Entity\ServiceCategory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Charge le catalogue de lancement (§3.1) et des zones du Grand Tunis.
 * Idempotent : relançable sans doublon (correspondance par code).
 * Les contours de zones sont des rectangles approximatifs, à remplacer par des limites officielles.
 */
#[AsCommand(name: 'app:seed-catalog', description: 'Charge les catégories, services et zones de démonstration')]
final class SeedCatalogCommand extends Command
{
    /** code => [fr, ar, sensible, services[code => [fr, ar, qualification requise]]] */
    private const CATALOG = [
        'home' => ['Maison', 'المنزل', false, [
            'plumbing' => ['Plomberie', 'سباكة', false],
            'electricity' => ['Électricité', 'كهرباء', true],
            'locksmith' => ['Serrurerie', 'أقفال', false],
            'glazing' => ['Vitrerie', 'بلّور', false],
            'air_conditioning' => ['Climatisation', 'تكييف', false],
            'appliances' => ['Équipements domestiques', 'أجهزة منزلية', false],
            'housekeeping' => ['Ménage à domicile', 'تنظيف المنزل', false],
        ]],
        'car' => ['Automobile', 'السيارة', false, [
            'battery' => ['Batterie', 'بطارية', false],
            'tyre' => ['Pneu', 'عجلات', false],
            'roadside' => ['Panne sur place', 'عطب في المكان', false],
            'towing' => ['Remorquage', 'جرّ السيارات', false],
        ]],
        'health' => ['Orientation santé', 'التوجيه الصحي', true, [
            'on_call_dentist' => ['Dentiste de garde', 'طبيب أسنان مناوب', true],
            'doctor' => ['Médecin', 'طبيب', true],
        ]],
        'other' => ['Autres', 'أخرى', false, [
            'home_it_support' => ['Assistance technique à domicile', 'مساعدة تقنية في المنزل', false],
        ]],
    ];

    /** code => [nom, [lonMin, latMin, lonMax, latMax], active] — pilote : La Marsa et Carthage (§17.2). */
    private const AREAS = [
        'la_marsa' => ['La Marsa', [10.30, 36.86, 10.36, 36.91], true],
        'carthage' => ['Carthage', [10.31, 36.84, 10.35, 36.86], true],
        'ariana' => ['Ariana', [10.15, 36.85, 10.22, 36.90], false],
        'tunis_centre' => ['Tunis Centre', [10.15, 36.78, 10.21, 36.82], false],
        'le_bardo' => ['Le Bardo', [10.11, 36.80, 10.15, 36.82], false],
        'ben_arous' => ['Ben Arous', [10.20, 36.73, 10.26, 36.77], false],
    ];

    public function __construct(private readonly EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $categories = $this->em->getRepository(ServiceCategory::class);
        $services = $this->em->getRepository(Service::class);
        $areas = $this->em->getRepository(ServiceArea::class);
        $created = 0;

        $position = 0;
        foreach (self::CATALOG as $code => [$fr, $ar, $sensitive, $items]) {
            $category = $categories->findOneBy(['code' => $code]);
            if (null === $category) {
                $category = new ServiceCategory($code, $fr, $ar, $position, $sensitive);
                $this->em->persist($category);
                ++$created;
            }
            ++$position;

            foreach ($items as $serviceCode => [$serviceFr, $serviceAr, $qualification]) {
                if (null === $services->findOneBy(['code' => $serviceCode])) {
                    $service = new Service($category, $serviceCode, $serviceFr, $serviceAr);
                    $service->setRequiresQualification($qualification);
                    $service->setDefaultTravelFeeMillimes('car' === $code ? 30_000 : 20_000);
                    $this->em->persist($service);
                    ++$created;
                }
            }
        }

        foreach (self::AREAS as $code => [$name, [$lonMin, $latMin, $lonMax, $latMax], $active]) {
            if (null === $areas->findOneBy(['code' => $code])) {
                $boundary = \sprintf(
                    'SRID=4326;MULTIPOLYGON(((%1$F %2$F,%3$F %2$F,%3$F %4$F,%1$F %4$F,%1$F %2$F)))',
                    $lonMin, $latMin, $lonMax, $latMax,
                );
                $area = new ServiceArea($code, $name, $boundary);
                $area->setActive($active);
                $this->em->persist($area);
                ++$created;
            }
        }

        $this->em->flush();
        $io->success(\sprintf('Catalogue à jour (%d éléments créés).', $created));

        return Command::SUCCESS;
    }
}
