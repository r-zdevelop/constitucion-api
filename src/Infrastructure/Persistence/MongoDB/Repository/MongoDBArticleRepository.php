<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MongoDB\Repository;

use App\Domain\Document\Article;
use App\Domain\Repository\ArticleRepositoryInterface;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

final class MongoDBArticleRepository extends DocumentRepository implements ArticleRepositoryInterface
{
    public function __construct(DocumentManager $dm)
    {
        $uow = $dm->getUnitOfWork();
        $classMetadata = $dm->getClassMetadata(Article::class);
        parent::__construct($dm, $uow, $classMetadata);
    }

    public function findById(string $id): ?Article
    {
        return $this->find($id);
    }

    public function findByNumber(string $documentId, int $articleNumber): ?Article
    {
        return $this->findOneBy([
            'documentId' => $documentId,
            'articleNumber' => $articleNumber,
        ]);
    }

    public function findByArticleNumber(int $articleNumber): array
    {
        return $this->findBy(
            ['articleNumber' => $articleNumber],
            ['articleNumber' => 'asc']
        );
    }

    public function findAll(): array
    {
        return $this->findBy([], ['articleNumber' => 'asc']);
    }

    public function findAllChapters(): array
    {
        $builder = $this->createAggregationBuilder();
        $builder
            ->match()
                ->field('chapter')->notEqual(null)
                ->field('chapter')->notEqual('')
            ->group()
                ->field('id')->expression('$chapter')
            ->sort(['_id' => 1]);

        $results = $builder->execute()->toArray();

        return array_map(fn($row) => $row['_id'], $results);
    }

    public function findByChapter(string $chapter): array
    {
        return $this->findBy(
            ['chapter' => $chapter],
            ['articleNumber' => 'asc']
        );
    }

    public function fullTextSearch(string $query, int $limit = 50): array
    {
        $builder = $this->createQueryBuilder();
        $builder
            ->text($query)
            ->sort(['articleNumber' => 'asc'])
            ->limit($limit);

        return $builder->getQuery()->execute()->toArray();
    }

    public function searchPaginated(string $searchTerm, int $page = 1, int $itemsPerPage = 20): array
    {
        $skip = ($page - 1) * $itemsPerPage;

        // Use text search if MongoDB text index is available
        $queryBuilder = $this->createQueryBuilder();
        $queryBuilder->text($searchTerm);

        // Count total
        $countBuilder = $this->createQueryBuilder();
        $countBuilder->text($searchTerm);
        $total = $countBuilder->count()->getQuery()->execute();

        // Get paginated results
        $queryBuilder
            ->sort(['articleNumber' => 'asc'])
            ->skip($skip)
            ->limit($itemsPerPage);

        $items = $queryBuilder->getQuery()->execute()->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'pages' => (int) ceil($total / $itemsPerPage),
            'currentPage' => $page,
        ];
    }

    public function findAllPaginated(int $page = 1, int $itemsPerPage = 20, ?string $chapter = null): array
    {
        $skip = ($page - 1) * $itemsPerPage;

        $criteria = [];
        if ($chapter !== null && $chapter !== '') {
            $criteria['chapter'] = $chapter;
        }

        // Count total
        $total = $this->createQueryBuilder()
            ->find();

        if ($chapter !== null && $chapter !== '') {
            $total->field('chapter')->equals($chapter);
        }

        $totalCount = $total->count()->getQuery()->execute();

        // Get paginated results
        $queryBuilder = $this->createQueryBuilder()
            ->sort(['articleNumber' => 'asc'])
            ->skip($skip)
            ->limit($itemsPerPage);

        if ($chapter !== null && $chapter !== '') {
            $queryBuilder->field('chapter')->equals($chapter);
        }

        $items = $queryBuilder->getQuery()->execute()->toArray();

        return [
            'items' => $items,
            'total' => $totalCount,
            'pages' => (int) ceil($totalCount / $itemsPerPage),
            'currentPage' => $page,
        ];
    }

    public function save(Article $article): void
    {
        $this->getDocumentManager()->persist($article);
    }

    public function remove(Article $article): void
    {
        $this->getDocumentManager()->remove($article);
    }

    public function flush(): void
    {
        $this->getDocumentManager()->flush();
    }
}
