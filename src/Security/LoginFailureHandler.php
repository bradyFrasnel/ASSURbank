<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // Récupérer le type de login depuis la requête (champ _login_type dans le formulaire)
        $loginType = (string) $request->request->get('_login_type', 'client');

        // Déterminer la route de redirection selon le type
        $loginRoute = match ($loginType) {
            'banque' => 'app_login_banque',
            'admin' => 'app_login_admin',
            default => 'app_login_client',
        };

        // Sauvegarder l'erreur et le dernier username dans la session
        $session = $request->getSession();
        $session->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);
        $session->set(SecurityRequestAttributes::LAST_USERNAME, (string) $request->request->get('_username', ''));

        // Rediriger vers la bonne page de connexion
        return new RedirectResponse($this->urlGenerator->generate($loginRoute));
    }
}
