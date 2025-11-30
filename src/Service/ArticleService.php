<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ArticleRepositoryInterface;
use App\Entity\Article;
use App\Entity\ArticleHistory;

class ArticleService
{
    public function __construct(
        private ArticleRepositoryInterface $articles,
        private \Doctrine\ORM\EntityManagerInterface $em
    ) {}

    /**
     * Search articles by full text. Repository handles SQL/FTS specifics.
     *
     * @return Article[]
     */
    public function search(string $q, int $limit = 50): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        return $this->articles->fullTextSearch($q, $limit);
    }

    /**
     * Find article(s) by article number.
     * If documentId is provided, returns single article.
     * Otherwise, returns all articles with that number across documents.
     *
     * @return Article[]
     */
    public function findByArticleNumber(int $articleNumber, ?int $documentId = null): array
    {
        // Validate article number is positive
        if ($articleNumber <= 0) {
            return [];
        }

        // If document ID specified, return single article wrapped in array
        if ($documentId !== null) {
            $article = $this->articles->findByNumber($documentId, $articleNumber);
            return $article !== null ? [$article] : [];
        }

        // Otherwise, search across all documents
        return $this->articles->findByArticleNumber($articleNumber);
    }

    /**
     * Update article content and record history atomically.
     */
    public function updateContent(Article $article, string $newContent, string $modifiedBy, ?string $reason = null): void
    {
        $before = $article->getContent();
        if ($before === $newContent) {
            // nothing to do
            return;
        }

        $article->setContent($newContent);
        $article->setUpdatedAt(new \DateTimeImmutable());

        $history = new ArticleHistory($article, $before, $newContent, $modifiedBy, $reason);

        $this->em->persist($article);
        $this->em->persist($history);
        $this->em->flush();
    }
}
