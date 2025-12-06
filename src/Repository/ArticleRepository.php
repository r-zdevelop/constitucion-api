<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
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
     * Find articles by article number across all documents.
     * Returns multiple results if same article number exists in different documents.
     *
     * @return Article[]
     */
    public function findByArticleNumber(int $articleNumber): array
    {
        return $this->findBy(
            ['articleNumber' => $articleNumber],
            ['articleNumber' => 'ASC']
        );
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
     * Full text search for articles, ordered by article number.
     *
     * @return Article[]
     */
    public function fullTextSearch(string $query, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('a');
        $qb->where($qb->expr()->like('a.content', ':query'))
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('a.articleNumber', 'ASC')
            ->setMaxResults($limit);
        return $qb->getQuery()->getResult();
    }

    /**
     * Search articles by keywords (searches in title AND content) with pagination.
     *
     * Security: Uses Doctrine parameter binding to prevent SQL injection.
     * Performance: Uses Doctrine Paginator for efficient COUNT + LIMIT queries.
     *
     * @param string $searchTerm Search query (sanitized by Doctrine parameter binding)
     * @param int $page Current page (1-indexed, validated in service layer)
     * @param int $itemsPerPage Number of items per page (validated in service layer)
     * @return array{items: Article[], total: int, pages: int, currentPage: int}
     */
    public function searchPaginated(string $searchTerm, int $page = 1, int $itemsPerPage = 20): array
    {
        $qb = $this->createQueryBuilder('a');

        // Search in both title and content (OR condition for better results)
        $qb->where(
            $qb->expr()->orX(
                $qb->expr()->like('a.title', ':searchTerm'),
                $qb->expr()->like('a.content', ':searchTerm')
            )
        )
        ->setParameter('searchTerm', '%' . $searchTerm . '%')
        ->orderBy('a.articleNumber', 'ASC');

        // Apply pagination offset
        $qb->setFirstResult(($page - 1) * $itemsPerPage)
           ->setMaxResults($itemsPerPage);

        // Use Doctrine Paginator for efficient counting
        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'pages' => (int) ceil($total / $itemsPerPage),
            'currentPage' => $page,
        ];
    }

    /**
     * Get all articles with pagination and optional chapter filter.
     *
     * @param int $page Current page (1-indexed)
     * @param int $itemsPerPage Number of items per page
     * @param string|null $chapter Optional chapter filter
     * @return array{items: Article[], total: int, pages: int, currentPage: int}
     */
    public function findAllPaginated(int $page = 1, int $itemsPerPage = 20, ?string $chapter = null): array
    {
        $qb = $this->createQueryBuilder('a');
        $qb->orderBy('a.articleNumber', 'ASC');

        // Apply chapter filter if provided
        if ($chapter !== null && $chapter !== '') {
            $qb->where('a.chapter = :chapter')
               ->setParameter('chapter', $chapter);
        }

        // Apply pagination offset
        $qb->setFirstResult(($page - 1) * $itemsPerPage)
           ->setMaxResults($itemsPerPage);

        // Use Doctrine Paginator for efficient counting
        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'pages' => (int) ceil($total / $itemsPerPage),
            'currentPage' => $page,
        ];
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
