<?php

namespace App\DataFixtures;

use App\Entity\Booklet;
use App\Entity\BookletPercent;
use App\Entity\CurrentAccount;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture {
    /**
     * Faker Generator
     * @var Generator
     */
    private Generator $faker;

    /**
     * Class Hashent le password
     *
     * @var UserPasswordHasherInterface
     */
    private UserPasswordHasherInterface $userPasswordHasher;

    /**
     * Constructeur de la class des fixtures
     */
    public function __construct(UserPasswordHasherInterface $userPasswordHasher) {
        $this->faker = Factory::create("fr_FR");
        $this->userPasswordHasher = $userPasswordHasher;

    }

    /**
     * Fonction permettant de créer mes fixtures
     *
     * @param ObjectManager $manager
     * @return void
     */
    public function load(ObjectManager $manager): void {
        for ($i=0; $i<=10; $i++) {
            $userUser = new User();
            $password = $this->faker->password(2, 6);
            $userUser->setUsername($this->faker->userName() . "@" . $password);
            $userUser->setRoles(["ROLE_USER"]);
            $userUser->setPassword($this->userPasswordHasher->hashPassword($userUser, $password));

            $manager->persist($userUser);
        }

        for ($i=0; $i<1; $i++) {
            $userUser = new User();
            $password = $this->faker->password(2, 6);
            $userUser->setUsername("admin");
            $userUser->setRoles(["ROLE_ADMIN"]);
            $userUser->setPassword($this->userPasswordHasher->hashPassword($userUser, "password"));

            $manager->persist($userUser);
        }

        $bkl_pr_array = [];
        for ($i = 0; $i <= 3; $i++) {
            $bkl_pr = new BookletPercent();
            $bkl_pr->setPercent($this->faker->randomFloat(2, 0, 3));
            $bkl_pr_array[] = $bkl_pr;
            $manager->persist($bkl_pr);
        }

        $crt_acc_array = [];
        for ($i = 0; $i < 10; $i++) {
            $crt_acc = new CurrentAccount();
            $crt_acc->setName($this->faker->word());
            $crt_acc->setMoney($this->faker->randomFloat(2));
            $crt_acc->setStatus(rand(0, 1));
            $crt_acc_array[] = $crt_acc;
            $manager->persist($crt_acc);
        }

        for ($i = 0; $i <= 60; $i++) {
            $bkl = new Booklet();
            $bkl->setName($this->faker->word());
            $bkl->setMoney($this->faker->randomFloat(2));
            $bkl->setBookletPercent($bkl_pr_array[array_rand($bkl_pr_array)]);
            $bkl->setCurrentAccount($crt_acc_array[array_rand($crt_acc_array)]);
            $bkl->setStatus(rand(0, 1));
            $manager->persist($bkl);
        }

        $manager->flush();
    }
}
