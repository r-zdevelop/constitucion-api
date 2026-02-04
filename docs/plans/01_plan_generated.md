# Plan: Migrar a Clean Architecture + DDD con MongoDB

## Resumen
Migrar el proyecto Symfony 7.3 de MySQL/Doctrine ORM a MongoDB ODM siguiendo Clean Architecture y DDD.

**Decisiones:**
- Alcance: Solo Core (artículos + autenticación JWT) - sin sistema de pagos
- Frontend: Eliminar Twig, solo API REST
- MongoDB: Ya instalado en localhost:27017

## Estructura Final
```
src/
├── Domain/
│   ├── Document/         # MongoDB Documents
│   │   ├── Article.php
│   │   ├── LegalDocument.php
│   │   ├── User.php
│   │   └── EmbeddedConcordance.php
│   ├── ValueObject/
│   │   ├── Email.php
│   │   └── Role.php (enum)
│   ├── Repository/       # Interfaces
│   │   ├── ArticleRepositoryInterface.php
│   │   ├── UserRepositoryInterface.php
│   │   └── LegalDocumentRepositoryInterface.php
│   └── Exception/
│       ├── ArticleNotFoundException.php
│       ├── UserNotFoundException.php
│       ├── DuplicateEmailException.php
│       └── InvalidCredentialsException.php
├── Application/
│   ├── Service/
│   │   ├── ArticleService.php (migrar)
│   │   └── ChapterOrderService.php (migrar)
│   └── UseCase/
│       ├── Auth/
│       │   ├── RegisterUserUseCase.php
│       │   └── LoginUserUseCase.php
│       └── Article/
│           ├── SearchArticlesUseCase.php
│           └── GetArticleByNumberUseCase.php
├── Infrastructure/
│   ├── Persistence/MongoDB/Repository/
│   │   ├── MongoDBArticleRepository.php
│   │   ├── MongoDBUserRepository.php
│   │   └── MongoDBLegalDocumentRepository.php
│   └── Security/
│       ├── JwtTokenManager.php
│       └── CustomUserProvider.php
├── Presentation/API/
│   ├── Controller/
│   │   ├── AuthController.php
│   │   └── ArticleController.php
│   ├── Request/
│   │   ├── RegisterRequest.php
│   │   └── LoginRequest.php
│   ├── Response/
│   │   ├── ArticleResponse.php
│   │   ├── UserResponse.php
│   │   └── PaginatedResponse.php
│   └── EventSubscriber/
│       └── ExceptionSubscriber.php
└── Command/
    └── ImportConstitutionCommand.php (actualizar)
```

---

## Fases de Implementación

### Fase 1: Configuración de Dependencias
**Archivos a modificar:**
- `composer.json`
- `.env`
- `config/packages/doctrine_mongodb.yaml` (nuevo)

**Acciones:**
```bash
# Remover Doctrine ORM
composer remove doctrine/orm doctrine/doctrine-bundle doctrine/doctrine-migrations-bundle

# Instalar MongoDB ODM
composer require doctrine/mongodb-odm-bundle

# Instalar Security + JWT
composer require symfony/security-bundle lexik/jwt-authentication-bundle

# Instalar API tools
composer require nelmio/api-doc-bundle nelmio/cors-bundle
```

**Configuración MongoDB (.env):**
```
MONGODB_URL=mongodb://localhost:27017
MONGODB_DB=lexecuador_db
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase
```

---

### Fase 2: Domain Layer

#### 2.1 Value Objects
- `src/Domain/ValueObject/Email.php` - Validación de email
- `src/Domain/ValueObject/Role.php` - Enum (FREE, PREMIUM, ADMIN)

#### 2.2 Documents (MongoDB)
- `src/Domain/Document/Article.php` - Con índices de texto y concordancias embebidas
- `src/Domain/Document/LegalDocument.php`
- `src/Domain/Document/User.php` - Implementa UserInterface
- `src/Domain/Document/EmbeddedConcordance.php` - Documento embebido

#### 2.3 Repository Interfaces
- `src/Domain/Repository/ArticleRepositoryInterface.php`
- `src/Domain/Repository/UserRepositoryInterface.php`
- `src/Domain/Repository/LegalDocumentRepositoryInterface.php`

#### 2.4 Exceptions
- `ArticleNotFoundException`, `UserNotFoundException`, `DuplicateEmailException`, `InvalidCredentialsException`

---

### Fase 3: Infrastructure Layer

#### 3.1 MongoDB Repositories
- `src/Infrastructure/Persistence/MongoDB/Repository/MongoDBArticleRepository.php`
  - Implementa búsqueda con texto completo MongoDB
  - Paginación con aggregation pipeline
- `src/Infrastructure/Persistence/MongoDB/Repository/MongoDBUserRepository.php`
- `src/Infrastructure/Persistence/MongoDB/Repository/MongoDBLegalDocumentRepository.php`

#### 3.2 Security
- `src/Infrastructure/Security/JwtTokenManager.php`
- `src/Infrastructure/Security/CustomUserProvider.php`

---

### Fase 4: Application Layer

#### 4.1 Use Cases
- `src/Application/UseCase/Auth/RegisterUserUseCase.php`
- `src/Application/UseCase/Auth/LoginUserUseCase.php`
- `src/Application/UseCase/Article/SearchArticlesUseCase.php`
- `src/Application/UseCase/Article/GetArticleByNumberUseCase.php`

#### 4.2 Services (migrar)
- `src/Application/Service/ArticleService.php`
- `src/Application/Service/ChapterOrderService.php`

---

### Fase 5: Presentation Layer

#### 5.1 Request DTOs
- `RegisterRequest`, `LoginRequest` con validación Symfony

#### 5.2 Response DTOs
- `ArticleResponse`, `UserResponse`, `PaginatedResponse`

#### 5.3 Controllers
- `src/Presentation/API/Controller/AuthController.php`
  - POST `/api/v1/auth/register`
  - POST `/api/v1/auth/login`
- `src/Presentation/API/Controller/ArticleController.php`
  - GET `/api/v1/articles`
  - GET `/api/v1/articles/search`
  - GET `/api/v1/articles/number/{number}`
  - GET `/api/v1/articles/chapters`

#### 5.4 Exception Handler
- `src/Presentation/API/EventSubscriber/ExceptionSubscriber.php` - RFC 7807

---

### Fase 6: Configuración

**Archivos de configuración:**
- `config/packages/security.yaml` - JWT + roles
- `config/packages/lexik_jwt_authentication.yaml`
- `config/packages/nelmio_cors.yaml`
- `config/packages/nelmio_api_doc.yaml`
- `config/packages/doctrine_mongodb.yaml`
- `config/services.yaml` - Bindings de interfaces
- `config/routes/api.yaml`

---

### Fase 7: Migración de Datos

- Actualizar `ImportConstitutionCommand.php` para MongoDB
- Importar datos desde JSON a MongoDB

---

## Archivos Existentes a Eliminar
- `src/Entity/` (todo el directorio)
- `src/Repository/ArticleRepository.php`
- `src/Repository/ArticleRepositoryInterface.php` (se mueve a Domain)
- `src/Service/` (se mueven a Application/Service)
- `src/Controller/HomeController.php`
- `src/Controller/ArticleController.php` (se refactoriza)
- `config/packages/doctrine.yaml`
- `config/packages/doctrine_migrations.yaml`
- `migrations/` (directorio completo)
- `templates/` (directorio completo - solo API REST)

---

## Verificación

1. **Generar claves JWT:**
   ```bash
   php bin/console lexik:jwt:generate-keypair
   ```

2. **Importar datos:**
   ```bash
   php bin/console app:import-constitution
   ```

3. **Probar endpoints:**
   ```bash
   # Registro
   curl -X POST http://localhost:8000/api/v1/auth/register \
     -H "Content-Type: application/json" \
     -d '{"email":"test@test.com","password":"Password123","name":"Test"}'

   # Login
   curl -X POST http://localhost:8000/api/v1/auth/login \
     -H "Content-Type: application/json" \
     -d '{"email":"test@test.com","password":"Password123"}'

   # Listar artículos
   curl http://localhost:8000/api/v1/articles

   # Buscar artículos
   curl "http://localhost:8000/api/v1/articles/search?q=derechos"
   ```

4. **Ver documentación:**
   - Abrir `http://localhost:8000/api/doc`
