<?php

namespace App\ApiResource\Intervention;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Corps de POST /api/interventions/{id}/quotes (§9.2) : lignes en millimes et explication.
 * L'explication est obligatoire dès la deuxième version (modification de prix après diagnostic, §6.6).
 */
final class ProposeQuoteInput
{
    /** @var list<array{label: string, amountMillimes: int}> */
    #[Assert\Count(min: 1, max: 20)]
    #[Assert\All([
        new Assert\Collection(fields: [
            'label' => [new Assert\NotBlank(), new Assert\Type('string'), new Assert\Length(max: 120)],
            'amountMillimes' => [new Assert\NotNull(), new Assert\Type('int'), new Assert\Range(min: 0, max: 100_000_000)],
        ]),
    ])]
    public array $lines = [];

    #[Assert\Length(max: 1000)]
    public ?string $explanation = null;
}
