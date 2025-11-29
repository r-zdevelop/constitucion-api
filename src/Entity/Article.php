<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\ArticleRepository;
// concordances will be stored as JSON (array) on the Article now

#[ORM\Entity(repositoryClass: ArticleRepository::class)]
#[ORM\Table(name: 'articles')]
#[ORM\UniqueConstraint(name: 'unique_article', columns: ['document_id', 'article_number'])]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\ManyToOne(targetEntity: LegalDocument::class)]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private LegalDocument $document;

    #[ORM\ManyToOne(targetEntity: DocumentSection::class)]
    #[ORM\JoinColumn(name: 'section_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?DocumentSection $section = null;

    #[ORM\Column(type: 'integer')]
    private int $articleNumber;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $chapter = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'string', length: 32)]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'json')]
    private array $concordances = [];

    public function __construct(LegalDocument $document, int $articleNumber, string $content, ?string $title = null)
    {
        $this->document = $document;
        $this->articleNumber = $articleNumber;
        $this->content = $content;
        $this->title = $title;
        $this->status = 'active';
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->concordances = [];
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getDocument(): LegalDocument
    {
        return $this->document;
    }

    public function getSection(): ?DocumentSection
    {
        return $this->section;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Get concordances stored as an array (JSON column).
     * Each concordance is represented as an associative array.
     *
     * @return array<int, array<string,mixed>>
     */
    public function getConcordances(): array
    {
        return $this->concordances;
    }

    /**
     * Append a concordance entry (associative array) to the concordances JSON.
     * This keeps a simple array-of-objects structure.
     */
    public function addConcordance(array $concordance): void
    {
        // simple append; callers are responsible for shape/validation
        $this->concordances[] = $concordance;
    }

    public function setNumber(int $articleNumber): void
    {
        $this->articleNumber = $articleNumber;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function setChapter(?string $chapter): void
    {
        $this->chapter = $chapter;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
