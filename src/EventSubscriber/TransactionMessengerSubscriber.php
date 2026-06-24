<?php

namespace App\EventSubscriber;

use App\Event\DepotEffectueEvent;
use App\Event\RetraitEffectueEvent;
use App\Event\VirementEffectueEvent;
use App\Message\TransactionNotificationMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class TransactionMessengerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            VirementEffectueEvent::NAME => 'onTransaction',
            DepotEffectueEvent::NAME => 'onTransaction',
            RetraitEffectueEvent::NAME => 'onTransaction',
        ];
    }

    public function onTransaction(object $event): void
    {
        $transaction = null;

        if ($event instanceof VirementEffectueEvent) {
            $transaction = $event->getTransactionDebit();
        } elseif ($event instanceof DepotEffectueEvent || $event instanceof RetraitEffectueEvent) {
            $transaction = $event->getTransaction();
        }

        if ($transaction && $transaction->getId()) {
            $this->bus->dispatch(new TransactionNotificationMessage($transaction->getId()));
        }
    }
}
