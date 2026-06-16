<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Compte;
use App\Form\ChangePasswordType;
use App\Form\ClientInscriptionType;
use App\Form\TransactionFilterType;
use App\Form\VirementType;
use App\Security\CompteVoter;
use App\Security\TransactionVoter;
use App\Repository\CompteRepository;
use App\Repository\TransactionRepository;
use App\Service\VirementService;
use App\Service\CompteService;
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
        BanqueRepository $banqueRepository,
    ): Response {
        if ($this->isGranted('ROLE_CLIENT')) {
            return $this->redirectToRoute('app_client_dashboard');
        }

        $client = new Client();
        $form = $this->createForm(ClientInscriptionType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $client->setRole('ROLE_CLIENT');
            $client->setStatut('actif');
            $client->setDateCreation(new \DateTimeImmutable());
            $client->setPassword($passwordHasher->hashPassword($client, $form->get('plainPassword')->getData()));

            $entityManager->persist($client);
            $entityManager->flush();

            $this->addFlash('success', 'Inscription réussie. Vous pouvez maintenant vous connecter.');

            return $this->redirectToRoute('app_login_client');
        }

        return $this->render('client/inscription.html.twig', [
            'form' => $form,
            'banques' => $banqueRepository->findBy(['statut' => 'actif']),
        ]);
    }

    #[Route('/dashboard', name: 'app_client_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT')]
    public function dashboard(
        CompteRepository $compteRepository,
        TransactionRepository $transactionRepository,
    ): Response {
        /** @var Client $client */
        $client = $this->getUser();
        $comptes = $compteRepository->findBy(['client' => $client]);
        $pagination = $transactionRepository->findByClientPaginated($client, 1, 5);

        return $this->render('client/dashboard.html.twig', [
            'client' => $client,
            'comptes' => $comptes,
            'transactions' => $pagination->items,
        ]);
    }

    #[Route('/comptes', name: 'app_client_comptes', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT')]
    public function comptes(CompteRepository $compteRepository): Response
    {
        /** @var Client $client */
        $client = $this->getUser();

        return $this->render('client/comptes.html.twig', [
            'comptes' => $compteRepository->findBy(['client' => $client]),
        ]);
    }

    #[Route('/compte/{id}', name: 'app_client_compte_show', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT')]
    public function showCompte(
        Request $request,
        Compte $compte,
        TransactionRepository $transactionRepository,
    ): Response {
        // Vérifier l'accès avec le Voter CompteVoter
        $this->denyAccessUnlessGranted(CompteVoter::VIEW, $compte);

        $page = max(1, $request->query->getInt('page', 1));
        $pagination = $transactionRepository->findByComptePaginated($compte, $page, 10);

        return $this->render('client/compte_show.html.twig', [
            'compte' => $compte,
            'pagination' => $pagination,
            'transactions' => $pagination->items,
        ]);
    }

    #[Route('/compte/{id}/depot', name: 'app_client_compte_depot', methods: ['POST'])]
    #[IsGranted('ROLE_CLIENT')]
    public function depotCompte(Compte $compte, Request $request, CompteService $compteService): Response
    {
        // Vérifier que le client peut effectuer des opérations sur ce compte
        $this->denyAccessUnlessGranted(CompteVoter::OPERATIONS, $compte);

        $montant = (float) str_replace(',', '.', (string) $request->request->get('montant'));
        $libelle = (string) $request->request->get('libelle', 'Dépôt');

        try {
            $compteService->depot($compte, $montant, $libelle);
            $this->addFlash('success', 'Dépôt effectué.');
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_client_compte_show', ['id' => $compte->getId()]);
    }

    #[Route('/compte/{id}/retrait', name: 'app_client_compte_retrait', methods: ['POST'])]
    #[IsGranted('ROLE_CLIENT')]
    public function retraitCompte(Compte $compte, Request $request, CompteService $compteService): Response
    {
        // Vérifier que le client peut effectuer des opérations sur ce compte
        $this->denyAccessUnlessGranted(CompteVoter::OPERATIONS, $compte);

        $montant = (float) str_replace(',', '.', (string) $request->request->get('montant'));
        $libelle = (string) $request->request->get('libelle', 'Retrait');

        try {
            $compteService->retrait($compte, $montant, $libelle);
            $this->addFlash('success', 'Retrait effectué.');
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_client_compte_show', ['id' => $compte->getId()]);
    }

    #[Route('/transactions', name: 'app_client_transactions', methods: ['GET'])]
    #[IsGranted('ROLE_CLIENT')]
    public function transactions(Request $request, TransactionRepository $transactionRepository): Response
    {
        /** @var Client $client */
        $client = $this->getUser();

        $filterForm = $this->createForm(TransactionFilterType::class);
        $filterForm->handleRequest($request);

        $filters = $filterForm->isSubmitted() ? $filterForm->getData() : [];
        $filters = array_filter($filters ?? [], fn ($v) => $v !== null && $v !== '');

        if (isset($filters['date_debut']) && $filters['date_debut'] instanceof \DateTimeInterface) {
            $filters['date_debut'] = $filters['date_debut']->format('Y-m-d');
        }
        if (isset($filters['date_fin']) && $filters['date_fin'] instanceof \DateTimeInterface) {
            $filters['date_fin'] = $filters['date_fin']->format('Y-m-d');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $pagination = $transactionRepository->findByClientPaginated($client, $page, 10, $filters);

        $queryParams = $request->query->all();
        unset($queryParams['page']);

        return $this->render('client/transactions.html.twig', [
            'filterForm' => $filterForm,
            'pagination' => $pagination,
            'transactions' => $pagination->items,
            'query_params' => $queryParams,
        ]);
    }

    #[Route('/virement', name: 'app_client_virement', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_CLIENT')]
    public function virement(
        Request $request,
        VirementService $virementService,
        CompteRepository $compteRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        /** @var Client $client */
        $client = $this->getUser();
        $comptes = $compteRepository->findBy(['client' => $client, 'statut' => 'actif']);

        $form = $this->createForm(VirementType::class, null, ['comptes' => $comptes]);
        $form->handleRequest($request);

        $error = null;
        $success = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $compteSource = $form->get('compteSource')->getData();
            $compteDestination = $form->get('compteDestination')->getData();
            $montant = (float) $form->get('montant')->getData();
            $libelle = $form->get('libelle')->getData();

            // Vérifier l'accès avec les Voters
            try {
                $this->denyAccessUnlessGranted(CompteVoter::OPERATIONS, $compteSource);
                $this->denyAccessUnlessGranted(CompteVoter::VIEW, $compteDestination);
            } catch (\Exception $e) {
                $error = 'Vous n\'avez pas l\'autorisation d\'effectuer ce virement.';
            }

            if ($compteSource === $compteDestination) {
                $error = 'Impossible de virer vers le même compte.';
            } else if (!isset($error)) {
                try {
                    $virementService->effectuerVirement($compteSource, $compteDestination, $montant, $libelle);
                    $success = 'Virement effectué avec succès.';
                    $entityManager->refresh($compteSource);
                    $entityManager->refresh($compteDestination);
                    $form = $this->createForm(VirementType::class, null, ['comptes' => $comptes]);
                } catch (\Exception $e) {
                    $error = $e->getMessage();
                }
            }
        }

        return $this->render('client/virement.html.twig', [
            'form' => $form,
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
        EntityManagerInterface $entityManager,
    ): Response {
        /** @var Client $client */
        $client = $this->getUser();

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $client->setPassword($passwordHasher->hashPassword($client, $form->get('plainPassword')->getData()));
            $entityManager->flush();
            $this->addFlash('success', 'Mot de passe modifié avec succès.');

            return $this->redirectToRoute('app_client_profil');
        }

        return $this->render('client/profil.html.twig', [
            'client' => $client,
            'form' => $form,
        ]);
    }
}
