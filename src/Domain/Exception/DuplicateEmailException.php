<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use Exception;

final class DuplicateEmailException extends Exception
{
    public static function create(string $email): self
    {
        return new self(sprintf('A user with email "%s" already exists.', $email));
    }
}
