# 04 - Plan Sprint 1: Infraestructura Base

**Sprint:** 1 de 3
**Duración:** 2 semanas (Semana 1-2)
**Objetivo:** Configurar infraestructura base de la API con autenticación JWT y Clean Architecture
**Fecha inicio:** 2025-12-19
**Fecha fin:** 2026-01-02

---

## 🎯 Objetivo del Sprint

Establecer las bases técnicas de la API:
- ✅ Bundles de seguridad y API instalados y configurados
- ✅ JWT Authentication funcional
- ✅ Estructura de Clean Architecture implementada
- ✅ Entidades de dominio creadas (User, Subscription, Payment)
- ✅ Migraciones de base de datos ejecutadas
- ✅ CORS configurado para Angular
- ✅ Primeros endpoints de autenticación funcionando

**Entregable:** API funcional con registro, login y protección JWT

---

## 📋 Tareas del Sprint 1

### Fase 1: Instalación de Dependencias (2-3 horas)

#### Tarea 1.1: Instalar Bundles de Seguridad

**Objetivo:** Añadir bundles necesarios para JWT, serialización, validación y documentación

**Comandos:**
```bash
# Security & Authentication
composer require symfony/security-bundle
composer require lexik/jwt-authentication-bundle

# API Tools
composer require symfony/serializer
composer require symfony/validator
composer require symfony/property-access
composer require symfony/property-info

# API Documentation
composer require nelmio/api-doc-bundle
composer require nelmio/cors-bundle

# Development tools
composer require --dev symfony/maker-bundle
composer require --dev symfony/test-pack
composer require --dev phpunit/phpunit
composer require --dev doctrine/doctrine-fixtures-bundle

# UUID support (para IDs)
composer require symfony/uid
```

**Verificación:**
```bash
# Listar bundles instalados
php bin/console debug:container --parameters | grep kernel.bundles

# Debe mostrar:
# - SecurityBundle
# - LexikJWTAuthenticationBundle
# - NelmioApiDocBundle
# - NelmioCorsBundle
```

**Criterios de aceptación:**
- [ ] Todos los bundles se instalaron sin errores
- [ ] `composer.json` actualizado correctamente
- [ ] `config/bundles.php` contiene los nuevos bundles

**Tiempo estimado:** 30 minutos

---

#### Tarea 1.2: Generar Claves JWT

**Objetivo:** Crear par de claves RSA para firmar tokens JWT

**Comandos:**
```bash
# Generar claves (crea config/jwt/private.pem y public.pem)
php bin/console lexik:jwt:generate-keypair

# Verificar que se crearon
ls -la config/jwt/
# Debe mostrar:
# private.pem
# public.pem
```

**Configurar variables de entorno (.env):**
```env
###> lexik/jwt-authentication-bundle ###
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_super_secret_passphrase_change_in_production
JWT_TTL=3600
###< lexik/jwt-authentication-bundle ###
```

**IMPORTANTE - Seguridad:**
```bash
# Añadir claves a .gitignore
echo "/config/jwt/*.pem" >> .gitignore

# Verificar que NO se commitean
git status
# NO debe aparecer config/jwt/private.pem ni public.pem
```

**Criterios de aceptación:**
- [ ] Claves generadas en `config/jwt/`
- [ ] Variables de entorno configuradas
- [ ] Claves NO se commitean a git
- [ ] Passphrase es fuerte (min 16 chars)

**Tiempo estimado:** 15 minutos

---

### Fase 2: Configuración de Bundles (3-4 horas)

#### Tarea 2.1: Configurar JWT Authentication

**Archivo:** `config/packages/lexik_jwt_authentication.yaml`

**Crear archivo con este contenido:**
```yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    token_ttl: '%env(int:JWT_TTL)%'

    # Extracción del token desde header Authorization
    token_extractors:
        authorization_header:
            enabled: true
            prefix: Bearer
            name: Authorization

    # Encoder
    encoder:
        service: lexik_jwt_authentication.encoder.lcobucci
        crypto_engine: openssl
        signature_algorithm: RS256
```

**Verificar configuración:**
```bash
php bin/console debug:config lexik_jwt_authentication
```

**Criterios de aceptación:**
- [ ] Archivo creado correctamente
- [ ] Debug config muestra configuración sin errores
- [ ] TTL es 3600 (1 hora)

**Tiempo estimado:** 20 minutos

---

#### Tarea 2.2: Configurar Security Bundle

**Archivo:** `config/packages/security.yaml`

**Reemplazar contenido completo con:**
```yaml
security:
    # Password hasher
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'
        App\Domain\Entity\User:
            algorithm: auto
            cost: 12

    # Provider de usuarios
    providers:
        app_user_provider:
            entity:
                class: App\Domain\Entity\User
                property: email

    firewalls:
        # Dev toolbar (deshabilitado en prod)
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        # Endpoints públicos (registro, login)
        public:
            pattern: ^/api/v1/auth/(register|login|refresh)
            stateless: true
            security: false

        # API protegida con JWT
        api:
            pattern: ^/api/v1
            stateless: true
            jwt: ~

        # Fallback (main)
        main:
            lazy: true

    # Control de acceso
    access_control:
        # Público
        - { path: ^/api/v1/auth/(register|login|refresh), roles: PUBLIC_ACCESS }
        - { path: ^/api/doc, roles: PUBLIC_ACCESS }

        # Requiere autenticación
        - { path: ^/api/v1/users/me, roles: ROLE_USER }
        - { path: ^/api/v1/subscriptions, roles: ROLE_USER }

        # Solo premium
        - { path: ^/api/v1/articles/\d+/full, roles: ROLE_PREMIUM }

        # Solo admin
        - { path: ^/api/v1/admin, roles: ROLE_ADMIN }

    # Jerarquía de roles
    role_hierarchy:
        ROLE_USER: []
        ROLE_FREE: ROLE_USER
        ROLE_PREMIUM: ROLE_FREE
        ROLE_ENTERPRISE: ROLE_PREMIUM
        ROLE_ADMIN: ROLE_ENTERPRISE

when@test:
    security:
        password_hashers:
            Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface:
                algorithm: auto
                cost: 4 # Más rápido para tests
                time_cost: 3
                memory_cost: 10
```

**Verificar configuración:**
```bash
php bin/console debug:firewall

# Debe mostrar:
# - dev (pattern: ^/_(profiler|wdt))
# - public (pattern: ^/api/v1/auth/(register|login|refresh))
# - api (pattern: ^/api/v1)
```

**Criterios de aceptación:**
- [ ] Configuración válida sin errores
- [ ] Firewalls configurados correctamente
- [ ] Jerarquía de roles definida
- [ ] Access control rules creados

**Tiempo estimado:** 30 minutos

---

#### Tarea 2.3: Configurar CORS

**Archivo:** `config/packages/nelmio_cors.yaml`

**Crear archivo con:**
```yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_methods: ['GET', 'OPTIONS', 'POST', 'PUT', 'PATCH', 'DELETE']
        allow_headers: ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept']
        expose_headers: ['Link', 'X-Total-Count', 'X-RateLimit-Limit', 'X-RateLimit-Remaining']
        max_age: 3600

    paths:
        '^/api/':
            allow_origin: ['*']  # En producción: dominio específico
            allow_headers: ['Content-Type', 'Authorization']
            allow_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
            expose_headers: ['Link', 'X-Total-Count']
            max_age: 3600
```

**Añadir a .env:**
```env
###> nelmio/cors-bundle ###
# Desarrollo: permitir localhost en cualquier puerto
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'

# Producción (descomentar y ajustar):
# CORS_ALLOW_ORIGIN='^https://app\.lexecuador\.com$'
###< nelmio/cors-bundle ###
```

**Verificar:**
```bash
php bin/console debug:config nelmio_cors
```

**Criterios de aceptación:**
- [ ] CORS configurado para desarrollo
- [ ] Variables de entorno definidas
- [ ] Config válida sin errores

**Tiempo estimado:** 15 minutos

---

#### Tarea 2.4: Configurar API Documentation (Swagger)

**Archivo:** `config/packages/nelmio_api_doc.yaml`

**Crear archivo con:**
```yaml
nelmio_api_doc:
    documentation:
        info:
            title: LexEcuador API
            description: |
                API REST para consulta de la Constitución del Ecuador.

                ## Autenticación
                La API usa JWT Bearer tokens. Para autenticarte:
                1. Registrate en `/api/v1/auth/register`
                2. Haz login en `/api/v1/auth/login`
                3. Usa el token en el header: `Authorization: Bearer {token}`

                ## Rate Limiting
                - FREE: 100 requests/día
                - PREMIUM: 10,000 requests/día
                - ENTERPRISE: ilimitado
            version: 1.0.0
            contact:
                email: support@lexecuador.com

        servers:
            - url: http://localhost:8000
              description: Development
            - url: https://api.lexecuador.com
              description: Production

        components:
            securitySchemes:
                bearerAuth:
                    type: http
                    scheme: bearer
                    bearerFormat: JWT
                    description: "JWT Authorization header using the Bearer scheme. Example: 'Authorization: Bearer {token}'"

        security:
            - bearerAuth: []

        tags:
            - name: Authentication
              description: Registro, login y gestión de tokens
            - name: Articles
              description: Consulta de artículos constitucionales
            - name: Subscriptions
              description: Gestión de suscripciones y planes
            - name: Users
              description: Gestión de perfil de usuario

    areas:
        path_patterns:
            - ^/api(?!/doc$) # Documentar todas las rutas /api/* excepto /api/doc
        disable_default_routes: false
```

**Añadir rutas en `config/routes.yaml`:**
```yaml
# API Documentation
app.swagger_ui:
    path: /api/doc
    methods: GET
    controller: nelmio_api_doc.controller.swagger_ui

app.swagger:
    path: /api/doc.json
    methods: GET
    controller: nelmio_api_doc.controller.swagger
```

**Verificar:**
```bash
# Acceder a Swagger UI
# http://localhost/api/doc
```

**Criterios de aceptación:**
- [ ] Swagger UI accesible en `/api/doc`
- [ ] OpenAPI spec en `/api/doc.json`
- [ ] Documentación muestra título y descripción
- [ ] SecurityScheme JWT configurado

**Tiempo estimado:** 20 minutos

---

#### Tarea 2.5: Configurar Serializer

**Archivo:** `config/packages/framework.yaml`

**Añadir sección serializer:**
```yaml
framework:
    secret: '%env(APP_SECRET)%'

    # Serializer
    serializer:
        enabled: true
        enable_annotations: true
        name_converter: 'serializer.name_converter.camel_case_to_snake_case'
        circular_reference_handler: ~
        default_context:
            # Formato de fechas ISO 8601
            datetime_format: 'Y-m-d\TH:i:sP'
            # Opciones de JSON
            json_encode_options: !php/const JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION
```

**Criterios de aceptación:**
- [ ] Serializer habilitado
- [ ] Formato de fechas ISO 8601
- [ ] Name converter camelCase ↔ snake_case

**Tiempo estimado:** 10 minutos

---

#### Tarea 2.6: Configurar Validator

**Archivo:** `config/packages/validator.yaml`

**Crear archivo con:**
```yaml
framework:
    validation:
        email_validation_mode: html5
        enable_annotations: true

        # Traducción de mensajes de error
        translation_domain: validators

        # Mapeo automático de constraints
        auto_mapping:
            App\Domain\Entity\: []
            App\Presentation\API\Request\: []

        # Not Compromised Password (opcional)
        not_compromised_password:
            enabled: true
            endpoint: ~
```

**Criterios de aceptación:**
- [ ] Validator habilitado con annotations
- [ ] Auto-mapping configurado
- [ ] Email validation en modo HTML5

**Tiempo estimado:** 10 minutos

---

### Fase 3: Refactoring a Clean Architecture (4-6 horas)

#### Tarea 3.1: Crear Estructura de Directorios

**Objetivo:** Reorganizar `src/` según Clean Architecture

**Comandos:**
```bash
# Crear estructura de directorios
mkdir -p src/Domain/Entity
mkdir -p src/Domain/ValueObject
mkdir -p src/Domain/Repository
mkdir -p src/Domain/Exception

mkdir -p src/Application/UseCase/Auth
mkdir -p src/Application/UseCase/Article
mkdir -p src/Application/UseCase/Subscription
mkdir -p src/Application/Service

mkdir -p src/Infrastructure/Persistence/Doctrine/Repository
mkdir -p src/Infrastructure/Payment
mkdir -p src/Infrastructure/Security

mkdir -p src/Presentation/API/Controller
mkdir -p src/Presentation/API/Request
mkdir -p src/Presentation/API/Response
mkdir -p src/Presentation/API/EventSubscriber

# Verificar estructura
tree src/ -L 3
```

**Criterios de aceptación:**
- [ ] Estructura de directorios creada
- [ ] Permisos correctos (755 para directorios)

**Tiempo estimado:** 10 minutos

---

#### Tarea 3.2: Mover Entidades Existentes

**Objetivo:** Mover entidades a `src/Domain/Entity/`

**Comandos:**
```bash
# Mover entidades
mv src/Entity/Article.php src/Domain/Entity/
mv src/Entity/LegalDocument.php src/Domain/Entity/
mv src/Entity/ArticleHistory.php src/Domain/Entity/

# Eliminar entidades legacy
rm src/Entity/Concordance.php  # No se usa
rm src/Entity/DocumentSection.php  # No se usa en MVP

# Eliminar directorio vacío
rmdir src/Entity/
```

**Actualizar namespaces en archivos movidos:**

**Archivo:** `src/Domain/Entity/Article.php`
```php
<?php

declare(strict_types=1);

namespace App\Domain\Entity;  // ← Cambiar de App\Entity

use Doctrine\ORM\Mapping as ORM;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineArticleRepository;

#[ORM\Entity(repositoryClass: DoctrineArticleRepository::class)]
#[ORM\Table(name: 'articles')]
// ... resto del código igual
```

**Repetir para `LegalDocument.php` y `ArticleHistory.php`**

**Criterios de aceptación:**
- [ ] Entidades movidas a `Domain/Entity/`
- [ ] Namespaces actualizados
- [ ] Entidades legacy eliminadas
- [ ] No hay errores de autoload

**Tiempo estimado:** 30 minutos

---

#### Tarea 3.3: Mover Repositorios

**Objetivo:** Mover repositorios a Infrastructure layer

**Comandos:**
```bash
# Mover repositorios
mv src/Repository/ArticleRepository.php src/Infrastructure/Persistence/Doctrine/Repository/DoctrineArticleRepository.php
mv src/Repository/ArticleRepositoryInterface.php src/Domain/Repository/

# Eliminar directorio vacío
rmdir src/Repository/
```

**Actualizar namespace de interfaz:**

**Archivo:** `src/Domain/Repository/ArticleRepositoryInterface.php`
```php
<?php

declare(strict_types=1);

namespace App\Domain\Repository;  // ← Cambiar

use App\Domain\Entity\Article;

interface ArticleRepositoryInterface
{
    public function findById(int $id): ?Article;
    public function findByNumber(int $documentId, int $articleNumber): ?Article;
    public function findByArticleNumber(int $articleNumber): array;
    public function findAll(): array;
    public function findAllChapters(): array;
    public function save(Article $article): void;
    public function remove(Article $article): void;
}
```

**Actualizar implementación:**

**Archivo:** `src/Infrastructure/Persistence/Doctrine/Repository/DoctrineArticleRepository.php`
```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;  // ← Cambiar

use App\Domain\Entity\Article;  // ← Cambiar
use App\Domain\Repository\ArticleRepositoryInterface;  // ← Cambiar
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DoctrineArticleRepository extends ServiceEntityRepository implements ArticleRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    // ... resto del código igual
}
```

**Criterios de aceptación:**
- [ ] Repositorios movidos correctamente
- [ ] Namespaces actualizados
- [ ] Interfaces en Domain, implementaciones en Infrastructure
- [ ] No hay errores de autoload

**Tiempo estimado:** 30 minutos

---

#### Tarea 3.4: Mover Servicios

**Objetivo:** Mover servicios a Application layer

**Comandos:**
```bash
# Mover servicios
mv src/Service/ArticleService.php src/Application/Service/
mv src/Service/ChapterOrderService.php src/Application/Service/

# Eliminar directorio vacío
rmdir src/Service/
```

**Actualizar namespaces:**

**Archivo:** `src/Application/Service/ArticleService.php`
```php
<?php

declare(strict_types=1);

namespace App\Application\Service;  // ← Cambiar

use App\Domain\Repository\ArticleRepositoryInterface;  // ← Cambiar
use App\Domain\Entity\Article;  // ← Cambiar
use Doctrine\ORM\EntityManagerInterface;

class ArticleService
{
    public function __construct(
        private ArticleRepositoryInterface $articles,
        private EntityManagerInterface $em
    ) {}

    // ... resto del código igual
}
```

**Repetir para `ChapterOrderService.php`**

**Criterios de aceptación:**
- [ ] Servicios movidos a Application/Service
- [ ] Namespaces actualizados
- [ ] Imports corregidos
- [ ] No hay errores de autoload

**Tiempo estimado:** 20 minutos

---

#### Tarea 3.5: Configurar Doctrine para Nuevas Rutas

**Archivo:** `config/packages/doctrine.yaml`

**Actualizar sección mappings:**
```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        driver: 'pdo_mysql'
        server_version: '8.0'
        charset: utf8mb4

    orm:
        auto_generate_proxy_classes: false
        enable_lazy_ghost_objects: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true

        mappings:
            App:
                type: attribute
                is_bundle: false
                dir: '%kernel.project_dir%/src/Domain/Entity'  # ← Cambiar ruta
                prefix: 'App\Domain\Entity'  # ← Cambiar namespace
                alias: App
```

**Verificar:**
```bash
# Validar esquema de Doctrine
php bin/console doctrine:schema:validate

# Debe mostrar:
# [OK] The mapping files are correct.
# [OK] The database schema is in sync with the mapping files.
```

**Criterios de aceptación:**
- [ ] Doctrine mapea desde `src/Domain/Entity`
- [ ] Schema validation OK
- [ ] No hay errores de mapping

**Tiempo estimado:** 15 minutos

---

#### Tarea 3.6: Configurar Servicios DI

**Archivo:** `config/services.yaml`

**Actualizar configuración:**
```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Auto-register de servicios
    App\Domain\:
        resource: '../src/Domain/*'
        exclude:
            - '../src/Domain/Entity/'
            - '../src/Domain/ValueObject/'

    App\Application\:
        resource: '../src/Application/*'

    App\Infrastructure\:
        resource: '../src/Infrastructure/*'
        exclude:
            - '../src/Infrastructure/Persistence/Doctrine/Repository/'

    App\Presentation\:
        resource: '../src/Presentation/*'

    # Repositories como servicios
    App\Infrastructure\Persistence\Doctrine\Repository\:
        resource: '../src/Infrastructure/Persistence/Doctrine/Repository/*'
        tags: ['doctrine.repository_service']

    # Alias de interfaces a implementaciones
    App\Domain\Repository\ArticleRepositoryInterface:
        class: App\Infrastructure\Persistence\Doctrine\Repository\DoctrineArticleRepository
        public: false
```

**Verificar:**
```bash
# Listar servicios registrados
php bin/console debug:container App\\ --show-arguments

# Verificar que ArticleRepositoryInterface está mapeado
php bin/console debug:container ArticleRepositoryInterface
```

**Criterios de aceptación:**
- [ ] Servicios auto-registrados
- [ ] Repositories como servicios
- [ ] Interfaces mapeadas a implementaciones
- [ ] No hay errores de DI

**Tiempo estimado:** 20 minutos

---

### Fase 4: Crear Entidades de Dominio (4-5 horas)

#### Tarea 4.1: Crear Value Object Email

**Objetivo:** Encapsular validación de email en un Value Object

**Archivo:** `src/Domain/ValueObject/Email.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class Email implements \Stringable
{
    private function __construct(
        private string $value
    ) {}

    public static function fromString(string $email): self
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid email address: "%s"', $email)
            );
        }

        return new self($email);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
```

**Test unitario:** `tests/Unit/Domain/ValueObject/EmailTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testCreateValidEmail(): void
    {
        $email = Email::fromString('user@example.com');

        $this->assertSame('user@example.com', $email->toString());
    }

    public function testEmailIsNormalizedToLowercase(): void
    {
        $email = Email::fromString('USER@EXAMPLE.COM');

        $this->assertSame('user@example.com', $email->toString());
    }

    public function testEmailIsTrimmed(): void
    {
        $email = Email::fromString('  user@example.com  ');

        $this->assertSame('user@example.com', $email->toString());
    }

    public function testInvalidEmailThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address');

        Email::fromString('invalid-email');
    }

    public function testTwoEmailsAreEqual(): void
    {
        $email1 = Email::fromString('user@example.com');
        $email2 = Email::fromString('user@example.com');

        $this->assertTrue($email1->equals($email2));
    }
}
```

**Ejecutar test:**
```bash
php bin/phpunit tests/Unit/Domain/ValueObject/EmailTest.php
```

**Criterios de aceptación:**
- [ ] Value Object creado
- [ ] Validación funciona correctamente
- [ ] Tests pasan al 100%
- [ ] Inmutable (readonly)

**Tiempo estimado:** 45 minutos

---

#### Tarea 4.2: Crear Value Object Role

**Archivo:** `src/Domain/ValueObject/Role.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum Role: string
{
    case FREE = 'ROLE_FREE';
    case PREMIUM = 'ROLE_PREMIUM';
    case ENTERPRISE = 'ROLE_ENTERPRISE';
    case ADMIN = 'ROLE_ADMIN';

    public function isAtLeast(self $role): bool
    {
        $hierarchy = [
            self::FREE->value => 0,
            self::PREMIUM->value => 1,
            self::ENTERPRISE->value => 2,
            self::ADMIN->value => 3,
        ];

        return $hierarchy[$this->value] >= $hierarchy[$role->value];
    }

    public function canAccessPremiumContent(): bool
    {
        return $this->isAtLeast(self::PREMIUM);
    }

    public function canAccessEnterpriseFeatures(): bool
    {
        return $this->isAtLeast(self::ENTERPRISE);
    }
}
```

**Criterios de aceptación:**
- [ ] Enum creado con 4 roles
- [ ] Método `isAtLeast()` funciona correctamente
- [ ] Helper methods implementados

**Tiempo estimado:** 30 minutos

---

#### Tarea 4.3: Crear Entidad User

**Archivo:** `src/Domain/Entity/User.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Role;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'unique_email', columns: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['user:read'])]
    private string $id;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Groups(['user:read', 'user:write'])]
    private string $email;

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'string', length: 100)]
    #[Groups(['user:read', 'user:write'])]
    private string $name;

    #[ORM\Column(type: 'string', length: 32)]
    #[Groups(['user:read'])]
    private string $role;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['user:read'])]
    private bool $isActive;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['user:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['user:read'])]
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        string $id,
        string $email,
        string $password,
        string $name,
        string $role,
        bool $isActive,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->password = $password;
        $this->name = $name;
        $this->role = $role;
        $this->isActive = $isActive;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function register(
        Email $email,
        string $hashedPassword,
        string $name,
        Role $role = Role::FREE
    ): self {
        $now = new \DateTimeImmutable();

        return new self(
            id: Uuid::v4()->toString(),
            email: $email->toString(),
            password: $hashedPassword,
            name: $name,
            role: $role->value,
            isActive: true,
            createdAt: $now,
            updatedAt: $now
        );
    }

    // UserInterface implementation
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        return [$this->role, 'ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
        // No temporary credentials to erase
    }

    // PasswordAuthenticatedUserInterface
    public function getPassword(): string
    {
        return $this->password;
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRole(): Role
    {
        return Role::from($this->role);
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Business logic
    public function updateProfile(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function changePassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function upgradeToRole(Role $role): void
    {
        $this->role = $role->value;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function activate(): void
    {
        $this->isActive = true;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function hasPremiumAccess(): bool
    {
        return $this->getRole()->canAccessPremiumContent();
    }

    public function hasEnterpriseAccess(): bool
    {
        return $this->getRole()->canAccessEnterpriseFeatures();
    }
}
```

**Criterios de aceptación:**
- [ ] Entidad User creada
- [ ] Implementa UserInterface y PasswordAuthenticatedUserInterface
- [ ] Factory method `register()` implementado
- [ ] Grupos de serialización definidos
- [ ] Business logic encapsulada

**Tiempo estimado:** 1.5 horas

---

#### Tarea 4.4: Crear Migración para User

**Comandos:**
```bash
php bin/console make:migration
```

**Editar migración generada:** `migrations/Version*.php`

```php
public function up(Schema $schema): void
{
    $this->addSql('
        CREATE TABLE users (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            email VARCHAR(180) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            name VARCHAR(100) NOT NULL,
            role VARCHAR(32) NOT NULL DEFAULT "ROLE_FREE",
            is_active BOOLEAN NOT NULL DEFAULT TRUE,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            UNIQUE INDEX unique_email (email),
            INDEX idx_role (role),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
}

public function down(Schema $schema): void
{
    $this->addSql('DROP TABLE users');
}
```

**Ejecutar migración:**
```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

**Verificar:**
```bash
# Ver esquema de tabla users
php bin/console doctrine:schema:validate

mysql -u admin -padmin constitucion_ec -e "DESCRIBE users;"
```

**Criterios de aceptación:**
- [ ] Migración creada
- [ ] Tabla `users` creada correctamente
- [ ] Índices creados
- [ ] Schema validation OK

**Tiempo estimado:** 30 minutos

---

#### Tarea 4.5: Crear Repository de User

**Archivo:** `src/Domain/Repository/UserRepositoryInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\User;

interface UserRepositoryInterface
{
    public function findById(string $id): ?User;
    public function findByEmail(string $email): ?User;
    public function save(User $user): void;
    public function remove(User $user): void;
}
```

**Archivo:** `src/Infrastructure/Persistence/Doctrine/Repository/DoctrineUserRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DoctrineUserRepository extends ServiceEntityRepository implements UserRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findById(string $id): ?User
    {
        return $this->find($id);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function save(User $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function remove(User $user): void
    {
        $this->getEntityManager()->remove($user);
        $this->getEntityManager()->flush();
    }
}
```

**Registrar en services.yaml:**
```yaml
services:
    # ...existing config...

    App\Domain\Repository\UserRepositoryInterface:
        class: App\Infrastructure\Persistence\Doctrine\Repository\DoctrineUserRepository
```

**Criterios de aceptación:**
- [ ] Interfaz creada
- [ ] Implementación Doctrine creada
- [ ] Repository registrado como servicio
- [ ] CRUD básico funciona

**Tiempo estimado:** 30 minutos

---

### Fase 5: Implementar Autenticación (6-8 horas)

#### Tarea 5.1: Crear DTO RegisterRequest

**Archivo:** `src/Presentation/API/Request/RegisterRequest.php`

```php
<?php

declare(strict_types=1);

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
            minMessage: 'Password must be at least {{ limit }} characters',
            maxMessage: 'Password cannot be longer than {{ limit }} characters'
        )]
        #[Assert\Regex(
            pattern: '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)/',
            message: 'Password must contain at least one uppercase letter, one lowercase letter, and one number'
        )]
        public string $password,

        #[Assert\NotBlank(message: 'Name is required')]
        #[Assert\Length(
            min: 2,
            max: 100,
            minMessage: 'Name must be at least {{ limit }} characters',
            maxMessage: 'Name cannot be longer than {{ limit }} characters'
        )]
        public string $name,
    ) {}
}
```

**Criterios de aceptación:**
- [ ] DTO creado con validaciones
- [ ] Readonly para inmutabilidad
- [ ] Constraints de Symfony Validator

**Tiempo estimado:** 20 minutos

---

**[CONTINÚA EN SIGUIENTE MENSAJE - LÍMITE DE CARACTERES]**

---

## 📊 Checklist de Completitud del Sprint 1

Al finalizar el Sprint 1, debes poder verificar:

### Instalación y Configuración
- [ ] Todos los bundles instalados sin errores
- [ ] Claves JWT generadas y NO commiteadas
- [ ] Configuración de security.yaml completa
- [ ] CORS configurado para desarrollo
- [ ] Swagger UI accesible en `/api/doc`

### Clean Architecture
- [ ] Estructura de directorios creada
- [ ] Entidades movidas a `Domain/Entity`
- [ ] Repositorios en `Infrastructure`
- [ ] Servicios en `Application`
- [ ] Doctrine mapeando correctamente

### Base de Datos
- [ ] Migración de User ejecutada
- [ ] Tabla `users` creada correctamente
- [ ] Schema validation OK

### Autenticación
- [ ] Endpoint POST `/api/v1/auth/register` funciona
- [ ] Endpoint POST `/api/v1/auth/login` funciona
- [ ] JWT tokens se generan correctamente
- [ ] Rutas protegidas requieren token

### Tests
- [ ] Tests unitarios de Value Objects pasan
- [ ] Tests de integración de User pasan
- [ ] Cobertura >80% en Value Objects

### Documentación
- [ ] Endpoints documentados en Swagger
- [ ] Try-it-out funciona

---

**Archivo generado:** `04_PLAN_SPRINT_1.md`
**Tiempo total estimado:** ~40 horas (2 semanas)
**Siguiente:** `05_PLAN_SPRINT_2.md` - Core Features (Artículos y Búsqueda)
