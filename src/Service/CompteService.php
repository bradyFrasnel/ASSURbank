<?php

namespace App\Service;

use App\Entity\Compte;
use App\Entity\Transaction;
use Doctrine\ORM\EntityManagerInterface;
use Exception;

class CompteService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * Effectue un dépôt sur un compte et crée la transaction correspondante.
     *
     * @throws Exception
     */
    public function depot(Compte $compte, float $montant, string $libelle = 'Dépôt'): Transaction
    {
        if ($compte->getStatut() !== 'actif') {
            throw new Exception('Le compte est désactivé.');
        }

        if ($montant <= 0) {
            throw new Exception('Le montant doit être positif.');
        }

        $this->entityManager->beginTransaction();

        try {
            $compte->deposer($montant);

            $transaction = new Transaction();
            $transaction->setMontant($montant);
            $transaction->setType('crédit');
            $transaction->setLibelle($libelle);
            $transaction->setFrais(0.00);
            $transaction->setDateTransaction(new \DateTimeImmutable());
            $transaction->setCompteSource($compte);
            $transaction->setCompteDestination($compte);
            $transaction->setStatut('succès');

            $this->entityManager->persist($compte);
            $this->entityManager->persist($transaction);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $transaction;
        } catch (Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Effectue un retrait sur un compte et crée la transaction correspondante.
     *
     * @throws Exception
     */
    public function retrait(Compte $compte, float $montant, string $libelle = 'Retrait'): Transaction
    {
        if ($compte->getStatut() !== 'actif') {
            throw new Exception('Le compte est désactivé.');
        }

        if ($montant <= 0) {
            throw new Exception('Le montant doit être positif.');
        }

        if ($compte->getSolde() < $montant) {
            throw new Exception('Solde insuffisant pour effectuer ce retrait.');
        }

        $this->entityManager->beginTransaction();

        try {
            $compte->retirer($montant);

            $transaction = new Transaction();
            $transaction->setMontant($montant);
            $transaction->setType('débit');
            $transaction->setLibelle($libelle);
            $transaction->setFrais(0.00);
            $transaction->setDateTransaction(new \DateTimeImmutable());
            $transaction->setCompteSource($compte);
            $transaction->setCompteDestination($compte);
            $transaction->setStatut('succès');

            $this->entityManager->persist($compte);
            $this->entityManager->persist($transaction);
            $this->entityManager->flush();
            $this->entityManager->commit();

            return $transaction;
        } catch (Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }
    }
}
