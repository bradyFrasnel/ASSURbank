<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        /** @var UserInterface $user */
        $user = $token->getUser();
        $roles = $user->getRoles();
        $loginType = (string) $request->request->get('_login_type', 'client');

        $expectedRole = match ($loginType) {
            'banque' => 'ROLE_BANQUE',
            'admin' => 'ROLE_ADMIN',
            default => 'ROLE_CLIENT',
        };

        if (!in_array($expectedRole, $roles, true)) {
            $this->tokenStorage->setToken(null);
            $request->getSession()->remove('_security_main');
            $request->getSession()->getFlashBag()->add(
                'danger',
                'Ce compte n\'a pas accès à cet espace. Veuillez utiliser le bon portail de connexion.'
            );

            return new RedirectResponse($this->urlGenerator->generate($this->getLoginRoute($loginType)));
        }

        if (in_array('ROLE_ADMIN', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }

        if (in_array('ROLE_BANQUE', $roles, true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_banque_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_client_dashboard'));
    }

    private function getLoginRoute(string $loginType): string
    {
        return match ($loginType) {
            'banque' => 'app_login_banque',
            'admin' => 'app_login_admin',
            default => 'app_login_client',
        };
    }
}
