<?php

declare(strict_types=1);

namespace App\Application\UseCase\Collection;

use App\Domain\Document\Collection;
use App\Domain\Document\User;
use App\Domain\Exception\CollectionAccessDeniedException;
use App\Domain\Exception\CollectionNotFoundException;
use App\Domain\Repository\CollectionRepositoryInterface;

final readonly class GetCollectionUseCase
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository
    ) {
    }

    /**
     * @throws CollectionNotFoundException
     * @throws CollectionAccessDeniedException
     */
    public function execute(User $user, string $collectionId): Collection
    {
        $collection = $this->collectionRepository->findById($collectionId);

        if ($collection === null) {
            throw CollectionNotFoundException::withId($collectionId);
        }

        if ($collection->getUserId() !== $user->getId()) {
            throw CollectionAccessDeniedException::notOwner();
        }

        return $collection;
    }
}
