<?php

namespace App\Sms;

/** Envoi de SMS (codes OTP, repli des notifications §19). Le fournisseur tunisien reste à choisir (§20). */
interface SmsSender
{
    public function send(string $phoneE164, string $message): void;
}
