<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;

interface ArticleRepositoryInterface
{
    public function findById(int $id): ?Article;

    public function findByNumber(int $documentId, int $articleNumber): ?Article;

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

    public function save(Article $article): void;

    public function remove(Article $article): void;
}
