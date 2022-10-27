<?php

namespace App\Repository;

use App\Entity\Booklet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Query\Parameter;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booklet>
 *
 * @method Booklet|null find($id, $lockMode = null, $lockVersion = null)
 * @method Booklet|null findOneBy(array $criteria, array $orderBy = null)
 * @method Booklet[]    findAll()
 * @method Booklet[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BookletRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) {
        parent::__construct($registry, Booklet::class);
    }

    public function save(Booklet $entity, bool $flush = false): void {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Booklet $entity, bool $flush = false): void {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findWithPagination($page, $limit) {
        $qb = $this->createQueryBuilder("b")->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);
        return $qb->getQuery()->getResult();
    }

    public function findAllActivated() {
        return $this->createQueryBuilder("b")
            ->andWhere("b.status = 1")
            ->getQuery()
            ->getResult();
    }

    public function findActivated(Booklet $booklet) {
        return $this->createQueryBuilder("b")
            ->andWhere("b.status = 1")
            ->andWhere("b.id = :idBooklet")
            ->setParameter("idBooklet", $booklet->getId())
            ->getQuery()
            ->getResult();
    }

    public function findBetweenDates(\DateTimeImmutable $dateStart, \DateTimeImmutable $dateEnd){
        $qb = $this->createQueryBuilder("b");
        $qb->add(
            "WHERE",
            $qb->expr()->andX(
                $qb->expr()->gte("b.createdAt", ":dateStart"),  // x supérieur ou égale à y
                $qb->expr()->lte("b.createdAt", ":dateEnd")     // x inférieur ou égale à y
            ))
        ->setParameters(
            new ArrayCollection(
                [
                    new Parameter("dateStart", $dateStart, Types::DATETIME_IMMUTABLE),
                    new Parameter("dateEnd", $dateEnd, Types::DATETIME_IMMUTABLE)
                ]
            )
        );

        return $qb->getQuery()->getResult();
    }


//    /**
//     * @return Booklet[] Returns an array of Booklet objects
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

//    public function findOneBySomeField($value): ?Booklet
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
