<?php

namespace App\Controller;

use App\Entity\Product;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\ProductRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializationContext;

class ProductController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer l'ensemble des produits.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne la liste des produits",
     *     @Model(type=Product::class, groups={"getProducts"})
     *     )
     * )
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="La page que l'on veut récupérer",
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="Le nombre d'éléments que l'on veut récupérer",
     *     @OA\Schema(type="int")
     * )
     * @OA\Tag(name="Products")
     *
     * @param ProductRepository $productRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @param TagAwareCacheInterface $cache
     * @param VersioningService $versioningService
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route(
        path: '/api/products',
        name: 'api_products',
        methods: ['GET']
    )]
    public function getAllProducts(
        ProductRepository $productRepository,
        SerializerInterface $serializer,
        Request $request,
        TagAwareCacheInterface $cache,
        VersioningService $versioningService
    ): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 3);

        $idCache = "getAllProducts-" . $page . "-" . $limit;

        $jsonProductList = $cache->get($idCache, function (ItemInterface $item) use ($productRepository, $page, $limit, $serializer, $versioningService) {
            $item->tag("productsCache");
            $productList = $productRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['getProducts']);
            $version = $versioningService->getVersion();
            $context->setVersion($version);
            return $serializer->serialize($productList, 'json', $context);
        });
        return new JsonResponse($jsonProductList, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de récupérer un produit en particulier.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne le détail d'un produit",
     *     @Model(type=Product::class, groups={"getProducts"})
     *     )
     * )
     * @OA\Tag(name="Products")
     *
     * @param Product $product
     * @param SerializerInterface $serializer
     * @param VersioningService $versioningService
     * @return JsonResponse
     */
    #[Route(
        path: '/api/products/{id}',
        name: 'api_detailProduct',
        methods: ['GET']
    )]
    public function getDetailProduct(Product $product, SerializerInterface $serializer, VersioningService $versioningService): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['getProducts']);
        $version = $versioningService->getVersion();
        $context->setVersion($version);
        $jsonProduct = $serializer->serialize($product, 'json', $context);
        return new JsonResponse($jsonProduct, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet à l'administrateur de créer un produit.
     *
     * @OA\Response(
     *     response=200,
     *     description="Création d'un produit",
     *     @Model(type=Product::class, groups={"getProducts"})
     *     )
     * )
     * @OA\Tag(name="Products")
     * @OA\RequestBody(@Model(type=Product::class, groups={"create"}))
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $manager
     * @param UrlGeneratorInterface $urlGenerator
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route(
        path: '/api/products',
        name: 'api_create_product',
        methods: ['POST']
    )]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un produit')]
    public function createProduct(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $manager,
        UrlGeneratorInterface $urlGenerator,
        ValidatorInterface $validator
    ): JsonResponse
    {
        $product = $serializer->deserialize($request->getContent(), Product::class, 'json');

        //On vérifie les erreurs
        $errors = $validator->validate($product);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }

        $manager->persist($product);
        $manager->flush();

        $context = SerializationContext::create()->setGroups(['getProducts']);
        $jsonProduct = $serializer->serialize($product, 'json', $context);

        $location = $urlGenerator->generate('api_detailProduct', ['id' => $product->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonProduct, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Cette méthode permet à l'administrateur de supprimer un produit.
     *
     * @OA\Response(
     *     response=204,
     *     description="Suppression d'un produit"
     *     )
     * )
     * @OA\Tag(name="Products")
     *
     * @param EntityManagerInterface $manager
     * @param Product $product
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route(
        path: '/api/products/{id}',
        name: 'api_delete_product',
        methods: ['DELETE']
    )]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un produit')]
    public function deleteProduct(
        Product $product,
        EntityManagerInterface $manager,
        TagAwareCacheInterface $cache
    ): JsonResponse
    {
        $cache->invalidateTags(["productsCache"]);
        $manager->remove($product);
        $manager->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

}
