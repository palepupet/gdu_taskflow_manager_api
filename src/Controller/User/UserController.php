<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Dto\User\CreateUserRequest;
use App\Dto\User\UpdateUserProfileRequest;
use App\Dto\User\UserProfileResponse;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserController extends AbstractController
{
    #[Route('/me', name:'me_show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        $user = $this->getCurrentUser();

        return new JsonResponse(UserProfileResponse::fromUser($user)->toArray());
    }

    #[Route('/me', name:'me_update', methods: ['PATCH'])]
    public function update(
        Request $request,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse([
                'code' => 'BAD_REQUEST',
                'message' => 'Donnée(s) JSON invalide(s)',
            ], 400);
        }

        /** @var array<string, mixed> $data */
        $dto = UpdateUserProfileRequest::fromArray($data);

        $violations = $validator->validate($dto);
        if ($violations->count() > 0) {
            return new JsonResponse([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Donnée(s) invalide(s): '.$violations->get(0)->getMessage(),
            ], 400);
        }

        if (\is_string($dto->firstName)) {
            $user->setFirstName($dto->firstName);
        }
        if (\is_string($dto->lastName)) {
            $user->setLastName($dto->lastName);
        }

        $violations = $validator->validate($user);
        if ($violations->count() > 0) {
            return new JsonResponse([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Donnée(s) invalide(s)'.$violations->get(0)->getMessage(),
            ], 400);
        }

        $entityManager->flush();

        return new JsonResponse(UserProfileResponse::fromUser($user)->toArray());
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     */
    #[IsGranted('ROLE_MANAGER')]
    #[Route('/user', name:'user_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return new JsonResponse([
                'code' => 'BAD_REQUEST',
                'message' => 'Donnée(s) JSON invalide(s)',
            ], Response::HTTP_BAD_REQUEST);
        }

        /** @var array<string, mixed> $data */
        $dto = CreateUserRequest::fromArray($data);

        $violations = $validator->validate($dto);
        if ($violations->count() > 0) {
            return new JsonResponse([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Donnée(s) invalide(s): '.$violations->get(0)->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        if (null !== $userRepository->findOneBy(['email' => $dto->email])) {
            return new JsonResponse([
                'code' => 'USER_ALREADY_EXISTS',
                'message' => 'Cet email est déjà utilisé par un autre utilisateur.',
            ], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user
            ->setFirstName((string) $dto->firstName)
            ->setLastName((string) $dto->lastName)
            ->setEmail((string) $dto->email)
            ->setRoles($dto->resolveRoles())
            ->setPassword($passwordHasher->hashPassword($user, (string) $dto->password));

        $violations = $validator->validate($user);
        if ($violations->count() > 0) {
            return new JsonResponse([
                'code' => 'VALIDATION_ERROR',
                'message' => 'Donnée(s) invalide(s): '.$violations->get(0)->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse(
            UserProfileResponse::fromUser($user)->toArray(),
            Response::HTTP_CREATED,
        );
    }

    #[IsGranted('ROLE_MANAGER')]
    #[Route('/users', name:'users_list', methods: ['GET'])]
    public function list(UserRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findAll();

        $data = array_map(
            static fn (User $user): array => UserProfileResponse::fromUser($user)->toArray(),
            $users,
        );

        return new JsonResponse($data);
    }

    #[IsGranted('ROLE_MANAGER')]
    #[Route('/user/{id}', name: 'user_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);
        if (!$user instanceof User) {
            return new JsonResponse([
                'code' => 'NOT_FOUND',
                'message' => 'Utilisateur introuvable.',
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(UserProfileResponse::fromUser($user)->toArray());
    }

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
