<?php

declare(strict_types=1);

namespace App\Presentation\API\Response;

use App\Domain\Document\Collection;

final readonly class CollectionResponse
{
    public string $id;
    public string $name;
    public ?string $description;
    public int $articleCount;
    public array $articleIds;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Collection $collection)
    {
        $this->id = $collection->getId() ?? '';
        $this->name = $collection->getName();
        $this->description = $collection->getDescription();
        $this->articleCount = $collection->getArticleCount();
        $this->articleIds = $collection->getArticleIds();
        $this->createdAt = $collection->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $collection->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'articleCount' => $this->articleCount,
            'articleIds' => $this->articleIds,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    /**
     * @param Collection[] $collections
     * @return array[]
     */
    public static function fromCollection(array $collections): array
    {
        return array_map(fn(Collection $collection) => (new self($collection))->toArray(), $collections);
    }
}
