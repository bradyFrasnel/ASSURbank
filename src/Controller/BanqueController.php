<?php

namespace App\Controller;

use App\Entity\Banque;
use App\Entity\Client;
use App\Entity\Compte;
use App\Form\BanqueInscriptionType;
use App\Form\CompteCreateType;
use App\Repository\ClientRepository;
use App\Repository\CompteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/banque')]
class BanqueController extends AbstractController
{
    #[Route('/inscription', name: 'app_banque_inscription', methods: ['GET', 'POST'])]
    public function inscription(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->isGranted('ROLE_BANQUE')) {
            return $this->redirectToRoute('app_banque_dashboard');
        }

        $banque = new Banque();
        $form = $this->createForm(BanqueInscriptionType::class, $banque);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $banque->setRole('ROLE_BANQUE');
            $banque->setStatut('actif');
            $banque->setDateCreation(new \DateTimeImmutable());
            $banque->setPassword($passwordHasher->hashPassword($banque, $form->get('plainPassword')->getData()));

            $entityManager->persist($banque);
            $entityManager->flush();

            $this->addFlash('success', 'Banque inscrite avec succès. Vous pouvez vous connecter.');

            return $this->redirectToRoute('app_login_banque');
        }

        return $this->render('banque/inscription.html.twig', ['form' => $form]);
    }

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
    public function clients(Request $request, ClientRepository $clientRepository): Response
    {
        /** @var Banque $banque */
        $banque = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $pagination = $clientRepository->findByBanquePaginated($banque, $page, 10);

        return $this->render('banque/clients.html.twig', [
            'clients' => $pagination->items,
            'pagination' => $pagination,
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

        $clients = $clientRepository->findBy(['banque' => $banque, 'statut' => 'actif']);
        $form = $this->createForm(CompteCreateType::class, null, ['clients' => $clients]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var Client $client */
            $client = $form->get('client')->getData();

            if ($client->getBanque() !== $banque) {
                throw $this->createAccessDeniedException('Client invalide.');
            }

            $numeroCompte = $this->generateNumeroCompte();

            $compte = new Compte();
            $compte->setNumeroCompte($numeroCompte);
            $compte->setType($form->get('type')->getData());
            $compte->setSolde(0.00);
            $compte->setStatut('actif');
            $compte->setDateCreation(new \DateTimeImmutable());
            $compte->setClient($client);

            $entityManager->persist($compte);
            $entityManager->flush();

            $this->addFlash('success', 'Compte créé avec succès.');

            return $this->redirectToRoute('app_banque_client_show', ['id' => $client->getId()]);
        }

        if ($request->query->has('client_id')) {
            $preselected = $clientRepository->find($request->query->getInt('client_id'));
            if ($preselected && $preselected->getBanque() === $banque) {
                $form->get('client')->setData($preselected);
            }
        }

        return $this->render('banque/compte_create.html.twig', [
            'form' => $form,
            'clients' => $clients,
        ]);
    }

    private function generateNumeroCompte(): string
    {
        $digits = '';
        for ($i = 0; $i < 24; ++$i) {
            $digits .= (string) random_int(0, 9);
        }

        return 'FR'.$digits;
    }
}
