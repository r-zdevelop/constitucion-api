<?php

declare(strict_types=1);

namespace App\Application\UseCase\Collection;

use App\Domain\Document\Collection;
use App\Domain\Document\User;
use App\Domain\Exception\CollectionLimitExceededException;
use App\Domain\Exception\DuplicateCollectionNameException;
use App\Domain\Repository\CollectionRepositoryInterface;
use App\Domain\ValueObject\Role;

final readonly class CreateCollectionUseCase
{
    private const FREE_MAX_COLLECTIONS = 5;

    public function __construct(
        private CollectionRepositoryInterface $collectionRepository
    ) {
    }

    /**
     * @throws CollectionLimitExceededException
     * @throws DuplicateCollectionNameException
     */
    public function execute(User $user, string $name, ?string $description = null): Collection
    {
        $userId = $user->getId();

        // Check collection limit for FREE users
        if (!$user->getRole()->isPremium()) {
            $currentCount = $this->collectionRepository->countByUserId($userId);
            if ($currentCount >= self::FREE_MAX_COLLECTIONS) {
                throw CollectionLimitExceededException::maxCollections(self::FREE_MAX_COLLECTIONS);
            }
        }

        // Check for duplicate name
        $existing = $this->collectionRepository->findByUserIdAndName($userId, $name);
        if ($existing !== null) {
            throw DuplicateCollectionNameException::create($name);
        }

        $collection = new Collection($userId, $name, $description);

        $this->collectionRepository->save($collection);
        $this->collectionRepository->flush();

        return $collection;
    }
}
