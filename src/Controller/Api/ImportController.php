<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\AccountRepository;
use App\Repository\ImportedStatementRepository;
use App\Repository\StatementEntryRepository;
use App\Service\Import\CsvImportService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ImportController extends AbstractApiController
{
    public function __construct(
        private CsvImportService $csvImportService,
        private AccountRepository $accountRepository,
        private ImportedStatementRepository $importedStatementRepository,
        private StatementEntryRepository $statementEntryRepository,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    #[Route('/api/imports/preview', name: 'api_imports_preview', methods: ['POST'])]
    public function preview(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $accountId = $request->request->get('accountId');

        if ($accountId === null || !is_numeric($accountId)) {
            return $this->jsonError('O campo "accountId" é obrigatório e deve ser um número inteiro.');
        }

        $account = $this->accountRepository->findOneByIdAndUser((int) $accountId, $user);

        if ($account === null) {
            return $this->notFound('Conta');
        }

        $uploadedFile = $request->files->get('file');

        if ($uploadedFile === null) {
            return $this->jsonError('O arquivo CSV é obrigatório.');
        }

        $originalFilename = $uploadedFile->getClientOriginalName();
        $extension        = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        if ($extension !== 'csv') {
            return $this->jsonError('O arquivo deve ter extensão .csv.');
        }

        if ($uploadedFile->getSize() > 10 * 1024 * 1024) {
            return $this->jsonError('O arquivo não pode ter mais de 10 MB.');
        }

        $csvContent = file_get_contents($uploadedFile->getPathname());

        if ($csvContent === false) {
            return $this->jsonError('Não foi possível ler o arquivo enviado.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        try {
            $result = $this->csvImportService->preview(
                $account,
                $csvContent,
                $originalFilename,
                $this->em,
                $this->statementEntryRepository
            );

            return $this->json(['data' => $result], JsonResponse::HTTP_CREATED);
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            $this->logger->error('ImportController::preview error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao processar o arquivo.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/imports/{id}/confirm', name: 'api_imports_confirm', methods: ['POST'])]
    public function confirm(int $id): JsonResponse
    {
        /** @var User $user */
        $user      = $this->getUser();
        $statement = $this->importedStatementRepository->findOneByIdAndUser($id, $user);

        if ($statement === null) {
            return $this->notFound('Importação');
        }

        try {
            $result = $this->csvImportService->confirm($statement, $this->em);

            return $this->json(['data' => $result]);
        } catch (\DomainException $e) {
            return $this->jsonError($e->getMessage(), JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            $this->logger->error('ImportController::confirm error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao confirmar importação.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/api/imports/{id}', name: 'api_imports_show', methods: ['GET'])]
    public function show(int $id): JsonResponse
    {
        /** @var User $user */
        $user      = $this->getUser();
        $statement = $this->importedStatementRepository->findOneByIdAndUser($id, $user);

        if ($statement === null) {
            return $this->notFound('Importação');
        }

        return $this->json(['data' => $this->serializeStatement($statement)]);
    }

    #[Route('/api/imports', name: 'api_imports_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user  = $this->getUser();
        $page  = max(1, (int) $request->query->get('page', '1'));
        $limit = min(100, max(1, (int) $request->query->get('limit', '20')));

        try {
            $result = $this->importedStatementRepository->findByUser($user, $page, $limit);

            $data = array_map(
                fn ($s): array => $this->serializeStatement($s),
                $result['data']
            );

            return $this->json([
                'data'  => $data,
                'total' => $result['total'],
                'page'  => $page,
                'limit' => $limit,
                'pages' => $limit > 0 ? (int) ceil($result['total'] / $limit) : 1,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('ImportController::list error', ['exception' => $e]);

            return $this->jsonError('Erro interno ao listar importações.', JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStatement(\App\Entity\ImportedStatement $statement): array
    {
        return [
            'id'               => $statement->getId(),
            'accountId'        => $statement->getAccount()->getId(),
            'accountName'      => $statement->getAccount()->getName(),
            'originalFilename' => $statement->getOriginalFilename(),
            'status'           => $statement->getStatus()->value,
            'totalRows'        => $statement->getTotalRows(),
            'importedRows'     => $statement->getImportedRows(),
            'rejectedRows'     => $statement->getRejectedRows(),
            'createdAt'        => $statement->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
