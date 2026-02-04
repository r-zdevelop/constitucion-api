<?php

declare(strict_types=1);

namespace App\Application\UseCase\Article;

use App\Domain\Document\Article;
use App\Domain\Exception\ArticleNotFoundException;
use App\Domain\Repository\ArticleRepositoryInterface;

final readonly class GetArticleByNumberUseCase
{
    public function __construct(
        private ArticleRepositoryInterface $articleRepository
    ) {
    }

    /**
     * Find article by number.
     * If documentId is provided, searches in specific document.
     * Otherwise, returns all articles with that number across documents.
     *
     * @return Article[]
     * @throws ArticleNotFoundException when documentId is provided but article not found
     */
    public function execute(int $articleNumber, ?string $documentId = null): array
    {
        if ($articleNumber <= 0) {
            return [];
        }

        if ($documentId !== null) {
            $article = $this->articleRepository->findByNumber($documentId, $articleNumber);

            if ($article === null) {
                throw ArticleNotFoundException::withDocumentAndNumber($documentId, $articleNumber);
            }

            return [$article];
        }

        return $this->articleRepository->findByArticleNumber($articleNumber);
    }
}
