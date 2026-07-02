<?php

declare(strict_types=1);

namespace App\Controller\User;

use App\Dto\User\UpdateUserProfileRequest;
use App\Dto\User\UserProfileResponse;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
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
                'message' => 'Donée(s) invalide(s): '.$violations->get(0)->getMessage(),
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

    private function getCurrentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
