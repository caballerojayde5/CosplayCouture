<?php

namespace App\Repository;

use App\Entity\CustomOrder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CustomOrder>
 */
class CustomOrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CustomOrder::class);
    }

    /**
     * @return CustomOrder[] Returns an array of CustomOrder objects created by the given staff
     */
    public function findByCreatedBy(Staff $staff): array
    {
        return $this->createQueryBuilder('co')
            ->andWhere('co.createdBy = :staff')
            ->setParameter('staff', $staff)
            ->orderBy('co.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

//    /**
//     * @return CustomOrder[] Returns an array of CustomOrder objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('c.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

    public function getTotalSales(): float
    {
        return (float) $this->createQueryBuilder('co')
            ->select('SUM(co.price)')
            ->where('co.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getBestSellingCosplays(): array
    {
        $qb = $this->createQueryBuilder('co')
            ->select('co.cosplayName, COUNT(co.id) as count')
            ->where('co.status = :status')
            ->setParameter('status', 'completed')
            ->groupBy('co.cosplayName')
            ->orderBy('count', 'DESC')
            ->setMaxResults(5);

        return $qb->getQuery()->getResult();
    }

//    public function findOneBySomeField($value): ?CustomOrder
//    {
//        return $this->createQueryBuilder('c')
//            ->andWhere('c.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
