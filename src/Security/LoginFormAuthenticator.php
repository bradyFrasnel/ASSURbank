<?php

namespace App\Security;

use App\Entity\Banque;
use App\Entity\Client;
use App\Repository\BanqueRepository;
use App\Repository\ClientRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ClientRepository $clientRepository,
        private readonly BanqueRepository $banqueRepository,
        private readonly RateLimiterFactory $loginIpLimiter,
        private readonly RateLimiterFactory $loginUsernameLimiter,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $username = (string) $request->request->get('_username', '');
        $password = (string) $request->request->get('_password', '');
        $csrfToken = (string) $request->request->get('_csrf_token', '');
        $loginType = (string) $request->request->get('_login_type', 'client');

        $request->getSession()->set(Security::LAST_USERNAME, $username);

        // Rate limiting
        $limiterIp = $this->loginIpLimiter->create($request->getClientIp());
        $limitIp = $limiterIp->consume();

        $limiterUsername = $this->loginUsernameLimiter->create($username);
        $limitUsername = $limiterUsername->consume();

        if (!$limitIp->isAccepted() || !$limitUsername->isAccepted()) {
            $waitTime = max($limitIp->getRetryAfter()->getTimestamp() - time(), $limitUsername->getRetryAfter()->getTimestamp() - time());
            throw new TooManyLoginAttemptsAuthenticationException($waitTime);
        }

        $userLoader = function ($userIdentifier) use ($loginType) {
            return match ($loginType) {
                'banque' => $this->banqueRepository->findOneBy(['email' => $userIdentifier]),
                'admin' => $this->clientRepository->findOneBy(['email' => $userIdentifier, 'role' => 'ROLE_ADMIN']),
                default => $this->clientRepository->findOneBy(['email' => $userIdentifier]),
            };
        };

        return new Passport(
            new UserBadge($username, $userLoader),
            new PasswordCredentials($password),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Réinitialiser les rate limiters
        $username = (string) $request->request->get('_username', '');
        $this->loginIpLimiter->create($request->getClientIp())->reset();
        $this->loginUsernameLimiter->create($username)->reset();

        // Redirection par rôle
        $user = $token->getUser();
        $loginType = (string) $request->request->get('_login_type', 'client');

        $expectedRole = match ($loginType) {
            'banque' => 'ROLE_BANQUE',
            'admin' => 'ROLE_ADMIN',
            default => 'ROLE_CLIENT',
        };

        $route = match (true) {
            $user instanceof Banque || in_array('ROLE_BANQUE', $user->getRoles(), true) => 'app_banque_dashboard',
            in_array('ROLE_ADMIN', $user->getRoles(), true) => 'app_admin_dashboard',
            default => 'app_client_dashboard',
        };

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate($route));
    }

    protected function getLoginUrl(Request $request): string
    {
        $loginType = (string) $request->request->get('_login_type', 'client');

        return match ($loginType) {
            'banque' => $this->urlGenerator->generate('app_login_banque'),
            'admin' => $this->urlGenerator->generate('app_login_admin'),
            default => $this->urlGenerator->generate('app_login_client'),
        };
    }
}
