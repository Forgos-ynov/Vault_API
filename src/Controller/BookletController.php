<?php

namespace App\Controller;

use App\Entity\Booklet;
use App\Repository\BookletPercentRepository;
use App\Repository\BookletRepository;
use App\Repository\CurrentAccountRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializationContext;

class BookletController extends GlobalAbstractController
{

    private EntityManagerInterface $entityManager;
    private SerializerInterface $serializer;
    private BookletRepository $bookletRepository;

    /**
     * Constructeur de mon controlleur de booklet
     *
     * @param EntityManagerInterface $entityManager
     * @param ValidatorInterface $validator
     * @param UrlGeneratorInterface $urlGenerator
     * @param SerializerInterface $serializer
     * @param BookletRepository $bookletRepository
     */
    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator,
                                UrlGeneratorInterface $urlGenerator, SerializerInterface $serializer,
                                BookletRepository $bookletRepository)
    {
        parent::__construct($validator, $urlGenerator, $serializer);
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->bookletRepository = $bookletRepository;
    }

    /**
     * Route permettant de récupérer tous les booklets
     *
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/booklets', name: 'booklets_get_all_booklets', methods: ["GET"])]
    public function get_all_booklets(TagAwareCacheInterface $cache): JsonResponse
    {
        $jsonBooklets = $this->cachingAllBooklets($cache, "getAllBooklets");

        return $this->jsonResponseOk($jsonBooklets);
    }

    /**
     * Route permettant de récupérer un booklet suivant son id
     *
     * @param BookletRepository $bookletRepository
     * @param Booklet $booklet
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/booklets/{idBooklet}', name: 'booklets_get_booklet_by_id', methods: ["GET"])]
    #[ParamConverter("booklet", options: ["id" => "idBooklet"])]
    public function get_booklet_by_id(BookletRepository $bookletRepository, Booklet $booklet, TagAwareCacheInterface $cache): JsonResponse
    {
        $booklet = $bookletRepository->findActivated($booklet);

        if (sizeof($booklet) == 0) {
            return $this->jsonResponseNoContent();
        }
        $bookletData = $booklet[0];

        $jsonBooklet = $this->cachingOneBooklet($cache, "getBookletId", $bookletData);
        return $this->jsonResponseOk($jsonBooklet);
    }

    /**
     * Route permettant de désactiver un booklet
     *
     * @param Booklet $booklet
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/booklets/{idBooklet}', name: 'booklets_booklet_turn_off', methods: ["DELETE"])]
    #[IsGranted("ROLE_ADMIN", message: "Vous n'avez rien à faire avec cette route.")]
    #[ParamConverter("booklet", options: ["id" => "idBooklet"])]
    public function booklet_turn_off(Booklet $booklet, TagAwareCacheInterface $cache): JsonResponse
    {
        $this->invalideCacheBooklet($cache);
        $booklet->setStatus(false);
        $this->entityManager->flush();
        return $this->jsonResponseNoContent();
    }

    /**
     * Route permettant de supprimer totalement un booklet
     *
     * @param Booklet $booklet
     * @return JsonResponse
     */
    #[Route('', name: 'booklets_delete_booklet', methods: ["DELETE"])]
    #[IsGranted("ROLE_ADMI", message: "Vous n'avez rien à faire avec cette route.")]
    #[ParamConverter("booklet", options: ["id" => "idBooklet"])]
    public function delete_booklet(Booklet $booklet): JsonResponse
    {
        $this->entityManager->remove($booklet);
        $this->entityManager->flush();
        return $this->jsonResponseNoContent();
    }

    /**
     * Route permettant de créer un booklet
     *
     * @param Request $request
     * @param BookletPercentRepository $percentRepository
     * @param CurrentAccountRepository $accountRepository
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/booklets', name: 'booklets_delete_booklet', methods: ["POST"])]
    #[IsGranted("ROLE_ADMIN", message: "Vous n'avez rien à faire avec cette route.")]
    public function create_booklet(Request $request, BookletPercentRepository $percentRepository, CurrentAccountRepository $accountRepository, TagAwareCacheInterface $cache): JsonResponse
    {
        $booklet = $this->serializer->deserialize($request->getContent(), Booklet::class, "json");
        $booklet->setStatus(True);
        $today = new \DateTime();
        $today->format("Y-m-d H:i:s");
        $booklet->setCreatedAt($today);
        $content = $request->toArray();

        $bookletPercent = $percentRepository->find($content["idBookletPercent"] ?? -1);
        $booklet->setBookletPercent($bookletPercent);
        $currentAccount = $accountRepository->find($content["idCurrentAccount"] ?? -1);
        $booklet->setCurrentAccount($currentAccount);

        if ($this->validatorError($booklet)) {
            return $this->jsonResponseValidatorError($booklet);
        }

        $this->invalideCacheBooklet($cache);

        $this->entityManager->persist($booklet);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups([$this->groupsGetBooklet]);
        $jsonBooklet = $this->serializer->serialize($booklet, "json", $context);
        $location = $this->urlGenerator_get_booklet_by_id($booklet);
        return $this->jsonResponseCreated($jsonBooklet, ["location" => $location]);
    }

    /**
     * Route qui permet de modifier un booklet
     *
     * @param Request $request
     * @param Booklet $booklet
     * @param BookletPercentRepository $percentRepository
     * @param CurrentAccountRepository $currentAccountRepository
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/booklets/{idBooklet}', name: 'booklets_update_booklet', methods: ["PUT"])]
    #[ParamConverter("booklet", options: ["id" => "idBooklet"])]
    public function update_booklet(Request $request, Booklet $booklet, BookletPercentRepository $percentRepository, CurrentAccountRepository $currentAccountRepository, TagAwareCacheInterface $cache): JsonResponse
    {
        $updateBooklet = $this->serializer->deserialize($request->getContent(), Booklet::class, "json");
        $content = $request->toArray();
        $booklet = $this->loadBookletData($updateBooklet, $booklet);
        $booklet = $this->setBookletPercent($percentRepository, $content, $booklet);
        $booklet = $this->setCurrentAccount($currentAccountRepository, $content, $booklet);

        if ($this->validatorError($booklet)) {
            return $this->jsonResponseValidatorError($booklet);
        }

        $this->invalideCacheBooklet($cache);

        $this->entityManager->persist($booklet);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups([$this->groupsGetBooklet]);
        $jsonBooklet = $this->serializer->serialize($booklet, "json", $context);
        $location = $this->urlGenerator_get_booklet_by_id($booklet);
        return $this->jsonResponseCreated($jsonBooklet, ["location" => $location]);
    }

    /**
     * Fonction permettant de récupérer le cache de tous les booklets et si il n'existe pas / est invalide de le créer
     *
     * @param TagAwareCacheInterface $cache
     * @param string $cacheKey
     * @return string
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function cachingAllBooklets(TagAwareCacheInterface $cache, string $cacheKey): string
    {
        return $cache->get(
            $cacheKey,
            function (ItemInterface $item) {
                echo("Mise en cache.\n");
                $item->tag("bookletCache");
                $repositoryResults = $this->bookletRepository->findAllActivated();
                $context = SerializationContext::create()->setGroups([$this->groupsGetBooklet]);
                return $this->serializer->serialize($repositoryResults, "json", $context);
            }
        );
    }

    /**
     * Fonction permettant de récupérer le cache d'un booklet et si il n'existe pas / est invalide de le créer
     *
     * @param TagAwareCacheInterface $cache
     * @param string $cacheKey
     * @param Booklet $booklet
     * @return string
     * @throws InvalidArgumentException
     */
    private function cachingOneBooklet(TagAwareCacheInterface $cache, string $cacheKey, Booklet $booklet): string
    {
        return $cache->get(
            $cacheKey . $booklet->getId(),
            function (ItemInterface $item) use ($booklet) {
                echo("Mise en cache.\n");
                $item->tag("bookletCache");
                $repositoryResults = $this->bookletRepository->findActivated($booklet);
                $context = SerializationContext::create()->setGroups([$this->groupsGetBooklet]);
                return $this->serializer->serialize($repositoryResults, "json", $context);
            }
        );
    }

    /**
     * Fonction permettant d'invalider le cache de Tag bookletCache
     *
     * @param TagAwareCacheInterface $cache
     * @return void
     * @throws InvalidArgumentException
     */
    private function invalideCacheBooklet(TagAwareCacheInterface $cache): void
    {
        $cache->invalidateTags(["bookletCache"]);
    }

    /**
     * Fonction permettant de charger les donné modifiée dans update booklet sinon elle mets les données de base de booklet
     *
     * @param Booklet $updateBooklet
     * @param Booklet $booklet
     * @return Booklet
     */
    private function loadBookletData(Booklet $updateBooklet, Booklet $booklet): Booklet
    {
        $booklet->setName($updateBooklet->getName() ?? $booklet->getName());
        $booklet->setMoney($updateBooklet->getMoney() ?? $booklet->getMoney());
        $booklet->setCreatedAt($booklet->getCreatedAt());

        return $booklet;
    }

    /**
     * Fonction permettant d'initialiser dans updateBooklet le idBookletPercent passé dans le content sinon ce sera celle de booklet
     *
     * @param BookletPercentRepository $percentRepository
     * @param array $content
     * @param Booklet $booklet
     * @return Booklet
     */
    private function setBookletPercent(BookletPercentRepository $percentRepository, array $content, Booklet $booklet): Booklet
    {
        $bookletPercent = $percentRepository->find($content["idBookletPercent"] ?? $booklet->getBookletPercent()->getId());
        $booklet->setBookletPercent($bookletPercent);
        return $booklet;
    }

    /**
     * Fonction permettant d'initialiser dans updateBooklet le idCurrentAccount passé dans le content sinon ce sera celle de booklet
     *
     * @param CurrentAccountRepository $currentAccountRepository
     * @param array $content
     * @param Booklet $booklet
     * @return Booklet
     */
    private function setCurrentAccount(CurrentAccountRepository $currentAccountRepository, array $content, Booklet $booklet): Booklet
    {
        $currentAccount = $currentAccountRepository->find($content["idCurrentAccount"] ?? $booklet->getCurrentAccount()->getId());
        $booklet->setCurrentAccount($currentAccount);
        return $booklet;
    }
}
