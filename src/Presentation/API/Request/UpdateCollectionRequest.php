<?php

declare(strict_types=1);

namespace App\Presentation\API\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateCollectionRequest
{
    public function __construct(
        #[Assert\Length(
            min: 1,
            max: 100,
            minMessage: 'Name must be at least {{ limit }} character.',
            maxMessage: 'Name must not exceed {{ limit }} characters.'
        )]
        public ?string $name = null,

        #[Assert\Length(
            max: 500,
            maxMessage: 'Description must not exceed {{ limit }} characters.'
        )]
        public ?string $description = null
    ) {
    }
}
