<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Document\User;

interface UserRepositoryInterface
{
    public function findById(string $id): ?User;

    public function findByEmail(string $email): ?User;

    public function existsByEmail(string $email): bool;

    public function save(User $user): void;

    public function remove(User $user): void;

    public function flush(): void;
}
