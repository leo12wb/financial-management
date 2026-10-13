<?php

declare(strict_types=1);

namespace App\Service\Category;

use App\DTO\CreateCategoryDTO;
use App\Entity\Category;
use App\Entity\User;
use App\Enum\CategoryType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;

class CategoryService
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private EntityManagerInterface $em,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(User $user, ?string $type = null, bool $activeOnly = false): array
    {
        $categoryType = null;

        if ($type !== null) {
            $categoryType = CategoryType::from($type);
        }

        $categories = $this->categoryRepository->findByUser($user, $categoryType, $activeOnly);

        return array_map(
            fn (Category $category): array => $this->serialize($category),
            $categories
        );
    }

    public function create(User $user, CreateCategoryDTO $dto): Category
    {
        $category = new Category();
        $category->setUser($user);
        $category->setName($dto->name);
        $category->setType(CategoryType::from($dto->type));

        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(Category $category, array $data): Category
    {
        if (isset($data['name']) && is_string($data['name'])) {
            $category->setName($data['name']);
        }

        if (array_key_exists('isActive', $data) && is_bool($data['isActive'])) {
            $category->setIsActive($data['isActive']);
        }

        $this->em->flush();

        return $category;
    }

    public function toggleActive(Category $category): Category
    {
        $category->setIsActive(!$category->isActive());

        $this->em->flush();

        return $category;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(Category $category): array
    {
        return [
            'id'        => $category->getId(),
            'name'      => $category->getName(),
            'type'      => $category->getType()->value,
            'isActive'  => $category->isActive(),
            'createdAt' => $category->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
