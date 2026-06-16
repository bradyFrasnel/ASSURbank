<?php

namespace App\EventSubscriber;

use App\Event\DepotEffectueEvent;
use App\Event\RetraitEffectueEvent;
use App\Event\VirementEffectueEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber qui met à jour les statistiques de transactions
 * 
 * Découplage : la logique métier (Services) ne connaît pas l'existence de ce subscriber
 * Les événements permettent d'ajouter cette fonctionnalité sans modifier les Services
 */
class TransactionStatistiquesSubscriber implements EventSubscriberInterface
{
    // Simulé : en prod, ça serait une vraie classe avec persistence en DB
    private array $stats = [
        'virements_total' => 0,
        'virements_montant_total' => 0.0,
        'depots_total' => 0,
        'depots_montant_total' => 0.0,
        'retraits_total' => 0,
        'retraits_montant_total' => 0.0,
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            VirementEffectueEvent::NAME => ['onVirementEffectue', 50],   // Après logger (100)
            DepotEffectueEvent::NAME => ['onDepotEffectue', 50],
            RetraitEffectueEvent::NAME => ['onRetraitEffectue', 50],
        ];
    }

    /**
     * Mettre à jour les stats lors d'un virement
     */
    public function onVirementEffectue(VirementEffectueEvent $event): void
    {
        $this->stats['virements_total']++;
        $this->stats['virements_montant_total'] += $event->getMontant();
    }

    /**
     * Mettre à jour les stats lors d'un dépôt
     */
    public function onDepotEffectue(DepotEffectueEvent $event): void
    {
        $this->stats['depots_total']++;
        $this->stats['depots_montant_total'] += $event->getMontant();
    }

    /**
     * Mettre à jour les stats lors d'un retrait
     */
    public function onRetraitEffectue(RetraitEffectueEvent $event): void
    {
        $this->stats['retraits_total']++;
        $this->stats['retraits_montant_total'] += $event->getMontant();
    }

    /**
     * Récupérer les statistiques actuelles
     */
    public function getStatistiques(): array
    {
        return $this->stats;
    }
}
