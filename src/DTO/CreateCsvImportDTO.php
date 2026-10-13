<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateCsvImportDTO
{
    #[Assert\NotBlank(message: 'A conta é obrigatória.')]
    #[Assert\Positive(message: 'O ID da conta deve ser um número positivo.')]
    public int $accountId = 0;
}
