<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'article_history')]
class ArticleHistory
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Article $article;

    #[ORM\Column(type: 'text')]
    private string $contentBefore;

    #[ORM\Column(type: 'text')]
    private string $contentAfter;

    #[ORM\Column(type: 'string', length: 100)]
    private string $modifiedBy;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $modificationReason = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeImmutable $modifiedAt;

    public function __construct(Article $article, string $contentBefore, string $contentAfter, string $modifiedBy, ?string $reason = null)
    {
        $this->article = $article;
        $this->contentBefore = $contentBefore;
        $this->contentAfter = $contentAfter;
        $this->modifiedBy = $modifiedBy;
        $this->modificationReason = $reason;
        $this->modifiedAt = new \DateTimeImmutable();
    }

    // ... getters
}
