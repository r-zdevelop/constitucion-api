<?php

declare(strict_types=1);

namespace App\Domain\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use DateTimeImmutable;
use DateTimeInterface;

#[ODM\Document(collection: 'legal_documents')]
#[ODM\Index(keys: ['name' => 1, 'year' => 1], options: ['unique' => true])]
class LegalDocument
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    private string $name;

    #[ODM\Field(type: 'string')]
    private string $documentType;

    #[ODM\Field(type: 'int')]
    private int $year;

    #[ODM\Field(type: 'date')]
    private DateTimeInterface $lastModified;

    #[ODM\Field(type: 'int')]
    private int $totalArticles;

    #[ODM\Field(type: 'string')]
    private string $status;

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $createdAt;

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $name,
        string $documentType,
        int $year,
        DateTimeInterface $lastModified,
        int $totalArticles,
        string $status = 'active'
    ) {
        $this->name = $name;
        $this->documentType = $documentType;
        $this->year = $year;
        $this->lastModified = $lastModified;
        $this->totalArticles = $totalArticles;
        $this->status = $status;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function getLastModified(): DateTimeInterface
    {
        return $this->lastModified;
    }

    public function getTotalArticles(): int
    {
        return $this->totalArticles;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setTotalArticles(int $totalArticles): void
    {
        $this->totalArticles = $totalArticles;
        $this->updatedAt = new DateTimeImmutable();
    }
}
