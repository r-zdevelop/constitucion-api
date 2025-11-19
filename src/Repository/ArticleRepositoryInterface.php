<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;

interface ArticleRepositoryInterface
{
    public function findById(int $id): ?Article;

    public function findByNumber(int $documentId, int $articleNumber): ?Article;

    /**
     * Full text search. Query sanitized by Doctrine parameter binding.
     *
     * @return Article[]
     */
    public function fullTextSearch(string $query, int $limit = 50): array;

    public function save(Article $article): void;

    public function remove(Article $article): void;
}
