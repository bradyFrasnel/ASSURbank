<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_dashboard');
        }
        if ($this->isGranted('ROLE_BANQUE')) {
            return $this->redirectToRoute('app_banque_dashboard');
        }
        if ($this->isGranted('ROLE_CLIENT')) {
            return $this->redirectToRoute('app_client_dashboard');
        }

        return $this->render('home/index.html.twig');
    }
}
