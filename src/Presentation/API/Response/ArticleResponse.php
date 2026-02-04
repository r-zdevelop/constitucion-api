<?php

declare(strict_types=1);

namespace App\Presentation\API\Response;

use App\Domain\Document\Article;

final readonly class ArticleResponse
{
    public string $id;
    public string $documentId;
    public int $articleNumber;
    public ?string $title;
    public string $content;
    public ?string $chapter;
    public string $status;
    public array $concordances;
    public string $createdAt;
    public string $updatedAt;

    public function __construct(Article $article)
    {
        $this->id = $article->getId() ?? '';
        $this->documentId = $article->getDocumentId();
        $this->articleNumber = $article->getArticleNumber();
        $this->title = $article->getTitle();
        $this->content = $article->getContent();
        $this->chapter = $article->getChapter();
        $this->status = $article->getStatus();
        $this->concordances = $article->getConcordancesAsArray();
        $this->createdAt = $article->getCreatedAt()->format(\DateTimeInterface::ATOM);
        $this->updatedAt = $article->getUpdatedAt()->format(\DateTimeInterface::ATOM);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'documentId' => $this->documentId,
            'articleNumber' => $this->articleNumber,
            'title' => $this->title,
            'content' => $this->content,
            'chapter' => $this->chapter,
            'status' => $this->status,
            'concordances' => $this->concordances,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    /**
     * @param Article[] $articles
     * @return array[]
     */
    public static function fromCollection(array $articles): array
    {
        return array_map(fn(Article $article) => (new self($article))->toArray(), $articles);
    }
}
