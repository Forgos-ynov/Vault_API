<?php

namespace App\Controller;

use App\Repository\BookletPercentRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
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
     * Route permettant de récupérer tous les booklets percent
     *
     * @param BookletPercentRepository $percentRepository
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route('/api/bookletPercents', name: 'bookletPercents_get_all_booklet_percent', methods: ["GET"])]
    public function get_all_booklet_percent(BookletPercentRepository $percentRepository, TagAwareCacheInterface $cache): JsonResponse
    {
        $jsonBooklets = $this->cachingAllBooklets($cache, $percentRepository, $this->serializer, "getAllBookletPercents");

        return $this->jsonResponseOk($jsonBooklets);
    }

    /**
     * Fonction permettant de mettre en cache la route qui récupère tous les booklet percent
     *
     * @param TagAwareCacheInterface $cache
     * @param BookletPercentRepository $repository
     * @param SerializerInterface $serializer
     * @param string $cacheKey
     * @return string
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function cachingAllBooklets(TagAwareCacheInterface $cache, BookletPercentRepository $repository,
                                        SerializerInterface    $serializer, string $cacheKey): string
    {
        return $cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($repository, $serializer) {
                echo("Mise en cache.\n");
                $item->tag("bookletPercentCache");
                $repositoryResults = $repository->findAll();
                $context = SerializationContext::create()->setGroups([$this->groupsGetBookletPercent]);
                return $serializer->serialize($repositoryResults, "json", $context);
            }
        );
    }
}
