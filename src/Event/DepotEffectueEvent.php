<?php

namespace App\Event;

use App\Entity\Transaction;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Événement déclenché quand un dépôt est effectué avec succès
 */
class DepotEffectueEvent extends Event
{
    public const NAME = 'depot.effectue';

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
