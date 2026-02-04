# 01 - Análisis del Repositorio Actual

**Proyecto:** Visualizador de la Constitución del Ecuador
**Stack actual:** Symfony 7.3 + Doctrine ORM 3.5 + Tailwind CSS + Stimulus
**PHP:** 8.2+ con strict types
**Base de datos:** MySQL 8.0 (constitucion_ec)
**Fecha de análisis:** 2025-12-19

---

## 📂 Estructura de Directorios

```
constitucion-api/
├── bin/
│   └── console                    # CLI de Symfony
├── config/
│   ├── packages/                  # Configuración de bundles
│   │   ├── doctrine.yaml          # ORM + MySQL
│   │   └── [otros bundles]
│   ├── routes.yaml                # Rutas (usa attributes)
│   └── services.yaml              # DI container
├── data/
│   └── constitucion.json          # 935KB - Datos fuente (467 artículos)
├── migrations/
│   ├── Version20251119220232.php  # Schema inicial (5 tablas)
│   └── Version20251119235720.php  # Añade concordances JSON
├── public/
│   └── index.php                  # Entry point
├── src/
│   ├── Command/
│   │   └── ImportConstitutionCommand.php  # Import desde JSON
│   ├── Controller/
│   │   ├── ArticleController.php  # 2 endpoints (list + search API)
│   │   └── HomeController.php     # Landing page
│   ├── Entity/
│   │   ├── Article.php            # Artículo constitucional ⭐
│   │   ├── ArticleHistory.php     # Auditoría de cambios
│   │   ├── Concordance.php        # Referencias legales (tabla legacy)
│   │   ├── DocumentSection.php    # Estructura jerárquica
│   │   └── LegalDocument.php      # Documento legal (Constitución)
│   ├── Repository/
│   │   ├── ArticleRepository.php          # Implementación Doctrine ⭐
│   │   └── ArticleRepositoryInterface.php # Interfaz Clean Architecture
│   └── Service/
│       ├── ArticleService.php             # Lógica de negocio ⭐
│       └── ChapterOrderService.php        # Orden personalizado capítulos
├── templates/
│   ├── article/
│   │   └── list.html.twig         # Vista principal (474 líneas)
│   ├── home/
│   │   └── index.html.twig        # Landing page
│   └── base.html.twig             # Layout base
├── assets/
│   ├── controllers/
│   │   └── article_search_controller.js  # AJAX search (258 líneas)
│   └── styles/
│       └── app.css                # Tailwind imports
├── composer.json                  # Dependencias (PHP 8.2, Symfony 7.3)
├── schema.sql                     # Schema de referencia
└── .env                           # Variables de entorno
```

**Total estimado:** ~15-20 archivos de código fuente PHP
**LOC estimadas:** ~2,500-3,000 líneas (sin vendor)

---

## 🗄️ Entidades Existentes

### 1. Entity: `Article` ⭐ CORE ENTITY

**Archivo:** `src/Entity/Article.php` (179 líneas)

**Propósito:** Representa un artículo de la Constitución del Ecuador (467 artículos totales)

**Campos:**

| Campo | Tipo | Descripción | Constraints |
|-------|------|-------------|-------------|
| `id` | `int` (unsigned) | PK autoincremental | `@ORM\Id` |
| `document` | `ManyToOne → LegalDocument` | Documento padre | `NOT NULL`, `CASCADE` |
| `section` | `ManyToOne → DocumentSection` | Sección jerárquica | `nullable`, `SET NULL` |
| `articleNumber` | `int` | Número de artículo (1-467) | `NOT NULL` |
| `content` | `text` | Contenido completo del artículo | `NOT NULL` |
| `title` | `string(255)` | Título opcional | `nullable` |
| `chapter` | `string(255)` | Capítulo (ej: "Derechos") | `nullable` |
| `notes` | `text` | Notas adicionales | `nullable` |
| `status` | `string(32)` | Estado (`active`) | `NOT NULL`, default `'active'` |
| `createdAt` | `DateTimeImmutable` | Fecha creación | `NOT NULL` |
| `updatedAt` | `DateTimeImmutable` | Fecha modificación | `NOT NULL` |
| `concordances` | `json` | Array de concordancias | `NOT NULL`, default `[]` |

**Constraints:**
```sql
UNIQUE INDEX unique_article (document_id, article_number)
```

**Relaciones:**
- `ManyToOne` con `LegalDocument` (onDelete: CASCADE)
- `ManyToOne` con `DocumentSection` (onDelete: SET NULL)
- Tiene un campo JSON `concordances` (reemplaza tabla `Concordance`)

**Métodos destacados:**
```php
__construct(LegalDocument $document, int $articleNumber, string $content, ?string $title)
addConcordance(array $concordance): void  // Append a JSON concordances
setContent(string $content): void
setUpdatedAt(DateTimeImmutable $updatedAt): void
```

**✅ Calidad del código:**
- ✅ PHP 8.2 strict types
- ✅ Constructor con named parameters
- ✅ Inmutabilidad en fechas (`DateTimeImmutable`)
- ✅ Type hints completos
- ✅ PHPDoc para métodos complejos

---

### 2. Entity: `LegalDocument`

**Archivo:** `src/Entity/LegalDocument.php`

**Propósito:** Representa un documento legal completo (ej: Constitución 2008)

**Campos:**
- `id` (int, unsigned, PK)
- `name` (string 255) - "Constitución de la República del Ecuador"
- `documentType` (string 64) - Tipo de documento
- `year` (int) - 2008
- `lastModified` (DateTimeImmutable)
- `totalArticles` (int) - 467
- `status` (string 32) - 'active'

**Relaciones:**
- `OneToMany` con `DocumentSection`
- `OneToMany` con `Article`

**Uso actual:** 1 registro (Constitución del Ecuador 2008)

---

### 3. Entity: `DocumentSection`

**Archivo:** `src/Entity/DocumentSection.php`

**Propósito:** Estructura jerárquica del documento (Títulos → Capítulos → Secciones)

**Campos:**
- `id` (int, unsigned, PK)
- `document` (ManyToOne → LegalDocument)
- `parent` (ManyToOne → DocumentSection, self-referencing)
- `sectionType` (string 32) - 'title', 'chapter', 'section'
- `name` (string 255) - Nombre de la sección
- `orderIndex` (int) - Orden de aparición

**Relaciones:**
- Self-referencing para jerarquía padre/hijo
- `OneToMany` children para sub-secciones

**Nota:** No se usa actualmente en el código, los capítulos están guardados como strings en `Article.chapter`

---

### 4. Entity: `Concordance` (LEGACY - NO USADA)

**Archivo:** `src/Entity/Concordance.php`

**Propósito:** Referencias cruzadas entre artículos (reemplazada por JSON en Article)

**Campos:**
- `id` (int, unsigned, PK)
- `article` (ManyToOne → Article)
- `referencedLaw` (string 255) - Ley referenciada
- `referencedArticles` (json) - Artículos referenciados

**⚠️ PROBLEMA:** Esta tabla fue reemplazada por el campo `concordances` JSON en `Article`, pero la entidad y migración siguen existiendo (código muerto).

---

### 5. Entity: `ArticleHistory`

**Archivo:** `src/Entity/ArticleHistory.php`

**Propósito:** Auditoría de cambios en artículos (para trazabilidad)

**Campos:**
- `id` (int, unsigned, PK)
- `article` (ManyToOne → Article)
- `contentBefore` (text)
- `contentAfter` (text)
- `modifiedBy` (string 128) - Usuario/sistema que hizo el cambio
- `modificationReason` (text, nullable)
- `modifiedAt` (DateTimeImmutable)

**Uso:** Se crea automáticamente en `ArticleService::updateContent()` (línea 122-138)

---

## 🎯 Controladores y Rutas

### 1. `HomeController` (Simple)

**Archivo:** `src/Controller/HomeController.php`

**Ruta:** `GET /` → `app_home`

**Acción:** Renderiza landing page con información del proyecto

**Código:**
```php
#[Route('/', name: 'app_home')]
public function index(): Response
{
    return $this->render('home/index.html.twig');
}
```

**Template:** `templates/home/index.html.twig` (tarjeta de bienvenida con CTA)

---

### 2. `ArticleController` ⭐ CONTROLADOR PRINCIPAL

**Archivo:** `src/Controller/ArticleController.php`

#### Ruta 1: Listado de Artículos (Vista HTML)

```php
#[Route('/articles', name: 'app_articles_list', methods: ['GET'])]
public function list(
    Request $request,
    ArticleService $articleService,
    ChapterOrderService $chapterOrderService
): Response
```

**Parámetros GET:**
- `chapter` (string, opcional) - Filtrar por capítulo
- `search` (string, opcional) - Búsqueda por palabra clave (min 2 chars)
- `page` (int, opcional, default: 1) - Página actual

**Funcionalidades:**
1. **Sin filtros:** Lista todos los artículos agrupados por capítulo (paginado 20/página)
2. **Con `chapter`:** Filtra artículos del capítulo especificado
3. **Con `search`:** Busca en título y contenido (paginado)
4. **Orden personalizado:** Usa `ChapterOrderService` para orden constitucional

**Response:** Renderiza `templates/article/list.html.twig` con:
```php
[
    'articlesByChapter' => [...],  // Agrupados por capítulo
    'allChapters' => [...],        // Dropdown de filtros
    'selectedChapter' => '...',    // Capítulo activo
    'searchTerm' => '...',         // Término de búsqueda
    'pagination' => [              // Paginación
        'total' => 467,
        'pages' => 24,
        'currentPage' => 1,
    ]
]
```

**Validación:**
- Mínimo 2 caracteres para búsqueda
- Page >= 1
- Items per page: 20 (fijo en vista, 10-100 en servicio)

---

#### Ruta 2: API de Búsqueda por Número (JSON)

```php
#[Route('/api/articles/search-by-number', name: 'api_articles_search_by_number', methods: ['GET'])]
public function searchByNumber(Request $request, ArticleService $articleService): JsonResponse
```

**Parámetros GET:**
- `number` (int, required) - Número de artículo a buscar
- `documentId` (int, opcional) - Filtrar por documento específico

**Response Success (200):**
```json
{
  "count": 1,
  "articles": [
    {
      "id": 123,
      "articleNumber": 1,
      "title": "Título del artículo",
      "content": "Ecuador es un Estado...",
      "chapter": "Principios fundamentales",
      "status": "active",
      "notes": null,
      "concordances": [
        {"referencedLaw": "Ley X", "referencedArticles": [10, 20]}
      ]
    }
  ]
}
```

**Response Error (400):**
```json
{
  "error": "El número de artículo debe ser un entero positivo"
}
```

**Validación:**
- `number` debe ser entero positivo
- `documentId` (si se envía) debe ser entero positivo

**Uso:** Consumida por Stimulus controller `article_search_controller.js` (AJAX)

---

## 🔧 Servicios y Repositorios

### 1. `ArticleRepository` ⭐ REPOSITORIO PRINCIPAL

**Archivo:** `src/Repository/ArticleRepository.php` (207 líneas)

**Extiende:** `ServiceEntityRepository`
**Implementa:** `ArticleRepositoryInterface` (Clean Architecture)

**Métodos públicos:**

| Método | Descripción | Return Type |
|--------|-------------|-------------|
| `findById(int $id)` | Busca artículo por ID | `?Article` |
| `findByNumber(int $documentId, int $articleNumber)` | Busca artículo específico en documento | `?Article` |
| `findByArticleNumber(int $articleNumber)` | Busca artículos con ese número en todos los documentos | `Article[]` |
| `findAll()` | Lista todos ordenados por número | `Article[]` |
| `findAllChapters()` | Obtiene lista de capítulos únicos | `string[]` |
| `findByChapter(string $chapter)` | Filtra artículos por capítulo | `Article[]` |
| `fullTextSearch(string $query, int $limit)` | Búsqueda en contenido (LIKE) | `Article[]` |
| `searchPaginated(...)` | Búsqueda paginada (título + contenido) | `array{items, total, pages, currentPage}` |
| `findAllPaginated(...)` | Lista paginada con filtro opcional de capítulo | `array{items, total, pages, currentPage}` |
| `save(Article $article)` | Persiste artículo | `void` |
| `remove(Article $article)` | Elimina artículo | `void` |

**Características destacadas:**

✅ **Seguridad:** Usa Doctrine parameter binding (previene SQL injection)

```php
// ✅ CORRECTO - Parameter binding
$qb->where('a.articleNumber = :number')
   ->setParameter('number', $articleNumber);
```

✅ **Performance:** Usa `Doctrine\ORM\Tools\Pagination\Paginator` para queries eficientes

```php
$paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);
$total = count($paginator);  // COUNT optimizado
```

✅ **Clean Architecture:** Implementa interfaz para inversión de dependencias

⚠️ **Limitación actual:** No usa índices full-text de MySQL, solo `LIKE '%term%'` (menos eficiente)

---

### 2. `ArticleRepositoryInterface`

**Archivo:** `src/Repository/ArticleRepositoryInterface.php`

**Propósito:** Abstracción para Clean Architecture (principio SOLID de inversión de dependencias)

**Métodos definidos:**
```php
interface ArticleRepositoryInterface
{
    public function findById(int $id): ?Article;
    public function findByNumber(int $documentId, int $articleNumber): ?Article;
    public function findAll(): array;
    public function save(Article $article): void;
    public function remove(Article $article): void;
}
```

**✅ Ventaja:** Permite testear `ArticleService` con mocks sin base de datos

---

### 3. `ArticleService` ⭐ CAPA DE NEGOCIO

**Archivo:** `src/Service/ArticleService.php` (140 líneas)

**Dependencias inyectadas:**
- `ArticleRepositoryInterface` (no la implementación concreta ✅)
- `EntityManagerInterface` (para transacciones)

**Métodos públicos:**

#### `search(string $q, int $limit = 50): array`
Búsqueda full-text simple.

**Reglas de negocio:**
- Trim del query
- Si está vacío, retorna `[]`

---

#### `findByArticleNumber(int $articleNumber, ?int $documentId = null): array`
Busca artículos por número.

**Reglas de negocio:**
- Si `$articleNumber <= 0`, retorna `[]`
- Si `$documentId` es provisto, busca en ese documento específico
- Sino, busca en todos los documentos

**Return:** Array de artículos (puede estar vacío)

---

#### `searchArticlesPaginated(string $searchTerm, int $page = 1, int $itemsPerPage = 20): array`
Búsqueda con paginación y validaciones estrictas.

**Reglas de negocio:**
- **Mínimo 2 caracteres** para buscar (previene queries pesadas)
- `$page` se fuerza a >= 1
- `$itemsPerPage` se clampea entre 10-100 (previene abuso de recursos)
- Busca en `title` + `content` (OR condition)

**Return:**
```php
[
    'items' => Article[],
    'total' => 467,
    'pages' => 24,
    'currentPage' => 1,
    'searchTerm' => 'derechos'
]
```

---

#### `getAllArticlesPaginated(int $page = 1, int $itemsPerPage = 20, ?string $chapter = null): array`
Lista todos los artículos con paginación y filtro opcional.

**Reglas de negocio:**
- Mismas validaciones de page e itemsPerPage
- Si `$chapter` es provisto y no está vacío, filtra por ese capítulo

---

#### `updateContent(Article $article, string $newContent, string $modifiedBy, ?string $reason = null): void`
Actualiza contenido de artículo con auditoría automática.

**Reglas de negocio:**
- Si el contenido es igual, no hace nada (early return)
- Actualiza `content` y `updatedAt`
- Crea registro en `ArticleHistory` automáticamente
- Todo en una transacción (flush)

**✅ Código robusto:** Atomic operation, auditoría automática

---

### 4. `ChapterOrderService`

**Archivo:** `src/Service/ChapterOrderService.php`

**Propósito:** Ordenar capítulos en orden constitucional en lugar de alfabético

**Orden definido:**
1. Principios fundamentales
2. Derechos
3. Garantías constitucionales
4. Participación y organización del poder
5. (Resto alfabéticamente)

**Métodos:**
```php
public function sortChapters(array $chapters): array
public function sortChapterGroups(array $chapterGroups): array
private function getChapterPriority(string $chapter): int
```

**✅ Ventaja:** UX mejorada, respeta estructura constitucional

---

## ⚙️ Configuración Actual

### 1. `composer.json`

**Dependencias de producción:**

| Paquete | Versión | Propósito |
|---------|---------|-----------|
| `php` | `>=8.2` | Lenguaje |
| `symfony/framework-bundle` | `7.3.*` | Framework core |
| `symfony/console` | `7.3.*` | CLI commands |
| `symfony/dotenv` | `7.3.*` | Variables de entorno |
| `symfony/asset-mapper` | `7.3.*` | Assets modernos (sin Webpack) |
| `symfony/stimulus-bundle` | `^2.31` | JavaScript framework |
| `symfony/twig-bundle` | `7.3.*` | Motor de plantillas |
| `symfony/translation` | `7.3.*` | Internacionalización |
| `doctrine/orm` | `^3.5` | ORM |
| `doctrine/doctrine-bundle` | `^3.0` | Integración Symfony |
| `doctrine/doctrine-migrations-bundle` | `^3.7` | Migraciones |
| `symfonycasts/tailwind-bundle` | `^0.11.1` | CSS framework |

**Extensiones PHP:**
- `ext-ctype`
- `ext-iconv`

**❌ Faltantes para API:**
- `lexik/jwt-authentication-bundle` (JWT tokens)
- `nelmio/api-doc-bundle` (OpenAPI/Swagger)
- `nelmio/cors-bundle` (CORS headers)
- `symfony/serializer` (JSON serialization)
- `symfony/validator` (validación de DTOs)
- `symfony/security-bundle` (autenticación/autorización)
- `phpunit/phpunit` (testing)

---

### 2. `config/packages/doctrine.yaml`

**Configuración actual:**

```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        # MySQL/MariaDB via DATABASE_URL en .env

    orm:
        auto_generate_proxy_classes: true  # Cambiar a false en producción
        enable_lazy_ghost_objects: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            App:
                type: attribute  # ✅ Usa PHP 8 attributes
                is_bundle: false
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
                alias: App
```

**Base de datos actual (.env):**
```
DATABASE_URL="mysql://admin:admin@127.0.0.1:3306/constitucion_ec?serverVersion=8.0"
```

**✅ Compatible con API:** Solo necesita ajustar `auto_generate_proxy_classes: false` en producción

---

### 3. `config/services.yaml`

**Configuración actual:**

```yaml
services:
    _defaults:
        autowire: true       # ✅ Inyección automática de dependencias
        autoconfigure: true  # ✅ Auto-registro de servicios

    App\:
        resource: '../src/'
        exclude:
            - '../src/DependencyInjection/'
            - '../src/Entity/'
            - '../src/Kernel.php'
```

**✅ Listo para API:** La configuración actual es perfecta para Clean Architecture

---

### 4. `config/routes.yaml`

**Configuración actual:**

```yaml
controllers:
    resource: ../src/Controller/
    type: attribute  # ✅ Usa PHP 8 attributes para rutas
```

**Rutas actuales (definidas en controladores):**
1. `GET /` → HomeController::index
2. `GET /articles` → ArticleController::list (HTML)
3. `GET /api/articles/search-by-number` → ArticleController::searchByNumber (JSON)

**✅ Compatible con API:** Solo requiere añadir prefijo `/api/v1` y eliminar rutas HTML

---

## 💾 Base de Datos y Migraciones

### Migración 1: `Version20251119220232.php` (Schema Inicial)

**Fecha:** 19 de noviembre 2025, 22:02:32

**Tablas creadas:**

#### 1. `legal_documents`
```sql
CREATE TABLE legal_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    document_type VARCHAR(64) NOT NULL,
    year INT NOT NULL,
    last_modified DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    total_articles INT NOT NULL,
    status VARCHAR(32) NOT NULL,
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Registro actual:**
- ID: 1
- Name: "Constitución de la República del Ecuador"
- Year: 2008
- Total articles: 467

---

#### 2. `document_sections`
```sql
CREATE TABLE document_sections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    parent_id INT UNSIGNED DEFAULT NULL,
    section_type VARCHAR(32) NOT NULL,
    name VARCHAR(255) NOT NULL,
    order_index INT NOT NULL,
    FOREIGN KEY (document_id) REFERENCES legal_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES document_sections(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**⚠️ NO USADA:** La estructura jerárquica no se usa actualmente, los capítulos están en `articles.chapter` como string

---

#### 3. `articles` ⭐ TABLA PRINCIPAL
```sql
CREATE TABLE articles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id INT UNSIGNED NOT NULL,
    section_id INT UNSIGNED DEFAULT NULL,
    article_number INT NOT NULL,
    content TEXT NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    chapter VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status VARCHAR(32) NOT NULL,
    created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    UNIQUE INDEX unique_article (document_id, article_number),
    FOREIGN KEY (document_id) REFERENCES legal_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (section_id) REFERENCES document_sections(id) ON DELETE SET NULL,
    FULLTEXT INDEX idx_fulltext (content, title, chapter)  # ⚠️ No se usa
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Registros actuales:** 467 artículos

**⚠️ Problema:** Índice FULLTEXT creado pero no utilizado (usa LIKE en vez de MATCH AGAINST)

---

#### 4. `concordances` (LEGACY)
```sql
CREATE TABLE concordances (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id INT UNSIGNED NOT NULL,
    referenced_law VARCHAR(255) NOT NULL,
    referenced_articles JSON NOT NULL,
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**⚠️ CÓDIGO MUERTO:** Esta tabla fue reemplazada por el campo `concordances` JSON en `articles` (ver migración 2)

---

#### 5. `article_history`
```sql
CREATE TABLE article_history (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    article_id INT UNSIGNED NOT NULL,
    content_before TEXT NOT NULL,
    content_after TEXT NOT NULL,
    modified_by VARCHAR(128) NOT NULL,
    modification_reason TEXT DEFAULT NULL,
    modified_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Uso:** Se llena automáticamente al usar `ArticleService::updateContent()`

---

### Migración 2: `Version20251119235720.php` (Concordances JSON)

**Fecha:** 19 de noviembre 2025, 23:57:20

**Cambio:**
```sql
ALTER TABLE articles ADD concordances JSON NOT NULL;
```

**Propósito:** Mover concordancias de tabla relacional a campo JSON en `Article`

**⚠️ Problema:** No se eliminó la tabla `concordances` ni la entidad `Concordance.php`, quedando código duplicado

---

### Estado de la Base de Datos

**Tablas activas:**
- ✅ `legal_documents` (1 registro)
- ⚠️ `document_sections` (0 registros - no usada)
- ✅ `articles` (467 registros)
- ❌ `concordances` (legacy - no usada)
- ✅ `article_history` (usada para auditoría)

**Índices:**
- ✅ Unique constraint `unique_article` (document_id, article_number) - previene duplicados
- ⚠️ Fulltext index `idx_fulltext` - creado pero no usado en queries

---

## ✅ Funcionalidades YA Implementadas

### 1. Sistema de Visualización de Artículos ⭐

**Feature completa:** Navegación y búsqueda de artículos constitucionales

**Incluye:**

#### 1.1 Listado Paginado
- ✅ Muestra 20 artículos por página
- ✅ Navegación prev/next + números de página
- ✅ Total de páginas calculado dinámicamente
- ✅ Agrupación por capítulo
- ✅ Orden personalizado de capítulos (Principios → Derechos → Garantías)

**Código:** `ArticleController::list()` + `ArticleRepository::findAllPaginated()`

---

#### 1.2 Búsqueda por Palabra Clave
- ✅ Busca en título + contenido
- ✅ Mínimo 2 caracteres requeridos
- ✅ Paginación integrada
- ✅ Muestra total de resultados encontrados
- ✅ Botón "Limpiar búsqueda"

**Query usado:**
```sql
SELECT a.* FROM articles a
WHERE a.title LIKE '%derechos%' OR a.content LIKE '%derechos%'
ORDER BY a.article_number ASC
LIMIT 20 OFFSET 0
```

---

#### 1.3 Filtro por Capítulo
- ✅ Dropdown con todos los capítulos
- ✅ Orden personalizado (no alfabético)
- ✅ Combinable con paginación
- ✅ Botón "Todos los capítulos" para resetear

**Capítulos disponibles:**
1. Principios fundamentales
2. Derechos
3. Garantías constitucionales
4. Participación y organización del poder
5. (Otros...)

---

#### 1.4 Búsqueda por Número de Artículo (AJAX)
- ✅ Endpoint API: `GET /api/articles/search-by-number?number=1`
- ✅ Respuesta JSON
- ✅ Consumido por Stimulus controller
- ✅ Loading spinner mientras carga
- ✅ Manejo de errores con mensajes user-friendly
- ✅ XSS prevention (HTML escaping)
- ✅ Búsqueda con Enter key

**Stimulus controller:** `assets/controllers/article_search_controller.js` (258 líneas)

**Features del AJAX search:**
```javascript
// Fetch API con parámetros
fetch(`/api/articles/search-by-number?number=${articleNumber}`)

// Muestra spinner
this.loadingTarget.classList.remove('hidden')

// Renderiza resultados dinámicamente
this.resultsTarget.innerHTML = resultsHTML

// Escapa HTML para prevenir XSS
function escapeHtml(text) {
    const div = document.createElement('div')
    div.textContent = text
    return div.innerHTML
}
```

---

### 2. Importación de Datos desde JSON ✅

**Feature completa:** Comando CLI para importar la Constitución desde archivo JSON

**Comando:** `php bin/console app:import-constitution`

**Archivo fuente:** `data/constitucion.json` (935KB)

**Proceso:**
1. Lee JSON con estructura:
   ```json
   {
     "name": "Constitución de la República del Ecuador",
     "year": 2008,
     "total_articles": 467,
     "articles": [
       {
         "number": 1,
         "title": "...",
         "content": "Ecuador es un Estado...",
         "chapter": "Principios fundamentales",
         "concordancias": [...]
       }
     ]
   }
   ```

2. Crea/obtiene `LegalDocument` (o lo busca si ya existe)
3. Itera sobre artículos:
   - Verifica si ya existe (por documento + número)
   - Si no existe, lo crea
   - Añade concordancias como JSON
4. Persiste en base de datos
5. Muestra progreso en consola

**✅ Idempotente:** Puede ejecutarse múltiples veces sin duplicar datos

---

### 3. Auditoría de Cambios (Article History) ✅

**Feature completa:** Tracking automático de modificaciones a artículos

**Implementación:** `ArticleService::updateContent()`

**Proceso:**
```php
public function updateContent(
    Article $article,
    string $newContent,
    string $modifiedBy,
    ?string $reason = null
): void {
    $before = $article->getContent();

    if ($before === $newContent) {
        return;  // Sin cambios
    }

    $article->setContent($newContent);
    $article->setUpdatedAt(new \DateTimeImmutable());

    // Crear registro de auditoría
    $history = new ArticleHistory(
        $article,
        $before,
        $newContent,
        $modifiedBy,
        $reason
    );

    // Persistir ambos en transacción
    $this->em->persist($article);
    $this->em->persist($history);
    $this->em->flush();
}
```

**✅ Garantiza:** Trazabilidad completa de cambios, quién lo hizo y por qué

---

### 4. UI/UX Moderna ✅

**Stack frontend:**
- ✅ Tailwind CSS para estilos
- ✅ Stimulus.js para interactividad (no jQuery)
- ✅ Mobile-first responsive design
- ✅ Asset Mapper (no requiere Webpack/npm build)

**Features UI:**
- ✅ Landing page con CTA
- ✅ Header con logo/nombre del proyecto
- ✅ Footer con copyright
- ✅ Cards con sombras y bordes redondeados
- ✅ Estados de loading (spinners)
- ✅ Mensajes de error user-friendly
- ✅ Estados vacíos ("No se encontraron resultados")
- ✅ Botones de acción claros
- ✅ Tipografía legible (sans-serif)
- ✅ Colores consistentes (azul primary)

**Responsive:**
- ✅ Desktop: Multi-columna
- ✅ Tablet: 2 columnas
- ✅ Mobile: 1 columna, touch-friendly

---

## 🏗️ Arquitectura Actual

### Capas Implementadas

```
┌─────────────────────────────────────────┐
│         PRESENTATION LAYER              │
│  (Controllers + Templates + Stimulus)   │
│                                         │
│  - HomeController                       │
│  - ArticleController                    │
│  - article_search_controller.js         │
└─────────────────┬───────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────┐
│        APPLICATION LAYER                │
│           (Services)                    │
│                                         │
│  - ArticleService  ⭐                   │
│  - ChapterOrderService                  │
└─────────────────┬───────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────┐
│      INFRASTRUCTURE LAYER               │
│   (Repositories + Persistence)          │
│                                         │
│  - ArticleRepository                    │
│  - ArticleRepositoryInterface ⭐        │
│  - Doctrine ORM                         │
└─────────────────┬───────────────────────┘
                  │
                  ▼
┌─────────────────────────────────────────┐
│          DOMAIN LAYER                   │
│          (Entities)                     │
│                                         │
│  - Article ⭐                           │
│  - LegalDocument                        │
│  - ArticleHistory                       │
│  - DocumentSection (no usada)           │
│  - Concordance (legacy)                 │
└─────────────────────────────────────────┘
```

### Principios SOLID Aplicados

✅ **Single Responsibility Principle (SRP)**
- Cada clase tiene una responsabilidad clara
- `ArticleService` → lógica de negocio
- `ArticleRepository` → acceso a datos
- `ChapterOrderService` → orden de capítulos

✅ **Open/Closed Principle (OCP)**
- Extensible mediante herencia y composición
- Servicios configurables por DI

✅ **Liskov Substitution Principle (LSP)**
- `ArticleRepository` es sustituible por cualquier implementación de `ArticleRepositoryInterface`

✅ **Interface Segregation Principle (ISP)**
- `ArticleRepositoryInterface` define solo métodos esenciales
- No interfaces obesas

✅ **Dependency Inversion Principle (DIP)** ⭐
- `ArticleService` depende de `ArticleRepositoryInterface`, NO de la implementación concreta
- Permite testing con mocks

---

### Patrones de Diseño Aplicados

1. **Repository Pattern** ⭐
   - `ArticleRepository` abstrae acceso a datos
   - Queries encapsuladas

2. **Service Layer Pattern**
   - Lógica de negocio en `ArticleService`
   - Controladores delgados

3. **Dependency Injection**
   - Constructor injection en todos los servicios
   - Autowiring automático de Symfony

4. **DTO Pattern (parcial)**
   - Arrays asociativos para paginación
   - ⚠️ No hay clases DTO específicas

5. **Command Pattern**
   - `ImportConstitutionCommand` para CLI

---

## ♻️ Código Reutilizable para la API

### ✅ Reutilizable sin cambios

#### 1. Entidades de Dominio
**Archivos:**
- ✅ `src/Entity/Article.php` → Perfecto para API
- ✅ `src/Entity/LegalDocument.php` → Reutilizar
- ✅ `src/Entity/ArticleHistory.php` → Reutilizar para auditoría
- ⚠️ `src/Entity/DocumentSection.php` → Evaluar si usar en v2
- ❌ `src/Entity/Concordance.php` → ELIMINAR (legacy)

**Razón:** Las entidades son agnósticas de la capa de presentación, funcionan igual en API

---

#### 2. Repositorios
**Archivos:**
- ✅ `src/Repository/ArticleRepository.php` → Reutilizar completo
- ✅ `src/Repository/ArticleRepositoryInterface.php` → Mantener

**Cambios menores necesarios:**
- Añadir métodos para filtros avanzados de suscripciones (ej: `findPremiumArticles()`)
- Añadir método `findByIds(array $ids)` para búsquedas múltiples

---

#### 3. Servicios de Lógica de Negocio
**Archivos:**
- ✅ `src/Service/ArticleService.php` → Reutilizar el 90%
- ✅ `src/Service/ChapterOrderService.php` → Reutilizar completo

**Cambios menores:**
- Añadir método `getArticlesByRole(User $user)` para filtrar por suscripción
- Añadir validaciones de permisos

---

#### 4. Comando de Importación
**Archivo:**
- ✅ `src/Command/ImportConstitutionCommand.php` → Mantener

**Razón:** Útil para seeds y migraciones de datos en API

---

#### 5. Configuración Doctrine
**Archivos:**
- ✅ `config/packages/doctrine.yaml` → Reutilizar
- ✅ Migraciones en `migrations/` → Mantener

**Cambios necesarios:**
- Añadir migraciones para nuevas entidades (User, Subscription, etc.)

---

### ⚠️ Reutilizable con refactoring

#### 1. Lógica de Validación
**Código actual:**
```php
// En ArticleController::searchByNumber()
if (!is_numeric($number) || (int)$number <= 0) {
    return new JsonResponse(['error' => 'El número de artículo debe ser un entero positivo'], 400);
}
```

**Refactoring necesario:**
- ✅ Mover a DTOs con Symfony Validator
- ✅ Crear `SearchByNumberRequest` DTO
- ✅ Usar constraints: `@Assert\Positive`, `@Assert\Type("integer")`

---

#### 2. Serialización JSON
**Código actual (manual):**
```php
$articleData = [
    'id' => $article->getId(),
    'articleNumber' => $article->getArticleNumber(),
    'title' => $article->getTitle(),
    'content' => $article->getContent(),
    'chapter' => $article->getChapter(),
    'status' => $article->getStatus(),
    'notes' => $article->getNotes(),
    'concordances' => $article->getConcordances(),
];
```

**Refactoring necesario:**
- ✅ Usar Symfony Serializer
- ✅ Crear grupos de serialización (`@Groups(["api:article:read"])`)
- ✅ Normalizers personalizados si es necesario

**Ejemplo:**
```php
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity]
class Article {
    #[Groups(['api:article:read', 'api:article:write'])]
    private int $articleNumber;

    #[Groups(['api:article:read'])]
    private \DateTimeImmutable $createdAt;
}

// En controller
return $this->json($article, 200, [], ['groups' => 'api:article:read']);
```

---

### ❌ NO Reutilizable (Eliminar)

#### 1. Controladores HTML
**Archivos a eliminar:**
- ❌ `src/Controller/HomeController.php`
- ⚠️ `src/Controller/ArticleController.php::list()` (ruta HTML)

**Razón:** La API no renderiza HTML, solo retorna JSON

**Mantener:**
- ✅ `ArticleController::searchByNumber()` → Refactorizar a endpoint REST completo

---

#### 2. Templates Twig
**Directorio a eliminar:**
- ❌ `templates/` (completo)
- ❌ `assets/controllers/article_search_controller.js`
- ❌ `assets/styles/app.css`

**Razón:** Frontend Angular consumirá la API, no necesita vistas server-side

---

#### 3. Bundles de Frontend
**Remover de composer.json:**
- ❌ `symfony/twig-bundle`
- ❌ `symfony/asset-mapper`
- ❌ `symfony/stimulus-bundle`
- ❌ `symfonycasts/tailwind-bundle`

**Añadir para API:**
- ✅ `symfony/serializer`
- ✅ `symfony/validator`
- ✅ `symfony/security-bundle`
- ✅ `lexik/jwt-authentication-bundle`
- ✅ `nelmio/cors-bundle`
- ✅ `nelmio/api-doc-bundle`

---

## ⚠️ Problemas y Code Smells Detectados

### 1. 🔴 Código Muerto: Tabla y Entidad `Concordance`

**Problema:**
- Existe tabla `concordances` en base de datos
- Existe entidad `src/Entity/Concordance.php`
- **PERO** se usa campo JSON `concordances` en `Article`
- Migración añadió JSON pero no eliminó tabla legacy

**Impacto:**
- Confusión para desarrolladores
- Base de datos con tabla vacía innecesaria
- Código duplicado

**Solución:**
```php
// Crear migración
php bin/console make:migration

// En la migración
public function up(Schema $schema): void
{
    $this->addSql('DROP TABLE concordances');
}

// Eliminar archivo
rm src/Entity/Concordance.php
```

---

### 2. 🟡 Entidad `DocumentSection` No Utilizada

**Problema:**
- Tabla `document_sections` existe en BD (0 registros)
- Entidad `DocumentSection.php` implementada
- **NO se usa** en ninguna parte del código
- Los capítulos se guardan como string en `Article.chapter`

**Opciones:**
1. **Eliminar:** Si no se planea usar estructura jerárquica
2. **Implementar:** Si se quiere navegación por secciones en v2

**Recomendación para MVP:** ELIMINAR

---

### 3. 🟡 Índice FULLTEXT Creado pero No Usado

**Problema:**
```sql
FULLTEXT INDEX idx_fulltext (content, title, chapter)
```

Creado en migración, pero queries usan `LIKE`:
```php
$qb->where($qb->expr()->like('a.content', ':query'))
```

**Impacto:**
- Performance subóptima en búsquedas de texto
- Índice ocupa espacio sin usarse

**Solución:**
```php
// Usar MySQL MATCH AGAINST
$qb->where('MATCH(a.content, a.title, a.chapter) AGAINST(:query IN BOOLEAN MODE)')
   ->setParameter('query', $searchTerm);
```

**O eliminar el índice** si se prefiere LIKE por flexibilidad

---

### 4. 🟡 Validación Mezclada en Controller

**Problema:**
```php
// En ArticleController::searchByNumber()
if (!is_numeric($number) || (int)$number <= 0) {
    return new JsonResponse(['error' => '...'], 400);
}
```

**Code smell:**
- Validación en controller en lugar de usar Symfony Validator
- Mensajes de error hardcodeados
- No sigue convenciones de API (RFC 7807)

**Solución:**
```php
// DTO con validación
use Symfony\Component\Validator\Constraints as Assert;

class SearchByNumberRequest
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Assert\Type('integer')]
    public int $number;

    #[Assert\Positive(message: 'Document ID must be positive')]
    #[Assert\Type('integer')]
    public ?int $documentId = null;
}

// En controller
public function searchByNumber(#[MapQueryString] SearchByNumberRequest $request): JsonResponse
{
    // Validación automática por ParamConverter
}
```

---

### 5. 🟡 Serialización Manual en Lugar de Serializer

**Problema:**
```php
$articlesData = [];
foreach ($articles as $article) {
    $articlesData[] = [
        'id' => $article->getId(),
        'articleNumber' => $article->getArticleNumber(),
        // ...12 campos manualmente
    ];
}
return new JsonResponse(['articles' => $articlesData]);
```

**Code smell:**
- Verbose y propenso a errores
- No reutilizable
- Dificil de mantener si cambian entidades

**Solución:**
```php
// Usar Serializer de Symfony
return $this->json($articles, 200, [], [
    'groups' => ['api:article:read']
]);
```

---

### 6. 🟢 Falta de Tests

**Problema:**
- ❌ No hay directorio `tests/`
- ❌ No hay PHPUnit configurado
- ❌ No hay tests unitarios ni de integración

**Impacto:**
- Refactoring riesgoso
- Bugs no detectados
- Difícil onboarding de desarrolladores

**Solución:**
```bash
composer require --dev phpunit/phpunit symfony/test-pack

# Tests unitarios
tests/Unit/Service/ArticleServiceTest.php

# Tests de integración
tests/Integration/Repository/ArticleRepositoryTest.php

# Tests de API
tests/Functional/Controller/ArticleApiTest.php
```

---

### 7. 🟡 Falta de API Documentation

**Problema:**
- Endpoint `/api/articles/search-by-number` sin documentación formal
- No hay OpenAPI/Swagger
- Dificulta integración del frontend Angular

**Solución:**
```bash
composer require nelmio/api-doc-bundle

# Añadir annotations en controllers
use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/articles/{id}',
    summary: 'Get article by ID',
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
    ],
    responses: [
        new OA\Response(response: 200, description: 'Article found'),
        new OA\Response(response: 404, description: 'Article not found')
    ]
)]
```

---

### 8. 🔴 Credenciales de BD en .env (Seguridad)

**Problema:**
```env
DATABASE_URL="mysql://admin:admin@127.0.0.1:3306/constitucion_ec"
```

**Code smell:**
- Usuario `admin` con contraseña `admin` (inseguro)
- ⚠️ Si `.env` se commitea a git, credentials expuestas

**Solución:**
```bash
# .env debe estar en .gitignore
echo ".env" >> .gitignore

# Usar .env.local para desarrollo
# .env.prod.local para producción

# En producción: usar variables de entorno del servidor
DATABASE_URL=${DATABASE_URL}
```

---

### 9. 🟡 No Hay Rate Limiting

**Problema:**
- API endpoint `/api/articles/search-by-number` sin límite de requests
- Vulnerable a abuso/DOS

**Solución:**
```bash
composer require symfony/rate-limiter

# Configurar en controller
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[Route('/api/v1/articles/search')]
public function search(RateLimiterFactory $apiLimiter): JsonResponse
{
    $limiter = $apiLimiter->create($request->getClientIp());
    if (!$limiter->consume(1)->isAccepted()) {
        return new JsonResponse(['error' => 'Too many requests'], 429);
    }
    // ...
}
```

---

### 10. 🟢 Docker Compose Configura PostgreSQL pero Usa MySQL

**Problema:**
- Existe `docker-compose.yml` con PostgreSQL
- Pero `.env` usa MySQL local
- Confusión sobre cuál es el setup correcto

**Solución:**
- Decidir: ¿PostgreSQL en Docker o MySQL local?
- Para API REST en producción: **PostgreSQL recomendado** (mejor JSON support, performance)
- Actualizar `.env` y `docker-compose.yml` para consistencia

---

## 💡 Recomendaciones Finales

### ✅ MANTENER (Código de Alta Calidad)

| Componente | Razón | Prioridad |
|------------|-------|-----------|
| `src/Entity/Article.php` | Entidad bien diseñada, reutilizable | 🔴 CRÍTICO |
| `src/Entity/LegalDocument.php` | Necesario para multi-documentos en v2 | 🟡 MEDIA |
| `src/Entity/ArticleHistory.php` | Auditoría esencial para compliance | 🔴 CRÍTICO |
| `src/Repository/ArticleRepository.php` | Queries optimizadas, seguras | 🔴 CRÍTICO |
| `src/Repository/ArticleRepositoryInterface.php` | Clean Architecture | 🔴 CRÍTICO |
| `src/Service/ArticleService.php` | Lógica de negocio bien encapsulada | 🔴 CRÍTICO |
| `src/Service/ChapterOrderService.php` | UX mejorada | 🟢 BAJA |
| `src/Command/ImportConstitutionCommand.php` | Útil para seeds | 🟡 MEDIA |
| `config/packages/doctrine.yaml` | Configuración correcta | 🔴 CRÍTICO |
| `migrations/Version*.php` | Historia de schema | 🔴 CRÍTICO |

**Total a mantener:** ~10 archivos (~1,500 LOC)

---

### ⚠️ REFACTORIZAR (Necesita Cambios)

| Componente | Cambios Necesarios | Esfuerzo |
|------------|-------------------|----------|
| `src/Controller/ArticleController.php` | Convertir a API REST, añadir DTOs, usar Serializer | 4 horas |
| Validación en controllers | Mover a DTOs con Symfony Validator | 2 horas |
| Serialización manual | Usar Symfony Serializer + grupos | 3 horas |
| Índice FULLTEXT | Implementar MATCH AGAINST o eliminar | 1 hora |
| `src/Entity/Article.php` | Añadir grupos de serialización | 1 hora |

**Total refactoring:** ~11 horas

---

### ❌ ELIMINAR (Código Legacy/No Usado)

| Componente | Razón | Prioridad Eliminación |
|------------|-------|----------------------|
| `src/Entity/Concordance.php` | Reemplazado por JSON en Article | 🔴 INMEDIATO |
| Tabla `concordances` | Vacía, no usada | 🔴 INMEDIATO |
| `src/Entity/DocumentSection.php` | No se usa (0 registros) | 🟡 MVP |
| Tabla `document_sections` | Vacía, no usada | 🟡 MVP |
| `src/Controller/HomeController.php` | API no necesita HTML | 🔴 INMEDIATO |
| `templates/` (completo) | Frontend será Angular | 🔴 INMEDIATO |
| `assets/` (JS/CSS) | Frontend será Angular | 🔴 INMEDIATO |
| Bundles de frontend (Twig, Stimulus, Tailwind) | No necesarios en API | 🔴 INMEDIATO |

**Total a eliminar:** ~15 archivos (~2,000 LOC)

---

### 🚀 AÑADIR (Features Faltantes para API)

| Feature | Propósito | Prioridad | Esfuerzo |
|---------|-----------|-----------|----------|
| **JWT Authentication** | Login/Register con tokens | 🔴 CRÍTICO | 8 horas |
| **User Entity** | Usuarios con roles (FREE/PREMIUM/ENTERPRISE) | 🔴 CRÍTICO | 4 horas |
| **Subscription Entity** | Suscripciones y planes | 🔴 CRÍTICO | 6 horas |
| **CORS Configuration** | Permitir requests desde Angular | 🔴 CRÍTICO | 1 hora |
| **API Versioning** | Prefijo `/api/v1` | 🔴 CRÍTICO | 2 horas |
| **OpenAPI Documentation** | Swagger UI para endpoints | 🟡 ALTA | 4 horas |
| **DTOs + Validation** | Request/Response classes | 🔴 CRÍTICO | 8 horas |
| **Serialization Groups** | Control de campos en JSON | 🔴 CRÍTICO | 3 horas |
| **Rate Limiting** | Prevenir abuso | 🟡 ALTA | 2 horas |
| **Error Handling (RFC 7807)** | Respuestas de error estandarizadas | 🟡 ALTA | 3 horas |
| **Tests (PHPUnit)** | Unit + Integration + Functional | 🟡 ALTA | 16 horas |
| **Stripe Integration** | Pagos de suscripciones | 🔴 CRÍTICO | 12 horas |
| **PayPhone Integration** | Pagos locales Ecuador | 🟡 ALTA | 8 horas |

**Total desarrollo nuevo:** ~77 horas (~2 semanas para 1 dev senior)

---

### 📊 Resumen Ejecutivo

**Estado actual del proyecto:** ✅ **85% production-ready** como visualizador web

**Aprovechamiento para API:** ✅ **60% reutilizable** (entidades, repositorios, servicios)

**Deuda técnica:** 🟡 **Media** (código muerto, falta de tests, validación manual)

**Esfuerzo estimado para conversión completa a API:**
- Refactoring: ~11 horas
- Eliminación de código legacy: ~2 horas
- Desarrollo de features nuevas (Auth, Suscripciones, Pagos): ~77 horas
- Testing: ~16 horas
- **TOTAL: ~106 horas (~3 semanas para 1 dev senior)**

**Puntos fuertes del código actual:**
1. ✅ Arquitectura limpia con separación de capas
2. ✅ Principios SOLID aplicados correctamente
3. ✅ Repositorios con queries optimizadas
4. ✅ Auditoría implementada (ArticleHistory)
5. ✅ Symfony 7.3 + PHP 8.2 (stack moderno)
6. ✅ Doctrine ORM bien configurado
7. ✅ Código documentado con PHPDoc

**Puntos a mejorar:**
1. ❌ Eliminar código muerto (Concordance, DocumentSection)
2. ❌ Implementar tests (0% coverage)
3. ❌ Añadir autenticación/autorización
4. ❌ Migrar a DTOs + Symfony Validator
5. ❌ Configurar CORS y rate limiting
6. ❌ Documentar API con OpenAPI

---

## ✅ Conclusión

El repositorio actual es una **excelente base** para construir la API de LexEcuador. El código sigue buenas prácticas de Clean Architecture y SOLID, con una separación clara de responsabilidades.

**El 60% del código es directamente reutilizable**, especialmente las capas de dominio (entidades) e infraestructura (repositorios). La lógica de negocio en `ArticleService` solo necesita extenderse para manejar permisos por roles.

**Próximos pasos recomendados:**
1. Leer y aprobar este análisis
2. Revisar el archivo `02_ARQUITECTURA_API.md` (próximo)
3. Definir el MVP en `03_MVP_FEATURES.md`
4. Ejecutar sprints según planes detallados

**Riesgo de la conversión:** 🟢 **BAJO** - La arquitectura actual facilita la transición a API REST sin grandes refactorings.

---

**Archivo generado:** `01_ANALISIS_REPOSITORIO.md`
**Siguiente:** Esperar aprobación del usuario para generar `02_ARQUITECTURA_API.md`
