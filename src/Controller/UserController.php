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
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserController extends GlobalAbstractController {

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator,
                                UrlGeneratorInterface $urlGenerator, SerializerInterface $serializer) {
        parent::__construct($validator, $urlGenerator, $serializer);
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    /**
     * @param UserRepository $userRepository
     * @param User $userEntry
     * @return JsonResponse
     */
    #[Route('/api/users/{idUser}', name: 'users_get_all_money_user', methods: ["GET"])]
    #[ParamConverter("userEntry", options: ["id" => "idUser"])]
    public function get_all_money_user(UserRepository $userRepository, User $userEntry): JsonResponse {
        $user = $userRepository->find($userEntry);
        $money = $userRepository->get_all_money_by_user($user);

        $jsonUser = $this->serializer->serialize($money, "json");
        return $this->jsonResponseOk($jsonUser);

    }
}
