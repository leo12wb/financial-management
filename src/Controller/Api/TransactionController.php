<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\CreateTransactionDTO;
use App\DTO\UpdateTransactionDTO;
use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use App\Repository\TransactionRepository;
use App\Service\Transaction\TransactionService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class TransactionController extends AbstractApiController
{
    public function __construct(
        private TransactionService $transactionService,
        private TransactionRepository $transactionRepository,
        private AccountRepository $accountRepository,
        private CategoryRepository $categoryRepository,
        private LoggerInterface $logger,
    ) {}

    #[Route('/api/transactions', name: 'api_transactions_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $filters = [
            'accountId'            => $request->query->get('accountId'),
            'categoryId'           => $request->query->get('categoryId'),
            'type'                 => $request->query->get('type'),
            'reconciliationStatus' => $request->query->get('reconciliationStatus'),
            'dateFrom'             => $request->query->get('dateFrom'),
            'dateTo'               => $request->query->get('dateTo'),
            'search'               => $request->query->get('search'),
            'source'               => $request->query->get('source'),
        ];

        $filters = array_filter($filters, static fn (mixed $v): bool => $v !== null);

        $page  = max(1, (int) $request->query->get('page', '1'));
        $limit = min(100, max(1, (int) $request->query->get('limit', '20')));

        try {
            $result = $this->transactionService->list($user, $filters, $page, $limit);

            return $this->json($result);
        } catch (\Exception $e) {
            $this->logger->error('TransactionController::list error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao listar transações.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/transactions', name: 'api_transactions_create', methods: ['POST'])]
    public function create(Request $request, ValidatorInterface $validator): JsonResponse
    {
        /** @var User $user */
        $user    = $this->getUser();
        $payload = json_decode((string) $request->getContent(), true) ?? [];

        $dto                    = new CreateTransactionDTO();
        $dto->accountId         = isset($payload['accountId']) ? (int) $payload['accountId'] : 0;
        $dto->categoryId        = isset($payload['categoryId']) ? (int) $payload['categoryId'] : null;
        $dto->description       = (string) ($payload['description'] ?? '');
        $dto->amount            = (string) ($payload['amount'] ?? '');
        $dto->type              = (string) ($payload['type'] ?? '');
        $dto->transactionDate   = (string) ($payload['transactionDate'] ?? '');
        $dto->externalReference = isset($payload['externalReference']) ? (string) $payload['externalReference'] : null;

        $errorResponse = $this->validateDTO($dto, $validator);

        if ($errorResponse !== null) {
            return $errorResponse;
        }

        try {
            $transaction = $this->transactionService->create(
                $user,
                $dto,
                $this->accountRepository,
                $this->categoryRepository
            );

            return $this->json(
                ['data' => $this->transactionService->serialize($transaction)],
                JsonResponse::HTTP_CREATED
            );
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            $this->logger->error('TransactionController::create error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao criar transação.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/transactions/{id}', name: 'api_transactions_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user        = $this->getUser();
        $transaction = $this->transactionRepository->findOneByIdAndUser($id, $user);

        if ($transaction === null) {
            return $this->notFound('Transação');
        }

        return $this->json(['data' => $this->transactionService->getDetail($transaction)]);
    }

    #[Route('/api/transactions/{id}', name: 'api_transactions_update', methods: ['PUT'])]
    public function update(int $id, Request $request, ValidatorInterface $validator): JsonResponse
    {
        /** @var User $user */
        $user        = $this->getUser();
        $transaction = $this->transactionRepository->findOneByIdAndUser($id, $user);

        if ($transaction === null) {
            return $this->notFound('Transação');
        }

        $payload = json_decode((string) $request->getContent(), true) ?? [];

        $dto                    = new UpdateTransactionDTO();
        $dto->accountId         = isset($payload['accountId']) ? (int) $payload['accountId'] : null;
        $dto->categoryId        = isset($payload['categoryId']) ? (int) $payload['categoryId'] : null;
        $dto->description       = isset($payload['description']) ? (string) $payload['description'] : null;
        $dto->amount            = isset($payload['amount']) ? (string) $payload['amount'] : null;
        $dto->type              = isset($payload['type']) ? (string) $payload['type'] : null;
        $dto->transactionDate   = isset($payload['transactionDate']) ? (string) $payload['transactionDate'] : null;
        $dto->externalReference = isset($payload['externalReference']) ? (string) $payload['externalReference'] : null;

        $errorResponse = $this->validateDTO($dto, $validator);

        if ($errorResponse !== null) {
            return $errorResponse;
        }

        try {
            $updated = $this->transactionService->update($transaction, $dto);

            return $this->json(['data' => $this->transactionService->serialize($updated)]);
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            $this->logger->error('TransactionController::update error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao atualizar transação.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
