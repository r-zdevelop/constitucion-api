<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use Exception;

final class CollectionLimitExceededException extends Exception
{
    public static function maxCollections(int $limit): self
    {
        return new self(sprintf(
            'You have reached the maximum number of collections (%d). Upgrade to Premium for unlimited collections.',
            $limit
        ));
    }

    public static function maxArticlesInCollection(int $limit): self
    {
        return new self(sprintf(
            'This collection has reached the maximum number of articles (%d). Upgrade to Premium for unlimited articles per collection.',
            $limit
        ));
    }
}
