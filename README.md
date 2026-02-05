# LexEcuador

Aplicación full-stack para consulta de la Constitución de la República del Ecuador.

## Tecnologías

### Backend
- **Framework:** PHP 8.2+, Symfony 7.3
- **Base de datos:** MongoDB (Doctrine ODM)
- **Autenticación:** JWT (LexikJWTAuthenticationBundle)
- **Documentación:** OpenAPI/Swagger (NelmioApiDocBundle)
- **Arquitectura:** Clean Architecture + Domain-Driven Design

### Frontend
- **Framework:** Angular 17+ (Standalone Components)
- **UI:** Angular Material
- **Estado:** Angular Signals
- **Rutas:** Lazy Loading

## Requisitos

### Backend
- PHP 8.2+
- Extensión MongoDB para PHP (`ext-mongodb`)
- MongoDB 4.4+
- Composer

### Frontend
- Node.js 18+
- npm 9+

## Instalación

### Backend

```bash
# Clonar el repositorio
git clone <repository-url>
cd constitucion-api

# Instalar dependencias
composer install

# Configurar variables de entorno
cp .env .env.local
# Editar .env.local con tus configuraciones

# Generar claves JWT
php bin/console lexik:jwt:generate-keypair

# Crear índices en MongoDB
php bin/console doctrine:mongodb:schema:create

# Importar datos de la constitución
php bin/console app:import-constitution
```

### Frontend

```bash
cd frontend

# Instalar dependencias
npm install

# Configurar entorno (opcional - editar src/environments/environment.ts)
# Por defecto conecta a http://localhost:8000/api/v1
```

## Docker (Recomendado)

La forma más sencilla de ejecutar el proyecto es con Docker.

### Requisitos
- Docker
- Docker Compose

### Inicio rápido

```bash
# Construir contenedores
make build

# Iniciar todos los servicios
make up

# Generar claves JWT (primera vez)
make jwt-keys

# Importar datos de la constitución
make import-data
```

Los servicios estarán disponibles en:
- **Backend API:** http://localhost:8000
- **Frontend:** http://localhost:4200
- **MongoDB:** localhost:27017
- **Swagger UI:** http://localhost:8000/api/doc

### Comandos Docker disponibles

```bash
make help           # Ver todos los comandos
make build          # Construir contenedores
make up             # Iniciar contenedores
make down           # Detener contenedores
make logs           # Ver logs
make shell-php      # Acceder al contenedor PHP
make shell-frontend # Acceder al contenedor frontend
make shell-mongo    # Acceder a MongoDB shell
make import-data    # Importar datos de constitución
make jwt-keys       # Generar claves JWT
make cache-clear    # Limpiar caché de Symfony
```

### Estructura Docker

```
.docker/
├── php/
│   └── Dockerfile      # PHP 8.2 + MongoDB extension + Symfony CLI
└── frontend/
    └── Dockerfile      # Node 22 + Angular CLI

docker-compose.yml      # Orquestación de servicios
Makefile               # Comandos simplificados
```

## Configuración

### Variables de entorno (.env.local)

```env
# MongoDB
MONGODB_URL=mongodb://localhost:27017
MONGODB_DB=lexecuador_db

# JWT
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=tu_passphrase_seguro

# CORS
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
```

## Uso

### Backend

```bash
# Iniciar servidor de desarrollo
symfony server:start

# O con PHP integrado
php -S 127.0.0.1:8000 -t public
```

### Frontend

```bash
cd frontend

# Iniciar servidor de desarrollo
npm start
# Disponible en http://localhost:4200

# Build de producción
npm run build
```

## API Endpoints

### Autenticación

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/v1/auth/register` | Registrar nuevo usuario |
| POST | `/api/v1/auth/login` | Iniciar sesión (obtener JWT) |

### Artículos

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `/api/v1/articles` | Listar artículos (paginado) |
| GET | `/api/v1/articles/search?q={term}` | Buscar artículos |
| GET | `/api/v1/articles/number/{number}` | Obtener artículo por número |
| GET | `/api/v1/articles/chapters` | Listar capítulos |

### Documentación

| Endpoint | Descripción |
|----------|-------------|
| `/api/doc` | Swagger UI |
| `/api/doc.json` | OpenAPI JSON |

## Ejemplos

### Registro de usuario

```bash
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"usuario@ejemplo.com","password":"Password123","name":"Usuario"}'
```

### Login

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"usuario@ejemplo.com","password":"Password123"}'
```

### Listar artículos

```bash
curl http://localhost:8000/api/v1/articles?page=1&limit=10
```

### Buscar artículos

```bash
curl "http://localhost:8000/api/v1/articles/search?q=derechos"
```

### Obtener artículo por número

```bash
curl http://localhost:8000/api/v1/articles/number/1
```

## Arquitectura

### Backend

```
src/
├── Domain/                    # Capa de dominio
│   ├── Document/             # Documentos MongoDB
│   │   ├── Article.php
│   │   ├── EmbeddedConcordance.php
│   │   ├── LegalDocument.php
│   │   └── User.php
│   ├── ValueObject/          # Objetos de valor
│   │   ├── Email.php
│   │   └── Role.php
│   ├── Repository/           # Interfaces de repositorio
│   │   ├── ArticleRepositoryInterface.php
│   │   ├── LegalDocumentRepositoryInterface.php
│   │   └── UserRepositoryInterface.php
│   └── Exception/            # Excepciones de dominio
│       ├── ArticleNotFoundException.php
│       ├── DuplicateEmailException.php
│       ├── InvalidCredentialsException.php
│       └── UserNotFoundException.php
├── Application/              # Capa de aplicación
│   ├── Service/              # Servicios de aplicación
│   │   ├── ArticleService.php
│   │   └── ChapterOrderService.php
│   └── UseCase/              # Casos de uso
│       ├── Auth/
│       │   ├── LoginUserUseCase.php
│       │   └── RegisterUserUseCase.php
│       └── Article/
│           ├── GetArticleByNumberUseCase.php
│           └── SearchArticlesUseCase.php
├── Infrastructure/           # Capa de infraestructura
│   ├── Persistence/MongoDB/Repository/
│   │   ├── MongoDBArticleRepository.php
│   │   ├── MongoDBLegalDocumentRepository.php
│   │   └── MongoDBUserRepository.php
│   └── Security/
│       ├── CustomUserProvider.php
│       └── JwtTokenManager.php
├── Presentation/API/         # Capa de presentación
│   ├── Controller/
│   │   ├── ArticleController.php
│   │   └── AuthController.php
│   ├── Request/              # DTOs de entrada
│   │   ├── LoginRequest.php
│   │   └── RegisterRequest.php
│   ├── Response/             # DTOs de salida
│   │   ├── ArticleResponse.php
│   │   ├── PaginatedResponse.php
│   │   └── UserResponse.php
│   └── EventSubscriber/
│       └── ExceptionSubscriber.php
└── Command/
    └── ImportConstitutionCommand.php
```

### Frontend

```
frontend/src/app/
├── core/                      # Servicios singleton, guards, interceptors
│   ├── auth/
│   │   ├── guards/           # auth.guard.ts, guest.guard.ts
│   │   └── interceptors/     # auth.interceptor.ts, error.interceptor.ts
│   └── services/             # auth.service.ts, article.service.ts, chapter.service.ts
├── shared/                    # Componentes reutilizables
│   ├── components/           # article-card, search-bar
│   ├── pipes/                # truncate.pipe.ts, highlight.pipe.ts
│   └── directives/           # debounce-input.directive.ts
├── models/                    # Interfaces TypeScript
├── features/                  # Módulos lazy-loaded
│   ├── auth/                 # login, register
│   ├── articles/             # list, detail, search
│   └── home/                 # landing page
├── layout/                    # header, footer, main-layout
├── app.component.ts
├── app.config.ts
└── app.routes.ts
```

## Comandos útiles

```bash
# Limpiar caché
php bin/console cache:clear

# Recrear índices de MongoDB
php bin/console doctrine:mongodb:schema:drop
php bin/console doctrine:mongodb:schema:create

# Importar/reimportar datos
php bin/console app:import-constitution

# Ver rutas disponibles
php bin/console debug:router
```

## Respuestas de error

La API utiliza el formato RFC 7807 (Problem Details) para errores:

```json
{
  "type": "https://tools.ietf.org/html/rfc7807",
  "title": "Not Found",
  "status": 404,
  "detail": "Article number 999 not found."
}
```

## Roles de usuario

| Rol | Descripción |
|-----|-------------|
| `ROLE_FREE` | Usuario gratuito (por defecto) |
| `ROLE_PREMIUM` | Usuario premium |
| `ROLE_ADMIN` | Administrador |

## Licencia

Propietario - Todos los derechos reservados.
