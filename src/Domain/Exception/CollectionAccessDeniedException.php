<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use Exception;

final class CollectionAccessDeniedException extends Exception
{
    public static function notOwner(): self
    {
        return new self('You do not have permission to access this collection.');
    }
}
