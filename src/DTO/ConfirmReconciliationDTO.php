<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class ConfirmReconciliationDTO
{
    #[Assert\NotBlank(message: 'O ID da transação é obrigatório.')]
    #[Assert\Positive(message: 'O ID da transação deve ser um número positivo.')]
    public int $transactionId = 0;

    #[Assert\NotBlank(message: 'O ID do lançamento do extrato é obrigatório.')]
    #[Assert\Positive(message: 'O ID do lançamento do extrato deve ser um número positivo.')]
    public int $statementEntryId = 0;
}
