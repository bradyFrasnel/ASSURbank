<?php

namespace App\Security;

use App\Entity\Client;
use App\Entity\Compte;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Voter pour autoriser les actions sur un Compte
 *
 * Seul le client propriétaire du compte (ou un admin/banque) peut le voir/modifier
 */
class CompteVoter extends Voter
{
    public const VIEW = 'compte_view';
    public const EDIT = 'compte_edit';
    public const OPERATIONS = 'compte_operations'; // Dépôt, retrait, virement

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::OPERATIONS])
            && $subject instanceof Compte;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof UserInterface) {
            return false;
        }

        // Admin a tous les droits (grâce à la hiérarchie)
        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
             return true;
        }

        // Banque a tous les droits sur ses clients
        if ($this->accessDecisionManager->decide($token, ['ROLE_BANQUE'])) {
             return true;
        }

        // Seul un client peut effectuer ces actions
        if (!$user instanceof Client) {
            return false;
        }

        return match($attribute) {
            self::VIEW => $this->peutVoir($subject, $user),
            self::EDIT => $this->peutModifier($subject, $user),
            self::OPERATIONS => $this->peutEffectuerOperations($subject, $user),
            default => false,
        };
    }

    /**
     * Peut voir le compte ?
     */
    private function peutVoir(Compte $compte, Client $user): bool
    {
        return $compte->getClient() === $user;
    }

    /**
     * Peut modifier le compte ?
     */
    private function peutModifier(Compte $compte, Client $user): bool
    {
        return $compte->getClient() === $user;
    }

    /**
     * Peut effectuer des opérations (dépôt, retrait, virement) ?
     */
    private function peutEffectuerOperations(Compte $compte, Client $user): bool
    {
        // Le compte doit appartenir au client ET être actif
        return $compte->getClient() === $user && $compte->getStatut() === 'actif';
    }
}
