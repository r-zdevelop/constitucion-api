<?php

declare(strict_types=1);

namespace App\Domain\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use DateTimeImmutable;

#[ODM\EmbeddedDocument]
class EmbeddedConcordance
{
    #[ODM\Field(type: 'string')]
    private string $referencedLaw;

    /** @var string[] */
    #[ODM\Field(type: 'collection')]
    private array $referencedArticles;

    #[ODM\Field(type: 'int')]
    private int $sourceArticleNumber;

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * @param string[] $referencedArticles
     */
    public function __construct(
        string $referencedLaw,
        array $referencedArticles,
        int $sourceArticleNumber
    ) {
        $this->referencedLaw = $referencedLaw;
        $this->referencedArticles = $referencedArticles;
        $this->sourceArticleNumber = $sourceArticleNumber;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getReferencedLaw(): string
    {
        return $this->referencedLaw;
    }

    /**
     * @return string[]
     */
    public function getReferencedArticles(): array
    {
        return $this->referencedArticles;
    }

    public function getSourceArticleNumber(): int
    {
        return $this->sourceArticleNumber;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function toArray(): array
    {
        return [
            'referencedLaw' => $this->referencedLaw,
            'referencedArticles' => $this->referencedArticles,
            'sourceArticleNumber' => $this->sourceArticleNumber,
            'createdAt' => $this->createdAt->format(DateTimeImmutable::ATOM),
        ];
    }
}
