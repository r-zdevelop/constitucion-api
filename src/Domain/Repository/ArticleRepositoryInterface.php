<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Document\Article;

interface ArticleRepositoryInterface
{
    public function findById(string $id): ?Article;

    public function findByNumber(string $documentId, int $articleNumber): ?Article;

    /**
     * Find articles by article number across all documents.
     *
     * @return Article[]
     */
    public function findByArticleNumber(int $articleNumber): array;

    /**
     * Find all articles ordered by article number.
     *
     * @return Article[]
     */
    public function findAll(): array;

    /**
     * Find all distinct chapters (non-null).
     *
     * @return string[]
     */
    public function findAllChapters(): array;

    /**
     * Find articles by chapter.
     *
     * @return Article[]
     */
    public function findByChapter(string $chapter): array;

    /**
     * Full text search using MongoDB text index.
     *
     * @return Article[]
     */
    public function fullTextSearch(string $query, int $limit = 50): array;

    /**
     * Search articles by keywords with pagination.
     *
     * @return array{items: Article[], total: int, pages: int, currentPage: int}
     */
    public function searchPaginated(string $searchTerm, int $page = 1, int $itemsPerPage = 20): array;

    /**
     * Get all articles with pagination and optional chapter filter.
     *
     * @return array{items: Article[], total: int, pages: int, currentPage: int}
     */
    public function findAllPaginated(int $page = 1, int $itemsPerPage = 20, ?string $chapter = null): array;

    public function save(Article $article): void;

    public function remove(Article $article): void;

    public function flush(): void;
}
