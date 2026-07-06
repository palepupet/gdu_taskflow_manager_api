<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationListInterface;

class ApiErrorResponse
{
    public static function create(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse([
            'code' => $code,
            'message' => $message,
        ], $status);
    }

    public static function badRequest(string $message = 'Donnée(s) JSON invalide(s)'): JsonResponse
    {
        return self::create('BAD_REQUEST', $message, Response::HTTP_BAD_REQUEST);
    }

    public static function notFound(string $message = 'Ressource introuvable.'): JsonResponse
    {
        return self::create('NOT_FOUND', $message, Response::HTTP_NOT_FOUND);
    }

    public static function conflict(string $code, string $message): JsonResponse
    {
        return self::create($code, $message, Response::HTTP_CONFLICT);
    }

    public static function validation(ConstraintViolationListInterface $violations): JsonResponse
    {
        $message = 'Donnée(s) invalide(s)';
        if ($violations->count() > 0) {
            $message .= ': '.$violations->get(0)->getMessage();
        }

        return self::create('VALIDATION_ERROR', $message, Response::HTTP_BAD_REQUEST);
    }
}
