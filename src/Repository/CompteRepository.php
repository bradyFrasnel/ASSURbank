<?php

namespace App\Repository;

use App\Entity\Banque;
use App\Entity\Client;
use App\Entity\Compte;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Compte>
 */
class CompteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Compte::class);
    }

    public function countByStatutAndBanque(Banque $banque, string $statut): int
    {
        return (int) $this->createQueryBuilder('co')
            ->select('COUNT(co.id)')
            ->join('co.client', 'c')
            ->andWhere('c.banque = :banque')
            ->andWhere('co.statut = :statut')
            ->setParameter('banque', $banque)
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatutAndClient(Client $client, string $statut): int
    {
        return (int) $this->createQueryBuilder('co')
            ->select('COUNT(co.id)')
            ->andWhere('co.client = :client')
            ->andWhere('co.statut = :statut')
            ->setParameter('client', $client)
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
