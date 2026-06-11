<?php

namespace App\Controller\Admin;

use App\Entity\Banque;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AdminDashboardController extends AbstractController
{
    #[Route('/admin', name: 'app_admin_dashboard')]
    public function index(EntityManagerInterface $entityManager): Response
    {
        // 1. Récupérer toutes les banques du système pour les afficher à l'admin
        $banqueRepository = $entityManager->getRepository(Banque::class);
        $banques = $banqueRepository->findAll();

        // 2. Calculer quelques statistiques rapides pour le tableau de bord
        $totalBanques = count($banques);
        
        $banquesActives = count(array_filter($banques, function($b) {
            return $b->getStatut() === 'actif';
        }));

        return $this->render('admin/admin_dashboard/index.html.twig', [
            'banques' => $banques,
            'totalBanques' => $totalBanques,
            'banquesActives' => $banquesActives,
        ]);
    }
}