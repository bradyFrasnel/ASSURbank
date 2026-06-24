<?php

namespace App\Tests\MessageHandler;

use App\Entity\Client;
use App\Entity\Compte;
use App\Entity\Transaction;
use App\Message\TransactionNotificationMessage;
use App\MessageHandler\TransactionNotificationHandler;
use App\Repository\TransactionRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class TransactionNotificationHandlerTest extends TestCase
{
    public function testInvokeSendsEmail(): void
    {
        $transactionRepo = $this->createMock(TransactionRepository::class);
        $mailer = $this->createMock(MailerInterface::class);

        $client = $this->createMock(Client::class);
        $client->method('getPrenom')->willReturn('Jean');
        $client->method('getNom')->willReturn('Dupont');
        $client->method('getEmail')->willReturn('jean.dupont@test.fr');

        $compte = $this->createMock(Compte::class);
        $compte->method('getClient')->willReturn($client);
        $compte->method('getNumeroCompte')->willReturn('FR76123456');

        $transaction = $this->createMock(Transaction::class);
        $transaction->method('getCompteSource')->willReturn($compte);
        $transaction->method('getType')->willReturn('depot');
        $transaction->method('getMontant')->willReturn(150.0);
        $transaction->method('getLibelle')->willReturn('Dépôt espèces');
        $transaction->method('getDateTransaction')->willReturn(new \DateTimeImmutable('2026-06-23 12:00:00'));

        $transactionRepo->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn($transaction);

        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getTo()[0]->getAddress() === 'jean.dupont@test.fr'
                    && str_contains($email->getSubject(), 'Depot')
                    && str_contains($email->getHtmlBody(), '150 €')
                    && str_contains($email->getHtmlBody(), 'FR76123456');
            }));

        $handler = new TransactionNotificationHandler($transactionRepo, $mailer);
        $message = new TransactionNotificationMessage(42);
        $handler($message);
    }
}
