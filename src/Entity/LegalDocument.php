<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'legal_documents')]
class LegalDocument
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 32)]
    private string $documentType;

    #[ORM\Column(type: 'integer')]
    private int $year;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $lastModified;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $totalArticles;

    #[ORM\Column(type: 'string', length: 16)]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /**
     * @var Collection<int, DocumentSection>
     */
    #[ORM\OneToMany(mappedBy: 'document', targetEntity: DocumentSection::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $sections;

    public function __construct(
        string $name,
        string $documentType,
        int $year,
        \DateTimeInterface $lastModified,
        int $totalArticles,
        string $status = 'active'
    ) {
        $this->name = $name;
        $this->documentType = $documentType;
        $this->year = $year;
        $this->lastModified = $lastModified;
        $this->totalArticles = $totalArticles;
        $this->status = $status;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->sections = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    // ... getters and setters for other fields (omitted for brevity)
}
