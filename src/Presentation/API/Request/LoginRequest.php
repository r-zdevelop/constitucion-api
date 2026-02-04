<?php

declare(strict_types=1);

namespace App\Presentation\API\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LoginRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: 'Invalid email format.')]
        public string $email,

        #[Assert\NotBlank(message: 'Password is required.')]
        public string $password
    ) {
    }
}
