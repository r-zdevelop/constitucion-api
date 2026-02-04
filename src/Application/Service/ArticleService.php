<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Document\Article;
use App\Domain\Repository\ArticleRepositoryInterface;

final readonly class ArticleService
{
    private const MIN_SEARCH_LENGTH = 2;
    private const MIN_ITEMS_PER_PAGE = 10;
    private const MAX_ITEMS_PER_PAGE = 100;

    public function __construct(
        private ArticleRepositoryInterface $articleRepository
    ) {
    }

    /**
     * Search articles by full text.
     *
     * @return Article[]
     */
    public function search(string $query, int $limit = 50): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        return $this->articleRepository->fullTextSearch($query, $limit);
    }

    /**
     * Find article(s) by article number.
     *
     * @return Article[]
     */
    public function findByArticleNumber(int $articleNumber, ?string $documentId = null): array
    {
        if ($articleNumber <= 0) {
            return [];
        }

        if ($documentId !== null) {
            $article = $this->articleRepository->findByNumber($documentId, $articleNumber);
            return $article !== null ? [$article] : [];
        }

        return $this->articleRepository->findByArticleNumber($articleNumber);
    }

    /**
     * Search articles with pagination and keyword filtering.
     *
     * @return array{items: Article[], total: int, pages: int, currentPage: int, searchTerm: string}
     */
    public function searchArticlesPaginated(string $searchTerm, int $page = 1, int $itemsPerPage = 20): array
    {
        $searchTerm = trim($searchTerm);

        if (mb_strlen($searchTerm) < self::MIN_SEARCH_LENGTH) {
            return [
                'items' => [],
                'total' => 0,
                'pages' => 0,
                'currentPage' => $page,
                'searchTerm' => $searchTerm,
            ];
        }

        $page = max(1, $page);
        $itemsPerPage = max(self::MIN_ITEMS_PER_PAGE, min(self::MAX_ITEMS_PER_PAGE, $itemsPerPage));

        $result = $this->articleRepository->searchPaginated($searchTerm, $page, $itemsPerPage);

        return array_merge($result, ['searchTerm' => $searchTerm]);
    }

    /**
     * Get all articles with pagination and optional chapter filter.
     *
     * @return array{items: Article[], total: int, pages: int, currentPage: int}
     */
    public function getAllArticlesPaginated(int $page = 1, int $itemsPerPage = 20, ?string $chapter = null): array
    {
        $page = max(1, $page);
        $itemsPerPage = max(self::MIN_ITEMS_PER_PAGE, min(self::MAX_ITEMS_PER_PAGE, $itemsPerPage));

        return $this->articleRepository->findAllPaginated($page, $itemsPerPage, $chapter);
    }

    /**
     * Get all distinct chapters.
     *
     * @return string[]
     */
    public function getAllChapters(): array
    {
        return $this->articleRepository->findAllChapters();
    }
}
