<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MongoDB\Repository;

use App\Domain\Document\Collection;
use App\Domain\Repository\CollectionRepositoryInterface;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

final class MongoDBCollectionRepository extends DocumentRepository implements CollectionRepositoryInterface
{
    public function __construct(DocumentManager $dm)
    {
        $uow = $dm->getUnitOfWork();
        $classMetadata = $dm->getClassMetadata(Collection::class);
        parent::__construct($dm, $uow, $classMetadata);
    }

    public function findById(string $id): ?Collection
    {
        return $this->find($id);
    }

    public function findByUserId(string $userId): array
    {
        return $this->findBy(
            ['userId' => $userId],
            ['createdAt' => 'desc']
        );
    }

    public function findByUserIdAndName(string $userId, string $name): ?Collection
    {
        return $this->findOneBy([
            'userId' => $userId,
            'name' => $name,
        ]);
    }

    public function countByUserId(string $userId): int
    {
        return $this->createQueryBuilder()
            ->field('userId')->equals($userId)
            ->count()
            ->getQuery()
            ->execute();
    }

    public function save(Collection $collection): void
    {
        $this->getDocumentManager()->persist($collection);
    }

    public function remove(Collection $collection): void
    {
        $this->getDocumentManager()->remove($collection);
    }

    public function flush(): void
    {
        $this->getDocumentManager()->flush();
    }
}
