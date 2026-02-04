<?php

declare(strict_types=1);

namespace App\Presentation\API\Response;

final readonly class PaginatedResponse
{
    public function __construct(
        public array $items,
        public int $total,
        public int $pages,
        public int $currentPage,
        public int $itemsPerPage
    ) {
    }

    public function toArray(): array
    {
        return [
            'data' => $this->items,
            'meta' => [
                'total' => $this->total,
                'pages' => $this->pages,
                'currentPage' => $this->currentPage,
                'itemsPerPage' => $this->itemsPerPage,
                'hasNextPage' => $this->currentPage < $this->pages,
                'hasPreviousPage' => $this->currentPage > 1,
            ],
        ];
    }
}
