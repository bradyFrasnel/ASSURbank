<?php

namespace App\EventSubscriber;

use App\Event\DepotEffectueEvent;
use App\Event\RetraitEffectueEvent;
use App\Event\VirementEffectueEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber qui enregistre les transactions en logs
 * 
 * Démonstration : quand un événement est déclenché, ce subscriber écoute et enregistre
 */
class TransactionLoggerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            VirementEffectueEvent::NAME => ['onVirementEffectue', 100],  // Priorité haute
            DepotEffectueEvent::NAME => ['onDepotEffectue', 100],
            RetraitEffectueEvent::NAME => ['onRetraitEffectue', 100],
        ];
    }

    /**
     * Appelé quand un virement est effectué
     */
    public function onVirementEffectue(VirementEffectueEvent $event): void
    {
        $debit = $event->getTransactionDebit();
        $montant = $event->getMontant();

        $this->logger->info('Virement effectué', [
            'montant' => $montant,
            'compte_source_id' => $debit->getCompteSource()?->getId(),
            'compte_destination_id' => $debit->getCompteDestination()?->getId(),
            'libelle' => $debit->getLibelle(),
        ]);
    }

    /**
     * Appelé quand un dépôt est effectué
     */
    public function onDepotEffectue(DepotEffectueEvent $event): void
    {
        $transaction = $event->getTransaction();
        $montant = $event->getMontant();

        $this->logger->info('Dépôt effectué', [
            'montant' => $montant,
            'compte_id' => $transaction->getCompteSource()?->getId(),
            'libelle' => $transaction->getLibelle(),
        ]);
    }

    /**
     * Appelé quand un retrait est effectué
     */
    public function onRetraitEffectue(RetraitEffectueEvent $event): void
    {
        $transaction = $event->getTransaction();
        $montant = $event->getMontant();

        $this->logger->info('Retrait effectué', [
            'montant' => $montant,
            'compte_id' => $transaction->getCompteSource()?->getId(),
            'libelle' => $transaction->getLibelle(),
        ]);
    }
}
