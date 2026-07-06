<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Controller\Trait\CurrentUserTrait;
use App\Dto\User\CreateUserRequest;
use App\Dto\User\UpdateUserProfileRequest;
use App\Dto\User\UpdateUserRequest;
use App\Dto\User\UserProfileResponse;
use App\Entity\User;
use App\Enum\UserRole;
use App\Http\ApiErrorResponse;
use App\Http\RequestPayloadParser;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @SuppressWarnings("PHPMD.CouplingBetweenObjects")
 */
class UserController extends AbstractController
{
    use CurrentUserTrait;

    public function __construct(
        private readonly RequestPayloadParser $requestPayloadParser,
    ) {
    }

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
    ): JsonResponse {
        $user = $this->getCurrentUser();

        $data = $this->requestPayloadParser->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $dto = $this->requestPayloadParser->validate(UpdateUserProfileRequest::fromArray($data));
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var UpdateUserProfileRequest $dto */
        if (\is_string($dto->firstName)) {
            $user->setFirstName($dto->firstName);
        }
        if (\is_string($dto->lastName)) {
            $user->setLastName($dto->lastName);
        }

        $error = $this->requestPayloadParser->validateEntity($user);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $entityManager->flush();

        return new JsonResponse(UserProfileResponse::fromUser($user)->toArray());
    }

    /**
     * @SuppressWarnings("PHPMD.ExcessiveMethodLength")
     * @SuppressWarnings("PHPMD.NPathComplexity")
     */
    #[IsGranted('ROLE_MANAGER')]
    #[Route('/user/{id}', name:'user_update', requirements: ['id' => '\d+'], methods: ['PATCH'])]
    public function updateById(
        int $id,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->findUserOrError($id, $userRepository);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $data = $this->requestPayloadParser->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $dto = $this->requestPayloadParser->validate(UpdateUserRequest::fromArray($data));
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var UpdateUserRequest $dto */
        if (\is_string($dto->firstName)) {
            $user->setFirstName($dto->firstName);
        }
        if (\is_string($dto->lastName)) {
            $user->setLastName($dto->lastName);
        }
        if (\is_string($dto->email)) {
            $conflict = $this->assertEmailAvailable($dto->email, $userRepository, $user->getId());
            if ($conflict instanceof JsonResponse) {
                return $conflict;
            }

            $user->setEmail($dto->email);
        }
        if (\is_bool($dto->isActive)) {
            $user->setIsActive($dto->isActive);
        }
        if (null !== $dto->roles) {
            $user->setRoles(UserRole::resolve($dto->roles));
        }

        $error = $this->requestPayloadParser->validateEntity($user);
        if ($error instanceof JsonResponse) {
            return $error;
        }

        $entityManager->flush();

        return new JsonResponse(UserProfileResponse::fromUser($user)->toArray());
    }

    #[IsGranted('ROLE_MANAGER')]
    #[Route('/user', name:'user_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
    ): JsonResponse {
        $data = $this->requestPayloadParser->decode($request);
        if ($data instanceof JsonResponse) {
            return $data;
        }

        $dto = $this->requestPayloadParser->validate(CreateUserRequest::fromArray($data));
        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        /** @var CreateUserRequest $dto */
        $conflict = $this->assertEmailAvailable((string) $dto->email, $userRepository);
        if ($conflict instanceof JsonResponse) {
            return $conflict;
        }

        $user = new User();
        $user
            ->setFirstName((string) $dto->firstName)
            ->setLastName((string) $dto->lastName)
            ->setEmail((string) $dto->email)
            ->setRoles(UserRole::resolve($dto->roles))
            ->setPassword($passwordHasher->hashPassword($user, (string) $dto->password));

        $error = $this->requestPayloadParser->validateEntity($user);
        if ($error instanceof JsonResponse) {
            return $error;
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
        $user = $this->findUserOrError($id, $userRepository);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        return new JsonResponse(UserProfileResponse::fromUser($user)->toArray());
    }

    #[IsGranted('ROLE_MANAGER')]
    #[Route('/user/{id}', name: 'user_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(
        int $id,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $user = $this->findUserOrError($id, $userRepository);
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $entityManager->remove($user);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private function findUserOrError(int $id, UserRepository $userRepository): User|JsonResponse
    {
        $user = $userRepository->find($id);
        if (!$user instanceof User) {
            return ApiErrorResponse::notFound('Utilisateur introuvable.');
        }

        return $user;
    }

    private function assertEmailAvailable(
        string $email,
        UserRepository $userRepository,
        ?int $excludeUserId = null,
    ): ?JsonResponse {
        $existing = $userRepository->findOneBy(['email' => $email]);
        if (!$existing instanceof User) {
            return null;
        }

        if (null !== $excludeUserId && $existing->getId() === $excludeUserId) {
            return null;
        }

        return ApiErrorResponse::conflict(
            'USER_ALREADY_EXISTS',
            'Cet email est déjà utilisé par un autre utilisateur.',
        );
    }
}
