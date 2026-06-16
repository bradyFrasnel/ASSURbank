<?php

namespace App\Event;

use App\Entity\Transaction;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Événement déclenché quand un virement est effectué avec succès
 */
class VirementEffectueEvent extends Event
{
    public const NAME = 'virement.effectue';

    public function __construct(
        private readonly Transaction $transactionDebit,
        private readonly Transaction $transactionCredit,
    ) {
    }

    public function getTransactionDebit(): Transaction
    {
        return $this->transactionDebit;
    }

    public function getTransactionCredit(): Transaction
    {
        return $this->transactionCredit;
    }

    public function getMontant(): float
    {
        return $this->transactionDebit->getMontant();
    }
}
