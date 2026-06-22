<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class RateLimitedFormAuthenticator extends AbstractAuthenticator implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RateLimiterFactory $loginIpLimiter,
        private readonly RateLimiterFactory $loginUsernameLimiter,
        private readonly AuthenticationSuccessHandlerInterface $successHandler,
        private readonly AuthenticationFailureHandlerInterface $failureHandler,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST') && $request->getPathInfo() === '/login';
    }

    public function authenticate(Request $request): Passport
    {
        $username = (string) $request->request->get('_username', '');
        $password = (string) $request->request->get('_password', '');
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        $ip = $request->getClientIp();

        // ÉTAPE 1 : RATE LIMITING D'ABORD !
        $limiterIp = $this->loginIpLimiter->create($ip);
        $limitIp = $limiterIp->consume();

        $limiterUsername = $this->loginUsernameLimiter->create($username);
        $limitUsername = $limiterUsername->consume();

        if (!$limitIp->isAccepted() || !$limitUsername->isAccepted()) {
            $waitTime = 60;
            if (!$limitIp->isAccepted()) {
                $waitTime = max($waitTime, $limitIp->getRetryAfter()->getTimestamp() - time());
            }
            if (!$limitUsername->isAccepted()) {
                $waitTime = max($waitTime, $limitUsername->getRetryAfter()->getTimestamp() - time());
            }
            // Stocker le temps d'attente dans la session pour le template
            $request->getSession()->set('rate_limit_wait_time', $waitTime);
            $request->getSession()->set('is_rate_limited', true);
            throw new TooManyLoginAttemptsAuthenticationException($waitTime);
        }

        // Si on arrive ici, pas de rate limiting : on nettoie la session
        $request->getSession()->remove('rate_limit_wait_time');
        $request->getSession()->remove('is_rate_limited');

        // ÉTAPE 2 : Authentification normale
        return new Passport(
            new UserBadge($username),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Réinitialiser les rate limiters
        $username = (string) $request->request->get('_username', '');
        $this->loginIpLimiter->create($request->getClientIp())->reset();
        $this->loginUsernameLimiter->create($username)->reset();

        // Nettoyer la session
        $request->getSession()->remove('rate_limit_wait_time');
        $request->getSession()->remove('is_rate_limited');

        // Utiliser le success handler existant
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // Utiliser le failure handler existant
        return $this->failureHandler->onAuthenticationFailure($request, $exception);
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        // Rediriger vers la page de login client par défaut
        return new RedirectResponse($this->urlGenerator->generate('app_login_client'));
    }
}
