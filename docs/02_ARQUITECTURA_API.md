# 02 - Arquitectura de la API REST

**Proyecto:** LeyesBook API
**Patrón arquitectónico:** Clean Architecture + DDD (Domain-Driven Design)
**Principios:** SOLID, DRY, KISS, YAGNI
**Stack:** Symfony 7.3 + Doctrine ORM 3.5 + JWT + PostgreSQL/MySQL/MongoDB
**Versión API:** v1
**Fecha:** 2025-12-19

---

## 🏛️ Diagrama de Capas (Clean Architecture)

```
┌─────────────────────────────────────────────────────────────────┐
│                     PRESENTATION LAYER                          │
│                  (Controllers + DTOs + Serialization)           │
│                                                                 │
│  📁 src/Presentation/API/Controller/                            │
│     ├── AuthController.php          (POST /api/v1/auth/*)      │
│     ├── ArticleController.php       (GET /api/v1/articles/*)   │
│     ├── SubscriptionController.php  (POST /api/v1/subscribe)   │
│     └── UserController.php          (GET /api/v1/users/me)     │
│                                                                 │
│  📁 src/Presentation/API/Request/  (DTOs de entrada)            │
│     ├── RegisterRequest.php                                    │
│     ├── LoginRequest.php                                       │
│     └── SearchArticlesRequest.php                              │
│                                                                 │
│  📁 src/Presentation/API/Response/ (DTOs de salida)             │
│     ├── ArticleResponse.php                                    │
│     ├── UserResponse.php                                       │
│     └── PaginatedResponse.php                                  │
│                                                                 │
└────────────────────────┬────────────────────────────────────────┘
                         │ HTTP Request/Response (JSON)
                         │ Symfony HttpKernel
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    APPLICATION LAYER                            │
│               (Use Cases + Business Logic)                      │
│                                                                 │
│  📁 src/Application/UseCase/                                    │
│     ├── Auth/                                                  │
│     │   ├── RegisterUserUseCase.php                            │
│     │   ├── LoginUserUseCase.php                               │
│     │   └── RefreshTokenUseCase.php                            │
│     ├── Article/                                               │
│     │   ├── SearchArticlesUseCase.php                          │
│     │   ├── GetArticleByNumberUseCase.php                      │
│     │   └── GetArticlesByChapterUseCase.php                    │
│     └── Subscription/                                          │
│         ├── CreateSubscriptionUseCase.php                      │
│         └── CancelSubscriptionUseCase.php                      │
│                                                                 │
│  📁 src/Application/Service/                                    │
│     ├── ArticleService.php          (ya existe ✅)             │
│     ├── ChapterOrderService.php     (ya existe ✅)             │
│     ├── SubscriptionService.php                                │
│     └── PaymentService.php                                     │
│                                                                 │
└────────────────────────┬────────────────────────────────────────┘
                         │ Interfaces (Dependency Inversion)
                         │ Repository Interfaces
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                   INFRASTRUCTURE LAYER                          │
│           (Repositories + External Services)                    │
│                                                                 │
│  📁 src/Infrastructure/Persistence/Doctrine/                    │
│     ├── Repository/                                            │
│     │   ├── DoctrineArticleRepository.php  (ya existe ✅)      │
│     │   ├── DoctrineUserRepository.php                         │
│     │   └── DoctrineSubscriptionRepository.php                 │
│     └── Migration/                                             │
│         └── Version*.php                                       │
│                                                                 │
│  📁 src/Infrastructure/Payment/                                 │
│     ├── StripePaymentGateway.php                               │
│     └── PayPhonePaymentGateway.php                             │
│                                                                 │
│  📁 src/Infrastructure/Security/                                │
│     ├── JwtTokenManager.php                                    │
│     └── PasswordHasher.php                                     │
│                                                                 │
└────────────────────────┬────────────────────────────────────────┘
                         │ Doctrine ORM
                         │ External APIs (Stripe, PayPhone)
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                       DOMAIN LAYER                              │
│              (Entities + Value Objects + Interfaces)            │
│                                                                 │
│  📁 src/Domain/Entity/                                          │
│     ├── Article.php                (ya existe ✅)              │
│     ├── LegalDocument.php          (ya existe ✅)              │
│     ├── ArticleHistory.php         (ya existe ✅)              │
│     ├── User.php                   (nuevo)                     │
│     ├── Subscription.php           (nuevo)                     │
│     └── Payment.php                (nuevo)                     │
│                                                                 │
│  📁 src/Domain/ValueObject/                                     │
│     ├── Email.php                                              │
│     ├── Role.php                                               │
│     ├── SubscriptionPlan.php                                   │
│     └── Money.php                                              │
│                                                                 │
│  📁 src/Domain/Repository/  (Interfaces)                        │
│     ├── ArticleRepositoryInterface.php  (ya existe ✅)         │
│     ├── UserRepositoryInterface.php                            │
│     └── SubscriptionRepositoryInterface.php                    │
│                                                                 │
│  📁 src/Domain/Exception/                                       │
│     ├── ArticleNotFoundException.php                           │
│     ├── UserNotFoundException.php                              │
│     ├── InvalidCredentialsException.php                        │
│     └── SubscriptionRequiredException.php                      │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
                         Pure Business Logic
                         No dependencies externas
```

---

## 📂 Estructura de Directorios Propuesta

```
constitucion-api/
├── bin/
│   └── console
├── config/
│   ├── packages/
│   │   ├── doctrine.yaml
│   │   ├── framework.yaml
│   │   ├── lexik_jwt_authentication.yaml      # ← NUEVO
│   │   ├── nelmio_api_doc.yaml                # ← NUEVO
│   │   ├── nelmio_cors.yaml                   # ← NUEVO
│   │   ├── security.yaml                      # ← NUEVO
│   │   └── validator.yaml                     # ← NUEVO
│   ├── routes/
│   │   └── api.yaml                           # ← NUEVO (rutas API v1)
│   └── services.yaml
├── migrations/
│   ├── Version20251119220232.php              # ← Existente
│   ├── Version20251120000001.php              # ← NUEVO (User + Subscription)
│   └── Version20251120000002.php              # ← NUEVO (Payment)
├── public/
│   └── index.php
├── src/
│   ├── Application/
│   │   ├── Service/
│   │   │   ├── ArticleService.php             # ✅ Mantener
│   │   │   ├── ChapterOrderService.php        # ✅ Mantener
│   │   │   ├── SubscriptionService.php        # ← NUEVO
│   │   │   └── PaymentService.php             # ← NUEVO
│   │   └── UseCase/
│   │       ├── Auth/
│   │       │   ├── RegisterUserUseCase.php    # ← NUEVO
│   │       │   ├── LoginUserUseCase.php       # ← NUEVO
│   │       │   └── RefreshTokenUseCase.php    # ← NUEVO
│   │       ├── Article/
│   │       │   ├── SearchArticlesUseCase.php  # ← NUEVO
│   │       │   └── GetArticleByNumberUseCase.php  # ← NUEVO
│   │       └── Subscription/
│   │           ├── CreateSubscriptionUseCase.php  # ← NUEVO
│   │           └── CancelSubscriptionUseCase.php  # ← NUEVO
│   ├── Domain/
│   │   ├── Entity/
│   │   │   ├── Article.php                    # ✅ Mantener
│   │   │   ├── ArticleHistory.php             # ✅ Mantener
│   │   │   ├── LegalDocument.php              # ✅ Mantener
│   │   │   ├── User.php                       # ← NUEVO
│   │   │   ├── Subscription.php               # ← NUEVO
│   │   │   └── Payment.php                    # ← NUEVO
│   │   ├── ValueObject/
│   │   │   ├── Email.php                      # ← NUEVO
│   │   │   ├── Role.php                       # ← NUEVO
│   │   │   ├── SubscriptionPlan.php           # ← NUEVO
│   │   │   └── Money.php                      # ← NUEVO
│   │   ├── Repository/
│   │   │   ├── ArticleRepositoryInterface.php # ✅ Mantener
│   │   │   ├── UserRepositoryInterface.php    # ← NUEVO
│   │   │   └── SubscriptionRepositoryInterface.php  # ← NUEVO
│   │   └── Exception/
│   │       ├── ArticleNotFoundException.php   # ← NUEVO
│   │       ├── UserNotFoundException.php      # ← NUEVO
│   │       ├── InvalidCredentialsException.php  # ← NUEVO
│   │       └── SubscriptionRequiredException.php  # ← NUEVO
│   ├── Infrastructure/
│   │   ├── Persistence/
│   │   │   └── Doctrine/
│   │   │       └── Repository/
│   │   │           ├── DoctrineArticleRepository.php  # ✅ Mover desde src/Repository
│   │   │           ├── DoctrineUserRepository.php     # ← NUEVO
│   │   │           └── DoctrineSubscriptionRepository.php  # ← NUEVO
│   │   ├── Payment/
│   │   │   ├── PaymentGatewayInterface.php    # ← NUEVO
│   │   │   ├── StripePaymentGateway.php       # ← NUEVO
│   │   │   └── PayPhonePaymentGateway.php     # ← NUEVO
│   │   └── Security/
│   │       ├── JwtTokenManager.php            # ← NUEVO
│   │       └── CustomUserProvider.php         # ← NUEVO
│   ├── Presentation/
│   │   └── API/
│   │       ├── Controller/
│   │       │   ├── AuthController.php         # ← NUEVO
│   │       │   ├── ArticleController.php      # ← REFACTOR desde src/Controller
│   │       │   ├── SubscriptionController.php # ← NUEVO
│   │       │   └── UserController.php         # ← NUEVO
│   │       ├── Request/  (DTOs de entrada)
│   │       │   ├── RegisterRequest.php        # ← NUEVO
│   │       │   ├── LoginRequest.php           # ← NUEVO
│   │       │   ├── SearchArticlesRequest.php  # ← NUEVO
│   │       │   └── CreateSubscriptionRequest.php  # ← NUEVO
│   │       ├── Response/  (DTOs de salida)
│   │       │   ├── ArticleResponse.php        # ← NUEVO
│   │       │   ├── UserResponse.php           # ← NUEVO
│   │       │   ├── SubscriptionResponse.php   # ← NUEVO
│   │       │   └── PaginatedResponse.php      # ← NUEVO
│   │       └── EventSubscriber/
│   │           ├── ExceptionSubscriber.php    # ← NUEVO (manejo de errores RFC 7807)
│   │           └── CorsSubscriber.php         # ← NUEVO (si no usas nelmio/cors)
│   ├── Command/
│   │   └── ImportConstitutionCommand.php      # ✅ Mantener
│   └── Kernel.php
├── tests/
│   ├── Unit/
│   │   ├── Application/
│   │   │   └── UseCase/
│   │   │       └── Auth/
│   │   │           └── RegisterUserUseCaseTest.php  # ← NUEVO
│   │   └── Domain/
│   │       └── ValueObject/
│   │           └── EmailTest.php              # ← NUEVO
│   ├── Integration/
│   │   └── Infrastructure/
│   │       └── Repository/
│   │           └── DoctrineUserRepositoryTest.php  # ← NUEVO
│   └── Functional/
│       └── API/
│           ├── AuthControllerTest.php         # ← NUEVO
│           └── ArticleControllerTest.php      # ← NUEVO
├── .env
├── .env.test
├── .gitignore
├── composer.json
├── phpunit.xml.dist
└── README.md
```

**Cambios de estructura:**
1. ✅ Mover `src/Repository/` → `src/Infrastructure/Persistence/Doctrine/Repository/`
2. ✅ Mover `src/Service/` → `src/Application/Service/`
3. ✅ Mover `src/Entity/` → `src/Domain/Entity/`
4. ❌ Eliminar `src/Controller/HomeController.php`
5. ✅ Refactorizar `src/Controller/ArticleController.php` → `src/Presentation/API/Controller/ArticleController.php`

---

## 🔄 Flujo Request/Response (Ejemplo: Registro de Usuario)

```
┌─────────────────────────────────────────────────────────────────┐
│ 1. HTTP REQUEST                                                 │
│    POST /api/v1/auth/register                                   │
│    Content-Type: application/json                               │
│                                                                 │
│    {                                                            │
│      "email": "user@example.com",                               │
│      "password": "SecurePass123!",                              │
│      "name": "John Doe"                                         │
│    }                                                            │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. ROUTING (config/routes/api.yaml)                             │
│    Symfony Router → AuthController::register()                  │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. PRESENTATION LAYER                                           │
│    src/Presentation/API/Controller/AuthController.php           │
│                                                                 │
│    #[Route('/api/v1/auth/register', methods: ['POST'])]        │
│    public function register(                                   │
│        #[MapRequestPayload] RegisterRequest $request           │
│    ): JsonResponse {                                           │
│        // Symfony deserializa y valida automáticamente         │
│        // Si hay errores, lanza ValidationException           │
│    }                                                           │
│                                                                 │
│    RegisterRequest (DTO):                                      │
│    - email: string (#[Assert\Email])                           │
│    - password: string (#[Assert\Length(min: 8)])               │
│    - name: string (#[Assert\NotBlank])                         │
└────────────────────────┬────────────────────────────────────────┘
                         │ DTO validado
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. APPLICATION LAYER                                            │
│    src/Application/UseCase/Auth/RegisterUserUseCase.php         │
│                                                                 │
│    public function execute(string $email, string $password,    │
│                           string $name): User {                │
│        // 1. Verificar que email no exista                     │
│        // 2. Hashear password                                  │
│        // 3. Crear User entity                                 │
│        // 4. Persistir en repositorio                          │
│        // 5. Enviar email de bienvenida (opcional)             │
│        // 6. Retornar User                                     │
│    }                                                           │
└────────────────────────┬────────────────────────────────────────┘
                         │ UserRepositoryInterface
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. INFRASTRUCTURE LAYER                                         │
│    src/Infrastructure/Persistence/Doctrine/                     │
│        Repository/DoctrineUserRepository.php                    │
│                                                                 │
│    public function save(User $user): void {                    │
│        $this->entityManager->persist($user);                   │
│        $this->entityManager->flush();                          │
│    }                                                           │
└────────────────────────┬────────────────────────────────────────┘
                         │ Doctrine ORM
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. DOMAIN LAYER                                                 │
│    src/Domain/Entity/User.php                                   │
│                                                                 │
│    - Validaciones de negocio                                   │
│    - Lógica de dominio pura                                    │
│    - Sin dependencias de framework                             │
└────────────────────────┬────────────────────────────────────────┘
                         │ User entity creado
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 7. RESPONSE SERIALIZATION                                       │
│    src/Presentation/API/Response/UserResponse.php               │
│                                                                 │
│    Symfony Serializer convierte User → JSON con grupos:        │
│    #[Groups(['user:read'])]                                    │
│                                                                 │
│    return $this->json($user, 201, [], [                        │
│        'groups' => ['user:read']                               │
│    ]);                                                         │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 8. HTTP RESPONSE (201 Created)                                 │
│    Content-Type: application/json                               │
│                                                                 │
│    {                                                            │
│      "id": "550e8400-e29b-41d4-a716-446655440000",             │
│      "email": "user@example.com",                               │
│      "name": "John Doe",                                        │
│      "role": "ROLE_FREE",                                       │
│      "createdAt": "2025-12-19T10:30:00Z",                      │
│      "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."        │
│    }                                                            │
└─────────────────────────────────────────────────────────────────┘
```

**Tiempo total:** ~50-100ms (sin cache)

**Puntos de control:**
- ✅ Validación automática en DTO (Symfony Validator)
- ✅ Lógica de negocio en Use Case (testeable)
- ✅ Persistencia abstraída por interfaz (SOLID)
- ✅ Serialización controlada por grupos

---

## 🧩 Principios SOLID Aplicados

### 1. Single Responsibility Principle (SRP) ✅

**Cada clase tiene UNA sola razón para cambiar**

```php
// ❌ MAL - Controller con lógica de negocio
class ArticleController
{
    public function search(Request $request): JsonResponse
    {
        // Validación
        if (strlen($request->get('q')) < 2) {
            return new JsonResponse(['error' => 'Min 2 chars'], 400);
        }

        // Lógica de negocio
        $articles = $this->entityManager->getRepository(Article::class)
            ->createQueryBuilder('a')
            ->where('a.content LIKE :q')
            ->setParameter('q', '%' . $request->get('q') . '%')
            ->getQuery()
            ->getResult();

        // Serialización
        $data = array_map(fn($a) => ['id' => $a->getId(), ...], $articles);

        return new JsonResponse($data);
    }
}

// ✅ BIEN - Separación de responsabilidades
class ArticleController  // Responsabilidad: HTTP handling
{
    public function search(
        #[MapRequestPayload] SearchArticlesRequest $request,  // Responsabilidad: Validación
        SearchArticlesUseCase $useCase                       // Responsabilidad: Lógica de negocio
    ): JsonResponse {
        $articles = $useCase->execute(
            $request->query,
            $request->page,
            $request->limit
        );

        return $this->json($articles, 200, [], [
            'groups' => ['article:read']  // Responsabilidad: Serialización
        ]);
    }
}

class SearchArticlesUseCase  // Responsabilidad: Búsqueda de artículos
{
    public function __construct(
        private ArticleRepositoryInterface $articles
    ) {}

    public function execute(string $query, int $page, int $limit): array
    {
        return $this->articles->search($query, $page, $limit);
    }
}
```

---

### 2. Open/Closed Principle (OCP) ✅

**Abierto para extensión, cerrado para modificación**

```php
// ✅ Interfaz para payment gateways
interface PaymentGatewayInterface
{
    public function charge(Money $amount, string $token): PaymentResult;
    public function refund(string $paymentId): RefundResult;
}

// Implementación Stripe
class StripePaymentGateway implements PaymentGatewayInterface
{
    public function charge(Money $amount, string $token): PaymentResult
    {
        // Lógica Stripe
    }
}

// Implementación PayPhone (Ecuador)
class PayPhonePaymentGateway implements PaymentGatewayInterface
{
    public function charge(Money $amount, string $token): PaymentResult
    {
        // Lógica PayPhone
    }
}

// Servicio que usa gateway (NO necesita modificarse al añadir nuevos gateways)
class SubscriptionService
{
    public function __construct(
        private PaymentGatewayInterface $gateway  // ← Inyección de dependencia
    ) {}

    public function subscribe(User $user, SubscriptionPlan $plan): Subscription
    {
        // Mismo código funciona con Stripe, PayPhone, o cualquier futuro gateway
        $result = $this->gateway->charge($plan->getPrice(), $user->getPaymentToken());
        // ...
    }
}

// Configuración en services.yaml
services:
    App\Infrastructure\Payment\PaymentGatewayInterface:
        class: App\Infrastructure\Payment\StripePaymentGateway
        # Para cambiar a PayPhone, solo cambiar esta línea (no tocar código)
```

---

### 3. Liskov Substitution Principle (LSP) ✅

**Subclases deben ser sustituibles por su clase base**

```php
// ✅ Todas las implementaciones de Repository cumplen el contrato
interface ArticleRepositoryInterface
{
    public function findById(int $id): ?Article;
    public function save(Article $article): void;
}

class DoctrineArticleRepository implements ArticleRepositoryInterface
{
    public function findById(int $id): ?Article
    {
        return $this->find($id);  // Cumple contrato
    }

    public function save(Article $article): void
    {
        $this->em->persist($article);  // Cumple contrato
        $this->em->flush();
    }
}

class InMemoryArticleRepository implements ArticleRepositoryInterface
{
    private array $articles = [];

    public function findById(int $id): ?Article
    {
        return $this->articles[$id] ?? null;  // Cumple contrato
    }

    public function save(Article $article): void
    {
        $this->articles[$article->getId()] = $article;  // Cumple contrato
    }
}

// El use case NO sabe ni le importa qué implementación usa
class GetArticleByNumberUseCase
{
    public function __construct(
        private ArticleRepositoryInterface $repository  // ← Cualquier implementación
    ) {}

    public function execute(int $number): ?Article
    {
        return $this->repository->findById($number);
        // Funciona igual con Doctrine, InMemory, Redis, etc.
    }
}
```

---

### 4. Interface Segregation Principle (ISP) ✅

**Interfaces específicas son mejores que una interfaz general**

```php
// ❌ MAL - Interfaz obesa
interface ArticleRepositoryInterface
{
    public function findById(int $id): ?Article;
    public function findAll(): array;
    public function search(string $q): array;
    public function findPremiumArticles(): array;  // Solo para premium
    public function findByChapter(string $chapter): array;
    public function exportToPdf(): string;  // No es responsabilidad del repo
    public function sendEmail(Article $article): void;  // No es responsabilidad del repo
}

// ✅ BIEN - Interfaces segregadas
interface ArticleRepositoryInterface
{
    public function findById(int $id): ?Article;
    public function save(Article $article): void;
    public function remove(Article $article): void;
}

interface ArticleSearchInterface
{
    public function search(string $query, int $page, int $limit): array;
    public function findByChapter(string $chapter): array;
}

interface PremiumContentInterface
{
    public function findPremiumArticles(): array;
    public function hasAccessTo(User $user, Article $article): bool;
}

// Los clientes solo dependen de lo que necesitan
class SearchArticlesUseCase
{
    public function __construct(
        private ArticleSearchInterface $search  // Solo búsqueda
    ) {}
}

class SubscriptionService
{
    public function __construct(
        private PremiumContentInterface $premiumContent  // Solo premium
    ) {}
}
```

---

### 5. Dependency Inversion Principle (DIP) ✅

**Depender de abstracciones, no de concreciones**

```php
// ❌ MAL - Depende de implementación concreta
class RegisterUserUseCase
{
    private DoctrineUserRepository $users;  // ← Acoplamiento fuerte

    public function __construct()
    {
        $this->users = new DoctrineUserRepository();  // ← Hardcoded
    }

    public function execute(string $email, string $password): User
    {
        // ...
    }
}

// ✅ BIEN - Depende de abstracción
class RegisterUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $users,  // ← Abstracción
        private PasswordHasherInterface $hasher  // ← Abstracción
    ) {}

    public function execute(string $email, string $password, string $name): User
    {
        // Verificar que no exista
        if ($this->users->findByEmail($email) !== null) {
            throw new DuplicateEmailException();
        }

        // Crear usuario
        $user = new User(
            Email::fromString($email),
            $this->hasher->hash($password),
            $name
        );

        $this->users->save($user);

        return $user;
    }
}

// Configuración de DI (services.yaml)
services:
    # Autowiring automático de interfaces
    App\Domain\Repository\UserRepositoryInterface:
        class: App\Infrastructure\Persistence\Doctrine\Repository\DoctrineUserRepository

    App\Infrastructure\Security\PasswordHasherInterface:
        class: App\Infrastructure\Security\SymfonyPasswordHasher
```

**Ventajas:**
- ✅ Testeable con mocks
- ✅ Cambiar implementación sin modificar código
- ✅ Bajo acoplamiento

---

## 🎨 Patrones de Diseño Utilizados

### 1. Repository Pattern ⭐

**Propósito:** Abstraer acceso a datos

```php
// Interfaz (Domain)
namespace App\Domain\Repository;

interface UserRepositoryInterface
{
    public function findById(string $id): ?User;
    public function findByEmail(string $email): ?User;
    public function save(User $user): void;
    public function remove(User $user): void;
}

// Implementación Doctrine (Infrastructure)
namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Repository\UserRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

class DoctrineUserRepository extends ServiceEntityRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function save(User $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }
}
```

---

### 2. Use Case Pattern (Application Service)

**Propósito:** Encapsular lógica de negocio de un caso de uso específico

```php
namespace App\Application\UseCase\Auth;

use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Exception\DuplicateEmailException;

final readonly class RegisterUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $users,
        private PasswordHasherInterface $hasher
    ) {}

    public function execute(string $email, string $password, string $name): User
    {
        // 1. Validar que email no exista
        if ($this->users->findByEmail($email) !== null) {
            throw new DuplicateEmailException("Email already registered");
        }

        // 2. Hashear password
        $hashedPassword = $this->hasher->hash($password);

        // 3. Crear usuario
        $user = User::register(
            email: Email::fromString($email),
            password: $hashedPassword,
            name: $name,
            role: Role::FREE
        );

        // 4. Persistir
        $this->users->save($user);

        // 5. Evento de dominio (opcional)
        // $this->eventBus->dispatch(new UserRegisteredEvent($user));

        return $user;
    }
}
```

**Ventajas:**
- ✅ Lógica de negocio aislada
- ✅ Fácilmente testeable
- ✅ Reutilizable desde diferentes controllers/CLI

---

### 3. DTO Pattern (Data Transfer Object)

**Propósito:** Transportar datos entre capas sin lógica

```php
namespace App\Presentation\API\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class RegisterRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(message: 'Invalid email format')]
        public string $email,

        #[Assert\NotBlank(message: 'Password is required')]
        #[Assert\Length(
            min: 8,
            max: 64,
            minMessage: 'Password must be at least 8 characters'
        )]
        #[Assert\Regex(
            pattern: '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)/',
            message: 'Password must contain uppercase, lowercase, and number'
        )]
        public string $password,

        #[Assert\NotBlank(message: 'Name is required')]
        #[Assert\Length(min: 2, max: 100)]
        public string $name,
    ) {}
}

// Uso en controller
#[Route('/api/v1/auth/register', methods: ['POST'])]
public function register(
    #[MapRequestPayload] RegisterRequest $request,  // ← Deserializa y valida automáticamente
    RegisterUserUseCase $useCase
): JsonResponse {
    $user = $useCase->execute(
        $request->email,
        $request->password,
        $request->name
    );

    return $this->json($user, 201, [], ['groups' => ['user:read']]);
}
```

---

### 4. Value Object Pattern

**Propósito:** Representar conceptos de dominio sin identidad

```php
namespace App\Domain\ValueObject;

final readonly class Email
{
    private function __construct(
        private string $value
    ) {}

    public static function fromString(string $email): self
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidEmailException("Invalid email: {$email}");
        }

        return new self($email);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

// Uso en Entity
class User
{
    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private Email $email;

    public function __construct(Email $email, string $password, string $name)
    {
        $this->email = $email;  // Ya validado en el VO
        // ...
    }
}
```

**Ventajas:**
- ✅ Validación en un solo lugar
- ✅ Inmutabilidad
- ✅ Expresividad del dominio

---

### 5. Factory Pattern (Named Constructors)

**Propósito:** Crear objetos complejos con intención clara

```php
namespace App\Domain\Entity;

class User
{
    private function __construct(
        private string $id,
        private Email $email,
        private string $password,
        private string $name,
        private Role $role,
        private \DateTimeImmutable $createdAt
    ) {}

    // Factory method para registro normal
    public static function register(
        Email $email,
        string $password,
        string $name,
        Role $role = Role::FREE
    ): self {
        return new self(
            id: Uuid::v4()->toString(),
            email: $email,
            password: $password,
            name: $name,
            role: $role,
            createdAt: new \DateTimeImmutable()
        );
    }

    // Factory method para admin
    public static function createAdmin(
        Email $email,
        string $password,
        string $name
    ): self {
        return new self(
            id: Uuid::v4()->toString(),
            email: $email,
            password: $password,
            name: $name,
            role: Role::ADMIN,
            createdAt: new \DateTimeImmutable()
        );
    }

    // Factory method para OAuth
    public static function fromOAuth(
        Email $email,
        string $name,
        string $provider
    ): self {
        return new self(
            id: Uuid::v4()->toString(),
            email: $email,
            password: '',  // No password para OAuth
            name: $name,
            role: Role::FREE,
            createdAt: new \DateTimeImmutable()
        );
    }
}

// Uso
$user = User::register(
    Email::fromString('user@example.com'),
    'hashed_password',
    'John Doe'
);
```

---

### 6. Strategy Pattern (Payment Gateways)

**Propósito:** Intercambiar algoritmos en runtime

```php
// Estrategia
interface PaymentGatewayInterface
{
    public function charge(Money $amount, string $token): PaymentResult;
}

// Estrategias concretas
class StripePaymentGateway implements PaymentGatewayInterface { /* ... */ }
class PayPhonePaymentGateway implements PaymentGatewayInterface { /* ... */ }

// Contexto
class PaymentService
{
    private PaymentGatewayInterface $gateway;

    public function setGateway(PaymentGatewayInterface $gateway): void
    {
        $this->gateway = $gateway;
    }

    public function processPayment(Money $amount, string $token): PaymentResult
    {
        return $this->gateway->charge($amount, $token);
    }
}

// Uso
$paymentService = new PaymentService();

// Para usuarios internacionales
$paymentService->setGateway(new StripePaymentGateway());

// Para usuarios de Ecuador
$paymentService->setGateway(new PayPhonePaymentGateway());

$result = $paymentService->processPayment($amount, $token);
```

---

## 📛 Convenciones de Naming

### Namespaces

```php
// Domain
App\Domain\Entity\Article
App\Domain\ValueObject\Email
App\Domain\Repository\UserRepositoryInterface
App\Domain\Exception\ArticleNotFoundException

// Application
App\Application\UseCase\Auth\RegisterUserUseCase
App\Application\Service\ArticleService

// Infrastructure
App\Infrastructure\Persistence\Doctrine\Repository\DoctrineUserRepository
App\Infrastructure\Payment\StripePaymentGateway
App\Infrastructure\Security\JwtTokenManager

// Presentation
App\Presentation\API\Controller\AuthController
App\Presentation\API\Request\RegisterRequest
App\Presentation\API\Response\UserResponse
```

### Clases

| Tipo | Sufijo | Ejemplo |
|------|--------|---------|
| Controller | `Controller` | `AuthController` |
| Use Case | `UseCase` | `RegisterUserUseCase` |
| Service | `Service` | `PaymentService` |
| Repository (Interfaz) | `RepositoryInterface` | `UserRepositoryInterface` |
| Repository (Implementación) | `Repository` | `DoctrineUserRepository` |
| Request DTO | `Request` | `RegisterRequest` |
| Response DTO | `Response` | `UserResponse` |
| Exception | `Exception` | `ArticleNotFoundException` |
| Event | `Event` | `UserRegisteredEvent` |
| Listener | `Listener` | `SendWelcomeEmailListener` |
| Value Object | Sin sufijo | `Email`, `Money`, `Role` |

### Métodos

```php
// Controllers: verbos HTTP o acciones
public function register(): JsonResponse
public function login(): JsonResponse
public function show(int $id): JsonResponse
public function list(): JsonResponse

// Use Cases: execute() siempre
public function execute(...): ReturnType

// Repositories: find*, save, remove
public function findById(int $id): ?Entity
public function findByEmail(string $email): ?User
public function findAll(): array
public function save(Entity $entity): void
public function remove(Entity $entity): void

// Services: verbos de negocio
public function calculateTotal(array $items): Money
public function sendEmail(User $user): void
public function processPayment(Money $amount): PaymentResult

// Value Objects: fromString(), toString(), equals()
public static function fromString(string $value): self
public function toString(): string
public function equals(ValueObject $other): bool
```

### Variables

```php
// Camel case
$userId = 123;
$articleRepository = $this->articles;
$hashedPassword = $this->hasher->hash($password);

// Booleanos: prefijos is, has, can
$isValid = $user->isActive();
$hasSubscription = $user->hasActiveSubscription();
$canAccessArticle = $permission->canAccess($article);

// Arrays: plurales
$articles = $repository->findAll();
$users = $this->users->findByRole(Role::PREMIUM);
```

---

## 📦 Bundles y Configuración

### Instalación de Dependencias

```bash
# Security & Authentication
composer require symfony/security-bundle
composer require lexik/jwt-authentication-bundle

# API Tools
composer require symfony/serializer
composer require symfony/validator
composer require nelmio/api-doc-bundle
composer require nelmio/cors-bundle

# Development
composer require --dev symfony/maker-bundle
composer require --dev symfony/test-pack
composer require --dev phpunit/phpunit

# Opcional: API Platform (si quieres accelerar desarrollo)
# composer require api-platform/core
```

---

### Configuración: JWT Authentication

**Archivo:** `config/packages/lexik_jwt_authentication.yaml`

```yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    token_ttl: 3600  # 1 hora
    refresh_token_ttl: 604800  # 7 días

    # Configuración de tokens
    token_extractors:
        authorization_header:
            enabled: true
            prefix: Bearer
            name: Authorization

    # Payload personalizado
    encoder:
        service: lexik_jwt_authentication.encoder.lcobucci
        crypto_engine: openssl
        signature_algorithm: RS256
```

**Generar claves:**

```bash
php bin/console lexik:jwt:generate-keypair

# Crea:
# config/jwt/private.pem
# config/jwt/public.pem
```

**Variables de entorno (.env):**

```env
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase_here
```

---

### Configuración: Security

**Archivo:** `config/packages/security.yaml`

```yaml
security:
    # Password hasher
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

    # User provider
    providers:
        app_user_provider:
            entity:
                class: App\Domain\Entity\User
                property: email

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        # Login endpoint (no requiere autenticación)
        login:
            pattern: ^/api/v1/auth/(login|register|refresh)
            stateless: true

        # API protegida
        api:
            pattern: ^/api/v1
            stateless: true
            jwt: ~

    # Control de acceso
    access_control:
        # Público
        - { path: ^/api/v1/auth/(login|register), roles: PUBLIC_ACCESS }

        # Solo autenticados
        - { path: ^/api/v1/users/me, roles: ROLE_USER }

        # Solo premium
        - { path: ^/api/v1/articles/\d+/full, roles: ROLE_PREMIUM }

        # Solo admin
        - { path: ^/api/v1/admin, roles: ROLE_ADMIN }

    # Jerarquía de roles
    role_hierarchy:
        ROLE_PREMIUM: ROLE_FREE
        ROLE_ENTERPRISE: ROLE_PREMIUM
        ROLE_ADMIN: ROLE_ENTERPRISE
```

---

### Configuración: CORS

**Archivo:** `config/packages/nelmio_cors.yaml`

```yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
        allow_headers: ['Content-Type', 'Authorization', 'X-Requested-With']
        expose_headers: ['Link', 'X-Total-Count']
        max_age: 3600
    paths:
        '^/api/':
            allow_origin: ['*']  # En producción: solo tu dominio Angular
            allow_headers: ['Content-Type', 'Authorization']
            allow_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']
            max_age: 3600
```

**Variables de entorno (.env):**

```env
# Desarrollo
CORS_ALLOW_ORIGIN=^http://localhost(:[0-9]+)?$

# Producción
# CORS_ALLOW_ORIGIN=^https://app\.lexecuador\.com$
```

---

### Configuración: API Documentation (Swagger)

**Archivo:** `config/packages/nelmio_api_doc.yaml`

```yaml
nelmio_api_doc:
    documentation:
        info:
            title: LexEcuador API
            description: API REST para consulta legal de la Constitución del Ecuador
            version: 1.0.0
        paths:
            /api/v1/auth/register:
                post:
                    summary: Register a new user
                    tags: [Authentication]
        components:
            securitySchemes:
                bearerAuth:
                    type: http
                    scheme: bearer
                    bearerFormat: JWT
        security:
            - bearerAuth: []

    areas:
        path_patterns:
            - ^/api(?!/doc$)  # Documentar todas las rutas /api/* excepto /api/doc
        host_patterns:
            - ^api\.  # Para subdominios api.*
```

**Acceso a documentación:**
- Swagger UI: `http://localhost/api/doc`
- JSON: `http://localhost/api/doc.json`

---

### Configuración: Serializer

**Archivo:** `config/packages/framework.yaml`

```yaml
framework:
    serializer:
        enabled: true
        # Habilitar anotaciones para grupos
        enable_annotations: true
        # Formato de fechas
        default_context:
            datetime_format: 'Y-m-d\TH:i:sP'  # ISO 8601
            # Excluir valores null
            json_encode_options: !php/const JSON_THROW_ON_ERROR
```

**Uso en entidades:**

```php
use Symfony\Component\Serializer\Annotation\Groups;

class User
{
    #[Groups(['user:read', 'user:write'])]
    private string $email;

    #[Groups(['user:read'])]  // Solo lectura, nunca escribir
    private string $id;

    #[Groups(['user:write'])]  // Solo escritura, nunca leer
    private string $password;

    #[Groups(['user:read'])]
    private \DateTimeImmutable $createdAt;
}

// En controller
return $this->json($user, 200, [], [
    'groups' => ['user:read']  // Solo campos con grupo user:read
]);
```

---

### Configuración: Validator

**Archivo:** `config/packages/validator.yaml`

```yaml
framework:
    validation:
        email_validation_mode: html5
        # Habilitar anotaciones
        enable_annotations: true
        # Caché de validaciones
        cache: validator.mapping.cache.pool

# Pool de caché
services:
    validator.mapping.cache.pool:
        parent: cache.app
        tags:
            - { name: cache.pool }
```

---

### Configuración: Doctrine (Actualizada)

**Archivo:** `config/packages/doctrine.yaml`

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        driver: 'pdo_mysql'  # o pdo_pgsql para PostgreSQL
        server_version: '8.0'  # Versión de MySQL
        charset: utf8mb4
        default_table_options:
            charset: utf8mb4
            collate: utf8mb4_unicode_ci

    orm:
        auto_generate_proxy_classes: false  # false en producción
        enable_lazy_ghost_objects: true
        report_fields_where_declared: true
        validate_xml_mapping: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            App:
                type: attribute
                is_bundle: false
                dir: '%kernel.project_dir%/src/Domain/Entity'  # ← Cambio
                prefix: 'App\Domain\Entity'
                alias: App
        controller_resolver:
            auto_mapping: false

when@prod:
    doctrine:
        orm:
            auto_generate_proxy_classes: false
            proxy_dir: '%kernel.cache_dir%/doctrine/orm/Proxies'
            query_cache_driver:
                type: pool
                pool: doctrine.system_cache_pool
            result_cache_driver:
                type: pool
                pool: doctrine.result_cache_pool

    framework:
        cache:
            pools:
                doctrine.result_cache_pool:
                    adapter: cache.app
                doctrine.system_cache_pool:
                    adapter: cache.system
```

---

### Rutas API

**Archivo:** `config/routes/api.yaml`

```yaml
# API v1
api_v1:
    resource: '../../src/Presentation/API/Controller/'
    type: attribute
    prefix: /api/v1
    name_prefix: api_v1_

# API Documentation
api_doc:
    path: /api/doc
    controller: nelmio_api_doc.controller.swagger_ui
```

---

## ✅ Checklist de Configuración

**Después de configurar todo:**

```bash
# 1. Instalar dependencias
composer install

# 2. Generar claves JWT
php bin/console lexik:jwt:generate-keypair

# 3. Crear base de datos
php bin/console doctrine:database:create

# 4. Ejecutar migraciones
php bin/console doctrine:migrations:migrate

# 5. Limpiar cache
php bin/console cache:clear

# 6. Validar configuración
php bin/console debug:config security
php bin/console debug:config lexik_jwt_authentication
php bin/console debug:config nelmio_cors

# 7. Listar rutas
php bin/console debug:router | grep api_v1

# 8. Ver documentación
# Abrir http://localhost/api/doc
```

---

## 📊 Resumen de Arquitectura

**Capas:**
- ✅ **Domain:** Entidades + Value Objects + Excepciones (sin dependencias)
- ✅ **Application:** Use Cases + Services (lógica de negocio)
- ✅ **Infrastructure:** Repositories + Payment + Security (detalles técnicos)
- ✅ **Presentation:** Controllers + DTOs + Serializers (HTTP layer)

**Principios SOLID:**
- ✅ SRP: Cada clase una responsabilidad
- ✅ OCP: Extensible sin modificación
- ✅ LSP: Intercambiabilidad de implementaciones
- ✅ ISP: Interfaces segregadas
- ✅ DIP: Dependencias invertidas

**Patrones:**
- ✅ Repository Pattern
- ✅ Use Case Pattern
- ✅ DTO Pattern
- ✅ Value Object Pattern
- ✅ Factory Pattern
- ✅ Strategy Pattern

**Bundles instalados:**
- ✅ `lexik/jwt-authentication-bundle`
- ✅ `nelmio/cors-bundle`
- ✅ `nelmio/api-doc-bundle`
- ✅ `symfony/serializer`
- ✅ `symfony/validator`
- ✅ `symfony/security-bundle`

**Próximo paso:** Generar `03_MVP_FEATURES.md` para definir alcance del MVP

---

**Archivo generado:** `02_ARQUITECTURA_API.md`
**Siguiente:** Esperar aprobación para generar `03_MVP_FEATURES.md`
