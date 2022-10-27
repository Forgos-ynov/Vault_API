<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserController extends GlobalAbstractController {

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator,
                                UrlGeneratorInterface $urlGenerator, SerializerInterface $serializer) {
        parent::__construct($validator, $urlGenerator, $serializer);
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    #[Route('/api/users/{idUser}', name: 'users_get_all_money_user', methods: ["GET"])]
    #[ParamConverter("userEntry", options: ["id" => "idUser"])]
    public function get_all_money_user(UserRepository $userRepository, User $userEntry): JsonResponse {
        $user = $userRepository->find($userEntry);
        $currentAccount = $user->getCurrentAccount();
        $money = $currentAccount->getMoney();
        foreach ($currentAccount->getBooklets() as $booklet) {
            $money = $money + $booklet->getMoney();
        }

        // A faire le return
        return $this->jsonResponseNoContent();

    }
}
