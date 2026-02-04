<?php

declare(strict_types=1);

namespace App\Presentation\API\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class CreateCollectionRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name is required.')]
        #[Assert\Length(
            min: 1,
            max: 100,
            minMessage: 'Name must be at least {{ limit }} character.',
            maxMessage: 'Name must not exceed {{ limit }} characters.'
        )]
        public string $name,

        #[Assert\Length(
            max: 500,
            maxMessage: 'Description must not exceed {{ limit }} characters.'
        )]
        public ?string $description = null
    ) {
    }
}
