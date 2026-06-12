<?php

namespace App\Controller\Admin;

use App\Entity\Banque;
use App\Repository\BanqueRepository;
use App\Repository\ClientRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminDashboardController extends AbstractController
{
    public function index(
        Request $request,
        BanqueRepository $banqueRepository,
        ClientRepository $clientRepository,
        TransactionRepository $transactionRepository,
    ): Response {
        $page = max(1, $request->query->getInt('page', 1));
        $pagination = $banqueRepository->findPaginated($page, 10);

        $banques = $pagination->items;
        $totalBanques = $pagination->total;
        $banquesActives = $banqueRepository->countByStatut('actif');
        $banquesInactives = $banqueRepository->countByStatut('inactif');
        $banquesEnAttente = $banqueRepository->findBy(['statut' => 'inactif'], ['dateCreation' => 'DESC'], 10);

        return $this->render('admin/admin_dashboard/index.html.twig', [
            'banques' => $banques,
            'pagination' => $pagination,
            'totalBanques' => $totalBanques,
            'banquesActives' => $banquesActives,
            'banquesInactives' => $banquesInactives,
            'banquesEnAttente' => $banquesEnAttente,
            'totalClients' => $clientRepository->countAll(),
            'totalTransactions' => $transactionRepository->countAll(),
            'volumeTransactions' => $transactionRepository->sumMontantSucces(),
        ]);
    }

    public function activerBanque(Banque $banque, EntityManagerInterface $entityManager): Response
    {
        $banque->setStatut('actif');
        $entityManager->flush();
        $this->addFlash('success', sprintf('La banque « %s » a été activée.', $banque->getNom()));

        return $this->redirectToRoute('app_admin_dashboard');
    }

    public function desactiverBanque(Banque $banque, EntityManagerInterface $entityManager): Response
    {
        $banque->setStatut('inactif');
        $entityManager->flush();
        $this->addFlash('success', sprintf('La banque « %s » a été désactivée.', $banque->getNom()));

        return $this->redirectToRoute('app_admin_dashboard');
    }
}
