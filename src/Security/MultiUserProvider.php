<?php

namespace App\Security;

use App\Entity\Banque;
use App\Entity\Client;
use App\Repository\BanqueRepository;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class MultiUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly BanqueRepository $banqueRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $client = $this->clientRepository->findOneBy(['email' => $identifier]);
        if ($client instanceof Client) {
            return $client;
        }

        $banque = $this->banqueRepository->findOneBy(['email' => $identifier]);
        if ($banque instanceof Banque) {
            return $banque;
        }

        throw new UserNotFoundException(sprintf('Aucun utilisateur trouvé pour l\'email "%s".', $identifier));
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Client && !$user instanceof Banque) {
            throw new UnsupportedUserException(sprintf('Utilisateur de type "%s" non supporté.', $user::class));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return Client::class === $class
            || Banque::class === $class
            || is_subclass_of($class, Client::class)
            || is_subclass_of($class, Banque::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if ($user instanceof Client || $user instanceof Banque) {
            $user->setPassword($newHashedPassword);
            $this->entityManager->flush();
        }
    }
}
