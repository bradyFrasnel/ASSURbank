<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
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

        $username = $request->request->get('_username', '');

        // Limiter par IP
        $limiterIp = $this->loginIpLimiter->create($request->getClientIp());
        $limitIp = $limiterIp->consume();

        // Limiter par username
        $limiterUsername = $this->loginUsernameLimiter->create($username);
        $limitUsername = $limiterUsername->consume();

        if (!$limitIp->isAccepted() || !$limitUsername->isAccepted()) {
            $waitTime = max($limitIp->getRetryAfter()->getTimestamp() - time(), $limitUsername->getRetryAfter()->getTimestamp() - time());
            throw new TooManyLoginAttemptsAuthenticationException($waitTime);
        }
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $username = $request->request->get('_username', '');

        // Réinitialiser les limiteurs lors d'un login réussi
        $limiterIp = $this->loginIpLimiter->create($request->getClientIp());
        $limiterIp->reset();

        $limiterUsername = $this->loginUsernameLimiter->create($username);
        $limiterUsername->reset();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AuthenticationFailureEvent::class => 'onAuthenticationFailure',
            LoginSuccessEvent::class => 'onLoginSuccess',
        ];
    }
}
