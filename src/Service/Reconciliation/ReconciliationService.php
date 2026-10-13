<?php

declare(strict_types=1);

namespace App\Service\Reconciliation;

use App\Entity\Reconciliation;
use App\Entity\StatementEntry;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\ReconciliationMatchStatus;
use App\Enum\ReconciliationStatus;
use App\Repository\ReconciliationRepository;
use App\Repository\StatementEntryRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReconciliationService
{
    public function __construct(
        private ReconciliationRepository $reconciliationRepo,
        private TransactionRepository $transactionRepo,
        private StatementEntryRepository $entryRepo,
        private EntityManagerInterface $em,
    ) {}

    /**
     * Returns reconciliation suggestions by matching pending statement entries
     * against pending transactions of the same user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSuggestions(User $user, ?int $accountId = null): array
    {
        // Query 1: pending statement entries — eagerly load importedStatement + account
        $entriesQb = $this->em->createQueryBuilder()
            ->select('e', 's', 'a')
            ->from(StatementEntry::class, 'e')
            ->join('e.importedStatement', 's')
            ->join('s.account', 'a')
            ->andWhere('a.user = :user')
            ->andWhere('e.reconciliationStatus = :status')
            ->setParameter('user', $user)
            ->setParameter('status', ReconciliationStatus::Pending);

        if ($accountId !== null) {
            $entriesQb->andWhere('a.id = :accountId')
                      ->setParameter('accountId', $accountId);
        }

        /** @var StatementEntry[] $pendingEntries */
        $pendingEntries = $entriesQb->getQuery()->getResult();

        if (empty($pendingEntries)) {
            return [];
        }

        // Query 2: ALL pending transactions for this user — eagerly load account
        // This replaces the N inner queries (one per entry) with a single fetch
        $txQb = $this->em->createQueryBuilder()
            ->select('t', 'ta')
            ->from(Transaction::class, 't')
            ->join('t.account', 'ta')
            ->andWhere('ta.user = :user')
            ->andWhere('t.reconciliationStatus = :status')
            ->setParameter('user', $user)
            ->setParameter('status', ReconciliationStatus::Pending);

        if ($accountId !== null) {
            $txQb->andWhere('ta.id = :accountId')
                 ->setParameter('accountId', $accountId);
        }

        /** @var Transaction[] $allPendingTx */
        $allPendingTx = $txQb->getQuery()->getResult();

        // Group transactions by account ID for O(1) lookup per entry
        /** @var array<int, Transaction[]> $txByAccount */
        $txByAccount = [];
        foreach ($allPendingTx as $tx) {
            $txByAccount[$tx->getAccount()->getId()][] = $tx;
        }

        $suggestions = [];

        foreach ($pendingEntries as $entry) {
            $account     = $entry->getImportedStatement()->getAccount();
            $accountTx   = $txByAccount[$account->getId()] ?? [];
            $entryDate   = $entry->getTransactionDate();
            $entryAmount = $entry->getAmount();

            foreach ($accountTx as $transaction) {
                // Filter: amount must match exactly
                if (bccomp($transaction->getAmount(), $entryAmount, 2) !== 0) {
                    continue;
                }

                // Filter: date within 3 days
                $diffDays = (int) abs($entryDate->diff($transaction->getTransactionDate())->days);
                if ($diffDays > 3) {
                    continue;
                }

                $score        = 0.5; // amount match guaranteed
                $matchReasons = ['Valor igual'];

                if ($diffDays === 0) {
                    $score += 0.3;
                    $matchReasons[] = 'Data exata';
                } elseif ($diffDays <= 1) {
                    $score += 0.2;
                    $matchReasons[] = 'Data próxima (1 dia)';
                } else {
                    $score += 0.1;
                    $matchReasons[] = 'Data próxima (3 dias)';
                }

                similar_text(
                    strtolower($entry->getDescription()),
                    strtolower($transaction->getDescription()),
                    $similarityPercent
                );

                if ($similarityPercent > 60) {
                    $score += 0.2;
                    $matchReasons[] = sprintf('Descrição similar (%.0f%%)', $similarityPercent);
                }

                $suggestions[] = [
                    'statementEntry' => $this->serializeEntry($entry),
                    'transaction'    => $this->serializeTransaction($transaction),
                    'score'          => round($score, 2),
                    'matchReasons'   => $matchReasons,
                ];
            }
        }

        usort(
            $suggestions,
            static fn (array $a, array $b): int => $b['score'] <=> $a['score']
        );

        return $suggestions;
    }

    public function confirm(
        Transaction $transaction,
        StatementEntry $entry,
        User $user,
    ): Reconciliation {
        if ($transaction->getAccount()->getUser()->getId() !== $user->getId()) {
            throw new \DomainException('A transação não pertence ao usuário autenticado.');
        }

        if ($entry->getReconciliationStatus() === ReconciliationStatus::Reconciled) {
            throw new \DomainException('Este lançamento do extrato já está conciliado.');
        }

        if ($transaction->getReconciliationStatus() === ReconciliationStatus::Reconciled) {
            throw new \DomainException('Esta transação já está conciliada.');
        }

        $existingByTransaction = $this->reconciliationRepo->findActiveByTransaction($transaction);

        if ($existingByTransaction !== null) {
            throw new \DomainException('Já existe uma conciliação ativa para esta transação.');
        }

        $existingByEntry = $this->reconciliationRepo->findActiveByStatementEntry($entry);

        if ($existingByEntry !== null) {
            throw new \DomainException('Já existe uma conciliação ativa para este lançamento do extrato.');
        }

        $reconciliation = new Reconciliation();
        $reconciliation->setTransaction($transaction);
        $reconciliation->setStatementEntry($entry);
        $reconciliation->setStatus(ReconciliationMatchStatus::Reconciled);
        $reconciliation->setReconciledAt(new \DateTimeImmutable());

        $transaction->setReconciliationStatus(ReconciliationStatus::Reconciled);
        $entry->setReconciliationStatus(ReconciliationStatus::Reconciled);

        $this->em->persist($reconciliation);
        $this->em->flush();

        return $reconciliation;
    }

    public function undo(Reconciliation $reconciliation): Reconciliation
    {
        if ($reconciliation->getStatus() !== ReconciliationMatchStatus::Reconciled) {
            throw new \DomainException(
                'Apenas conciliações com status "reconciled" podem ser desfeitas.'
            );
        }

        $reconciliation->setStatus(ReconciliationMatchStatus::Undone);
        $reconciliation->setUndoneAt(new \DateTimeImmutable());

        $reconciliation->getTransaction()->setReconciliationStatus(ReconciliationStatus::Pending);
        $reconciliation->getStatementEntry()->setReconciliationStatus(ReconciliationStatus::Pending);

        $this->em->flush();

        return $reconciliation;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Reconciliation $reconciliation): array
    {
        return [
            'id'             => $reconciliation->getId(),
            'transaction'    => $this->serializeTransaction($reconciliation->getTransaction()),
            'statementEntry' => $this->serializeEntry($reconciliation->getStatementEntry()),
            'status'         => $reconciliation->getStatus()->value,
            'reconciledAt'   => $reconciliation->getReconciledAt()?->format(\DateTimeInterface::ATOM),
            'undoneAt'       => $reconciliation->getUndoneAt()?->format(\DateTimeInterface::ATOM),
            'createdAt'      => $reconciliation->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTransaction(Transaction $transaction): array
    {
        return [
            'id'                   => $transaction->getId(),
            'accountId'            => $transaction->getAccount()->getId(),
            'accountName'          => $transaction->getAccount()->getName(),
            'description'          => $transaction->getDescription(),
            'amount'               => $transaction->getAmount(),
            'type'                 => $transaction->getType()->value,
            'transactionDate'      => $transaction->getTransactionDate()->format('Y-m-d'),
            'reconciliationStatus' => $transaction->getReconciliationStatus()->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEntry(StatementEntry $entry): array
    {
        return [
            'id'                   => $entry->getId(),
            'importId'             => $entry->getImportedStatement()->getId(),
            'description'          => $entry->getDescription(),
            'amount'               => $entry->getAmount(),
            'transactionDate'      => $entry->getTransactionDate()->format('Y-m-d'),
            'externalReference'    => $entry->getExternalReference(),
            'reconciliationStatus' => $entry->getReconciliationStatus()->value,
        ];
    }
}
