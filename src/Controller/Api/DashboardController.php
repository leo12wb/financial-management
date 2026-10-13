<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\TransactionRepository;
use App\Service\Dashboard\DashboardService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class DashboardController extends AbstractApiController
{
    public function __construct(
        private DashboardService $dashboardService,
        private TransactionRepository $transactionRepository,
        private LoggerInterface $logger,
    ) {}

    #[Route('/api/dashboard', name: 'api_dashboard_index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $now      = new \DateTimeImmutable();
        $dateFrom = $request->query->get('dateFrom', $now->modify('first day of this month')->format('Y-m-d'));
        $dateTo   = $request->query->get('dateTo', $now->format('Y-m-d'));
        $months   = max(1, min(24, (int) $request->query->get('months', '6')));

        try {
            $summary          = $this->dashboardService->getSummary($user, (string) $dateFrom, (string) $dateTo);
            $chartData        = $this->dashboardService->getChartData($user, $months);
            $categoryExpenses = $this->dashboardService->getCategoryExpenses($user, (string) $dateFrom, (string) $dateTo);

            $recentResult = $this->transactionRepository->findByFilters($user, [], 1, 5);

            $recentTransactions = array_map(
                static function (\App\Entity\Transaction $t): array {
                    $category = $t->getCategory();

                    return [
                        'id'                   => $t->getId(),
                        'description'          => $t->getDescription(),
                        'amount'               => $t->getAmount(),
                        'type'                 => $t->getType()->value,
                        'transactionDate'      => $t->getTransactionDate()->format('Y-m-d'),
                        'accountName'          => $t->getAccount()->getName(),
                        'categoryName'         => $category?->getName(),
                        'reconciliationStatus' => $t->getReconciliationStatus()->value,
                    ];
                },
                $recentResult['data']
            );

            return $this->json([
                'data' => [
                    'summary'            => $summary,
                    'chartData'          => $chartData,
                    'categoryExpenses'   => $categoryExpenses,
                    'recentTransactions' => $recentTransactions,
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('DashboardController::index error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao carregar dashboard.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/me', name: 'api_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->json([
            'data' => [
                'id'        => $user->getId(),
                'email'     => $user->getEmail(),
                'roles'     => $user->getRoles(),
                'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ]);
    }
}
