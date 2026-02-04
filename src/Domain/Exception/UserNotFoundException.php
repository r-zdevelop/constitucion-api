<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use Exception;

final class UserNotFoundException extends Exception
{
    public static function withId(string $id): self
    {
        return new self(sprintf('User with ID "%s" not found.', $id));
    }

    public static function withEmail(string $email): self
    {
        return new self(sprintf('User with email "%s" not found.', $email));
    }
}
