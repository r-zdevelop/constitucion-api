<?php

declare(strict_types=1);

namespace App\Application\UseCase\Collection;

use App\Domain\Document\Collection;
use App\Domain\Document\User;
use App\Domain\Exception\CollectionAccessDeniedException;
use App\Domain\Exception\CollectionNotFoundException;
use App\Domain\Exception\DuplicateCollectionNameException;
use App\Domain\Repository\CollectionRepositoryInterface;

final readonly class UpdateCollectionUseCase
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository
    ) {
    }

    /**
     * @throws CollectionNotFoundException
     * @throws CollectionAccessDeniedException
     * @throws DuplicateCollectionNameException
     */
    public function execute(
        User $user,
        string $collectionId,
        ?string $name = null,
        ?string $description = null
    ): Collection {
        $collection = $this->collectionRepository->findById($collectionId);

        if ($collection === null) {
            throw CollectionNotFoundException::withId($collectionId);
        }

        if ($collection->getUserId() !== $user->getId()) {
            throw CollectionAccessDeniedException::notOwner();
        }

        // If name is being changed, check for duplicates
        if ($name !== null && $name !== $collection->getName()) {
            $existing = $this->collectionRepository->findByUserIdAndName($user->getId(), $name);
            if ($existing !== null) {
                throw DuplicateCollectionNameException::create($name);
            }
            $collection->setName($name);
        }

        if ($description !== null) {
            $collection->setDescription($description);
        }

        $this->collectionRepository->flush();

        return $collection;
    }
}
