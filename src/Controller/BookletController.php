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

class BookletController extends GlobalAbstractController {

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
    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator, UrlGeneratorInterface $urlGenerator, SerializerInterface $serializer) {
        parent::__construct($validator, $urlGenerator, $serializer);
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    /* #[Route('api/tests', name:"test.alexandre", methods: ["GET"])]
     public function getTests(EntityManagerInterface $em, CurrentAccountRepository $cRep)
     {
         dd($cRep->getAcountByMoney($em, 0, 10000000));
         die();
     }*/


    /**
     * Route permettant de récupérer tous les booklets
     *
     * @param Request $request
     * @param BookletRepository $bookletRepository
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route('/api/booklets', name: 'booklets_get_all_booklets', methods: ["GET"])]
    public function get_all_booklets(BookletRepository $bookletRepository, TagAwareCacheInterface $cache): JsonResponse {
        $jsonBooklets = $this->cachingAllBooklets($cache, $bookletRepository, $this->groupsGetBooklet, $this->serializer, "getAllBooklets");

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
    public function get_booklet_by_id(BookletRepository $bookletRepository, Booklet $booklet, TagAwareCacheInterface $cache): JsonResponse {
        $booklet = $bookletRepository->findActivated($booklet);

        if (sizeof($booklet) == 0) {
            return $this->jsonResponseNoContent();
        }
        $bookletData = $booklet[0];

        $jsonBooklet = $this->cachingOneBooklet($cache, $bookletRepository, $this->groupsGetBooklet, $this->serializer, "getBooklet", $bookletData);
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
    #[ParamConverter("booklet", options: ["id" => "idBooklet"])]
    public function booklet_turn_off(Booklet $booklet, TagAwareCacheInterface $cache): JsonResponse {
        $cache->invalidateTags(["bookletCache"]);
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
    #[ParamConverter("booklet", options: ["id" => "idBooklet"])]
    public function delete_booklet(Booklet $booklet): JsonResponse {
        $this->entityManager->remove($booklet);
        $this->entityManager->flush();
        return $this->jsonResponseNoContent();
    }

    /*-----------------------------------------Check OK up here-------------------------------------------------------*/

    /**
     * Route permettant de créer un booklet
     *
     * @param Request $request
     * @param BookletPercentRepository $percentRepository
     * @param CurrentAccountRepository $accountRepository
     * @return JsonResponse
     */
    #[Route('/api/booklets', name: 'booklets_delete_booklet', methods: ["POST"])]
    #[IsGranted("ROLE_ADMIN", message: "Vous n'avez rien à faire avec cette route.")]
    public function create_booklet(Request $request, BookletPercentRepository $percentRepository, CurrentAccountRepository $accountRepository): JsonResponse {
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

        $this->entityManager->persist($booklet);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups(["getBooklet"]);
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
     * @return JsonResponse
     */
    #[Route('/api/booklets/{idBooklet}', name: 'booklets_update_booklet', methods: ["PUT"])]
    #[ParamConverter("booklet", options: ["id" => "idBooklet"])]
    public function update_booklet(Request $request, Booklet $booklet, BookletPercentRepository $percentRepository): JsonResponse {
        $updateBooklet = $this->serializer->deserialize($request->getContent(), Booklet::class, "json");
        $content = $request->toArray();
        $updateBooklet->setName($updateBooklet->getName() ?? $booklet->getName());
        $updateBooklet->setStatus(true);

        $content = $request->toArray();
        $bookletPercent = $percentRepository->find($content["idBookletPercent"] ?? -1);
        $updateBooklet->setBookletPercent($bookletPercent);

        if ($this->validatorError($updateBooklet)) {
            return $this->jsonResponseValidatorError($updateBooklet);
        }

        $this->entityManager->persist($updateBooklet);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups(["getBooklet"]);
        $jsonBooklet = $this->serializer->serialize($updateBooklet, "json", $context);
        $location = $this->urlGenerator_get_booklet_by_id($updateBooklet);
        return $this->jsonResponseCreated($jsonBooklet, ["location" => $location]);
    }

    /**
     * Fonction permettant de récupérer le cache de tous les booklets et si il n'existe pas / est invalide de le créer
     *
     * @param TagAwareCacheInterface $cache
     * @param BookletRepository $repository
     * @param string $groups
     * @param SerializerInterface $serializer
     * @param string $cacheKey
     * @return string
     * @throws \Psr\Cache\InvalidArgumentException
     */
    public function cachingAllBooklets(TagAwareCacheInterface $cache, BookletRepository $repository, string $groups,
                            SerializerInterface $serializer, string $cacheKey) :string{
        return $cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($repository, $serializer, $groups) {
                $item->tag("bookletCache");
                $repositoryResults = $repository->findAllActivated();
                $context = SerializationContext::create()->setGroups([$groups]);
                return $serializer->serialize($repositoryResults, "json", $context);
            }
        );
    }

    /**
     * Fonction permettant de récupérer le cache d'un booklet et si il n'existe pas / est invalide de le créer
     *
     * @param TagAwareCacheInterface $cache
     * @param BookletRepository $repository
     * @param string $groups
     * @param SerializerInterface $serializer
     * @param string $cacheKey
     * @param Booklet $booklet
     * @return string
     * @throws InvalidArgumentException
     */
    public function cachingOneBooklet(TagAwareCacheInterface $cache, BookletRepository $repository, string $groups,
                            SerializerInterface $serializer, string $cacheKey, Booklet $booklet) :string{
        return $cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($repository, $serializer, $groups, $booklet) {
                $item->tag("bookletCache");
                $repositoryResults = $repository->findActivated($booklet);
                $context = SerializationContext::create()->setGroups([$groups]);
                return $serializer->serialize($repositoryResults, "json", $context);
            }
        );
    }
}
