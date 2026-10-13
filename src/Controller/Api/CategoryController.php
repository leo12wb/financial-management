<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\CreateCategoryDTO;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Service\Category\CategoryService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class CategoryController extends AbstractApiController
{
    public function __construct(
        private CategoryService $categoryService,
        private CategoryRepository $categoryRepository,
        private LoggerInterface $logger,
    ) {}

    #[Route('/api/categories', name: 'api_categories_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user       = $this->getUser();
        $type       = $request->query->get('type');
        $activeOnly = filter_var($request->query->get('activeOnly', 'false'), FILTER_VALIDATE_BOOLEAN);

        $typeParam = ($type !== null && in_array($type, ['income', 'expense'], true)) ? $type : null;

        try {
            $categories = $this->categoryService->list($user, $typeParam, $activeOnly);

            return $this->json(['data' => $categories]);
        } catch (\Exception $e) {
            $this->logger->error('CategoryController::list error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao listar categorias.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/categories', name: 'api_categories_create', methods: ['POST'])]
    public function create(Request $request, ValidatorInterface $validator): JsonResponse
    {
        /** @var User $user */
        $user    = $this->getUser();
        $payload = json_decode((string) $request->getContent(), true) ?? [];

        $dto       = new CreateCategoryDTO();
        $dto->name = (string) ($payload['name'] ?? '');
        $dto->type = (string) ($payload['type'] ?? '');

        $errorResponse = $this->validateDTO($dto, $validator);

        if ($errorResponse !== null) {
            return $errorResponse;
        }

        try {
            $category = $this->categoryService->create($user, $dto);

            return $this->json(
                ['data' => $this->categoryService->serialize($category)],
                JsonResponse::HTTP_CREATED
            );
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            $this->logger->error('CategoryController::create error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao criar categoria.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/categories/{id}', name: 'api_categories_update', methods: ['PUT'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var User $user */
        $user     = $this->getUser();
        $category = $this->categoryRepository->findOneByIdAndUser($id, $user);

        if ($category === null) {
            return $this->notFound('Categoria');
        }

        $payload = json_decode((string) $request->getContent(), true) ?? [];

        $data = [];

        if (isset($payload['name'])) {
            $data['name'] = (string) $payload['name'];
        }

        if (array_key_exists('isActive', $payload)) {
            $data['isActive'] = (bool) $payload['isActive'];
        }

        try {
            $updated = $this->categoryService->update($category, $data);

            return $this->json(['data' => $this->categoryService->serialize($updated)]);
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            $this->logger->error('CategoryController::update error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao atualizar categoria.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/categories/{id}/toggle', name: 'api_categories_toggle', methods: ['PATCH'])]
    public function toggle(int $id): JsonResponse
    {
        /** @var User $user */
        $user     = $this->getUser();
        $category = $this->categoryRepository->findOneByIdAndUser($id, $user);

        if ($category === null) {
            return $this->notFound('Categoria');
        }

        try {
            $updated = $this->categoryService->toggleActive($category);

            return $this->json(['data' => $this->categoryService->serialize($updated)]);
        } catch (\Exception $e) {
            $this->logger->error('CategoryController::toggle error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao alternar status da categoria.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
