<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Document\Collection;

interface CollectionRepositoryInterface
{
    public function findById(string $id): ?Collection;

    /**
     * Find all collections belonging to a user.
     *
     * @return Collection[]
     */
    public function findByUserId(string $userId): array;

    /**
     * Find a collection by user ID and name.
     */
    public function findByUserIdAndName(string $userId, string $name): ?Collection;

    /**
     * Count collections belonging to a user.
     */
    public function countByUserId(string $userId): int;

    public function save(Collection $collection): void;

    public function remove(Collection $collection): void;

    public function flush(): void;
}
