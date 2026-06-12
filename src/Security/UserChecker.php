<?php

namespace App\Security;

use App\Entity\Banque;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Banque) {
            return;
        }

        if ($user->getStatut() !== 'actif') {
            throw new DisabledException('Votre compte banque est en attente de validation administrative.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?\Symfony\Component\Security\Core\Authentication\Token\TokenInterface $token = null): void
    {
        // Rien à faire après authentification pour l'instant.
    }
}
