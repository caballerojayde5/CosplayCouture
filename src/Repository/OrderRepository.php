<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @return Order[] Returns an array of Order objects created by the given staff
     */
    public function findByCreatedBy(Staff $staff): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.createdBy = :staff')
            ->setParameter('staff', $staff)
            ->orderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

//    /**
//     * @return Order[] Returns an array of Order objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('o.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

    public function getBestSellingCosplays(): array
    {
        $qb = $this->createQueryBuilder('o')
            ->innerJoin('o.costume', 'c')
            ->select('c.name as cosplayName, COUNT(o.id) as count')
            ->where('o.status = :status')
            ->setParameter('status', 'completed')
            ->groupBy('c.name')
            ->orderBy('count', 'DESC')
            ->setMaxResults(5);

        return $qb->getQuery()->getResult();
    }

//    public function findOneBySomeField($value): ?Order
//    {
//        return $this->createQueryBuilder('o')
//            ->andWhere('o.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
