<?php

namespace App\Repository;

use App\Dto\PaginatedResult;
use App\Entity\Client;
use App\Entity\Compte;
use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function sumMontantSucces(): float
    {
        $result = $this->createQueryBuilder('t')
            ->select('COALESCE(SUM(t.montant), 0)')
            ->andWhere('t.statut = :statut')
            ->setParameter('statut', 'succès')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    /**
     * @param array{type?: string, statut?: string, libelle?: string, date_debut?: string, date_fin?: string} $filters
     *
     * @return PaginatedResult<Transaction>
     */
    public function findByClientPaginated(Client $client, int $page = 1, int $perPage = 10, array $filters = []): PaginatedResult
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.compteSource', 'cs')
            ->leftJoin('t.compteDestination', 'cd')
            ->andWhere('cs.client = :client OR cd.client = :client')
            ->setParameter('client', $client)
            ->orderBy('t.dateTransaction', 'DESC');

        if (!empty($filters['type'])) {
            $qb->andWhere('t.type = :type')->setParameter('type', $filters['type']);
        }
        if (!empty($filters['statut'])) {
            $qb->andWhere('t.statut = :statut')->setParameter('statut', $filters['statut']);
        }
        if (!empty($filters['libelle'])) {
            $qb->andWhere('LOWER(t.libelle) LIKE :libelle')
                ->setParameter('libelle', '%'.mb_strtolower($filters['libelle']).'%');
        }
        if (!empty($filters['date_debut'])) {
            $qb->andWhere('t.dateTransaction >= :date_debut')
                ->setParameter('date_debut', new \DateTimeImmutable($filters['date_debut'].' 00:00:00'));
        }
        if (!empty($filters['date_fin'])) {
            $qb->andWhere('t.dateTransaction <= :date_fin')
                ->setParameter('date_fin', new \DateTimeImmutable($filters['date_fin'].' 23:59:59'));
        }

        $countQb = clone $qb;
        $total = (int) $countQb
            ->select('COUNT(t.id)')
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

    /**
     * @return PaginatedResult<Transaction>
     */
    public function findByComptePaginated(Compte $compte, int $page = 1, int $perPage = 10): PaginatedResult
    {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.compteSource = :compte OR t.compteDestination = :compte')
            ->setParameter('compte', $compte)
            ->orderBy('t.dateTransaction', 'DESC');

        $total = (int) (clone $qb)
            ->select('COUNT(t.id)')
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
