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
     * Search articles with pagination and keyword filtering.
     *
     * Business rules:
     * - Minimum 2 characters for search (prevent performance issues)
     * - Page number must be >= 1
     * - Items per page clamped between 10-100 (prevent abuse)
     *
     * @param string $searchTerm Keyword to search (title + content)
     * @param int $page Current page (1-indexed)
     * @param int $itemsPerPage Items per page (default 20)
     * @return array{items: Article[], total: int, pages: int, currentPage: int, searchTerm: string}
     */
    public function searchArticlesPaginated(string $searchTerm, int $page = 1, int $itemsPerPage = 20): array
    {
        // Sanitize and validate search term
        $searchTerm = trim($searchTerm);

        // Business rule: minimum 2 characters to prevent performance issues
        if (mb_strlen($searchTerm) < 2) {
            return [
                'items' => [],
                'total' => 0,
                'pages' => 0,
                'currentPage' => $page,
                'searchTerm' => $searchTerm,
            ];
        }

        // Validate page number (must be positive)
        $page = max(1, $page);

        // Clamp items per page to reasonable bounds (10-100)
        $itemsPerPage = max(10, min(100, $itemsPerPage));

        // Delegate to repository
        $result = $this->articles->searchPaginated($searchTerm, $page, $itemsPerPage);

        // Add search term to result for view rendering
        return array_merge($result, ['searchTerm' => $searchTerm]);
    }

    /**
     * Get all articles with pagination and optional chapter filter.
     *
     * @param int $page Current page (1-indexed)
     * @param int $itemsPerPage Items per page (default 20)
     * @param string|null $chapter Optional chapter filter
     * @return array{items: Article[], total: int, pages: int, currentPage: int}
     */
    public function getAllArticlesPaginated(int $page = 1, int $itemsPerPage = 20, ?string $chapter = null): array
    {
        // Validate page number (must be positive)
        $page = max(1, $page);

        // Clamp items per page to reasonable bounds (10-100)
        $itemsPerPage = max(10, min(100, $itemsPerPage));

        // Delegate to repository
        return $this->articles->findAllPaginated($page, $itemsPerPage, $chapter);
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
