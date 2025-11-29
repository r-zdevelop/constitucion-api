<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * ArticleRepository
 *
 * Handles persistence and retrieval of Article entities.
 * Implements ArticleRepositoryInterface for Clean Architecture.
 */
final class ArticleRepository extends ServiceEntityRepository implements ArticleRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Find an article by its ID.
     */
    public function findById(int $id): ?Article
    {
        return $this->find($id);
    }

    /**
     * Find an article by document ID and article number.
     */
    public function findByNumber(int $documentId, int $articleNumber): ?Article
    {
        return $this->findOneBy([
            'document' => $documentId,
            'articleNumber' => $articleNumber,
        ]);
    }

    /**
     * Find all articles ordered by article number.
     *
     * @return Article[]
     */
    public function findAll(): array
    {
        return $this->findBy([], ['articleNumber' => 'ASC']);
    }

    /**
     * Find all distinct chapters (non-null, ordered alphabetically).
     *
     * @return string[] Array of chapter names
     */
    public function findAllChapters(): array
    {
        $qb = $this->createQueryBuilder('a');
        $qb->select('DISTINCT a.chapter')
            ->where($qb->expr()->isNotNull('a.chapter'))
            ->andWhere($qb->expr()->neq('a.chapter', ':empty'))
            ->setParameter('empty', '')
            ->orderBy('a.chapter', 'ASC');

        $result = $qb->getQuery()->getResult();

        // Extract scalar values from result array
        return array_map(fn(array $row): string => $row['chapter'], $result);
    }

    /**
     * Find articles by chapter, ordered by article number.
     *
     * @return Article[]
     */
    public function findByChapter(string $chapter): array
    {
        return $this->findBy(
            ['chapter' => $chapter],
            ['articleNumber' => 'ASC']
        );
    }

    /**
     * Full text search for articles.
     *
     * @return Article[]
     */
    public function fullTextSearch(string $query, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('a');
        $qb->where($qb->expr()->like('a.content', ':query'))
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit);
        return $qb->getQuery()->getResult();
    }

    /**
     * Save an article entity.
     */
    public function save(Article $article): void
    {
        $this->getEntityManager()->persist($article);
    }

    /**
     * Remove an article entity.
     */
    public function remove(Article $article): void
    {
        $this->getEntityManager()->remove($article);
    }
}
