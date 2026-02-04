<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use Exception;

final class DuplicateCollectionNameException extends Exception
{
    public static function create(string $name): self
    {
        return new self(sprintf('A collection with the name "%s" already exists.', $name));
    }
}
