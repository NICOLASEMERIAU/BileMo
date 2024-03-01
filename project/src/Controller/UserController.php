<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use App\Service\VersioningService;
use Doctrine\ORM\EntityManagerInterface;
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


class UserController extends AbstractController
{
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
