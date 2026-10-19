<?php

namespace App\State\Intervention;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\ApiResource\Intervention\InterventionView;
use App\ApiResource\Intervention\ProposeQuoteInput;
use App\Entity\Intervention;
use App\Intervention\QuoteNegotiation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * POST /api/interventions/{id}/quotes (§6.6, §9.2) : le professionnel assigné propose une nouvelle version de devis,
 * qui attend l'accord explicite du client (POST /api/quotes/{id}/accept|reject).
 *
 * @implements ProcessorInterface<ProposeQuoteInput, InterventionView>
 */
final class ProposeQuoteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly QuoteNegotiation $quotes,
        private readonly InterventionViews $views,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): InterventionView
    {
        \assert($data instanceof ProposeQuoteInput);
        $intervention = $this->em->find(Intervention::class, $uriVariables['id'] ?? null)
            ?? throw new NotFoundHttpException();

        $explanation = null === $data->explanation ? null : trim($data->explanation);
        $lines = array_map(
            static fn (array $line) => ['label' => trim($line['label']), 'amountMillimes' => $line['amountMillimes']],
            array_values($data->lines),
        );
        try {
            $this->quotes->propose($intervention, $this->views->currentUser(), $lines, '' === $explanation ? null : $explanation);
        } catch (\InvalidArgumentException $e) {
            throw new UnprocessableEntityHttpException($e->getMessage(), $e);
        }

        return $this->views->for($intervention);
    }
}
