# 05 - Plan Sprint 2: Core Features (Artículos y Búsqueda)

**Sprint:** 2 de 3
**Duración:** 2 semanas (Semana 3-4)
**Objetivo:** Implementar endpoints de artículos con control de acceso por roles y búsqueda avanzada
**Fecha inicio:** 2026-01-03
**Fecha fin:** 2026-01-16

---

## 🎯 Objetivo del Sprint

Construir la funcionalidad core del MVP:
- ✅ API REST para consulta de artículos constitucionales
- ✅ Control de acceso basado en roles (FREE ve 1-100, PREMIUM ve todos)
- ✅ Búsqueda por palabra clave con paginación
- ✅ Búsqueda por número de artículo
- ✅ Filtros por capítulo
- ✅ Rate limiting por rol
- ✅ Refactorizar código existente a Clean Architecture

**Entregable:** API funcional con búsqueda y control de acceso por suscripción

---

## 📋 Tareas del Sprint 2

### Fase 1: Refactoring de Código Existente (4-6 horas)

#### Tarea 1.1: Actualizar Entidad Article con Grupos de Serialización

**Objetivo:** Añadir grupos de serialización para controlar qué campos se exponen en la API

**Archivo:** `src/Domain/Entity/Article.php`

**Añadir annotations de Serializer:**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: DoctrineArticleRepository::class)]
#[ORM\Table(name: 'articles')]
#[ORM\UniqueConstraint(name: 'unique_article', columns: ['document_id', 'article_number'])]
class Article
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    #[ORM\GeneratedValue]
    #[Groups(['article:read', 'article:list'])]
    private int $id;

    #[ORM\ManyToOne(targetEntity: LegalDocument::class)]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['article:read'])]
    private LegalDocument $document;

    #[ORM\ManyToOne(targetEntity: DocumentSection::class)]
    #[ORM\JoinColumn(name: 'section_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?DocumentSection $section = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['article:read', 'article:list'])]
    private int $articleNumber;

    #[ORM\Column(type: 'text')]
    #[Groups(['article:read', 'article:list'])]
    private string $content;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['article:read', 'article:list'])]
    private ?string $title = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['article:read', 'article:list'])]
    private ?string $chapter = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['article:read'])]  // Solo en detalle, no en listado
    private ?string $notes = null;

    #[ORM\Column(type: 'string', length: 32)]
    #[Groups(['article:read', 'article:list'])]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['article:read'])]  // Solo en detalle
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['article:read'])]  // Solo en detalle
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'json')]
    #[Groups(['article:read:premium'])]  // Solo para usuarios PREMIUM+
    private array $concordances = [];

    // ... resto del código existente
}
```

**Criterios de aceptación:**
- [ ] Grupos de serialización añadidos
- [ ] `article:list` para listados (campos básicos)
- [ ] `article:read` para detalle (todos los campos excepto concordances)
- [ ] `article:read:premium` para concordances (solo PREMIUM+)

**Tiempo estimado:** 30 minutos

---

#### Tarea 1.2: Crear Value Object ArticleNumber

**Objetivo:** Encapsular validación de números de artículo

**Archivo:** `src/Domain/ValueObject/ArticleNumber.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class ArticleNumber
{
    private const MIN_ARTICLE = 1;
    private const MAX_ARTICLE = 467; // Total de artículos en la Constitución

    private function __construct(
        private int $value
    ) {}

    public static function fromInt(int $number): self
    {
        if ($number < self::MIN_ARTICLE || $number > self::MAX_ARTICLE) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Article number must be between %d and %d, got %d',
                    self::MIN_ARTICLE,
                    self::MAX_ARTICLE,
                    $number
                )
            );
        }

        return new self($number);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function isInFreeRange(): bool
    {
        return $this->value <= 100;
    }

    public function requiresPremium(): bool
    {
        return $this->value > 100;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
```

**Test:** `tests/Unit/Domain/ValueObject/ArticleNumberTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\ArticleNumber;
use PHPUnit\Framework\TestCase;

final class ArticleNumberTest extends TestCase
{
    public function testCreateValidArticleNumber(): void
    {
        $number = ArticleNumber::fromInt(50);

        $this->assertSame(50, $number->toInt());
    }

    public function testArticleInFreeRange(): void
    {
        $number = ArticleNumber::fromInt(50);

        $this->assertTrue($number->isInFreeRange());
        $this->assertFalse($number->requiresPremium());
    }

    public function testArticleRequiresPremium(): void
    {
        $number = ArticleNumber::fromInt(150);

        $this->assertFalse($number->isInFreeRange());
        $this->assertTrue($number->requiresPremium());
    }

    public function testArticleNumberTooLow(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ArticleNumber::fromInt(0);
    }

    public function testArticleNumberTooHigh(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ArticleNumber::fromInt(500);
    }
}
```

**Ejecutar test:**
```bash
php bin/phpunit tests/Unit/Domain/ValueObject/ArticleNumberTest.php
```

**Criterios de aceptación:**
- [ ] Value Object creado
- [ ] Validación de rango (1-467)
- [ ] Método `isInFreeRange()` funciona
- [ ] Tests pasan al 100%

**Tiempo estimado:** 45 minutos

---

#### Tarea 1.3: Crear Excepciones de Dominio

**Objetivo:** Excepciones específicas para casos de negocio

**Archivo:** `src/Domain/Exception/ArticleNotFoundException.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class ArticleNotFoundException extends \DomainException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('Article with ID %d not found', $id));
    }

    public static function withNumber(int $number): self
    {
        return new self(sprintf('Article number %d not found', $number));
    }
}
```

**Archivo:** `src/Domain/Exception/PremiumAccessRequiredException.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class PremiumAccessRequiredException extends \DomainException
{
    public static function forArticle(int $articleNumber): self
    {
        return new self(
            sprintf(
                'Article %d requires a Premium subscription. Upgrade your plan to access all articles.',
                $articleNumber
            )
        );
    }

    public static function forFeature(string $feature): self
    {
        return new self(
            sprintf(
                'Feature "%s" requires a Premium subscription. Upgrade your plan to access this feature.',
                $feature
            )
        );
    }
}
```

**Criterios de aceptación:**
- [ ] Excepciones creadas
- [ ] Factory methods para contextos específicos
- [ ] Mensajes descriptivos

**Tiempo estimado:** 15 minutos

---

#### Tarea 1.4: Extender ArticleRepositoryInterface

**Objetivo:** Añadir métodos necesarios para la API

**Archivo:** `src/Domain/Repository/ArticleRepositoryInterface.php`

**Añadir métodos:**

```php
<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Article;

interface ArticleRepositoryInterface
{
    // Métodos existentes
    public function findById(int $id): ?Article;
    public function findByNumber(int $documentId, int $articleNumber): ?Article;
    public function findByArticleNumber(int $articleNumber): array;
    public function findAll(): array;
    public function findAllChapters(): array;
    public function save(Article $article): void;
    public function remove(Article $article): void;

    // Nuevos métodos para API
    /**
     * Buscar artículos con paginación y rango opcional
     *
     * @param int $page Página actual (1-indexed)
     * @param int $limit Artículos por página
     * @param int|null $maxArticleNumber Límite superior de artículos (para FREE users)
     * @return array{items: Article[], total: int, pages: int, currentPage: int}
     */
    public function findPaginated(int $page, int $limit, ?int $maxArticleNumber = null): array;

    /**
     * Buscar artículos por palabra clave con paginación
     *
     * @param string $query Término de búsqueda
     * @param int $page Página actual
     * @param int $limit Artículos por página
     * @param int|null $maxArticleNumber Límite superior (para FREE users)
     * @return array{items: Article[], total: int, pages: int, currentPage: int, query: string}
     */
    public function searchPaginated(string $query, int $page, int $limit, ?int $maxArticleNumber = null): array;

    /**
     * Buscar artículos por capítulo con paginación
     *
     * @param string $chapter Nombre del capítulo
     * @param int $page Página actual
     * @param int $limit Artículos por página
     * @param int|null $maxArticleNumber Límite superior (para FREE users)
     * @return array{items: Article[], total: int, pages: int, currentPage: int}
     */
    public function findByChapterPaginated(string $chapter, int $page, int $limit, ?int $maxArticleNumber = null): array;

    /**
     * Obtener lista de capítulos con conteo de artículos
     *
     * @param int|null $maxArticleNumber Límite superior (para FREE users)
     * @return array<int, array{name: string, count: int}>
     */
    public function getChaptersWithCount(?int $maxArticleNumber = null): array;
}
```

**Criterios de aceptación:**
- [ ] Métodos añadidos a interfaz
- [ ] PHPDoc completo
- [ ] Parámetro `$maxArticleNumber` para control de acceso

**Tiempo estimado:** 20 minutos

---

#### Tarea 1.5: Implementar Nuevos Métodos en DoctrineArticleRepository

**Objetivo:** Implementar métodos de la interfaz con soporte para control de acceso

**Archivo:** `src/Infrastructure/Persistence/Doctrine/Repository/DoctrineArticleRepository.php`

**Añadir implementaciones:**

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Article;
use App\Domain\Repository\ArticleRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

final class DoctrineArticleRepository extends ServiceEntityRepository implements ArticleRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    // ... métodos existentes ...

    public function findPaginated(int $page, int $limit, ?int $maxArticleNumber = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.articleNumber', 'ASC');

        // Aplicar límite de artículos (para usuarios FREE)
        if ($maxArticleNumber !== null) {
            $qb->andWhere('a.articleNumber <= :maxNumber')
               ->setParameter('maxNumber', $maxArticleNumber);
        }

        // Paginación
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'pages' => (int) ceil($total / $limit),
            'currentPage' => $page,
        ];
    }

    public function searchPaginated(string $query, int $page, int $limit, ?int $maxArticleNumber = null): array
    {
        $qb = $this->createQueryBuilder('a');

        // Búsqueda en título y contenido
        $qb->where(
            $qb->expr()->orX(
                $qb->expr()->like('a.title', ':query'),
                $qb->expr()->like('a.content', ':query')
            )
        )
        ->setParameter('query', '%' . $query . '%')
        ->orderBy('a.articleNumber', 'ASC');

        // Aplicar límite de artículos (para usuarios FREE)
        if ($maxArticleNumber !== null) {
            $qb->andWhere('a.articleNumber <= :maxNumber')
               ->setParameter('maxNumber', $maxArticleNumber);
        }

        // Paginación
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'pages' => (int) ceil($total / $limit),
            'currentPage' => $page,
            'query' => $query,
        ];
    }

    public function findByChapterPaginated(string $chapter, int $page, int $limit, ?int $maxArticleNumber = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.chapter = :chapter')
            ->setParameter('chapter', $chapter)
            ->orderBy('a.articleNumber', 'ASC');

        // Aplicar límite de artículos (para usuarios FREE)
        if ($maxArticleNumber !== null) {
            $qb->andWhere('a.articleNumber <= :maxNumber')
               ->setParameter('maxNumber', $maxArticleNumber);
        }

        // Paginación
        $qb->setFirstResult(($page - 1) * $limit)
           ->setMaxResults($limit);

        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator),
            'total' => $total,
            'pages' => (int) ceil($total / $limit),
            'currentPage' => $page,
        ];
    }

    public function getChaptersWithCount(?int $maxArticleNumber = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.chapter, COUNT(a.id) as count')
            ->where($qb->expr()->isNotNull('a.chapter'))
            ->andWhere($qb->expr()->neq('a.chapter', ':empty'))
            ->setParameter('empty', '')
            ->groupBy('a.chapter');

        // Aplicar límite de artículos (para usuarios FREE)
        if ($maxArticleNumber !== null) {
            $qb->andWhere('a.articleNumber <= :maxNumber')
               ->setParameter('maxNumber', $maxArticleNumber);
        }

        $result = $qb->getQuery()->getResult();

        return array_map(function(array $row): array {
            return [
                'name' => $row['chapter'],
                'count' => (int) $row['count'],
            ];
        }, $result);
    }
}
```

**Criterios de aceptación:**
- [ ] Métodos implementados
- [ ] Soporte para `$maxArticleNumber` (control de acceso)
- [ ] Paginación eficiente con Doctrine Paginator
- [ ] No hay errores de sintaxis

**Tiempo estimado:** 1.5 horas

---

### Fase 2: Use Cases de Artículos (4-5 horas)

#### Tarea 2.1: Crear Use Case GetArticlesUseCase

**Objetivo:** Listar artículos con control de acceso por rol

**Archivo:** `src/Application/UseCase/Article/GetArticlesUseCase.php`

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCase\Article;

use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Repository\ArticleRepositoryInterface;

final readonly class GetArticlesUseCase
{
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;
    private const FREE_USER_ARTICLE_LIMIT = 100;

    public function __construct(
        private ArticleRepositoryInterface $articles
    ) {}

    /**
     * @return array{items: Article[], total: int, pages: int, currentPage: int}
     */
    public function execute(?User $user, int $page = 1, int $limit = self::DEFAULT_LIMIT): array
    {
        // Validar y normalizar parámetros
        $page = max(1, $page);
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        // Determinar límite de artículos según rol
        $maxArticleNumber = $this->getMaxArticleNumber($user);

        return $this->articles->findPaginated($page, $limit, $maxArticleNumber);
    }

    private function getMaxArticleNumber(?User $user): ?int
    {
        // Sin autenticación o usuario FREE: solo primeros 100 artículos
        if ($user === null || !$user->hasPremiumAccess()) {
            return self::FREE_USER_ARTICLE_LIMIT;
        }

        // Usuarios PREMIUM+ ven todos los artículos
        return null;
    }
}
```

**Criterios de aceptación:**
- [ ] Use Case creado
- [ ] Control de acceso por rol implementado
- [ ] FREE users ven solo artículos 1-100
- [ ] PREMIUM+ users ven todos los artículos
- [ ] Validación de parámetros

**Tiempo estimado:** 45 minutos

---

#### Tarea 2.2: Crear Use Case GetArticleByIdUseCase

**Objetivo:** Obtener artículo por ID con validación de acceso

**Archivo:** `src/Application/UseCase/Article/GetArticleByIdUseCase.php`

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCase\Article;

use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Exception\ArticleNotFoundException;
use App\Domain\Exception\PremiumAccessRequiredException;
use App\Domain\Repository\ArticleRepositoryInterface;

final readonly class GetArticleByIdUseCase
{
    private const FREE_USER_ARTICLE_LIMIT = 100;

    public function __construct(
        private ArticleRepositoryInterface $articles
    ) {}

    /**
     * @throws ArticleNotFoundException
     * @throws PremiumAccessRequiredException
     */
    public function execute(int $id, ?User $user): Article
    {
        $article = $this->articles->findById($id);

        if ($article === null) {
            throw ArticleNotFoundException::withId($id);
        }

        // Verificar acceso según rol
        $this->checkAccess($article, $user);

        return $article;
    }

    /**
     * @throws PremiumAccessRequiredException
     */
    private function checkAccess(Article $article, ?User $user): void
    {
        // Usuarios autenticados con premium tienen acceso total
        if ($user !== null && $user->hasPremiumAccess()) {
            return;
        }

        // Usuario FREE o anónimo: solo artículos 1-100
        if ($article->getArticleNumber() > self::FREE_USER_ARTICLE_LIMIT) {
            throw PremiumAccessRequiredException::forArticle($article->getArticleNumber());
        }
    }
}
```

**Test:** `tests/Unit/Application/UseCase/Article/GetArticleByIdUseCaseTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Article;

use App\Application\UseCase\Article\GetArticleByIdUseCase;
use App\Domain\Entity\Article;
use App\Domain\Entity\LegalDocument;
use App\Domain\Entity\User;
use App\Domain\Exception\ArticleNotFoundException;
use App\Domain\Exception\PremiumAccessRequiredException;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Role;
use PHPUnit\Framework\TestCase;

final class GetArticleByIdUseCaseTest extends TestCase
{
    private ArticleRepositoryInterface $articleRepository;
    private GetArticleByIdUseCase $useCase;

    protected function setUp(): void
    {
        $this->articleRepository = $this->createMock(ArticleRepositoryInterface::class);
        $this->useCase = new GetArticleByIdUseCase($this->articleRepository);
    }

    public function testFreeUserCanAccessArticleInFreeRange(): void
    {
        // Given: Artículo en rango FREE (1-100)
        $document = $this->createMock(LegalDocument::class);
        $article = new Article($document, 50, 'Content', 'Title');

        $this->articleRepository
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($article);

        // Given: Usuario FREE
        $user = User::register(
            Email::fromString('free@test.com'),
            'password',
            'Free User',
            Role::FREE
        );

        // When: Usuario accede al artículo
        $result = $this->useCase->execute(1, $user);

        // Then: Tiene acceso
        $this->assertSame($article, $result);
    }

    public function testFreeUserCannotAccessPremiumArticle(): void
    {
        // Given: Artículo fuera de rango FREE (>100)
        $document = $this->createMock(LegalDocument::class);
        $article = new Article($document, 150, 'Content', 'Title');

        $this->articleRepository
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($article);

        // Given: Usuario FREE
        $user = User::register(
            Email::fromString('free@test.com'),
            'password',
            'Free User',
            Role::FREE
        );

        // Then: Debe lanzar excepción
        $this->expectException(PremiumAccessRequiredException::class);

        // When: Usuario intenta acceder
        $this->useCase->execute(1, $user);
    }

    public function testPremiumUserCanAccessAnyArticle(): void
    {
        // Given: Artículo fuera de rango FREE
        $document = $this->createMock(LegalDocument::class);
        $article = new Article($document, 250, 'Content', 'Title');

        $this->articleRepository
            ->expects($this->once())
            ->method('findById')
            ->with(1)
            ->willReturn($article);

        // Given: Usuario PREMIUM
        $user = User::register(
            Email::fromString('premium@test.com'),
            'password',
            'Premium User',
            Role::PREMIUM
        );

        // When: Usuario accede al artículo
        $result = $this->useCase->execute(1, $user);

        // Then: Tiene acceso
        $this->assertSame($article, $result);
    }

    public function testNonExistentArticleThrowsException(): void
    {
        $this->articleRepository
            ->expects($this->once())
            ->method('findById')
            ->with(999)
            ->willReturn(null);

        $this->expectException(ArticleNotFoundException::class);

        $this->useCase->execute(999, null);
    }
}
```

**Ejecutar test:**
```bash
php bin/phpunit tests/Unit/Application/UseCase/Article/GetArticleByIdUseCaseTest.php
```

**Criterios de aceptación:**
- [ ] Use Case creado
- [ ] Control de acceso implementado
- [ ] FREE users bloqueados para artículos >100
- [ ] PREMIUM users acceden a todos
- [ ] Tests pasan al 100%

**Tiempo estimado:** 1.5 horas

---

#### Tarea 2.3: Crear Use Case GetArticleByNumberUseCase

**Archivo:** `src/Application/UseCase/Article/GetArticleByNumberUseCase.php`

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCase\Article;

use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Exception\ArticleNotFoundException;
use App\Domain\Exception\PremiumAccessRequiredException;
use App\Domain\Repository\ArticleRepositoryInterface;
use App\Domain\ValueObject\ArticleNumber;

final readonly class GetArticleByNumberUseCase
{
    private const DEFAULT_DOCUMENT_ID = 1; // Constitución del Ecuador

    public function __construct(
        private ArticleRepositoryInterface $articles
    ) {}

    /**
     * @throws ArticleNotFoundException
     * @throws PremiumAccessRequiredException
     * @throws \InvalidArgumentException
     */
    public function execute(int $articleNumber, ?User $user, ?int $documentId = null): Article
    {
        // Validar número de artículo
        $articleNumberVO = ArticleNumber::fromInt($articleNumber);

        // Verificar acceso según rol
        $this->checkAccess($articleNumberVO, $user);

        // Buscar artículo
        $documentId = $documentId ?? self::DEFAULT_DOCUMENT_ID;
        $article = $this->articles->findByNumber($documentId, $articleNumber);

        if ($article === null) {
            throw ArticleNotFoundException::withNumber($articleNumber);
        }

        return $article;
    }

    /**
     * @throws PremiumAccessRequiredException
     */
    private function checkAccess(ArticleNumber $articleNumber, ?User $user): void
    {
        // Usuarios premium tienen acceso total
        if ($user !== null && $user->hasPremiumAccess()) {
            return;
        }

        // Usuario FREE o anónimo: solo artículos en rango free
        if ($articleNumber->requiresPremium()) {
            throw PremiumAccessRequiredException::forArticle($articleNumber->toInt());
        }
    }
}
```

**Criterios de aceptación:**
- [ ] Use Case creado
- [ ] Usa ArticleNumber Value Object
- [ ] Control de acceso implementado
- [ ] Validación de número de artículo

**Tiempo estimado:** 45 minutos

---

#### Tarea 2.4: Crear Use Case SearchArticlesUseCase

**Archivo:** `src/Application/UseCase/Article/SearchArticlesUseCase.php`

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCase\Article;

use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\Repository\ArticleRepositoryInterface;

final readonly class SearchArticlesUseCase
{
    private const MIN_QUERY_LENGTH = 2;
    private const DEFAULT_LIMIT = 20;
    private const MAX_LIMIT = 100;
    private const FREE_USER_ARTICLE_LIMIT = 100;

    public function __construct(
        private ArticleRepositoryInterface $articles
    ) {}

    /**
     * @return array{items: Article[], total: int, pages: int, currentPage: int, query: string}
     * @throws \InvalidArgumentException
     */
    public function execute(string $query, ?User $user, int $page = 1, int $limit = self::DEFAULT_LIMIT): array
    {
        // Validar query
        $query = trim($query);
        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Search query must be at least %d characters', self::MIN_QUERY_LENGTH)
            );
        }

        // Validar y normalizar parámetros
        $page = max(1, $page);
        $limit = max(1, min(self::MAX_LIMIT, $limit));

        // Determinar límite de artículos según rol
        $maxArticleNumber = $this->getMaxArticleNumber($user);

        return $this->articles->searchPaginated($query, $page, $limit, $maxArticleNumber);
    }

    private function getMaxArticleNumber(?User $user): ?int
    {
        if ($user === null || !$user->hasPremiumAccess()) {
            return self::FREE_USER_ARTICLE_LIMIT;
        }

        return null;
    }
}
```

**Criterios de aceptación:**
- [ ] Use Case creado
- [ ] Validación de query (min 2 caracteres)
- [ ] Control de acceso por rol
- [ ] FREE users buscan solo en artículos 1-100

**Tiempo estimado:** 45 minutos

---

#### Tarea 2.5: Crear Use Case GetChaptersUseCase

**Archivo:** `src/Application/UseCase/Article/GetChaptersUseCase.php`

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCase\Article;

use App\Application\Service\ChapterOrderService;
use App\Domain\Entity\User;
use App\Domain\Repository\ArticleRepositoryInterface;

final readonly class GetChaptersUseCase
{
    private const FREE_USER_ARTICLE_LIMIT = 100;

    public function __construct(
        private ArticleRepositoryInterface $articles,
        private ChapterOrderService $chapterOrder
    ) {}

    /**
     * @return array<int, array{name: string, count: int}>
     */
    public function execute(?User $user): array
    {
        $maxArticleNumber = $this->getMaxArticleNumber($user);

        $chapters = $this->articles->getChaptersWithCount($maxArticleNumber);

        // Ordenar capítulos según jerarquía constitucional
        $chapterNames = array_column($chapters, 'name');
        $sortedNames = $this->chapterOrder->sortChapters($chapterNames);

        // Reordenar array de capítulos
        $sortedChapters = [];
        foreach ($sortedNames as $name) {
            $key = array_search($name, array_column($chapters, 'name'));
            if ($key !== false) {
                $sortedChapters[] = $chapters[$key];
            }
        }

        return $sortedChapters;
    }

    private function getMaxArticleNumber(?User $user): ?int
    {
        if ($user === null || !$user->hasPremiumAccess()) {
            return self::FREE_USER_ARTICLE_LIMIT;
        }

        return null;
    }
}
```

**Criterios de aceptación:**
- [ ] Use Case creado
- [ ] Usa ChapterOrderService para ordenar
- [ ] Control de acceso por rol
- [ ] FREE users ven solo capítulos con artículos 1-100

**Tiempo estimado:** 30 minutos

---

### Fase 3: DTOs y Controllers (6-8 horas)

#### Tarea 3.1: Crear DTOs de Request

**Archivo:** `src/Presentation/API/Request/GetArticlesRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Presentation\API\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class GetArticlesRequest
{
    public function __construct(
        #[Assert\Positive(message: 'Page must be a positive integer')]
        public int $page = 1,

        #[Assert\Range(
            min: 1,
            max: 100,
            notInRangeMessage: 'Limit must be between {{ min }} and {{ max }}'
        )]
        public int $limit = 20,
    ) {}
}
```

**Archivo:** `src/Presentation/API/Request/SearchArticlesRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Presentation\API\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class SearchArticlesRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Search query is required')]
        #[Assert\Length(
            min: 2,
            max: 200,
            minMessage: 'Search query must be at least {{ limit }} characters',
            maxMessage: 'Search query cannot be longer than {{ limit }} characters'
        )]
        public string $q,

        #[Assert\Positive(message: 'Page must be a positive integer')]
        public int $page = 1,

        #[Assert\Range(min: 1, max: 100)]
        public int $limit = 20,
    ) {}
}
```

**Criterios de aceptación:**
- [ ] DTOs creados con validaciones
- [ ] Valores por defecto definidos
- [ ] Constraints de Symfony Validator

**Tiempo estimado:** 30 minutos

---

#### Tarea 3.2: Crear ArticleController

**Archivo:** `src/Presentation/API/Controller/ArticleController.php`

```php
<?php

declare(strict_types=1);

namespace App\Presentation\API\Controller;

use App\Application\UseCase\Article\GetArticleByIdUseCase;
use App\Application\UseCase\Article\GetArticleByNumberUseCase;
use App\Application\UseCase\Article\GetArticlesUseCase;
use App\Application\UseCase\Article\GetChaptersUseCase;
use App\Application\UseCase\Article\SearchArticlesUseCase;
use App\Domain\Exception\ArticleNotFoundException;
use App\Domain\Exception\PremiumAccessRequiredException;
use App\Presentation\API\Request\GetArticlesRequest;
use App\Presentation\API\Request\SearchArticlesRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Domain\Entity\User;
use OpenApi\Attributes as OA;

#[Route('/api/v1/articles', name: 'api_v1_articles_')]
class ArticleController extends AbstractController
{
    #[Route('', name: 'list', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/articles',
        summary: 'Get paginated list of articles',
        security: [['bearerAuth' => []]],
        tags: ['Articles'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of articles',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'meta', type: 'object', properties: [
                            new OA\Property(property: 'total', type: 'integer'),
                            new OA\Property(property: 'page', type: 'integer'),
                            new OA\Property(property: 'limit', type: 'integer'),
                            new OA\Property(property: 'pages', type: 'integer'),
                        ]),
                    ]
                )
            ),
        ]
    )]
    public function list(
        #[MapQueryString] GetArticlesRequest $request,
        GetArticlesUseCase $getArticles,
        #[CurrentUser] ?User $user = null
    ): JsonResponse {
        $result = $getArticles->execute($user, $request->page, $request->limit);

        return $this->json([
            'data' => $result['items'],
            'meta' => [
                'total' => $result['total'],
                'page' => $result['currentPage'],
                'limit' => $request->limit,
                'pages' => $result['pages'],
            ],
        ], context: ['groups' => ['article:list']]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[OA\Get(
        path: '/api/v1/articles/{id}',
        summary: 'Get article by ID',
        security: [['bearerAuth' => []]],
        tags: ['Articles'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Article details'),
            new OA\Response(response: 403, description: 'Premium access required'),
            new OA\Response(response: 404, description: 'Article not found'),
        ]
    )]
    public function show(
        int $id,
        GetArticleByIdUseCase $getArticle,
        #[CurrentUser] ?User $user = null
    ): JsonResponse {
        try {
            $article = $getArticle->execute($id, $user);

            // Grupos de serialización según rol
            $groups = ['article:read'];
            if ($user !== null && $user->hasPremiumAccess()) {
                $groups[] = 'article:read:premium';
            }

            return $this->json(['data' => $article], context: ['groups' => $groups]);

        } catch (ArticleNotFoundException $e) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/not-found',
                'title' => 'Article Not Found',
                'status' => 404,
                'detail' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);

        } catch (PremiumAccessRequiredException $e) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/premium-required',
                'title' => 'Premium Access Required',
                'status' => 403,
                'detail' => $e->getMessage(),
                'upgradeUrl' => 'https://app.lexecuador.com/subscribe',
            ], Response::HTTP_FORBIDDEN);
        }
    }

    #[Route('/number/{number}', name: 'by_number', methods: ['GET'], requirements: ['number' => '\d+'])]
    #[OA\Get(
        path: '/api/v1/articles/number/{number}',
        summary: 'Get article by number',
        security: [['bearerAuth' => []]],
        tags: ['Articles'],
        parameters: [
            new OA\Parameter(name: 'number', in: 'path', required: true, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 467)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Article details'),
            new OA\Response(response: 400, description: 'Invalid article number'),
            new OA\Response(response: 403, description: 'Premium access required'),
            new OA\Response(response: 404, description: 'Article not found'),
        ]
    )]
    public function byNumber(
        int $number,
        GetArticleByNumberUseCase $getArticle,
        #[CurrentUser] ?User $user = null
    ): JsonResponse {
        try {
            $article = $getArticle->execute($number, $user);

            $groups = ['article:read'];
            if ($user !== null && $user->hasPremiumAccess()) {
                $groups[] = 'article:read:premium';
            }

            return $this->json(['data' => $article], context: ['groups' => $groups]);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/validation-error',
                'title' => 'Validation Error',
                'status' => 400,
                'detail' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);

        } catch (ArticleNotFoundException $e) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/not-found',
                'title' => 'Article Not Found',
                'status' => 404,
                'detail' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);

        } catch (PremiumAccessRequiredException $e) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/premium-required',
                'title' => 'Premium Access Required',
                'status' => 403,
                'detail' => $e->getMessage(),
                'upgradeUrl' => 'https://app.lexecuador.com/subscribe',
            ], Response::HTTP_FORBIDDEN);
        }
    }

    #[Route('/search', name: 'search', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/articles/search',
        summary: 'Search articles by keyword',
        security: [['bearerAuth' => []]],
        tags: ['Articles'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string', minLength: 2)),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Search results'),
            new OA\Response(response: 400, description: 'Invalid search query'),
        ]
    )]
    public function search(
        #[MapQueryString] SearchArticlesRequest $request,
        SearchArticlesUseCase $searchArticles,
        #[CurrentUser] ?User $user = null
    ): JsonResponse {
        try {
            $result = $searchArticles->execute($request->q, $user, $request->page, $request->limit);

            return $this->json([
                'data' => $result['items'],
                'meta' => [
                    'query' => $result['query'],
                    'total' => $result['total'],
                    'page' => $result['currentPage'],
                    'limit' => $request->limit,
                    'pages' => $result['pages'],
                ],
            ], context: ['groups' => ['article:list']]);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/validation-error',
                'title' => 'Validation Error',
                'status' => 400,
                'detail' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/chapters', name: 'chapters', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/articles/chapters',
        summary: 'Get list of chapters with article count',
        security: [['bearerAuth' => []]],
        tags: ['Articles'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of chapters',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'count', type: 'integer'),
                            ]
                        )),
                    ]
                )
            ),
        ]
    )]
    public function chapters(
        GetChaptersUseCase $getChapters,
        #[CurrentUser] ?User $user = null
    ): JsonResponse {
        $chapters = $getChapters->execute($user);

        return $this->json(['data' => $chapters]);
    }
}
```

**Criterios de aceptación:**
- [ ] Controller creado con 5 endpoints
- [ ] GET /api/v1/articles (lista)
- [ ] GET /api/v1/articles/{id} (detalle)
- [ ] GET /api/v1/articles/number/{number} (por número)
- [ ] GET /api/v1/articles/search (búsqueda)
- [ ] GET /api/v1/articles/chapters (capítulos)
- [ ] Documentación OpenAPI completa
- [ ] Manejo de errores RFC 7807
- [ ] Control de acceso por rol
- [ ] Grupos de serialización según rol

**Tiempo estimado:** 3 horas

---

**[CONTINÚA EN SIGUIENTE ARCHIVO POR LÍMITE DE CARACTERES]**

---

## 📊 Resumen del Sprint 2 (hasta ahora)

**Fases completadas:**
- ✅ Fase 1: Refactoring de código existente (5 tareas)
- ✅ Fase 2: Use Cases de artículos (5 tareas)
- ✅ Fase 3: DTOs y Controllers (2 tareas de 8)

**Pendiente:**
- Fase 3: Tests de integración, Rate Limiting
- Fase 4: Documentación y verificación final

**Tiempo estimado:** ~40 horas (2 semanas)

---

**Archivo generado:** `05_PLAN_SPRINT_2.md` (Parte 1)
**Siguiente:** Generar parte 2 o continuar con Sprint 3
