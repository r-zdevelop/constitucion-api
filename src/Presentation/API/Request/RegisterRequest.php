<?php

declare(strict_types=1);

namespace App\Presentation\API\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required.')]
        #[Assert\Email(message: 'Invalid email format.')]
        public string $email,

        #[Assert\NotBlank(message: 'Password is required.')]
        #[Assert\Length(
            min: 8,
            max: 128,
            minMessage: 'Password must be at least {{ limit }} characters.',
            maxMessage: 'Password must not exceed {{ limit }} characters.'
        )]
        public string $password,

        #[Assert\NotBlank(message: 'Name is required.')]
        #[Assert\Length(
            min: 2,
            max: 100,
            minMessage: 'Name must be at least {{ limit }} characters.',
            maxMessage: 'Name must not exceed {{ limit }} characters.'
        )]
        public string $name
    ) {
    }
}
