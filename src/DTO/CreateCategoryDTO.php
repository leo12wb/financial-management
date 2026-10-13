<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateCategoryDTO
{
    #[Assert\NotBlank(message: 'O nome da categoria é obrigatório.')]
    #[Assert\Length(max: 255, maxMessage: 'O nome não pode ter mais de {{ limit }} caracteres.')]
    public string $name = '';

    #[Assert\NotBlank(message: 'O tipo da categoria é obrigatório.')]
    #[Assert\Choice(
        choices: ['income', 'expense'],
        message: 'O tipo deve ser "income" ou "expense".'
    )]
    public string $type = 'expense';
}
