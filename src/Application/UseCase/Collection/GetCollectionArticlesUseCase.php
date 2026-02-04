<?php

declare(strict_types=1);

namespace App\Application\UseCase\Collection;

use App\Domain\Document\Article;
use App\Domain\Document\User;
use App\Domain\Exception\CollectionAccessDeniedException;
use App\Domain\Exception\CollectionNotFoundException;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\Repository\CollectionRepositoryInterface;

final readonly class GetCollectionArticlesUseCase
{
    public function __construct(
        private CollectionRepositoryInterface $collectionRepository,
        private ArticleRepositoryInterface $articleRepository
    ) {
    }

    /**
     * @return Article[]
     * @throws CollectionNotFoundException
     * @throws CollectionAccessDeniedException
     */
    public function execute(User $user, string $collectionId): array
    {
        $collection = $this->collectionRepository->findById($collectionId);

        if ($collection === null) {
            throw CollectionNotFoundException::withId($collectionId);
        }

        if ($collection->getUserId() !== $user->getId()) {
            throw CollectionAccessDeniedException::notOwner();
        }

        $articles = [];
        foreach ($collection->getArticleIds() as $articleId) {
            $article = $this->articleRepository->findById($articleId);
            if ($article !== null) {
                $articles[] = $article;
            }
        }

        return $articles;
    }
}
