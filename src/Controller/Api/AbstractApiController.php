<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

abstract class AbstractApiController extends AbstractController
{
    protected function validateDTO(object $dto, ValidatorInterface $validator): ?JsonResponse
    {
        $violations = $validator->validate($dto);

        if (count($violations) === 0) {
            return null;
        }

        $errors = [];

        foreach ($violations as $violation) {
            $errors[] = [
                'field'   => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return $this->json(
            [
                'error'      => 'Dados inválidos.',
                'violations' => $errors,
            ],
            JsonResponse::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    protected function jsonError(string $message, int $status = JsonResponse::HTTP_BAD_REQUEST): JsonResponse
    {
        return $this->json(['error' => $message], $status);
    }

    protected function notFound(string $entity = 'Recurso'): JsonResponse
    {
        return $this->json(
            ['error' => sprintf('%s não encontrado.', $entity)],
            JsonResponse::HTTP_NOT_FOUND
        );
    }
}
