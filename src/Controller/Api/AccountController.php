<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\CreateAccountDTO;
use App\DTO\UpdateAccountDTO;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Service\Account\AccountService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class AccountController extends AbstractApiController
{
    public function __construct(
        private AccountService $accountService,
        private AccountRepository $accountRepository,
        private LoggerInterface $logger,
    ) {}

    #[Route('/api/accounts', name: 'api_accounts_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user       = $this->getUser();
        $activeOnly = filter_var($request->query->get('activeOnly', 'false'), FILTER_VALIDATE_BOOLEAN);

        try {
            $accounts = $this->accountService->list($user, $activeOnly);

            return $this->json(['data' => $accounts]);
        } catch (\Exception $e) {
            $this->logger->error('AccountController::list error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao listar contas.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/accounts', name: 'api_accounts_create', methods: ['POST'])]
    public function create(Request $request, ValidatorInterface $validator): JsonResponse
    {
        /** @var User $user */
        $user    = $this->getUser();
        $payload = json_decode((string) $request->getContent(), true) ?? [];

        $dto                  = new CreateAccountDTO();
        $dto->name            = (string) ($payload['name'] ?? '');
        $dto->institutionName = (string) ($payload['institutionName'] ?? '');
        $dto->accountType     = (string) ($payload['accountType'] ?? 'checking');
        $dto->currency        = (string) ($payload['currency'] ?? 'BRL');
        $dto->initialBalance  = (string) ($payload['initialBalance'] ?? '0.00');

        $errorResponse = $this->validateDTO($dto, $validator);

        if ($errorResponse !== null) {
            return $errorResponse;
        }

        try {
            $account = $this->accountService->create($user, $dto);

            return $this->json(
                ['data' => $this->accountService->serialize($account)],
                JsonResponse::HTTP_CREATED
            );
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            $this->logger->error('AccountController::create error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao criar conta.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/accounts/{id}', name: 'api_accounts_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user    = $this->getUser();
        $account = $this->accountRepository->findOneByIdAndUser($id, $user);

        if ($account === null) {
            return $this->notFound('Conta');
        }

        return $this->json(['data' => $this->accountService->serialize($account)]);
    }

    #[Route('/api/accounts/{id}', name: 'api_accounts_update', methods: ['PUT'])]
    public function update(int $id, Request $request, ValidatorInterface $validator): JsonResponse
    {
        /** @var User $user */
        $user    = $this->getUser();
        $account = $this->accountRepository->findOneByIdAndUser($id, $user);

        if ($account === null) {
            return $this->notFound('Conta');
        }

        $payload = json_decode((string) $request->getContent(), true) ?? [];

        $dto                  = new UpdateAccountDTO();
        $dto->name            = isset($payload['name']) ? (string) $payload['name'] : null;
        $dto->institutionName = isset($payload['institutionName']) ? (string) $payload['institutionName'] : null;
        $dto->accountType     = isset($payload['accountType']) ? (string) $payload['accountType'] : null;
        $dto->currency        = isset($payload['currency']) ? (string) $payload['currency'] : null;
        $dto->initialBalance  = isset($payload['initialBalance']) ? (string) $payload['initialBalance'] : null;
        $dto->isActive        = isset($payload['isActive']) ? (bool) $payload['isActive'] : null;

        $errorResponse = $this->validateDTO($dto, $validator);

        if ($errorResponse !== null) {
            return $errorResponse;
        }

        try {
            $updated = $this->accountService->update($account, $dto);

            return $this->json(['data' => $this->accountService->serialize($updated)]);
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            $this->logger->error('AccountController::update error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao atualizar conta.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/accounts/{id}/toggle', name: 'api_accounts_toggle', methods: ['PATCH'])]
    public function toggle(int $id): JsonResponse
    {
        /** @var User $user */
        $user    = $this->getUser();
        $account = $this->accountRepository->findOneByIdAndUser($id, $user);

        if ($account === null) {
            return $this->notFound('Conta');
        }

        try {
            $updated = $this->accountService->toggleActive($account);

            return $this->json(['data' => $this->accountService->serialize($updated)]);
        } catch (\Exception $e) {
            $this->logger->error('AccountController::toggle error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao alternar status da conta.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
