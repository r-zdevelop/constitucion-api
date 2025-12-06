<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;

interface ArticleRepositoryInterface
{
    public function findById(int $id): ?Article;

    public function findByNumber(int $documentId, int $articleNumber): ?Article;

    /**
     * Find articles by article number across all documents.
     * Useful when document context is unknown.
     *
     * @return Article[]
     */
    public function findByArticleNumber(int $articleNumber): array;

    /**
     * Find all articles.
     *
     * @return Article[]
     */
    public function findAll(): array;

    /**
     * Find all distinct chapters (non-null).
     *
     * @return string[] Array of chapter names
     */
    public function findAllChapters(): array;

    /**
     * Find articles by chapter.
     *
     * @return Article[]
     */
    public function findByChapter(string $chapter): array;

    /**
     * Full text search. Query sanitized by Doctrine parameter binding.
     *
     * @return Article[]
     */
    public function fullTextSearch(string $query, int $limit = 50): array;

    /**
     * Search articles by keywords (title + content) with pagination.
     *
     * @param string $searchTerm Search query (sanitized by Doctrine)
     * @param int $page Current page (1-indexed)
     * @param int $itemsPerPage Number of items per page
     * @return array{items: Article[], total: int, pages: int, currentPage: int}
     */
    public function searchPaginated(string $searchTerm, int $page = 1, int $itemsPerPage = 20): array;

    /**
     * Get all articles with pagination and optional chapter filter.
     *
     * @param int $page Current page (1-indexed)
     * @param int $itemsPerPage Number of items per page
     * @param string|null $chapter Optional chapter filter
     * @return array{items: Article[], total: int, pages: int, currentPage: int}
     */
    public function findAllPaginated(int $page = 1, int $itemsPerPage = 20, ?string $chapter = null): array;

    public function save(Article $article): void;

    public function remove(Article $article): void;
}
