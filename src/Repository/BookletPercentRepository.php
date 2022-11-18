<?php

namespace App\Repository;

use App\Entity\BookletPercent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookletPercent>
 *
 * @method BookletPercent|null find($id, $lockMode = null, $lockVersion = null)
 * @method BookletPercent|null findOneBy(array $criteria, array $orderBy = null)
 * @method BookletPercent[]    findAll()
 * @method BookletPercent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BookletPercentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookletPercent::class);
    }

    public function save(BookletPercent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(BookletPercent $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findAllActivated() {
        return $this->createQueryBuilder("bp")
            ->andWhere("bp.status = 1")
            ->getQuery()
            ->getResult();
    }

    public function findActivated(BookletPercent $bookletPercent) {
        return $this->createQueryBuilder("bp")
            ->andWhere("bp.status = 1")
            ->andWhere("bp.id = :idBookletPercent")
            ->setParameter("idBookletPercent", $bookletPercent->getId())
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return BookletPercent[] Returns an array of BookletPercent objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('b.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?BookletPercent
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
