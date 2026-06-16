<?php

namespace App\Service;

use App\Entity\Compte;
use App\Entity\Transaction;
use App\Event\VirementEffectueEvent;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class VirementService
{
    private EntityManagerInterface $entityManager;
    private EventDispatcherInterface $dispatcher;

    public function __construct(
        EntityManagerInterface $entityManager,
        EventDispatcherInterface $dispatcher,
    ) {
        $this->entityManager = $entityManager;
        $this->dispatcher = $dispatcher;
    }

    /**
     * Effectue un virement entre deux comptes
     * 
     * @param Compte $compteSource Compte émetteur
     * @param Compte $compteDestination Compte destinataire
     * @param float $montant Montant du virement
     * @param string $libelle Libellé de la transaction
     * @return Transaction[] Tableau des transactions créées
     * @throws Exception Si le virement échoue
     */
    public function effectuerVirement(Compte $compteSource, Compte $compteDestination, float $montant, string $libelle): array
    {
        // R2: Vérifier que les comptes sont actifs
        if ($compteSource->getStatut() !== 'actif') {
            throw new Exception('Le compte source est désactivé.');
        }

        if ($compteDestination->getStatut() !== 'actif') {
            throw new Exception('Le compte destination est désactivé.');
        }

        // R1: Vérifier le solde suffisant
        if ($compteSource->getSolde() < $montant) {
            throw new Exception('Solde insuffisant pour effectuer ce virement.');
        }

        // R3: Le solde ne peut pas être négatif (déjà vérifié par R1)
        if ($montant <= 0) {
            throw new Exception('Le montant doit être positif.');
        }

        // Début de la transaction
        $this->entityManager->beginTransaction();

        try {
            // Débiter le compte source
            $nouveauSoldeSource = $compteSource->getSolde() - $montant;
            $compteSource->setSolde($nouveauSoldeSource);

            // Créditer le compte destination
            $nouveauSoldeDestination = $compteDestination->getSolde() + $montant;
            $compteDestination->setSolde($nouveauSoldeDestination);

            // R4: Créer 2 transactions (débit + crédit)
            $transactionDebit = new Transaction();
            $transactionDebit->setMontant($montant);
            $transactionDebit->setType('débit');
            $transactionDebit->setLibelle($libelle . ' (débit)');
            $transactionDebit->setFrais(0.00);
            $transactionDebit->setDateTransaction(new \DateTimeImmutable());
            $transactionDebit->setCompteSource($compteSource);
            $transactionDebit->setCompteDestination($compteDestination);
            $transactionDebit->setStatut('succès');

            $transactionCredit = new Transaction();
            $transactionCredit->setMontant($montant);
            $transactionCredit->setType('crédit');
            $transactionCredit->setLibelle($libelle . ' (crédit)');
            $transactionCredit->setFrais(0.00);
            $transactionCredit->setDateTransaction(new \DateTimeImmutable());
            $transactionCredit->setCompteSource($compteSource);
            $transactionCredit->setCompteDestination($compteDestination);
            $transactionCredit->setStatut('succès');

            // Persister les changements
            $this->entityManager->persist($compteSource);
            $this->entityManager->persist($compteDestination);
            $this->entityManager->persist($transactionDebit);
            $this->entityManager->persist($transactionCredit);

            $this->entityManager->flush();
            $this->entityManager->commit();

            // Dispatcher l'événement après le commit pour la cohérence
            $this->dispatcher->dispatch(new VirementEffectueEvent($transactionDebit, $transactionCredit));

            return [$transactionDebit, $transactionCredit];

        } catch (Exception $e) {
            $this->entityManager->rollback();

            // Enregistrer la transaction échouée
            $transactionEchouee = new Transaction();
            $transactionEchouee->setMontant($montant);
            $transactionEchouee->setType('débit');
            $transactionEchouee->setLibelle($libelle . ' (échoué)');
            $transactionEchouee->setFrais(0.00);
            $transactionEchouee->setDateTransaction(new \DateTimeImmutable());
            $transactionEchouee->setCompteSource($compteSource);
            $transactionEchouee->setCompteDestination($compteDestination);
            $transactionEchouee->setStatut('échoué');

            $this->entityManager->persist($transactionEchouee);
            $this->entityManager->flush();

            throw $e;
        }
    }

    /**
     * Vérifie si un virement est possible
     */
    public function verifierVirementPossible(Compte $compteSource, Compte $compteDestination, float $montant): bool
    {
        if ($compteSource->getStatut() !== 'actif' || $compteDestination->getStatut() !== 'actif') {
            return false;
        }

        if ($compteSource->getSolde() < $montant || $montant <= 0) {
            return false;
        }

        return true;
    }
}
