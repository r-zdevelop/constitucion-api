# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

LexEcuador API - A Symfony 7.3 REST API for browsing and searching the Ecuadorian Constitution, built with Clean Architecture and Domain-Driven Design patterns.

## Tech Stack

- **Backend:** PHP 8.2+, Symfony 7.3
- **Database:** MongoDB (Doctrine ODM)
- **Authentication:** JWT (LexikJWTAuthenticationBundle)
- **API Documentation:** OpenAPI/Swagger (NelmioApiDocBundle)
- **Architecture:** Clean Architecture + DDD

## Common Commands

```bash
# Development server
symfony server:start
# Or: php -S 127.0.0.1:8000 -t public

# Clear cache
php bin/console cache:clear

# MongoDB schema management
php bin/console doctrine:mongodb:schema:create
php bin/console doctrine:mongodb:schema:drop

# Import constitution data from JSON
php bin/console app:import-constitution

# Generate JWT keypair
php bin/console lexik:jwt:generate-keypair

# Debug routes
php bin/console debug:router
```

## Architecture

The codebase follows Clean Architecture with DDD principles:

```
src/
├── Domain/                    # Core business logic (no framework dependencies)
│   ├── Document/             # MongoDB documents (Article, User, LegalDocument)
│   ├── ValueObject/          # Immutable value objects (Email, Role enum)
│   ├── Repository/           # Repository interfaces (contracts)
│   └── Exception/            # Domain-specific exceptions
├── Application/              # Use cases and application services
│   ├── Service/              # Application services (ArticleService, ChapterOrderService)
│   └── UseCase/              # Single-purpose use cases (Auth/, Article/)
├── Infrastructure/           # External concerns implementation
│   ├── Persistence/MongoDB/  # MongoDB repository implementations
│   └── Security/             # JWT and user provider
├── Presentation/API/         # HTTP layer
│   ├── Controller/           # REST controllers
│   ├── Request/              # Input DTOs with validation
│   ├── Response/             # Output DTOs
│   └── EventSubscriber/      # Exception handling (RFC 7807)
└── Command/                  # Console commands
```

### Key Patterns

- **Repository abstraction:** Interfaces in `Domain/Repository/`, implementations in `Infrastructure/Persistence/`
- **Use Cases:** Single-responsibility classes for auth and article operations
- **Value Objects:** `Email` (validated), `Role` (enum with FREE, PREMIUM, ADMIN)
- **DTOs:** Request validation via Symfony Validator, Response formatting via dedicated classes
- **Exception handling:** `ExceptionSubscriber` converts domain exceptions to RFC 7807 responses

### Document Relationships

- `LegalDocument` ← `Article` (referenced by documentId string)
- `Article` embeds `EmbeddedConcordance[]` (denormalized)
- `User` implements Symfony `UserInterface` for JWT auth

## API Routes

| Method | Route | Description |
|--------|-------|-------------|
| POST | `/api/v1/auth/register` | Register new user |
| POST | `/api/v1/auth/login` | Login (returns JWT) |
| GET | `/api/v1/articles` | List articles (paginated) |
| GET | `/api/v1/articles/search?q=` | Full-text search |
| GET | `/api/v1/articles/number/{n}` | Get by article number |
| GET | `/api/v1/articles/chapters` | List all chapters |
| GET | `/api/doc` | Swagger UI |

Query parameters for `/api/v1/articles`: `?page=`, `?limit=`, `?chapter=`

## Database

MongoDB with text indexes for Spanish full-text search on articles (content, title, chapter).

## Environment

Configuration in `.env`:
```
MONGODB_URL=mongodb://localhost:27017
MONGODB_DB=lexecuador_db
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=your_passphrase
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

## Service Bindings

Repository interfaces are bound to MongoDB implementations in `config/services.yaml`:
- `ArticleRepositoryInterface` → `MongoDBArticleRepository`
- `UserRepositoryInterface` → `MongoDBUserRepository`
- `LegalDocumentRepositoryInterface` → `MongoDBLegalDocumentRepository`
