<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use Exception;

final class ArticleNotFoundException extends Exception
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Article with ID "%s" not found.', $id));
    }

    public static function withNumber(int $articleNumber): self
    {
        return new self(sprintf('Article number %d not found.', $articleNumber));
    }

    public static function withDocumentAndNumber(string $documentId, int $articleNumber): self
    {
        return new self(sprintf(
            'Article number %d not found in document "%s".',
            $articleNumber,
            $documentId
        ));
    }
}
