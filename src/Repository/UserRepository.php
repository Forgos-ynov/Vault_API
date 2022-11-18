<?php

namespace App\Repository;

use App\Controller\UserController;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use phpDocumentor\Reflection\Types\Integer;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newHashedPassword);

        $this->add($user, true);
    }

    public function findAllActivated() {
        return $this->createQueryBuilder("b")
            ->andWhere("b.status = 1")
            ->getQuery()
            ->getResult();
    }

    public function findActivated(User $user) {
        return $this->createQueryBuilder("u")
            ->andWhere("u.status = 1")
            ->andWhere("u.id = :idUser")
            ->setParameter("idUser", $user->getId())
            ->getQuery()
            ->getResult();
    }

    public function get_all_money_one_user(int $userId) {
        $connection = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT SUM(
                    (
                        SELECT SUM(booklet.money) FROM booklet WHERE current_account_id = (
                            SELECT id FROM current_account WHERE current_account.id = (
                                SELECT user.current_account_id FROM `user` WHERE user.id = :userId
                            )
                        )
                    ) + current_account.money
                ) AS totalMoney FROM current_account WHERE current_account.id = (SELECT user.current_account_id FROM `user` WHERE user.id = :userId)
        ";

        $request = $connection->prepare($sql);
        $result = $request->executeQuery(["userId" => $userId]);

        return $result->fetchAllAssociative();

    }

//    /**
//     * @return User[] Returns an array of User objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?User
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
