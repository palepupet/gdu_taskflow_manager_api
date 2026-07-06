<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RequestPayloadParser
{
    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @return array<string, mixed>|JsonResponse
     */
    public function decode(Request $request): array|JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!\is_array($data)) {
            return ApiErrorResponse::badRequest();
        }

        /** @var array<string, mixed> $data * */
        return $data;
    }

    /**
     * @return object|JsonResponse
     */
    public function validate(object $dto): object
    {
        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            return ApiErrorResponse::validation($violations);
        }

        return $dto;
    }

    public function validateEntity(object $entity): ?JsonResponse
    {
        $violations = $this->validator->validate($entity);
        if ($violations->count() > 0) {
            return ApiErrorResponse::validation($violations);
        }

        return null;
    }
}
