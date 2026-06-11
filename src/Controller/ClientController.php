<?php

namespace App\Controller;

use App\Entity\Banque;
use App\Entity\Client;
use App\Entity\Compte;
use App\Entity\Transaction;
use App\Repository\BanqueRepository;
use App\Repository\CompteRepository;
use App\Repository\TransactionRepository;
use App\Service\VirementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client')]
class ClientController extends AbstractController
{
    #[Route('/inscription', name: 'app_client_inscription', methods: ['GET', 'POST'])]
    public function inscription(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        BanqueRepository $banqueRepository
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_client_dashboard');
        }

        if ($request->isMethod('POST')) {
            $client = new Client();
            $client->setNom($request->request->get('nom'));
            $client->setPrenom($request->request->get('prenom'));
            $client->setEmail($request->request->get('email'));
            $client->setTelephone($request->request->get('telephone'));
            $client->setRole('ROLE_CLIENT');
            $client->setStatut('actif');
            $client->setDateCreation(new \DateTimeImmutable());

            // Mot de passe
            $password = $request->request->get('password');
            $hashedPassword = $passwordHasher->hashPassword($client, $password);
            $client->setPassword($hashedPassword);

            // Sélection de la banque
            $banqueId = $request->request->get('banque_id');
            if ($banqueId) {
                $banque = $banqueRepository->find($banqueId);
                if ($banque) {
                    $client->setBanque($banque);
                }
            }

            $entityManager->persist($client);
            $entityManager->flush();

            return $this->redirectToRoute('app_login');
        }

        $banques = $banqueRepository->findBy(['statut' => 'actif']);

        return $this->render('client/inscription.html.twig', [
            'banques' => $banques,
        ]);
    }

    #[Route('/dashboard', name: 'app_client_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT')]
    public function dashboard(
        CompteRepository $compteRepository,
        TransactionRepository $transactionRepository
    ): Response {
        /** @var Client $client */
        $client = $this->getUser();

        $comptes = $compteRepository->findBy(['client' => $client]);
        
        // Récupérer les transactions récentes
        $transactions = [];
        foreach ($comptes as $compte) {
            $compteTransactions = $transactionRepository->findBy(
                ['compteSource' => $compte],
                ['dateTransaction' => 'DESC'],
                10
            );
            $transactions = array_merge($transactions, $compteTransactions);
        }

        // Trier par date
        usort($transactions, function($a, $b) {
            return $b->getDateTransaction() <=> $a->getDateTransaction();
        });

        return $this->render('client/dashboard.html.twig', [
            'client' => $client,
            'comptes' => $comptes,
            'transactions' => array_slice($transactions, 0, 10),
        ]);
    }

    #[Route('/comptes', name: 'app_client_comptes', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT')]
    public function comptes(CompteRepository $compteRepository): Response
    {
        /** @var Client $client */
        $client = $this->getUser();
        $comptes = $compteRepository->findBy(['client' => $client]);

        return $this->render('client/comptes.html.twig', [
            'comptes' => $comptes,
        ]);
    }

    #[Route('/compte/{id}', name: 'app_client_compte_show', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT')]
    public function showCompte(
        Compte $compte,
        TransactionRepository $transactionRepository,
        CompteRepository $compteRepository
    ): Response {
        /** @var Client $client */
        $client = $this->getUser();

        // Vérifier que le compte appartient au client
        if ($compte->getClient() !== $client) {
            throw $this->createAccessDeniedException('Ce compte ne vous appartient pas.');
        }

        $transactions = $transactionRepository->findBy(
            ['compteSource' => $compte],
            ['dateTransaction' => 'DESC']
        );

        return $this->render('client/compte_show.html.twig', [
            'compte' => $compte,
            'transactions' => $transactions,
        ]);
    }

    #[Route('/virement', name: 'app_client_virement', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CLIENT')]
    public function virement(
        Request $request,
        VirementService $virementService,
        CompteRepository $compteRepository,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var Client $client */
        $client = $this->getUser();
        $comptes = $compteRepository->findBy(['client' => $client, 'statut' => 'actif']);

        $error = null;
        $success = null;

        if ($request->isMethod('POST')) {
            $compteSourceId = $request->request->get('compte_source');
            $compteDestinationId = $request->request->get('compte_destination');
            $montant = (float) $request->request->get('montant');
            $libelle = $request->request->get('libelle');

            $compteSource = $compteRepository->find($compteSourceId);
            $compteDestination = $compteRepository->find($compteDestinationId);

            if (!$compteSource || !$compteDestination) {
                $error = 'Comptes invalides.';
            } elseif ($compteSource->getClient() !== $client) {
                $error = 'Le compte source ne vous appartient pas.';
            } elseif ($compteSource === $compteDestination) {
                $error = 'Impossible de virer vers le même compte.';
            } else {
                try {
                    $virementService->effectuerVirement($compteSource, $compteDestination, $montant, $libelle);
                    $success = 'Virement effectué avec succès.';
                    $entityManager->refresh($compteSource);
                    $entityManager->refresh($compteDestination);
                } catch (\Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('client/virement.html.twig', [
            'comptes' => $comptes,
            'error' => $error,
            'success' => $success,
        ]);
    }

    #[Route('/profil', name: 'app_client_profil', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CLIENT')]
    public function profil(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var Client $client */
        $client = $this->getUser();
        $success = null;

        if ($request->isMethod('POST')) {
            $nouveauMotDePasse = $request->request->get('nouveau_mot_de_passe');
            if ($nouveauMotDePasse) {
                $hashedPassword = $passwordHasher->hashPassword($client, $nouveauMotDePasse);
                $client->setPassword($hashedPassword);
                $entityManager->flush();
                $success = 'Mot de passe modifié avec succès.';
            }
        }

        return $this->render('client/profil.html.twig', [
            'client' => $client,
            'success' => $success,
        ]);
    }
}
