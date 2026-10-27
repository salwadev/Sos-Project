<?php

namespace App\Intervention;

use App\Audit\AuditLogger;
use App\Domain\Clock;
use App\Domain\Request\RequestTransition;
use App\Domain\Request\ServiceRequestStateMachine;
use App\Entity\Commission;
use App\Entity\Complaint;
use App\Entity\Intervention;
use App\Entity\Payment;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Enum\InterventionStatus;
use App\Enum\PaymentMethod;
use App\Enum\PaymentStatus;
use App\Enum\ServiceRequestStatus;
use App\Matching\RequestLock;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Déroulé d'une intervention après la confirmation du client (§5.6, §5.7, §6.6) :
 * - le professionnel déclare « en route », « sur place », « travaux commencés » (ACCEPTED → IN_PROGRESS) puis la fin ;
 * - le client confirme la fin (IN_PROGRESS → COMPLETED, commission calculée une seule fois, §16.1) ou conteste ;
 * - le paiement en espèces est alors déclaré, puis le professionnel confirme l'avoir reçu.
 *
 * Comme pour les offres, chaque décision passe par RequestLock et revérifie l'état relu en base :
 * deux confirmations simultanées ne peuvent pas créer deux commissions.
 */
final class InterventionLifecycle
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestLock $lock,
        private readonly ServiceRequestStateMachine $stateMachine,
        private readonly CommissionPolicy $commissions,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Étape déclarée par le professionnel assigné. On n'avance que vers une étape ultérieure ; « en route » et
     * « sur place » peuvent être omises. Les travaux ne commencent qu'avec un devis accepté et aucun en attente
     * (§5.5, §6.6) ; la fin ne peut être déclarée qu'après le début des travaux.
     */
    public function advance(Intervention $intervention, InterventionStatus $target, User $professional, ?int $estimatedArrivalMinutes = null): Intervention
    {
        $from = $intervention->getStatus();
        $this->lock->run($intervention->getRequest(), function (ServiceRequest $request) use ($intervention, $target, $professional, $estimatedArrivalMinutes, &$from): void {
            $this->reload($intervention);
            self::assertInProgress($request);
            $from = $intervention->getStatus();
            $now = Clock::now();

            $rank = $target->executionRank();
            $currentRank = $from->executionRank();
            if (null === $rank || null === $currentRank || $rank <= $currentRank) {
                throw new InterventionConflict(\sprintf('Étape « %s » impossible : l\'intervention est à l\'étape « %s ».', $target->value, $from->value));
            }
            if (InterventionStatus::CompletionDeclared === $target && InterventionStatus::Working !== $from) {
                throw new InterventionConflict('La fin ne peut être déclarée qu\'après le début des travaux.');
            }
            if ($rank >= InterventionStatus::Working->executionRank()) {
                if (null !== $intervention->getPendingQuote()) {
                    throw new InterventionConflict('Un devis attend l\'accord du client : aucun travail supplémentaire avant sa décision.');
                }
                if (null === $intervention->getAcceptedQuote()) {
                    throw new InterventionConflict('Le client doit d\'abord accepter un devis avant le début des travaux.');
                }
            }

            if (InterventionStatus::OnTheWay === $target && null !== $estimatedArrivalMinutes) {
                $intervention->setEstimatedArrivalAt($now->modify(\sprintf('+%d minutes', $estimatedArrivalMinutes)));
            }
            if (ServiceRequestStatus::Accepted === $request->getStatusEnum() && $rank >= InterventionStatus::Working->executionRank()) {
                $this->stateMachine->apply($request, RequestTransition::StartWork, $professional);
            }
            $intervention->setStatus($target, $now);
        });

        $this->audit->record('intervention.status_changed', $professional, 'Intervention', $intervention->getId()->toRfc4122(), [
            'request' => $intervention->getRequest()->getReference(),
            'from' => $from->value,
            'to' => $target->value,
        ]);

        return $intervention;
    }

    /** Le client confirme la fin des travaux : la demande est close et la commission calculée, une seule fois. */
    public function confirmCompletion(Intervention $intervention, User $client): Commission
    {
        $commission = $this->lock->run($intervention->getRequest(), function (ServiceRequest $request) use ($intervention, $client): Commission {
            $this->reload($intervention);
            if (InterventionStatus::Completed === $intervention->getStatus()) {
                throw new InterventionConflict('La fin de cette intervention a déjà été confirmée.');
            }
            self::assertInProgress($request);
            if (InterventionStatus::CompletionDeclared !== $intervention->getStatus()) {
                throw new InterventionConflict('Le professionnel n\'a pas encore déclaré la fin de l\'intervention.');
            }
            if (null !== $this->em->getRepository(Commission::class)->findOneBy(['intervention' => $intervention])) {
                throw new InterventionConflict('La commission de cette intervention a déjà été calculée.');
            }

            $this->stateMachine->apply($request, RequestTransition::Complete, $client);
            $intervention->setStatus(InterventionStatus::Completed, Clock::now());
            $commission = $this->commissions->commissionFor($intervention);
            $this->em->persist($commission);
            // Paiement direct en espèces (§9.3) : déclaré pour le total du devis accepté, confirmé ensuite par le pro.
            $this->em->persist(new Payment($intervention, PaymentMethod::CashDirect, $commission->getBaseMillimes()));

            return $commission;
        });

        $this->audit->record('intervention.completed', $client, 'Intervention', $intervention->getId()->toRfc4122(), [
            'request' => $intervention->getRequest()->getReference(),
            'quoteVersion' => $intervention->getAcceptedQuote()?->getVersion(),
            'baseMillimes' => $commission->getBaseMillimes(),
            'rateBasisPoints' => $commission->getRateBasisPoints(),
            'commissionMillimes' => $commission->getAmountMillimes(),
        ]);

        return $commission;
    }

    /**
     * Le professionnel assigné confirme avoir reçu le paiement en espèces déclaré à la clôture (§9.3, §9.6).
     * Possible même si le client conteste ensuite : la remise de l'argent reste un fait à tracer.
     */
    public function confirmCashReceived(Intervention $intervention, User $professional): Payment
    {
        $payment = $this->lock->run($intervention->getRequest(), function () use ($intervention): Payment {
            $payment = $this->em->getRepository(Payment::class)->findOneBy(['intervention' => $intervention]);
            if (null === $payment) {
                throw new InterventionConflict('Le client n\'a pas encore confirmé la fin de l\'intervention.');
            }
            $this->em->refresh($payment);
            if (PaymentMethod::CashDirect !== $payment->getMethod()) {
                throw new InterventionConflict('Ce paiement n\'est pas un paiement en espèces.');
            }
            if (PaymentStatus::Declared !== $payment->getStatus()) {
                throw new InterventionConflict('Ce paiement a déjà été confirmé.');
            }
            $payment->setStatus(PaymentStatus::Confirmed);

            return $payment;
        });

        $this->audit->record('payment.cash_confirmed', $professional, 'Payment', $payment->getId()->toRfc4122(), [
            'request' => $intervention->getRequest()->getReference(),
            'grossMillimes' => $payment->getGrossMillimes(),
        ]);

        return $payment;
    }

    /**
     * Le client conteste, pendant les travaux ou après la clôture (§5.7, §9.5) : la demande passe DISPUTED avec
     * une trace horodatée (transition + réclamation ouverte pour le support, §7.4). Une commission déjà calculée
     * est conservée telle quelle jusqu'à la résolution.
     */
    public function dispute(Intervention $intervention, User $client, string $reason): Complaint
    {
        $complaint = $this->lock->run($intervention->getRequest(), function (ServiceRequest $request) use ($intervention, $client, $reason): Complaint {
            $this->reload($intervention);
            $this->stateMachine->apply($request, RequestTransition::Dispute, $client, mb_substr($reason, 0, 255));
            $intervention->setStatus(InterventionStatus::Disputed, Clock::now());
            $complaint = new Complaint($request, $client, 'intervention_dispute', $reason);
            $this->em->persist($complaint);

            return $complaint;
        });

        $this->audit->record('intervention.disputed', $client, 'Intervention', $intervention->getId()->toRfc4122(), [
            'request' => $intervention->getRequest()->getReference(),
            'complaint' => $complaint->getId()->toRfc4122(),
        ]);

        return $complaint;
    }

    /** Relit l'intervention et ses devis sous le verrou de la demande : les décisions portent sur l'état en base. */
    public function reload(Intervention $intervention): void
    {
        $this->em->refresh($intervention);
        foreach ($intervention->getQuotes() as $quote) {
            $this->em->refresh($quote);
        }
    }

    /** Demande en cours d'exécution (ACCEPTED ou IN_PROGRESS) ; sinon 409 explicite. */
    public static function assertInProgress(ServiceRequest $request): void
    {
        $message = match ($request->getStatusEnum()) {
            ServiceRequestStatus::Accepted, ServiceRequestStatus::InProgress => null,
            ServiceRequestStatus::Cancelled => 'Cette demande a été annulée.',
            ServiceRequestStatus::Completed => 'Cette intervention est terminée.',
            ServiceRequestStatus::Disputed => 'Cette intervention fait l\'objet d\'une contestation.',
            default => \sprintf('Action impossible : la demande est à l\'état « %s ».', $request->getStatus()),
        };
        if (null !== $message) {
            throw new InterventionConflict($message);
        }
    }
}
