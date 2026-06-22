<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function loginRedirect(): Response
    {
        return $this->redirectToRoute('app_home');
    }

    public function loginClient(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->renderLogin($authenticationUtils, [
            'login_type' => 'client',
            'required_role' => 'ROLE_CLIENT',
            'dashboard_route' => 'app_client_dashboard',
            'title' => 'Espace Client',
            'subtitle' => 'Connectez-vous pour accéder à vos comptes et virements',
            'icon' => 'bi-person-circle',
            'color' => 'primary',
            'inscription_route' => 'app_client_inscription',
            'inscription_label' => 'Créer un compte client',
        ]);
    }

    public function loginBanque(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->renderLogin($authenticationUtils, [
            'login_type' => 'banque',
            'required_role' => 'ROLE_BANQUE',
            'dashboard_route' => 'app_banque_dashboard',
            'title' => 'Espace Banque',
            'subtitle' => 'Connectez-vous pour gérer vos clients et leurs comptes',
            'icon' => 'bi-bank',
            'color' => 'success',
            'inscription_route' => 'app_banque_inscription',
            'inscription_label' => 'Inscrire une banque',
        ]);
    }

    public function loginAdmin(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->renderLogin($authenticationUtils, [
            'login_type' => 'admin',
            'required_role' => 'ROLE_ADMIN',
            'dashboard_route' => 'app_admin_dashboard',
            'title' => 'Espace Administrateur',
            'subtitle' => 'Accès réservé à l\'administration de la plateforme',
            'icon' => 'bi-shield-lock',
            'color' => 'danger',
            'inscription_route' => null,
            'inscription_label' => null,
        ]);
    }

    public function clearRateLimitSession(): JsonResponse
    {
        $this->cleanRateLimitSession();
        return new JsonResponse(['success' => true]);
    }

    public function loginCheck(): never
    {
        throw new \LogicException('Cette route est interceptée par le firewall de sécurité.');
    }

    public function logout(): void
    {
        throw new \LogicException('Cette route est interceptée par le firewall de sécurité.');
    }

    private function cleanRateLimitSession(): void
    {
        $session = $this->container->get('request_stack')->getSession();
        if ($session) {
            $session->remove('rate_limit_wait_time');
            $session->remove('is_rate_limited');
        }
    }

    /**
     * @param array{
     *     login_type: string,
     *     required_role: string,
     *     dashboard_route: string,
     *     title: string,
     *     subtitle: string,
     *     icon: string,
     *     color: string,
     *     inscription_route: ?string,
     *     inscription_label: ?string
     * } $config
     */
    private function renderLogin(AuthenticationUtils $authenticationUtils, array $config): Response
    {
        if ($this->isGranted($config['required_role'])) {
            return $this->redirectToRoute($config['dashboard_route']);
        }

        $wrongSession = $this->getUser() !== null;

        return $this->render('security/login.html.twig', array_merge($config, [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'wrong_session' => $wrongSession,
            'current_user_email' => $wrongSession ? $this->getUser()->getUserIdentifier() : null,
        ]));
    }
}
