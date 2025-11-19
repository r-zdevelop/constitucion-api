<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\ArticleRepository::class)]
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

    #[ORM\Column(type: 'datetime')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(LegalDocument $document, int $articleNumber, string $content, ?string $title = null)
    {
        $this->document = $document;
        $this->articleNumber = $articleNumber;
        $this->content = $content;
        $this->title = $title;
        $this->status = 'active';
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    // ... getters/setters, updateContent() method that triggers history recording in service layer
}
