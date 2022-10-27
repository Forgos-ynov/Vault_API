<?php

namespace App\Controller;

use App\Entity\Booklet;
use App\Repository\BookletPercentRepository;
use App\Repository\BookletRepository;
use App\Repository\CurrentAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

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
    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator,
                                UrlGeneratorInterface $urlGenerator, SerializerInterface $serializer) {
        parent::__construct($validator, $urlGenerator, $serializer);
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
    }

    /**
     * Route permettant de récupérer tous les booklets
     *
     * @param BookletRepository $bookletRepository
     * @return JsonResponse
     */
    #[Route('/api/booklets', name: 'booklets_get_all_booklets', methods: ["GET"])]
    public function get_all_booklets(Request $request,BookletRepository $bookletRepository,
                                     TagAwareCacheInterface $cache): JsonResponse {
//        $pages = $request->get("page", 1);
//        $limit = $request->get("limit", 10);
//        $booklets = $bookletRepository->findWithPagination($pages, $limit);

        $serializer = $this->serializer;
        $jsonBooklets = $cache->get(
            "getAllBooklets",
            function (ItemInterface $item) use ($bookletRepository, $serializer) {
                $item->tag("bookletCache");
                $booklets = $bookletRepository->findAllActivated();
                return $serializer->serialize($booklets, "json", $this->groupsGetBooklet);
            }
        );

        return $this->jsonResponseOk($jsonBooklets);
    }

    /**
     * Route permettant de récupérer un booklet suivant son id
     *
     * @param BookletRepository $bookletRepository
     * @param Booklet $booklet
     * @return JsonResponse
     */
    #[Route('/api/booklets/{idBooklet}', name: 'booklets_get_booklet_by_id', methods: ["GET"])]
    #[ParamConverter("booklet", options: ["id" => "idBooklet"])]
    public function get_booklet_by_id(BookletRepository $bookletRepository, Booklet $booklet): JsonResponse {
        $booklet = $bookletRepository->findActivated($booklet);

        if (sizeof($booklet) == 0){
            return $this->jsonResponseNoContent();
        }

        $jsonBooklet = $this->serializer->serialize($booklet, "json", $this->groupsGetBooklet);
        return $this->jsonResponseOk($jsonBooklet);
    }

    /**
     * Route permettant de désactiver un booklet
     *
     * @param Booklet $booklet
     * @return JsonResponse
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
    public function create_booklet(Request $request, BookletPercentRepository $percentRepository,
                                   CurrentAccountRepository $accountRepository): JsonResponse {

        $booklet = $this->serializer->deserialize(
            $request->getContent(),
            Booklet::class,
            "json"
        );
        $booklet->setStatus(True);
        $today = new \DateTime();
        $today->format("Y-m-d H:i:s");
        //dd(new \DateTime($today->format("Y-m-d H:i:s")));
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

        $jsonBooklet = $this->serializer->serialize($booklet, "json", $this->groupsGetBooklet);
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
    public function update_booklet(Request $request, Booklet $booklet,
                                   BookletPercentRepository $percentRepository): JsonResponse {

        $updateBooklet = $this->serializer->deserialize(
            $request->getContent(),
            Booklet::class,
            "json",
            [AbstractNormalizer::OBJECT_TO_POPULATE => $booklet]
        );

        $updateBooklet->setStatus(true);

        $content = $request->toArray();
        $bookletPercent = $percentRepository->find($content["idBookletPercent"] ?? -1);
        $updateBooklet->setBookletPercent($bookletPercent);

        if ($this->validatorError($updateBooklet)) {
            return $this->jsonResponseValidatorError($updateBooklet);
        }

        $this->entityManager->persist($updateBooklet);
        $this->entityManager->flush();

        $jsonBooklet = $this->serializer->serialize($updateBooklet, "json", $this->groupsGetBooklet);
        $location = $this->urlGenerator_get_booklet_by_id($updateBooklet);
        return $this->jsonResponseCreated($jsonBooklet, ["location" => $location]);
    }
}
