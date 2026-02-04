<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use Exception;

final class ArticleAlreadyInCollectionException extends Exception
{
    public static function create(string $articleId, string $collectionName): self
    {
        return new self(sprintf(
            'Article "%s" is already in collection "%s".',
            $articleId,
            $collectionName
        ));
    }
}
