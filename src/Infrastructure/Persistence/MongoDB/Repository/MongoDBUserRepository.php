<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MongoDB\Repository;

use App\Domain\Document\User;
use App\Domain\Repository\UserRepositoryInterface;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;

final class MongoDBUserRepository extends DocumentRepository implements UserRepositoryInterface
{
    public function __construct(DocumentManager $dm)
    {
        $uow = $dm->getUnitOfWork();
        $classMetadata = $dm->getClassMetadata(User::class);
        parent::__construct($dm, $uow, $classMetadata);
    }

    public function findById(string $id): ?User
    {
        return $this->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => strtolower(trim($email))]);
    }

    public function existsByEmail(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function save(User $user): void
    {
        $this->getDocumentManager()->persist($user);
    }

    public function remove(User $user): void
    {
        $this->getDocumentManager()->remove($user);
    }

    public function flush(): void
    {
        $this->getDocumentManager()->flush();
    }
}
