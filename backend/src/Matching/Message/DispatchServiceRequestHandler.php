<?php

namespace App\Matching\Message;

use App\Entity\ServiceRequest;
use App\Matching\RequestDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class DispatchServiceRequestHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RequestDispatcher $dispatcher,
    ) {
    }

    public function __invoke(DispatchServiceRequest $message): void
    {
        $request = $this->em->find(ServiceRequest::class, $message->requestId);
        if (null === $request) {
            return;
        }
        $this->dispatcher->dispatch($request);
    }
}
