<?php

namespace App\Tests\EventSubscriber;

use App\Entity\Transaction;
use App\Event\DepotEffectueEvent;
use App\Event\RetraitEffectueEvent;
use App\Event\VirementEffectueEvent;
use App\EventSubscriber\TransactionMessengerSubscriber;
use App\Message\TransactionNotificationMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class TransactionMessengerSubscriberTest extends TestCase
{
    public function testOnTransactionDepotDispatchesMessage(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $transaction = $this->createMock(Transaction::class);
        $transaction->method('getId')->willReturn(42);

        $envelope = new Envelope(new TransactionNotificationMessage(42));
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (TransactionNotificationMessage $msg) {
                return $msg->transactionId === 42;
            }))
            ->willReturn($envelope);

        $subscriber = new TransactionMessengerSubscriber($bus);
        $event = new DepotEffectueEvent($transaction);
        $subscriber->onTransaction($event);
    }

    public function testOnTransactionRetraitDispatchesMessage(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $transaction = $this->createMock(Transaction::class);
        $transaction->method('getId')->willReturn(43);

        $envelope = new Envelope(new TransactionNotificationMessage(43));
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (TransactionNotificationMessage $msg) {
                return $msg->transactionId === 43;
            }))
            ->willReturn($envelope);

        $subscriber = new TransactionMessengerSubscriber($bus);
        $event = new RetraitEffectueEvent($transaction);
        $subscriber->onTransaction($event);
    }

    public function testOnTransactionVirementDispatchesMessage(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $transactionDebit = $this->createMock(Transaction::class);
        $transactionDebit->method('getId')->willReturn(44);
        $transactionCredit = $this->createMock(Transaction::class);

        $envelope = new Envelope(new TransactionNotificationMessage(44));
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (TransactionNotificationMessage $msg) {
                return $msg->transactionId === 44;
            }))
            ->willReturn($envelope);

        $subscriber = new TransactionMessengerSubscriber($bus);
        $event = new VirementEffectueEvent($transactionDebit, $transactionCredit);
        $subscriber->onTransaction($event);
    }
}
