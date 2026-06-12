<?php

namespace App\Repository;

use App\Dto\PaginatedResult;
use App\Entity\Banque;
use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof Client) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByBanque(Banque $banque): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.banque = :banque')
            ->setParameter('banque', $banque)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countComptesByBanque(Banque $banque): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(co.id)')
            ->join('c.comptes', 'co')
            ->andWhere('c.banque = :banque')
            ->setParameter('banque', $banque)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumSoldeByBanque(Banque $banque): float
    {
        return (float) $this->createQueryBuilder('c')
            ->select('COALESCE(SUM(co.solde), 0)')
            ->join('c.comptes', 'co')
            ->andWhere('c.banque = :banque')
            ->setParameter('banque', $banque)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return Client[]
     */
    public function findRecentByBanque(Banque $banque, int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.banque = :banque')
            ->setParameter('banque', $banque)
            ->orderBy('c.dateCreation', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return PaginatedResult<Client>
     */
    public function findByBanquePaginated(Banque $banque, int $page = 1, int $perPage = 10): PaginatedResult
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.banque = :banque')
            ->setParameter('banque', $banque)
            ->orderBy('c.dateCreation', 'DESC');

        $total = (int) (clone $qb)
            ->select('COUNT(c.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult(($page - 1) * $perPage)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return new PaginatedResult($items, $total, $page, $perPage);
    }
}
