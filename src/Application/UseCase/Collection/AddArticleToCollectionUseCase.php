<?php

declare(strict_types=1);

namespace App\Application\UseCase\Collection;

use App\Domain\Document\Collection;
use App\Domain\Document\User;
use App\Domain\Exception\ArticleAlreadyInCollectionException;
use App\Domain\Exception\CollectionAccessDeniedException;
use App\Domain\Exception\CollectionLimitExceededException;
use App\Domain\Exception\CollectionNotFoundException;
use App\Domain\Exception\ArticleNotFoundException;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\CollectionRepositoryInterface;

final readonly class AddArticleToCollectionUseCase
{
    private const FREE_MAX_ARTICLES_PER_COLLECTION = 20;

    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private ArticleRepositoryInterface $articleRepository
    ) {
    }

    /**
     * @throws CollectionNotFoundException
     * @throws CollectionAccessDeniedException
     * @throws ArticleNotFoundException
     * @throws ArticleAlreadyInCollectionException
     * @throws CollectionLimitExceededException
     */
    public function execute(User $user, string $collectionId, string $articleId): Collection
    {
        $collection = $this->collectionRepository->findById($collectionId);

        if ($collection === null) {
            throw CollectionNotFoundException::withId($collectionId);
        }

        if ($collection->getUserId() !== $user->getId()) {
            throw CollectionAccessDeniedException::notOwner();
        }

        // Verify article exists
        $article = $this->articleRepository->findById($articleId);
        if ($article === null) {
            throw ArticleNotFoundException::withId($articleId);
        }

        // Check if article is already in collection
        if ($collection->hasArticle($articleId)) {
            throw ArticleAlreadyInCollectionException::create($articleId, $collection->getName());
        }

        // Check article limit for FREE users
        if (!$user->getRole()->isPremium()) {
            if ($collection->getArticleCount() >= self::FREE_MAX_ARTICLES_PER_COLLECTION) {
                throw CollectionLimitExceededException::maxArticlesInCollection(self::FREE_MAX_ARTICLES_PER_COLLECTION);
            }
        }

        $collection->addArticle($articleId);
        $this->collectionRepository->flush();

        return $collection;
    }
}
