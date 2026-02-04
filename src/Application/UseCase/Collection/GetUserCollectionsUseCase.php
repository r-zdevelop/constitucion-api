<?php

declare(strict_types=1);

namespace App\Application\UseCase\Collection;

use App\Domain\Document\Collection;
use App\Domain\Document\User;
use App\Domain\Repository\CollectionRepositoryInterface;

final readonly class GetUserCollectionsUseCase
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository
    ) {
    }

    /**
     * @return Collection[]
     */
    public function execute(User $user): array
    {
        return $this->collectionRepository->findByUserId($user->getId());
    }
}
