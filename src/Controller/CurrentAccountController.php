<?php

namespace App\Controller;

use App\Entity\CurrentAccount;
use App\Repository\CurrentAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CurrentAccountController extends GlobalAbstractController {
    private EntityManagerInterface $entityManager;
    private SerializerInterface $serializer;

    /**
     * Constructeur du controlleur de current account
     *
     * @param EntityManagerInterface $entityManager
     * @param ValidatorInterface $validator
     * @param UrlGeneratorInterface $urlGenerator
     * @param SerializerInterface $serializer
     */
    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator,
                                UrlGeneratorInterface $urlGenerator, SerializerInterface $serializer) {
        parent::__construct($validator, $urlGenerator, $serializer);
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    /**
     * Route permettant de récupérer tous les current accounts
     *
     * @param CurrentAccountRepository $currentAccountRepository
     * @return JsonResponse
     */
    #[Route('/api/current_accounts', name: 'current_accounts_get_all_current_accounts', methods: ["GET"])]
    public function get_all_current_accounts(CurrentAccountRepository $currentAccountRepository): JsonResponse {
        $currentsAccounts = $currentAccountRepository->findAll();
        $jsonCurrentAccounts = $this->serializer->serialize($currentsAccounts, "json",
                                                            $this->groupsGetCurrentAccount);

        return $this->jsonResponseOk($jsonCurrentAccounts);
    }

    /**
     * Route permettant de récupérer un current account suivant son id
     *
     * @param CurrentAccountRepository $currentAccountRepository
     * @param CurrentAccount $currentAccount
     * @return JsonResponse
     */
    #[Route('/api/current_accounts/{idCurrentAccount}', name: 'current_accounts_get_current_account_by_id',
            methods: ["GET"])]
    #[ParamConverter("current_account", options: ["id" => "idCurrentAccount"])]
    public function get_current_account_by_id(CurrentAccountRepository $currentAccountRepository,
                                              CurrentAccount $currentAccount): JsonResponse {
        $current_account = $currentAccountRepository->find($currentAccount);
        $jsonCurrentAccount = $this->serializer->serialize($current_account, "json", $this->groupsGetCurrentAccount);

        return$this->jsonResponseOk($jsonCurrentAccount);
    }

    /**
     * Route permettant de désactiver un current account
     *
     * @param CurrentAccount $currentAccount
     * @return JsonResponse
     */
    #[Route('/api/current_accounts/off/{idCurrentAccount}', name: 'current_accounts_current_account_turn_off',
            methods: ["DELETE"])]
    #[ParamConverter("current_account", options: ["id" => "idCurrentAccount"])]
    public function current_account_turn_off(CurrentAccount $currentAccount): JsonResponse {
        $currentAccount->setStatus(false);
        $this->entityManager->flush();
        return $this->jsonResponseNoContent();
    }

    /**
     * Route permettant de d'activer un current account
     *
     * @param CurrentAccount $currentAccount
     * @return JsonResponse
     */
    #[Route('/api/current_accounts/on/{idCurrentAccount}', name: 'current_accounts_current_account_turn_on',
            methods: ["DELETE"])]
    #[ParamConverter("current_account", options: ["id" => "idCurrentAccount"])]
    public function current_account_turn_on(CurrentAccount $currentAccount): JsonResponse {
        $currentAccount->setStatus(true);
        $this->entityManager->flush();
        return $this->jsonResponseNoContent();
    }
}
