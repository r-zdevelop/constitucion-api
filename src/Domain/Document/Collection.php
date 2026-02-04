<?php

declare(strict_types=1);

namespace App\Domain\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as ODM;
use DateTimeImmutable;

#[ODM\Document(collection: 'collections')]
#[ODM\Index(keys: ['userId' => 1])]
#[ODM\Index(keys: ['userId' => 1, 'name' => 1], options: ['unique' => true])]
class Collection
{
    #[ODM\Id]
    private ?string $id = null;

    #[ODM\Field(type: 'string')]
    private string $userId;

    #[ODM\Field(type: 'string')]
    private string $name;

    #[ODM\Field(type: 'string', nullable: true)]
    private ?string $description = null;

    #[ODM\Field(type: 'collection')]
    private array $articleIds = [];

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $createdAt;

    #[ODM\Field(type: 'date_immutable')]
    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $userId,
        string $name,
        ?string $description = null
    ) {
        $this->userId = $userId;
        $this->name = $name;
        $this->description = $description;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * @return string[]
     */
    public function getArticleIds(): array
    {
        return $this->articleIds;
    }

    public function getArticleCount(): int
    {
        return count($this->articleIds);
    }

    public function hasArticle(string $articleId): bool
    {
        return in_array($articleId, $this->articleIds, true);
    }

    public function addArticle(string $articleId): void
    {
        if (!$this->hasArticle($articleId)) {
            $this->articleIds[] = $articleId;
            $this->updatedAt = new DateTimeImmutable();
        }
    }

    public function removeArticle(string $articleId): void
    {
        $key = array_search($articleId, $this->articleIds, true);
        if ($key !== false) {
            array_splice($this->articleIds, $key, 1);
            $this->updatedAt = new DateTimeImmutable();
        }
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
