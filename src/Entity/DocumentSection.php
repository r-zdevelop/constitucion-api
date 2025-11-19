<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'document_sections')]
class DocumentSection
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\ManyToOne(targetEntity: LegalDocument::class, inversedBy: 'sections')]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private LegalDocument $document;

    #[ORM\Column(type: 'string', length: 32)]
    private string $sectionType;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'integer')]
    private int $orderIndex;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_section_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?DocumentSection $parentSection = null;

    #[ORM\OneToMany(mappedBy: 'parentSection', targetEntity: self::class)]
    private Collection $children;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeImmutable $createdAt;

    public function __construct(LegalDocument $document, string $sectionType, string $name, int $orderIndex, ?DocumentSection $parent = null)
    {
        $this->document = $document;
        $this->sectionType = $sectionType;
        $this->name = $name;
        $this->orderIndex = $orderIndex;
        $this->parentSection = $parent;
        $this->createdAt = new \DateTimeImmutable();
        $this->children = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    // ... getters/setters
}
