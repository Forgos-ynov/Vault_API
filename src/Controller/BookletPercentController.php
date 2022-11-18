<?php

namespace App\Controller;

use App\Entity\BookletPercent;
use App\Repository\BookletPercentRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class BookletPercentController extends GlobalAbstractController
{
    private EntityManagerInterface $entityManager;
    private SerializerInterface $serializer;
    private BookletPercentRepository $bookletPercentRepository;

    /**
     * Constructeur de mon controlleur de booklet
     *
     * @param EntityManagerInterface $entityManager
     * @param ValidatorInterface $validator
     * @param BookletPercentRepository $bookletPercentRepository
     * @param UrlGeneratorInterface $urlGenerator
     * @param SerializerInterface $serializer
     */
    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator,
                                UrlGeneratorInterface $urlGenerator, SerializerInterface $serializer,
                                BookletPercentRepository $bookletPercentRepository)
    {
        parent::__construct($validator, $urlGenerator, $serializer);
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->bookletPercentRepository = $bookletPercentRepository;
    }

    /**
     * Route permettant de récupérer tous les booklets percent
     *
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/api/booklet_percents', name: 'bookletPercents_get_all_booklet_percent', methods: ["GET"])]
    public function get_all_booklet_percent(TagAwareCacheInterface $cache): JsonResponse
    {
        $jsonBooklets = $this->cachingAllBooklets($cache, "getAllBookletPercents");

        return $this->jsonResponseOk($jsonBooklets);
    }

    /**
     * ROute permettant de récupérer 1 booklet percent
     *
     * @param BookletPercent $bookletPercent
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/api/booklet_percents/{idBookletPercent}', name: 'booklet_percents_get_booklet_percent_by_id', methods: ["GET"])]
    #[ParamConverter("bookletPercent", options: ["id" => "idBookletPercent"])]
    public function get_booklet_percent_by_id(BookletPercent $bookletPercent, TagAwareCacheInterface $cache): JsonResponse
    {
        $bookletPercent = $this->bookletPercentRepository->findActivated($bookletPercent);

        if (sizeof($bookletPercent) == 0) {
            return $this->jsonResponseNoContent();
        }
        $bookletPercentData = $bookletPercent[0];

        $jsonBooklet = $this->cachingOneBookletPercent($cache, "getBookletPercentId", $bookletPercentData);
        return $this->jsonResponseOk($jsonBooklet);
    }

    /**
     * Route permettant de supprimer un booklet percent
     *
     * @param BookletPercent $bookletPercent
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/api/booklet_percents/{idBookletPercent}', name: 'booklet_percents_booklet_percent_turn_off', methods: ["DELETE"])]
    #[IsGranted("ROLE_ADMIN", message: "Vous n'avez rien à faire avec cette route.")]
    #[ParamConverter("bookletPercent", options: ["id" => "idBookletPercent"])]
    public function booklet_percent_turn_off(BookletPercent $bookletPercent, TagAwareCacheInterface $cache): JsonResponse
    {
        $this->invalideCacheBookletPercent($cache);
        $bookletPercent->setStatus(false);
        $this->entityManager->flush();
        return $this->jsonResponseNoContent();
    }

    /**
     * Route permettant de créer un booklet percent
     *
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/api/booklet_percents', name: 'booklet_percents_create_booklet_percent', methods: ["POST"])]
    #[IsGranted("ROLE_ADMIN", message: "Vous n'avez rien à faire avec cette route.")]
    public function create_booklet_percent(Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $bookletPercent = $this->serializer->deserialize($request->getContent(), BookletPercent::class, "json");
        $bookletPercent->setStatus(True);

        if ($this->validatorError($bookletPercent)) {
            return $this->jsonResponseValidatorError($bookletPercent);
        }

        $this->invalideCacheBookletPercent($cache);

        $this->entityManager->persist($bookletPercent);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups([$this->groupsGetBookletPercent]);
        $jsonBookletPercent = $this->serializer->serialize($bookletPercent, "json", $context);
        $location = $this->urlGenerator_get_booklet_percent_by_id($bookletPercent);
        return $this->jsonResponseCreated($jsonBookletPercent, ["location" => $location]);
    }

    #[Route('/api/booklet_percents/{idBookletPercent}', name: 'booklet_percents_update_booklet_percent', methods: ["PUT"])]
    #[ParamConverter("bookletPercent", options: ["id" => "idBookletPercent"])]
    public function update_booklet_percent(Request $request, BookletPercent $bookletPercent, TagAwareCacheInterface $cache): JsonResponse
    {
        $updateBookletPercent = $this->serializer->deserialize($request->getContent(), BookletPercent::class, "json");
        $bookletPercent->setPercent($updateBookletPercent->getPercent() ?? $bookletPercent->getPercent());


        if ($this->validatorError($bookletPercent)) {
            return $this->jsonResponseValidatorError($bookletPercent);
        }

        $this->invalideCacheBookletPercent($cache);

        $this->entityManager->persist($bookletPercent);
        $this->entityManager->flush();

        $context = SerializationContext::create()->setGroups([$this->groupsGetBookletPercent]);
        $jsonBookletPercent = $this->serializer->serialize($bookletPercent, "json", $context);
        $location = $this->urlGenerator_get_booklet_percent_by_id($bookletPercent);
        return $this->jsonResponseCreated($jsonBookletPercent, ["location" => $location]);
    }

    /**
     * Fonction permettant de mettre en cache la route qui récupère tous les booklet percent
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
                $item->tag("bookletPercentCache");
                $repositoryResults = $this->bookletPercentRepository->findAllActivated();
                $context = SerializationContext::create()->setGroups([$this->groupsGetBookletPercent]);
                return $this->serializer->serialize($repositoryResults, "json", $context);
            }
        );
    }

    /**
     * Fonction permettant de mettre en cache pour la route qui récupère 1 booklet percent
     *
     * @param TagAwareCacheInterface $cache
     * @param string $cacheKey
     * @param BookletPercent $bookletPercent
     * @return string
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function cachingOneBookletPercent(TagAwareCacheInterface $cache, string $cacheKey, BookletPercent $bookletPercent): string
    {
        return $cache->get(
            $cacheKey . $bookletPercent->getId(),
            function (ItemInterface $item) use ($bookletPercent) {
                echo("Mise en cache.\n");
                $item->tag("bookletPercentCache");
                $repositoryResults = $this->bookletPercentRepository->findActivated($bookletPercent);
                $context = SerializationContext::create()->setGroups([$this->groupsGetBookletPercent]);
                return $this->serializer->serialize($repositoryResults, "json", $context);
            }
        );
    }

    /**
     * Fonction permettant d'invalider le cache des bookletsPercent
     *
     * @param TagAwareCacheInterface $cache
     * @return void
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function invalideCacheBookletPercent(TagAwareCacheInterface $cache): void
    {
        $cache->invalidateTags(["bookletPercentCache"]);
    }
}
