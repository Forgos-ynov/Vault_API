<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class UserController extends GlobalAbstractController
{

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator,
                                UrlGeneratorInterface  $urlGenerator, SerializerInterface $serializer)
    {
        parent::__construct($validator, $urlGenerator, $serializer);
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    /**
     * Route permettant de récupérer tous les utilisateurs
     *
     * @param UserRepository $userRepository
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/api/users', name: 'users_get_all_users', methods: ["GET"])]
    public function get_all_users(UserRepository $userRepository, TagAwareCacheInterface $cache): JsonResponse
    {
        $jsonUsers = $this->cachingAllUsers($cache, $userRepository, $this->serializer, "getAllUsers");

        return $this->jsonResponseOk($jsonUsers);
    }

    /**
     * Fonction permettant de mettre en cache la route qui récupère tous les utilisateurs
     *
     * @param TagAwareCacheInterface $cache
     * @param UserRepository $repository
     * @param SerializerInterface $serializer
     * @param string $cacheKey
     * @return string
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function cachingAllUsers(TagAwareCacheInterface $cache, UserRepository $repository,
                                        SerializerInterface    $serializer, string $cacheKey): string
    {
        return $cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($repository, $serializer) {
                echo("Mise en cache.\n");
                $item->tag("userCache");
                $repositoryResults = $repository->findAllActivated();
                $context = SerializationContext::create()->setGroups($this->groupsGetUser);
                return $serializer->serialize($repositoryResults, "json", $context);
            }
        );
    }
}
