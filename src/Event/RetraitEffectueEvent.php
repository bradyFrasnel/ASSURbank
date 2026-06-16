<?php

namespace App\Event;

use App\Entity\Transaction;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Événement déclenché quand un retrait est effectué avec succès
 */
class RetraitEffectueEvent extends Event
{
    public const NAME = 'retrait.effectue';

    public function __construct(
        private readonly Transaction $transaction,
    ) {
    }

    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    public function getMontant(): float
    {
        return $this->transaction->getMontant();
    }
}
