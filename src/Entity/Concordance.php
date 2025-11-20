<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'concordances')]
class Concordance
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Article::class)]
    #[ORM\JoinColumn(name: 'article_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Article $article;

    #[ORM\Column(type: 'string', length: 255)]
    private string $referencedLaw;

    #[ORM\Column(type: 'json')]
    private array $referencedArticles;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeImmutable $createdAt;

    public function __construct(Article $article, string $referencedLaw, array $referencedArticles)
    {
        $this->article = $article;
        $this->referencedLaw = $referencedLaw;
        $this->referencedArticles = $referencedArticles;
        $this->createdAt = new \DateTimeImmutable();
    }

    // ... getters
    public function setArticle(Article $article): void
    {
        $this->article = $article;
    }
}
