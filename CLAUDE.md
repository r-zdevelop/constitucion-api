# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Constitutional Articles Viewer (Visualizador de Artículos Constitucionales) - A Symfony 7.3 application for browsing and searching the Ecuadorian Constitution.

## Tech Stack

- **Backend:** PHP 8.2+, Symfony 7.3 (MicroKernel)
- **Database:** MySQL/MariaDB (primary), PostgreSQL (supported via Docker)
- **ORM:** Doctrine ORM 3.5 with Migrations
- **Frontend:** Twig, Tailwind CSS, Stimulus.js
- **Assets:** Symfony Asset Mapper (no webpack)

## Common Commands

```bash
# Development server
symfony server:start

# Clear cache
php bin/console cache:clear

# Database migrations
php bin/console doctrine:migrations:migrate

# Import constitution data from JSON
php bin/console app:import-constitution

# Generate new entity
php bin/console make:entity

# Generate new controller
php bin/console make:controller
```

## Architecture

The codebase follows Clean Architecture with Repository Pattern and Service Layer:

```
src/
├── Controller/     # HTTP request handlers, delegate to services
├── Entity/         # Doctrine ORM domain models (Article, LegalDocument, etc.)
├── Repository/     # Data access with interface abstraction
└── Service/        # Business logic (ArticleService, ChapterOrderService)
```

### Key Patterns

- **Repository abstraction:** `ArticleRepositoryInterface` defines contract, `ArticleRepository` implements with Doctrine
- **Service layer validation:** Pagination (10-100 items), search (min 2 chars) validated in services
- **Dependency injection:** Constructor injection via `services.yaml` auto-wiring

### Entity Relationships

- `LegalDocument` → `Article` (OneToMany)
- `LegalDocument` → `DocumentSection` (OneToMany, hierarchical)
- `Article` → `ArticleHistory` (OneToMany, audit trail)
- Concordances stored as JSON in Article entity (denormalized)

## Routes

| Route | Description |
|-------|-------------|
| `/` | Homepage |
| `/articles` | Article listing with search/filter/pagination |
| `/api/articles/search-by-number` | API: Search by article number |

Query parameters for `/articles`: `?chapter=`, `?search=`, `?page=`

## Database

MySQL/MariaDB with FULLTEXT index on articles (content, title, chapter) for efficient searching. Schema defined in `schema.sql` and managed via Doctrine migrations in `migrations/`.

## Environment

Database connection configured in `.env`:
```
DATABASE_URL=mysql://admin:admin@127.0.0.1:3306/constitucion_ec
```

Docker Compose (`compose.yaml`) provides PostgreSQL alternative.
