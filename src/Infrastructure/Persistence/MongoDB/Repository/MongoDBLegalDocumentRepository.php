<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MongoDB\Repository;

use App\Domain\Document\LegalDocument;
use App\Domain\Repository\LegalDocumentRepositoryInterface;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

final class MongoDBLegalDocumentRepository extends DocumentRepository implements LegalDocumentRepositoryInterface
{
    public function __construct(DocumentManager $dm)
    {
        $uow = $dm->getUnitOfWork();
        $classMetadata = $dm->getClassMetadata(LegalDocument::class);
        parent::__construct($dm, $uow, $classMetadata);
    }

    public function findById(string $id): ?LegalDocument
    {
        return $this->find($id);
    }

    public function findByNameAndYear(string $name, int $year): ?LegalDocument
    {
        return $this->findOneBy([
            'name' => $name,
            'year' => $year,
        ]);
    }

    public function findAll(): array
    {
        return $this->findBy([], ['year' => 'desc', 'name' => 'asc']);
    }

    public function save(LegalDocument $document): void
    {
        $this->getDocumentManager()->persist($document);
    }

    public function remove(LegalDocument $document): void
    {
        $this->getDocumentManager()->remove($document);
    }

    public function flush(): void
    {
        $this->getDocumentManager()->flush();
    }
}
