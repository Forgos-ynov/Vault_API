<?php

namespace App\Controller;

use App\Entity\CurrentAccount;
use App\Repository\BookletRepository;
use App\Repository\CurrentAccountRepository;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Psr\Cache\InvalidArgumentException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CurrentAccountController extends GlobalAbstractController
{
    private EntityManagerInterface $entityManager;
    private SerializerInterface $serializer;

    /**
     * Constructeur de mon controlleur de booklet
     *
     * @param EntityManagerInterface $entityManager
     * @param ValidatorInterface $validator
     * @param UrlGeneratorInterface $urlGenerator
     * @param SerializerInterface $serializer
     */
    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, UrlGeneratorInterface $urlGenerator, SerializerInterface $serializer)
    {
        parent::__construct($validator, $urlGenerator, $serializer);
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    /**
     * Route permettant de récupérer tous les current accounts
     *
     * @param CurrentAccountRepository $accountRepository
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/currentAccounts', name: 'currentAccounts_get_all_current_accounts', methods: ["GET"])]
    public function get_all_current_accounts(CurrentAccountRepository $accountRepository, TagAwareCacheInterface $cache): JsonResponse
    {
        $jsonCurrentAccounts = $this->cachingAllCurrentAccounts($cache, $accountRepository, $this->serializer, "getAllCurrentAccounts");

        return $this->jsonResponseOk($jsonCurrentAccounts);
    }

    /**
     * Route permettant récupérer un current account suivant son id
     *
     * @param CurrentAccountRepository $accountRepository
     * @param CurrentAccount $account
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     */
    #[Route('/api/currentAccounts/{idCurrentAccount}', name: 'currentAccounts_get_current_account_by_id', methods: ["GET"])]
    #[ParamConverter("account", options: ["id" => "idCurrentAccount"])]
    public function get_current_account_by_id(CurrentAccountRepository $accountRepository, CurrentAccount $account, TagAwareCacheInterface $cache): JsonResponse
    {
        $account = $accountRepository->findActivated($account);

        if (sizeof($account) == 0) {
            return $this->jsonResponseNoContent();
        }
        $accountData = $account[0];

        $jsonAccount = $this->cachingOneCurrentAccount($cache, $accountRepository, $this->serializer, "getAccountId", $accountData);
        return $this->jsonResponseOk($jsonAccount);
    }

    /**
     * Route permettant de définir le status d'un current account à false
     *
     * @param CurrentAccount $account
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/currentAccounts/{idCurrentAccount}', name: 'currentAccounts_current_account_turn_off', methods: ["DELETE"])]
    #[ParamConverter("account", options: ["id" => "idCurrentAccount"])]
    public function current_account_turn_off(CurrentAccount $account, TagAwareCacheInterface $cache): JsonResponse
    {
        $this->invalideCacheCurrentAccount($cache);
        $account->setStatus(false);
        $this->entityManager->flush();
        return $this->jsonResponseNoContent();
    }

    #[Route('/api/currentAccounts', name: 'currentAccounts_create_current_account', methods: ["POST"])]
    #[IsGranted("ROLE_ADMIN", message: "Vous n'avez rien à faire avec cette route.")]
    public function create_current_account(Request $request, CurrentAccountRepository $accountRepository, TagAwareCacheInterface $cache, BookletRepository $bookletRepository, UserRepository $userRepository): JsonResponse
    {
        $account = $this->serializer->deserialize($request->getContent(), CurrentAccount::class, "json");
        $account->setStatus(True);
        $today = new \DateTime();
        $today->format("Y-m-d H:i:s");
        $userId = $this->getUser()->getId();
        if (!isset($userId) && !is_null($userId)) {
            $this->jsonResponseUnauthorized();
        }
        $user = $userRepository->find($userId);
        $account->addUser($user);
        $account->setCreatedAt($today);

        $content = $request->toArray();


        foreach ($content["idBooklets"] as $idBooklet) {
            $booklet = $bookletRepository->find($idBooklet["idBooklet"] ?? -1);
            $account->addBooklet($booklet);
        }

        if ($this->validatorError($account)) {
            return $this->jsonResponseValidatorError($account);
        }

        $this->invalideCacheCurrentAccount($cache);

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups(["$this->groupsGetCurrentAccount"]);
        $jsonCurrentAccount = $this->serializer->serialize($account, "json", $context);
        $location = $this->urlGenerator_get_current_account_by_id($account);
        return $this->jsonResponseCreated($jsonCurrentAccount, ["location" => $location]);
    }

    #[Route('/api/currentAccounts/{idCurrentAccount}', name: 'currentAccounts_update_current_account', methods: ["PUT"])]
    #[ParamConverter("account", options: ["id" => "idCurrentAccount"])]
    #[IsGranted("ROLE_ADMIN", message: "Vous n'avez rien à faire avec cette route.")]
    public function update_current_account(Request $request, CurrentAccount $account, TagAwareCacheInterface $cache, BookletRepository $bookletRepository, UserRepository $userRepository): JsonResponse
    {
        $updateAccount = $this->serializer->deserialize($request->getContent(), CurrentAccount::class, "json");
        $content = $request->toArray();
        $account = $this->loadCurrentAccountData($updateAccount, $account);

        $account = $this->setUser($userRepository, $content, $account);
        $account = $this->setBooklet($bookletRepository, $content, $account);

        if ($this->validatorError($account)) {
            return $this->jsonResponseValidatorError($account);
        }

        $this->invalideCacheCurrentAccount($cache);

        $this->entityManager->persist($account);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups([$this->groupsGetCurrentAccount]);
        $jsonCurrentAccount = $this->serializer->serialize($account, "json", $context);
        $location = $this->urlGenerator_get_current_account_by_id($account);
        return $this->jsonResponseCreated($jsonCurrentAccount, ["location" => $location]);
    }

    /**
     * Fonction permettant de mettre en cache tous les current accounts
     *
     * @param TagAwareCacheInterface $cache
     * @param CurrentAccountRepository $repository
     * @param SerializerInterface $serializer
     * @param string $cacheKey
     * @return string
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function cachingAllCurrentAccounts(TagAwareCacheInterface $cache, CurrentAccountRepository $repository,
                                               SerializerInterface    $serializer, string $cacheKey): string
    {
        return $cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($repository, $serializer) {
                echo("Mise en cache.\n");
                $item->tag("currentAccountCache");
                $repositoryResults = $repository->findAllActivated();
                $context = SerializationContext::create()->setGroups([$this->groupsGetCurrentAccount]);
                return $serializer->serialize($repositoryResults, "json", $context);
            }
        );
    }

    /**
     * Fonction permettant de mettre en cache un current account
     *
     * @param TagAwareCacheInterface $cache
     * @param CurrentAccountRepository $accountRepository
     * @param SerializerInterface $serializer
     * @param string $cacheKey
     * @param CurrentAccount $account
     * @return string
     * @throws InvalidArgumentException
     */
    private function cachingOneCurrentAccount(TagAwareCacheInterface $cache, CurrentAccountRepository $accountRepository,
                                              SerializerInterface    $serializer, string $cacheKey, CurrentAccount $account): string
    {
        return $cache->get(
            $cacheKey . $account->getId(),
            function (ItemInterface $item) use ($accountRepository, $serializer, $account) {
                echo("Mise en cache.\n");
                $item->tag("currentAccountCache");
                $repositoryResults = $accountRepository->findActivated($account);
                $context = SerializationContext::create()->setGroups([$this->groupsGetCurrentAccount]);
                return $serializer->serialize($repositoryResults, "json", $context);
            }
        );
    }

    /**
     * Fonction permettant d'invalider le cache de current account
     *
     * @param TagAwareCacheInterface $cache
     * @return void
     * @throws InvalidArgumentException
     */
    private function invalideCacheCurrentAccount(TagAwareCacheInterface $cache): void
    {
        $cache->invalidateTags(["currentAccountCache"]);
    }

    /**
     * Fonction permettant de charcher les données passées dans le json dans le account (sinon valeurs par défaut)
     *
     * @param CurrentAccount $updateAccount
     * @param CurrentAccount $account
     * @return CurrentAccount
     */
    private function loadCurrentAccountData(CurrentAccount $updateAccount, CurrentAccount $account): CurrentAccount
    {
        $account->setName($updateAccount->getName() ?? $account->getName());
        $account->setMoney($updateAccount->getMoney() ?? $account->getMoney());
        return $account;
    }

    /**
     * Fonction permettant d'initialiser le(s) idUser au current account passé dans le content sinon ça restera celui de account
     *
     * @param UserRepository $userRepository
     * @param array $content
     * @param CurrentAccount $account
     * @return CurrentAccount
     */
    private function setUser(UserRepository $userRepository, array $content, CurrentAccount $account): CurrentAccount
    {
        $usersAccount = $account->getUsers();

        foreach ($usersAccount as $userAccount) {
            $user = $userRepository->find($content["idUser"] ?? $userAccount->getId());
            $account->addUser($user);
        }
        return $account;
    }

    /**
     * Fonction permettant d'initialiser le(s) idBooklet au current account passé dans le content sinon ça restera celui de account
     *
     * @param BookletRepository $bookletRepository
     * @param array $content
     * @param CurrentAccount $account
     * @return CurrentAccount
     */
    private function setBooklet(BookletRepository $bookletRepository, array $content, CurrentAccount $account): CurrentAccount
    {
        $bookletsAccount = $account->getBooklets();

        foreach ($bookletsAccount as $bookletAccount) {
            $booklet = $bookletRepository->find($content["idBooklet"] ?? $bookletAccount->getId());
            $account->addBooklet($booklet);
        }
        return $account;
    }
}
