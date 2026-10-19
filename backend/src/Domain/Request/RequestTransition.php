<?php

namespace App\Domain\Request;

/** Noms des transitions du workflow `service_request` (config/packages/workflow.yaml). */
enum RequestTransition: string
{
    case Submit = 'submit';
    case StartSearch = 'start_search';
    case ReceiveOffer = 'receive_offer';
    case ReopenSearch = 'reopen_search';
    case AcceptOffer = 'accept_offer';
    case StartWork = 'start_work';
    case Complete = 'complete';
    case Dispute = 'dispute';
    case ResolveDispute = 'resolve_dispute';
    case Cancel = 'cancel';
    case Expire = 'expire';
}
