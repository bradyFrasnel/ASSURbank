<?php

namespace App\Controller;

use App\Entity\Banque;
use App\Entity\Client;
use App\Entity\Compte;
use App\Repository\ClientRepository;
use App\Repository\CompteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/banque')]
class BanqueController extends AbstractController
{
    #[Route('/dashboard', name: 'app_banque_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_BANQUE')]
    public function dashboard(
        ClientRepository $clientRepository,
        CompteRepository $compteRepository
    ): Response {
        /** @var Banque $banque */
        $banque = $this->getUser();

        // Récupérer les clients de cette banque
        $clients = $clientRepository->findBy(['banque' => $banque]);

        // Calculer les statistiques
        $totalClients = count($clients);
        $totalComptes = 0;
        $montantTotal = 0;

        foreach ($clients as $client) {
            $comptes = $compteRepository->findBy(['client' => $client]);
            $totalComptes += count($comptes);
            foreach ($comptes as $compte) {
                $montantTotal += $compte->getSolde();
            }
        }

        return $this->render('banque/dashboard.html.twig', [
            'banque' => $banque,
            'clients' => $clients,
            'totalClients' => $totalClients,
            'totalComptes' => $totalComptes,
            'montantTotal' => $montantTotal,
        ]);
    }

    #[Route('/clients', name: 'app_banque_clients', methods: ['GET'])]
    #[IsGranted('ROLE_BANQUE')]
    public function clients(ClientRepository $clientRepository): Response
    {
        /** @var Banque $banque */
        $banque = $this->getUser();
        $clients = $clientRepository->findBy(['banque' => $banque]);

        return $this->render('banque/clients.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/client/{id}', name: 'app_banque_client_show', methods: ['GET'])]
    #[IsGranted('ROLE_BANQUE')]
    public function showClient(
        Client $client,
        CompteRepository $compteRepository
    ): Response {
        /** @var Banque $banque */
        $banque = $this->getUser();

        // Vérifier que le client appartient à cette banque
        if ($client->getBanque() !== $banque) {
            throw $this->createAccessDeniedException('Ce client ne fait pas partie de votre banque.');
        }

        $comptes = $compteRepository->findBy(['client' => $client]);

        return $this->render('banque/client_show.html.twig', [
            'client' => $client,
            'comptes' => $comptes,
        ]);
    }

    #[Route('/client/{id}/activer', name: 'app_banque_client_activer', methods: ['POST'])]
    #[IsGranted('ROLE_BANQUE')]
    public function activerClient(
        Client $client,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var Banque $banque */
        $banque = $this->getUser();

        if ($client->getBanque() !== $banque) {
            throw $this->createAccessDeniedException('Ce client ne fait pas partie de votre banque.');
        }

        $client->setStatut('actif');
        $entityManager->flush();

        return $this->redirectToRoute('app_banque_client_show', ['id' => $client->getId()]);
    }

    #[Route('/client/{id}/desactiver', name: 'app_banque_client_desactiver', methods: ['POST'])]
    #[IsGranted('ROLE_BANQUE')]
    public function desactiverClient(
        Client $client,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var Banque $banque */
        $banque = $this->getUser();

        if ($client->getBanque() !== $banque) {
            throw $this->createAccessDeniedException('Ce client ne fait pas partie de votre banque.');
        }

        $client->setStatut('inactif');
        $entityManager->flush();

        return $this->redirectToRoute('app_banque_client_show', ['id' => $client->getId()]);
    }

    #[Route('/compte/{id}/activer', name: 'app_banque_compte_activer', methods: ['POST'])]
    #[IsGranted('ROLE_BANQUE')]
    public function activerCompte(
        Compte $compte,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var Banque $banque */
        $banque = $this->getUser();

        // Vérifier que le compte appartient à un client de cette banque
        if ($compte->getClient()->getBanque() !== $banque) {
            throw $this->createAccessDeniedException('Ce compte ne fait pas partie de votre banque.');
        }

        $compte->setStatut('actif');
        $entityManager->flush();

        return $this->redirectToRoute('app_banque_client_show', ['id' => $compte->getClient()->getId()]);
    }

    #[Route('/compte/{id}/desactiver', name: 'app_banque_compte_desactiver', methods: ['POST'])]
    #[IsGranted('ROLE_BANQUE')]
    public function desactiverCompte(
        Compte $compte,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var Banque $banque */
        $banque = $this->getUser();

        // Vérifier que le compte appartient à un client de cette banque
        if ($compte->getClient()->getBanque() !== $banque) {
            throw $this->createAccessDeniedException('Ce compte ne fait pas partie de votre banque.');
        }

        $compte->setStatut('inactif');
        $entityManager->flush();

        return $this->redirectToRoute('app_banque_client_show', ['id' => $compte->getClient()->getId()]);
    }

    #[Route('/compte/create', name: 'app_banque_compte_create', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_BANQUE')]
    public function createCompte(
        Request $request,
        ClientRepository $clientRepository,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var Banque $banque */
        $banque = $this->getUser();

        if ($request->isMethod('POST')) {
            $clientId = $request->request->get('client_id');
            $type = $request->request->get('type');

            $client = $clientRepository->find($clientId);

            if (!$client || $client->getBanque() !== $banque) {
                throw $this->createAccessDeniedException('Client invalide.');
            }

            // Générer un numéro de compte unique
            $numeroCompte = 'FR' . str_pad(rand(0, 999999999999), 24, '0', STR_PAD_LEFT);

            $compte = new Compte();
            $compte->setNumeroCompte($numeroCompte);
            $compte->setType($type);
            $compte->setSolde(0.00);
            $compte->setStatut('actif');
            $compte->setDateCreation(new \DateTimeImmutable());
            $compte->setClient($client);

            $entityManager->persist($compte);
            $entityManager->flush();

            return $this->redirectToRoute('app_banque_client_show', ['id' => $client->getId()]);
        }

        $clients = $clientRepository->findBy(['banque' => $banque, 'statut' => 'actif']);

        return $this->render('banque/compte_create.html.twig', [
            'clients' => $clients,
        ]);
    }
}
