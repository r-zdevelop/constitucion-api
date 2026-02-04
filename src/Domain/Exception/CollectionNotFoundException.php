<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use Exception;

final class CollectionNotFoundException extends Exception
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Collection with ID "%s" not found.', $id));
    }
}
