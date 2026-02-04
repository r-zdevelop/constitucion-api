<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Document\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final readonly class JwtTokenManager
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager
    ) {
    }

    public function createToken(User $user): string
    {
        return $this->jwtManager->create($user);
    }
}
