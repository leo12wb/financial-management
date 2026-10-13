<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\DTO\ConfirmReconciliationDTO;
use App\Entity\User;
use App\Repository\ReconciliationRepository;
use App\Repository\StatementEntryRepository;
use App\Repository\TransactionRepository;
use App\Service\Reconciliation\ReconciliationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ReconciliationController extends AbstractApiController
{
    public function __construct(
        private ReconciliationService $reconciliationService,
        private ReconciliationRepository $reconciliationRepository,
        private TransactionRepository $transactionRepository,
        private StatementEntryRepository $statementEntryRepository,
        private LoggerInterface $logger,
    ) {}

    #[Route('/api/reconciliations', name: 'api_reconciliations_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user  = $this->getUser();
        $page  = max(1, (int) $request->query->get('page', '1'));
        $limit = min(100, max(1, (int) $request->query->get('limit', '20')));

        try {
            $result = $this->reconciliationRepository->findByUser($user, $page, $limit);

            $data = array_map(
                fn ($r): array => $this->reconciliationService->serialize($r),
                $result['data']
            );

            return $this->json(['data' => [
                'data'       => $data,
                'total'      => $result['total'],
                'page'       => $page,
                'limit'      => $limit,
                'totalPages' => $limit > 0 ? (int) ceil($result['total'] / $limit) : 1,
            ]]);
        } catch (\Exception $e) {
            $this->logger->error('ReconciliationController::list error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao listar conciliações.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/reconciliations/suggestions', name: 'api_reconciliations_suggestions', methods: ['GET'])]
    public function suggestions(Request $request): JsonResponse
    {
        /** @var User $user */
        $user      = $this->getUser();
        $accountId = $request->query->get('accountId');
        $accountIdInt = $accountId !== null && is_numeric($accountId) ? (int) $accountId : null;

        try {
            $suggestions = $this->reconciliationService->getSuggestions($user, $accountIdInt);

            return $this->json(['data' => $suggestions]);
        } catch (\Exception $e) {
            $this->logger->error('ReconciliationController::suggestions error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao buscar sugestões de conciliação.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/reconciliations/confirm', name: 'api_reconciliations_confirm', methods: ['POST'])]
    public function confirm(Request $request, ValidatorInterface $validator): JsonResponse
    {
        /** @var User $user */
        $user    = $this->getUser();
        $payload = json_decode((string) $request->getContent(), true) ?? [];

        $dto                   = new ConfirmReconciliationDTO();
        $dto->transactionId    = isset($payload['transactionId']) ? (int) $payload['transactionId'] : 0;
        $dto->statementEntryId = isset($payload['statementEntryId']) ? (int) $payload['statementEntryId'] : 0;

        $errorResponse = $this->validateDTO($dto, $validator);

        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $transaction = $this->transactionRepository->findOneByIdAndUser($dto->transactionId, $user);

        if ($transaction === null) {
            return $this->notFound('Transação');
        }

        $entry = $this->statementEntryRepository->find($dto->statementEntryId);

        if ($entry === null) {
            return $this->notFound('Lançamento do extrato');
        }

        try {
            $reconciliation = $this->reconciliationService->confirm($transaction, $entry, $user);

            return $this->json(
                ['data' => $this->reconciliationService->serialize($reconciliation)],
                JsonResponse::HTTP_CREATED
            );
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            $this->logger->error('ReconciliationController::confirm error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao confirmar conciliação.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/reconciliations/{id}/undo', name: 'api_reconciliations_undo', methods: ['POST'])]
    public function undo(int $id): JsonResponse
    {
        /** @var User $user */
        $user           = $this->getUser();
        $reconciliation = $this->reconciliationRepository->find($id);

        if ($reconciliation === null) {
            return $this->notFound('Conciliação');
        }

        // Ensure the reconciliation belongs to the authenticated user
        if ($reconciliation->getTransaction()->getAccount()->getUser()->getId() !== $user->getId()) {
            return $this->notFound('Conciliação');
        }

        try {
            $updated = $this->reconciliationService->undo($reconciliation);

            return $this->json(['data' => $this->reconciliationService->serialize($updated)]);
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            $this->logger->error('ReconciliationController::undo error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao desfazer conciliação.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
