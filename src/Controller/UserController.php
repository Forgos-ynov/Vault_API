<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CurrentAccountRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use phpDocumentor\Reflection\Types\Integer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\Serializer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class UserController extends GlobalAbstractController
{

    private UserRepository $userRepository;

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator,
                                UrlGeneratorInterface  $urlGenerator, SerializerInterface $serializer,
                                UserRepository $userRepository)
    {
        parent::__construct($validator, $urlGenerator, $serializer);
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->userRepository = $userRepository;
    }

    /**
     * Route permettant de récupérer tous les utilisateurs
     *
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/api/users', name: 'users_get_all_users', methods: ["GET"])]
    public function get_all_users(TagAwareCacheInterface $cache): JsonResponse
    {
        $jsonUsers = $this->cachingAllUsers($cache, "getAllUsers");

        return $this->jsonResponseOk($jsonUsers);
    }

    /**
     * Route permettant de récupérer un utilisateur suivant son id
     *
     * @param UserRepository $userRepository
     * @param User $user
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/api/users/{idUser}', name: 'users_get_user_by_id', methods: ["GET"])]
    #[ParamConverter("user", options: ["id" => "idUser"])]
    public function get_user_by_id(UserRepository $userRepository, User $user, TagAwareCacheInterface $cache): JsonResponse
    {
        $user = $userRepository->findActivated($user);

        if (sizeof($user) == 0) {
            return $this->jsonResponseNoContent();
        }
        $userData = $user[0];

        $jsonBooklet = $this->cachingOneUser($cache, "getUserId", $userData);
        return $this->jsonResponseOk($jsonBooklet);
    }

    /**
     * Route permettant de supprimer un utilisateur suivant son id
     *
     * @param User $user
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/api/users/{idUser}', name: 'users_user_turn_off', methods: ["DELETE"])]
    #[IsGranted("ROLE_ADMIN", message: "Vous n'avez rien à faire avec cette route.")]
    #[ParamConverter("user", options: ["id" => "idUser"])]
    public function user_turn_off(User $user, TagAwareCacheInterface $cache): JsonResponse
    {
        $this->invalideCacheUser($cache);
        $user->setStatus(false);
        $this->entityManager->flush();
        return $this->jsonResponseNoContent();
    }

    /**
     * Route permettant de créer un utilisateur
     *
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @param UserPasswordHasherInterface $passwordHasher
     * @param CurrentAccountRepository $accountRepository
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/api/users', name: 'users_create_user', methods: ["POST"])]
    #[IsGranted("ROLE_ADMIN", message: "Vous n'avez rien à faire avec cette route.")]
    public function create_user(Request $request, TagAwareCacheInterface $cache, UserPasswordHasherInterface $passwordHasher,
                                CurrentAccountRepository $accountRepository): JsonResponse
    {
        $user = $this->serializer->deserialize($request->getContent(), User::class, "json");
        $user->setStatus(True);
        $today = new \DateTime();
        $today->format("Y-m-d H:i:s");
        $user->setCreatedAt($today);
        $user->setRoles($user->getRoles());
        $content = $request->toArray();


        $user->setPassword(
            $passwordHasher->hashPassword($user, $user->getPassword())
        );

        $currentAccount = $accountRepository->find($content["idCurrentAccount"] ?? -1);
        $user->setCurrentAccount($currentAccount);

        if ($this->validatorError($user)) {
            return $this->jsonResponseValidatorError($user);
        }

        $this->invalideCacheUser($cache);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups([$this->groupsGetUser]);
        $jsonUser = $this->serializer->serialize($user, "json", $context);
        $location = $this->urlGenerator_get_user_by_id($user);
        return $this->jsonResponseCreated($jsonUser, ["location" => $location]);
    }

    /**
     * Route permettant de mettre à jour un utilisateur
     *
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @param User $user
     * @param CurrentAccountRepository $accountRepository
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/api/users/{idUser}', name: 'users_update_user', methods: ["PUT"])]
    #[ParamConverter("user", options: ["id" => "idUser"])]
    public function update_user(Request $request, TagAwareCacheInterface $cache, User $user, CurrentAccountRepository $accountRepository): JsonResponse
    {
        $updateUser = $this->serializer->deserialize($request->getContent(), User::class, "json");
        $content = $request->toArray();
        $user = $this->loadUserData($updateUser, $user);
        $user = $this->setCurrentAccount($accountRepository, $content, $user);

        if ($this->validatorError($user)) {
            return $this->jsonResponseValidatorError($user);
        }

        $this->invalideCacheUser($cache);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups([$this->groupsGetUser]);
        $jsonUser = $this->serializer->serialize($user, "json", $context);
        $location = $this->urlGenerator_get_user_by_id($user);
        return $this->jsonResponseCreated($jsonUser, ["location" => $location]);
    }

    #[Route('/api/users/filterMoney/{miniMoney}', name: 'users_get_users_have_more_x_money_in_total', methods: ["GET"])]
    #[ParamConverter("miniMoney", options: ["id" => "miniMoney"])]
    public function get_users_have_more_x_money_in_total(String $miniMoney): JsonResponse
    {
        $miniMoney = (int) $miniMoney;

        $users = $this->userRepository->findAllActivated();

        $usersReturn = [];
        foreach ($users as $user) {
            $allMoneyUser = $this->userRepository->get_all_money_one_user($user->getId());
            if ($allMoneyUser[0]["totalMoney"] >= $miniMoney){
                $usersReturn[] = $user;
            }
        }

        $context = SerializationContext::create()->setGroups($this->groupsGetUser);
        $jsonUsers = $this->serializer->serialize($usersReturn, "json", $context);
        return $this->jsonResponseOk($jsonUsers);

    }

    /**
     * Fonction permettant de mettre en cache la route qui récupère tous les users
     *
     * @param TagAwareCacheInterface $cache
     * @param string $cacheKey
     * @return string
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function cachingAllUsers(TagAwareCacheInterface $cache, string $cacheKey): string
    {
        return $cache->get(
            $cacheKey,
            function (ItemInterface $item) {
                echo("Mise en cache.\n");
                $item->tag("userCache");
                $repositoryResults = $this->userRepository->findAllActivated();
                $context = SerializationContext::create()->setGroups($this->groupsGetUser);
                return $this->serializer->serialize($repositoryResults, "json", $context);
            }
        );
    }

    /**
     * Fonction permettant de mettre en cache la route qui récupère 1 user
     *
     * @param TagAwareCacheInterface $cache
     * @param string $cacheKey
     * @param User $user
     * @return string
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function cachingOneUser(TagAwareCacheInterface $cache, string $cacheKey, User $user): string
    {
        return $cache->get(
            $cacheKey . $user->getId(),
            function (ItemInterface $item) use ($user) {
                echo("Mise en cache.\n");
                $item->tag("userCache");
                $repositoryResults = $this->userRepository->findActivated($user);
                $context = SerializationContext::create()->setGroups([$this->groupsGetUser]);
                return $this->serializer->serialize($repositoryResults, "json", $context);
            }
        );
    }

    /**
     * Fonction permettant d'invalider le cache de Tags userCache
     *
     * @param TagAwareCacheInterface $cache
     * @return void
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function invalideCacheUser(TagAwareCacheInterface $cache): void
    {
        $cache->invalidateTags(["userCache"]);
    }

    /**
     * Fonction permettant de charger les données déjà existante du user et de les modifier si demandé dans le body de la requête
     *
     * @param User $updateUser
     * @param User $user
     * @return User
     */
    private function loadUserData(User $updateUser, User $user): User
    {
        $user->setUsername($updateUser->getUsername() ?? $user->getUsername());
        $user->setPassword($updateUser->getPassword() ?? $user->getPassword());

        return $user;
    }

    private function setCurrentAccount(CurrentAccountRepository $currentAccountRepository, array $content, User $user): User
    {
        $currentAccount = $currentAccountRepository->find($content["idCurrentAccount"] ?? $user->getCurrentAccount()->getId());
        $user->setCurrentAccount($currentAccount);
        return $user;
    }
}
