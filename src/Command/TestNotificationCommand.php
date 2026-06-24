<?php

namespace App\Command;

use App\Repository\CompteRepository;
use App\Service\CompteService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test-notification',
    description: 'Simule un dépôt et place un message de notification dans la file Messenger.',
)]
class TestNotificationCommand extends Command
{
    public function __construct(
        private readonly CompteService $compteService,
        private readonly CompteRepository $compteRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('montant', InputArgument::OPTIONAL, 'Montant du dépôt (défaut : 100)', 100.0);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $montant = (float) $input->getArgument('montant');

        $compte = $this->compteRepository->findOneBy(['statut' => 'actif']);

        if (!$compte) {
            $io->error('Aucun compte actif trouvé. Lancez d\'abord : symfony console doctrine:fixtures:load');
            return Command::FAILURE;
        }

        $client = $compte->getClient();

        $io->title('Test End-to-End — Notification Messenger');
        $io->table(
            ['Champ', 'Valeur'],
            [
                ['Compte', $compte->getNumeroCompte()],
                ['Client', $client?->getPrenom() . ' ' . $client?->getNom()],
                ['Email', $client?->getEmail()],
                ['Solde actuel', $compte->getSolde() . ' €'],
                ['Montant dépôt', $montant . ' €'],
            ]
        );

        $io->section('Exécution du dépôt...');

        $transaction = $this->compteService->depot($compte, $montant, 'Dépôt test notification');

        $io->success(sprintf(
            'Dépôt de %.2f € effectué ! Transaction #%d créée. Message placé dans la file async.',
            $montant,
            $transaction->getId()
        ));

        $io->note([
            'Le Worker va traiter le message et envoyer l\'e-mail à : ' . $client?->getEmail(),
            'Consultez les e-mails capturés sur : http://localhost:56413',
        ]);

        return Command::SUCCESS;
    }
}
