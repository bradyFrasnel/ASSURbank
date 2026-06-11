<?php

namespace App\Repository;

use App\Dto\PaginatedResult;
use App\Entity\Banque;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Banque>
 */
class BanqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Banque::class);
    }

    /**
     * @return PaginatedResult<Banque>
     */
    public function findPaginated(int $page = 1, int $perPage = 10): PaginatedResult
    {
        $qb = $this->createQueryBuilder('b')
            ->orderBy('b.dateCreation', 'DESC');

        $total = (int) (clone $qb)
            ->select('COUNT(b.id)')
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

    public function countByStatut(string $statut): int
    {
        return (int) $this->count(['statut' => $statut]);
    }
}
