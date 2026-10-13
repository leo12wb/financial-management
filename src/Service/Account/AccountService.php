<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\DTO\CreateAccountDTO;
use App\DTO\UpdateAccountDTO;
use App\Entity\Account;
use App\Entity\User;
use App\Enum\AccountType;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;

class AccountService
{
    public function __construct(
        private AccountRepository $accountRepository,
        private TransactionRepository $transactionRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(User $user, bool $activeOnly = false): array
    {
        $accounts = $this->accountRepository->findByUser($user, $activeOnly);

        return array_map(
            fn (Account $account): array => $this->serialize($account),
            $accounts
        );
    }

    public function create(User $user, CreateAccountDTO $dto): Account
    {
        $account = new Account();
        $account->setUser($user);
        $account->setName($dto->name);
        $account->setInstitutionName($dto->institutionName);
        $account->setAccountType(AccountType::from($dto->accountType));
        $account->setCurrency(strtoupper($dto->currency));
        $account->setInitialBalance($dto->initialBalance);

        $this->em->persist($account);
        $this->em->flush();

        return $account;
    }

    public function update(Account $account, UpdateAccountDTO $dto): Account
    {
        if ($dto->name !== null) {
            $account->setName($dto->name);
        }

        if ($dto->institutionName !== null) {
            $account->setInstitutionName($dto->institutionName);
        }

        if ($dto->accountType !== null) {
            $account->setAccountType(AccountType::from($dto->accountType));
        }

        if ($dto->currency !== null) {
            $account->setCurrency(strtoupper($dto->currency));
        }

        if ($dto->initialBalance !== null) {
            $account->setInitialBalance($dto->initialBalance);
        }

        if ($dto->isActive !== null) {
            $account->setIsActive($dto->isActive);
        }

        $account->setUpdatedAt(new \DateTime());

        $this->em->flush();

        return $account;
    }

    public function toggleActive(Account $account): Account
    {
        $account->setIsActive(!$account->isActive());
        $account->setUpdatedAt(new \DateTime());

        $this->em->flush();

        return $account;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Account $account): array
    {
        $currentBalance = $this->transactionRepository->calculateAccountBalance($account);

        return [
            'id'              => $account->getId(),
            'name'            => $account->getName(),
            'institutionName' => $account->getInstitutionName(),
            'accountType'     => $account->getAccountType()->value,
            'currency'        => $account->getCurrency(),
            'initialBalance'  => $account->getInitialBalance(),
            'currentBalance'  => $currentBalance,
            'isActive'        => $account->isActive(),
            'createdAt'       => $account->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt'       => $account->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
