# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

LexEcuador - A full-stack application for browsing and searching the Ecuadorian Constitution. Backend built with Symfony 7.3 (Clean Architecture + DDD), frontend with Angular 17+ (Material UI + Signals).

## Tech Stack

### Backend
- **Framework:** PHP 8.2+, Symfony 7.3
- **Database:** MongoDB (Doctrine ODM)
- **Authentication:** JWT (LexikJWTAuthenticationBundle)
- **API Documentation:** OpenAPI/Swagger (NelmioApiDocBundle)
- **Architecture:** Clean Architecture + DDD

### Frontend (./frontend)
- **Framework:** Angular 17+ (Standalone Components)
- **UI:** Angular Material (azure-blue theme)
- **State:** Angular Signals
- **Routing:** Lazy Loading
- **Auth:** JWT Bearer token via HttpInterceptor

## Common Commands

### Backend

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

### Frontend

```bash
cd frontend

# Development server (http://localhost:4200)
npm start

# Production build
npm run build

# Run tests
npm test
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

### Frontend Architecture

```
frontend/src/app/
├── core/                      # Singleton services, guards, interceptors
│   ├── auth/
│   │   ├── guards/           # authGuard (protects routes), guestGuard (login/register)
│   │   └── interceptors/     # authInterceptor (JWT), errorInterceptor (RFC 7807)
│   └── services/             # AuthService, ArticleService, ChapterService (all use Signals)
├── shared/                    # Reusable components
│   ├── components/           # ArticleCardComponent, SearchBarComponent
│   ├── pipes/                # TruncatePipe, HighlightPipe
│   └── directives/           # DebounceInputDirective
├── models/                    # TypeScript interfaces (Article, User, Auth, PaginationMeta)
├── features/                  # Lazy-loaded feature modules
│   ├── auth/                 # LoginComponent, RegisterComponent
│   ├── articles/             # ArticleListComponent, ArticleDetailComponent, ArticleSearchComponent
│   └── home/                 # HomeComponent
├── layout/                    # HeaderComponent, FooterComponent, MainLayoutComponent
├── app.config.ts             # provideRouter, provideHttpClient, provideAnimations
└── app.routes.ts             # Lazy-loaded routes
```

### Key Frontend Patterns

- **Signals:** All services use Angular Signals for reactive state (articles, pagination, currentArticle, etc.)
- **Standalone Components:** No NgModules, all components are standalone with direct imports
- **Lazy Loading:** Features loaded on demand via `loadComponent()` in routes
- **Interceptors:** Functional interceptors for JWT auth and error handling

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

## Frontend Configuration

API URL configured in `frontend/src/environments/environment.ts`:
```typescript
export const environment = {
  production: false,
  apiUrl: 'http://localhost:8000/api/v1'
};
```

### Path Aliases (tsconfig.json)
- `@app/*` → `src/app/*`
- `@env/*` → `src/environments/*`

### API Response Formats (for frontend consumption)

```typescript
// GET /articles
{ data: Article[], meta: PaginationMeta }

// GET /articles/chapters
{ count: number, chapters: string[] }

// GET /articles/number/{n}
{ count: number, articles: Article[] }

// Article structure
{
  id, documentId, articleNumber, title, content, chapter, status,
  concordances: [{ referencedLaw, referencedArticles, sourceArticleNumber, createdAt }],
  createdAt, updatedAt
}
```
