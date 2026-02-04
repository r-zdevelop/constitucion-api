<?php

declare(strict_types=1);

namespace App\Presentation\API\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class AddArticleToCollectionRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Article ID is required.')]
        public string $articleId
    ) {
    }
}
