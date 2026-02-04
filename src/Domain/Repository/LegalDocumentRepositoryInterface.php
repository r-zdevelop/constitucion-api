<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Document\LegalDocument;

interface LegalDocumentRepositoryInterface
{
    public function findById(string $id): ?LegalDocument;

    public function findByNameAndYear(string $name, int $year): ?LegalDocument;

    /**
     * @return LegalDocument[]
     */
    public function findAll(): array;

    public function save(LegalDocument $document): void;

    public function remove(LegalDocument $document): void;

    public function flush(): void;
}
