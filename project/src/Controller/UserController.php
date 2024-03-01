<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Annotations as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;


class UserController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer l'ensemble des utilisateurs du client connecté.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne la liste des utilisateurs",
     *     @Model(type=User::class, groups={"getUsers"})
     *     )
     * )
     * @OA\Tag(name="Users")
     *
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     * @param VersioningService $versioningService
     * @return JsonResponse
     */
    #[Route(
        path: '/api/users',
        name: 'api_users',
        methods: ['GET']
    )]
    public function getAllUsers(UserRepository $userRepository, SerializerInterface $serializer, VersioningService $versioningService): JsonResponse
    {
        $userList = $userRepository->findBy(['client' => $this->getUser()]);
        $context = SerializationContext::create()->setGroups(['getUsers']);
        $version = $versioningService->getVersion();
        $context->setVersion($version);
        $jsonUserList = $serializer->serialize($userList, 'json', $context);
        return new JsonResponse($jsonUserList, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de créer un utilisateur pour le client connecté.
     *
     * @OA\Response(
     *     response=200,
     *     description="Crée un utilisateur lié à un client",
     *     @Model(type=User::class, groups={"getUsers"})
     *     )
     * )
     * @OA\Tag(name="Users")
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $manager
     * @param UrlGeneratorInterface $urlGenerator
     * @param ClientRepository $clientRepository
     * @param ValidatorInterface $validator
     * @return JsonResponse
     */
    #[Route(
        path: '/api/users',
        name: 'api_create_user',
        methods: ['POST']
    )]
    public function createUser(
        Request $request,
        SerializerInterface $serializer,
        EntityManagerInterface $manager,
        UrlGeneratorInterface $urlGenerator,
        ClientRepository $clientRepository,
        ValidatorInterface $validator
    ): JsonResponse
    {
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        //On vérifie les erreurs
        $errors = $validator->validate($user);
        if ($errors->count() > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
        }
        $user->setClient($clientRepository->find($this->getUser()));

        $manager->persist($user);
        $manager->flush();

        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUser = $serializer->serialize($user, 'json', $context);

        $location = $urlGenerator->generate('api_detailUser', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Cette méthode permet d'obtenir le détail d' un utilisateur du client connecté.
     *
     * @OA\Response(
     *     response=200,
     *     description="Affiche les détails d'un utilisateur lié à un client",
     *     @Model(type=User::class, groups={"getUsers"})
     *     )
     * )
     * @OA\Tag(name="Users")
     *
     * @param User $user
     * @param SerializerInterface $serializer
     * @param VersioningService $versioningService
     * @return JsonResponse
     */
    #[Route(
        path: '/api/users/{id}',
        name: 'api_detailUser',
        methods: ['GET']
    )]
    public function getDetailUser(User $user, SerializerInterface $serializer, VersioningService $versioningService): JsonResponse
    {
        if ($user->getClient() === $this->getUser()) {
            $context = SerializationContext::create()->setGroups(['getUsers']);
            $version = $versioningService->getVersion();
            $context->setVersion($version);
            $jsonUser = $serializer->serialize($user, 'json', $context);
            return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
        } else {
            $message = "Vous êtes perdus. Vous n'avez pas les droits.";
            return new JsonResponse($message, Response::HTTP_NON_AUTHORITATIVE_INFORMATION);
        }
    }

    /**
     * Cette méthode permet de mettre à jour les informations d'un utilisateur du client connecté.
     *
     * @OA\Response(
     *     response=200,
     *     description="Mets à jour les détails d'un utilisateur lié à un client",
     *     @Model(type=User::class, groups={"getUsers"})
     *     )
     * )
     * @OA\Tag(name="Users")
     *
     * @param Request $request
     * @param User $currentUser
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $manager
     * @param ClientRepository $clientRepository
     * @param VersioningService $versioningService
     * @param ValidatorInterface $validator
     * @param TagAwareCacheInterface $cache
     * @return JsonResponse
     * @throws InvalidArgumentException
     */
    #[Route(
        path: '/api/users/{id}',
        name: 'api_updateUser',
        methods: ['PUT']
    )]
    public function updateUser(
        Request $request,
        User $currentUser,
        SerializerInterface $serializer,
        EntityManagerInterface $manager,
        ClientRepository $clientRepository,
        ValidatorInterface $validator,
        TagAwareCacheInterface $cache,
        VersioningService $versioningService
    ): JsonResponse
    {
        if ($currentUser->getClient() === $this->getUser()) {
            $newUser = $serializer->deserialize($request->getContent(), User::class, 'json');
            $currentUser->setUsername($newUser->getUsername());
            $version = $versioningService->getVersion();
            if ($version >= 2) {
                $currentUser->setComment($newUser->getComment());
            }
            //On vérifie les erreurs
            $errors = $validator->validate($currentUser);
            if ($errors->count() > 0) {
                return new JsonResponse($serializer->serialize($errors, 'json'), JsonResponse::HTTP_BAD_REQUEST, [], true);
            }

            $content = $request->toArray();
            $idClient = $content['idClient'] ?? $this->getUser();

            $currentUser->setClient($clientRepository->find($idClient));

            $manager->persist($currentUser);
            $manager->flush();

            //On vide le cache
            $cache->invalidateTags(["usersCache"]);

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);

        } else {
            //renvoyer une erreur car l'utilisateur n'appartient pas au client ou n'existe pas
            $message = "Cet utilisateur n'existe pas.";
            return new JsonResponse($message, Response::HTTP_NON_AUTHORITATIVE_INFORMATION);

        }
    }

    /**
     * Cette méthode permet de supprimer un utilisateur d'un client connecté.
     *
     * @OA\Response(
     *     response=200,
     *     description="Supprime un utilisateur lié à un client",
     *     @Model(type=User::class, groups={"getUsers"})
     *     )
     * )
     * @OA\Tag(name="Users")
     *
     * @param User $user
     * @param EntityManagerInterface $manager
     * @return JsonResponse
     */
    #[Route(
        path: '/api/users/{id}',
        name: 'api_deleteUser',
        methods: ['DELETE']
    )]
    public function deleteUser(
        User $user,
        EntityManagerInterface $manager
    ): JsonResponse
    {
        if ($user->getClient() === $this->getUser()) {
            $manager->remove($user);
            $manager->flush();
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } else {
            //renvoyer une erreur car l'utilisateur n'appartient pas au client ou n'existe pas
            $message = "Cet utilisateur n'existe pas.";
            return new JsonResponse($message, Response::HTTP_NON_AUTHORITATIVE_INFORMATION);

        }
    }

}
