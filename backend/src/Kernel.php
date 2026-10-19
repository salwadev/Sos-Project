<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        // Dates enregistrées en UTC côté serveur, affichées en heure tunisienne côté client (§8.6).
        date_default_timezone_set('UTC');

        parent::boot();
    }
}
