<?php

namespace App\MessageHandler;

use App\Message\TransactionNotificationMessage;
use App\Repository\TransactionRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Email;

#[AsMessageHandler]
class TransactionNotificationHandler
{
    public function __construct(
        private readonly TransactionRepository $transactionRepository,
        private readonly MailerInterface $mailer,
    ) {}

    public function __invoke(TransactionNotificationMessage $message): void
    {
        $transaction = $this->transactionRepository->find($message->transactionId);
        if (!$transaction) {
            return;
        }

        $compte = $transaction->getCompteSource();
        if (!$compte) {
            return;
        }

        $client = $compte->getClient();
        if (!$client || !$client->getEmail()) {
            return;
        }

        $email = (new Email())
            ->from('notifications@assurbank.fr')
            ->to($client->getEmail())
            ->subject(sprintf('Notification de transaction - %s', ucfirst($transaction->getType() ?? '')))
            ->html(sprintf(
                '<p>Bonjour %s %s,</p>
                 <p>Une transaction de type <strong>%s</strong> d\'un montant de <strong>%s €</strong> a été effectuée sur votre compte n° <strong>%s</strong> le %s.</p>
                 <p>Libellé : %s</p>
                 <p>Merci pour votre confiance,<br>L\'équipe ASSURbank</p>',
                htmlspecialchars($client->getPrenom() ?? ''),
                htmlspecialchars($client->getNom() ?? ''),
                htmlspecialchars($transaction->getType() ?? ''),
                $transaction->getMontant(),
                htmlspecialchars($compte->getNumeroCompte() ?? ''),
                $transaction->getDateTransaction()?->format('d/m/Y H:i') ?? '',
                htmlspecialchars($transaction->getLibelle() ?? '')
            ));

        $this->mailer->send($email);
    }
}
