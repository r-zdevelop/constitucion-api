<?php

declare(strict_types=1);

namespace App\Domain\Document;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use DateTimeImmutable;

#[ODM\Document(collection: 'articles')]
#[ODM\Index(keys: ['documentId' => 1, 'articleNumber' => 1], options: ['unique' => true])]
#[ODM\Index(keys: ['chapter' => 1])]
#[ODM\Index(keys: ['articleNumber' => 1])]
#[ODM\Index(keys: ['content' => 'text', 'title' => 'text', 'chapter' => 'text'], options: ['name' => 'text_search_idx', 'default_language' => 'spanish'])]
class Article
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    #[ODM\Index]
    private string $documentId;

    #[ODM\Field(type: 'int')]
    private int $articleNumber;

    #[ODM\Field(type: 'string')]
    private string $content;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $title = null;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $chapter = null;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $notes = null;

    #[ODM\Field(type: 'string')]
    private string $status;

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $createdAt;

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $updatedAt;

    /** @var Collection<int, EmbeddedConcordance> */
    #[ODM\EmbedMany(targetDocument: EmbeddedConcordance::class)]
    private Collection $concordances;

    public function __construct(
        string $documentId,
        int $articleNumber,
        string $content,
        ?string $title = null
    ) {
        $this->documentId = $documentId;
        $this->articleNumber = $articleNumber;
        $this->content = $content;
        $this->title = $title;
        $this->status = 'active';
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
        $this->concordances = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getDocumentId(): string
    {
        return $this->documentId;
    }

    public function getArticleNumber(): int
    {
        return $this->articleNumber;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function getChapter(): ?string
    {
        return $this->chapter;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
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

    /**
     * @return Collection<int, EmbeddedConcordance>
     */
    public function getConcordances(): Collection
    {
        return $this->concordances;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getConcordancesAsArray(): array
    {
        return $this->concordances->map(fn(EmbeddedConcordance $c) => $c->toArray())->toArray();
    }

    public function addConcordance(EmbeddedConcordance $concordance): void
    {
        $this->concordances->add($concordance);
    }

    public function setArticleNumber(int $articleNumber): void
    {
        $this->articleNumber = $articleNumber;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setChapter(?string $chapter): void
    {
        $this->chapter = $chapter;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->updatedAt = new DateTimeImmutable();
    }
}
