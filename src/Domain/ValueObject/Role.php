<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum Role: string
{
    case FREE = 'ROLE_FREE';
    case PREMIUM = 'ROLE_PREMIUM';
    case ADMIN = 'ROLE_ADMIN';

    public static function default(): self
    {
        return self::FREE;
    }

    public function isAdmin(): bool
    {
        return $this === self::ADMIN;
    }

    public function isPremium(): bool
    {
        return $this === self::PREMIUM || $this === self::ADMIN;
    }
}
