<?php

declare(strict_types=1);

namespace App\Service\Transaction;

use App\DTO\CreateTransactionDTO;
use App\DTO\UpdateTransactionDTO;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\ReconciliationStatus;
use App\Enum\TransactionSource;
use App\Enum\TransactionType;
use App\Repository\AccountRepository;
use App\Repository\CategoryRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

class TransactionService
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function list(User $user, array $filters, int $page, int $limit): array
    {
        $mappedFilters = [];

        if (isset($filters['accountId'])) {
            $mappedFilters['accountId'] = (int) $filters['accountId'];
        }

        if (isset($filters['categoryId'])) {
            $mappedFilters['categoryId'] = (int) $filters['categoryId'];
        }

        if (isset($filters['type']) && in_array($filters['type'], ['income', 'expense'], true)) {
            $mappedFilters['type'] = TransactionType::from((string) $filters['type']);
        }

        if (isset($filters['reconciliationStatus'])) {
            $status = ReconciliationStatus::tryFrom((string) $filters['reconciliationStatus']);
            if ($status !== null) {
                $mappedFilters['reconciliationStatus'] = $status;
            }
        }

        if (isset($filters['dateFrom'])) {
            $mappedFilters['dateFrom'] = (string) $filters['dateFrom'];
        }

        if (isset($filters['dateTo'])) {
            $mappedFilters['dateTo'] = (string) $filters['dateTo'];
        }

        if (isset($filters['search'])) {
            $mappedFilters['search'] = (string) $filters['search'];
        }

        if (isset($filters['source'])) {
            $source = \App\Enum\TransactionSource::tryFrom((string) $filters['source']);
            if ($source !== null) {
                $mappedFilters['source'] = $source;
            }
        }

        $result = $this->transactionRepository->findByFilters($user, $mappedFilters, $page, $limit);

        $data = array_map(
            fn (Transaction $t): array => $this->serialize($t),
            $result['data']
        );

        return [
            'data'  => $data,
            'total' => $result['total'],
            'page'  => $page,
            'limit' => $limit,
            'pages' => $limit > 0 ? (int) ceil($result['total'] / $limit) : 1,
        ];
    }

    public function create(
        User $user,
        CreateTransactionDTO $dto,
        AccountRepository $accountRepo,
        CategoryRepository $catRepo,
    ): Transaction {
        $account = $accountRepo->findOneByIdAndUser($dto->accountId, $user);

        if ($account === null) {
            throw new \DomainException('Conta não encontrada ou não pertence ao usuário.');
        }

        $category = null;

        if ($dto->categoryId !== null) {
            $category = $catRepo->findOneByIdAndUser($dto->categoryId, $user);

            if ($category === null) {
                throw new \DomainException('Categoria não encontrada ou não pertence ao usuário.');
            }
        }

        $transaction = new Transaction();
        $transaction->setAccount($account);
        $transaction->setCategory($category);
        $transaction->setDescription($dto->description);
        $transaction->setAmount($dto->amount);
        $transaction->setType(TransactionType::from($dto->type));
        $transaction->setTransactionDate(new \DateTime($dto->transactionDate));
        $transaction->setExternalReference($dto->externalReference);
        $transaction->setSource(TransactionSource::Manual);
        $transaction->setReconciliationStatus(ReconciliationStatus::Pending);
        $transaction->setUpdatedAt(null);

        $this->em->persist($transaction);
        $this->em->flush();

        return $transaction;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDetail(Transaction $transaction): array
    {
        return $this->serialize($transaction);
    }

    public function update(Transaction $transaction, UpdateTransactionDTO $dto): Transaction
    {
        if ($transaction->getReconciliationStatus() === ReconciliationStatus::Reconciled) {
            throw new \DomainException('Não é possível editar uma transação já conciliada.');
        }

        if ($dto->description !== null) {
            $transaction->setDescription($dto->description);
        }

        if ($dto->amount !== null) {
            $transaction->setAmount($dto->amount);
        }

        if ($dto->type !== null) {
            $transaction->setType(TransactionType::from($dto->type));
        }

        if ($dto->transactionDate !== null) {
            $transaction->setTransactionDate(new \DateTime($dto->transactionDate));
        }

        if (array_key_exists('externalReference', (array) $dto)) {
            if ($dto->externalReference !== null) {
                $transaction->setExternalReference($dto->externalReference);
            }
        }

        $transaction->setUpdatedAt(new \DateTime());

        $this->em->flush();

        return $transaction;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Transaction $transaction): array
    {
        $category = $transaction->getCategory();
        $account  = $transaction->getAccount();

        return [
            'id'                    => $transaction->getId(),
            'accountId'             => $account->getId(),
            'accountName'           => $account->getName(),
            'categoryId'            => $category?->getId(),
            'categoryName'          => $category?->getName(),
            'description'           => $transaction->getDescription(),
            'amount'                => $transaction->getAmount(),
            'type'                  => $transaction->getType()->value,
            'transactionDate'       => $transaction->getTransactionDate()->format('Y-m-d'),
            'externalReference'     => $transaction->getExternalReference(),
            'source'                => $transaction->getSource()->value,
            'reconciliationStatus'  => $transaction->getReconciliationStatus()->value,
            'createdAt'             => $transaction->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'             => $transaction->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
