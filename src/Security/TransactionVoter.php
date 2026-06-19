<?php

namespace App\Security;

use App\Entity\Client;
use App\Entity\Transaction;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Voter pour autoriser l'accès aux Transactions
 * 
 * Un client peut voir les transactions de ses propres comptes
 */
class TransactionVoter extends Voter
{
    public const VIEW = 'transaction_view';

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::VIEW && $subject instanceof Transaction;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        // Admin voit tout (grâce à la hiérarchie)
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        // Banque voit tout
        if ($this->accessDecisionManager->decide($token, ['ROLE_BANQUE'])) {
            return true;
        }

        // Seul un client peut voir
        if (!$user instanceof Client) {
            return false;
        }

        // Le client peut voir la transaction si elle concerne un de ses comptes
        $compteSource = $subject->getCompteSource();
        $compteDestination = $subject->getCompteDestination();

        $appartientAuClient = false;
        if ($compteSource && $compteSource->getClient() === $user) {
            $appartientAuClient = true;
        }
        if ($compteDestination && $compteDestination->getClient() === $user) {
            $appartientAuClient = true;
        }

        return $appartientAuClient;
    }
}
