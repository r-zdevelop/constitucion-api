<?php

declare(strict_types=1);

namespace App\Application\UseCase\Article;

use App\Domain\Document\Article;
use App\Domain\Repository\ArticleRepositoryInterface;

final readonly class SearchArticlesUseCase
{
    private const MIN_SEARCH_LENGTH = 2;
    private const MIN_ITEMS_PER_PAGE = 10;
    private const MAX_ITEMS_PER_PAGE = 100;
    private const DEFAULT_ITEMS_PER_PAGE = 20;

    public function __construct(
        private ArticleRepositoryInterface $articleRepository
    ) {
    }

    /**
     * Search articles with pagination.
     *
     * @return array{items: Article[], total: int, pages: int, currentPage: int, searchTerm: string}
     */
    public function execute(string $searchTerm, int $page = 1, int $itemsPerPage = self::DEFAULT_ITEMS_PER_PAGE): array
    {
        $searchTerm = trim($searchTerm);

        // Minimum 2 characters to prevent performance issues
        if (mb_strlen($searchTerm) < self::MIN_SEARCH_LENGTH) {
            return [
                'items' => [],
                'total' => 0,
                'pages' => 0,
                'currentPage' => $page,
                'searchTerm' => $searchTerm,
            ];
        }

        // Validate page number
        $page = max(1, $page);

        // Clamp items per page
        $itemsPerPage = max(self::MIN_ITEMS_PER_PAGE, min(self::MAX_ITEMS_PER_PAGE, $itemsPerPage));

        $result = $this->articleRepository->searchPaginated($searchTerm, $page, $itemsPerPage);

        return array_merge($result, ['searchTerm' => $searchTerm]);
    }
}
