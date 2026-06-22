<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginRateLimiterSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $loginIpLimiter,
        private readonly RateLimiterFactory $loginUsernameLimiter,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $username = (string) $request->request->get('_username', '');
        $ip = $request->getClientIp();

        // Vérifier d'abord si on a déjà atteint la limite (avant de consommer un token supplémentaire !)
        $limiterIp = $this->loginIpLimiter->create($ip);
        $limitIp = $limiterIp->consume();

        $limiterUsername = null;
        $limitUsername = null;
        if ($username !== '') {
            $limiterUsername = $this->loginUsernameLimiter->create($username);
            $limitUsername = $limiterUsername->consume();
        }

        if (!$limitIp->isAccepted() || ($limitUsername && !$limitUsername->isAccepted())) {
            $waitTime = 60;
            if (!$limitIp->isAccepted()) {
                $waitTime = max($waitTime, $limitIp->getRetryAfter()->getTimestamp() - time());
            }
            if ($limitUsername && !$limitUsername->isAccepted()) {
                $waitTime = max($waitTime, $limitUsername->getRetryAfter()->getTimestamp() - time());
            }
            throw new TooManyLoginAttemptsAuthenticationException($waitTime);
        }
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $username = $event->getPassport()->getUser()->getUserIdentifier();

        // Réinitialiser les limiteurs lors d'un login réussi
        $limiterIp = $this->loginIpLimiter->create($request->getClientIp());
        $limiterIp->reset();

        $limiterUsername = $this->loginUsernameLimiter->create($username);
        $limiterUsername->reset();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AuthenticationFailureEvent::class => ['onAuthenticationFailure', 10000], // Priorité HAUTE !
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }
}
