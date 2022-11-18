<?php

namespace App\Controller;

use App\Entity\Picture;
use App\Repository\PictureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

class PictureController extends AbstractController {

    public function __construct(EntityManagerInterface $entityManager, SerializerInterface $serializer,
                                UrlGeneratorInterface $urlGenerator) {
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->urlGenerator = $urlGenerator;
    }

    #[Route('/api/pictures/{idPicture}', name: 'pictures_get_picture_by_id', methods: ["GET"])]
    #[ParamConverter("picture", options: ["id" => "idPicture"])]
    public function get_picture_by_id(Picture $picture, Request $request): JsonResponse {
        $location_picture = $picture->getPublicPath() . "/" . $picture->getRealPath();
        $location_path = $request->getUriForPath("/");
        $location = $location_path . str_replace("/assets", "assets", $location_picture);

        $jsonPicture = $this->serializer->serialize($picture, "json", ["groups" => "getPicture"]);

        return new JsonResponse($jsonPicture, Response::HTTP_OK, ["location" => $location], true);
    }

    #[Route('/api/pictures', name: 'pictures_create_picture', methods: ["POST"])]
    public function create_picture(Request $request): JsonResponse {
        $file = $request->files->get("file");
        $picture = new Picture();
        $picture->setFile($file);
        $picture->setMineType($file->getClientMimeType());
        $picture->setRealName($file->getClientOriginalName());
        $picture->setPublicPath("/assets/pictures");
        $picture->setStatus(true);
        $picture->setUploadDate(new \DateTime());

        $this->entityManager->persist($picture);
        $this->entityManager->flush();

        $jsonPicture = $this->serializer->serialize($picture, "json", ["groups" => "getPicture"]);
        $location = $this->urlGenerator->generate("pictures_get_picture_by_id",  ["idPicture" => $picture->getId()],
                                                UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonPicture, Response::HTTP_CREATED, ["location" => $location], true);
    }
}
