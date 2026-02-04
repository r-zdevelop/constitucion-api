# 08 - Especificación de Endpoints: Artículos Constitucionales

**Módulo:** Articles
**Base URL:** `/api/v1/articles`
**Autenticación:** Requerida (Bearer token)
**Versión:** 1.0.0

---

## 📋 Endpoints Disponibles

| Método | Endpoint | Descripción | Rol Mínimo |
|--------|----------|-------------|------------|
| GET | `/articles` | Listar artículos paginados | FREE |
| GET | `/articles/{id}` | Obtener artículo por ID | FREE |
| GET | `/articles/number/{number}` | Obtener artículo por número | FREE |
| GET | `/articles/search` | Buscar artículos por palabra clave | FREE |
| GET | `/articles/chapters` | Listar capítulos con conteo | FREE |

---

## 1. Listar Artículos

### GET `/api/v1/articles`

**Descripción:** Retorna lista paginada de artículos constitucionales.

**Autenticación:** Requerida (Bearer token)

**Control de Acceso:**
- Usuarios FREE: Artículos 1-100
- Usuarios PREMIUM+: Todos los artículos (1-467)

**Request Headers:**
```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

**Query Parameters:**
| Parámetro | Tipo | Requerido | Default | Descripción |
|-----------|------|-----------|---------|-------------|
| `page` | integer | No | 1 | Número de página |
| `limit` | integer | No | 20 | Artículos por página (max: 100) |

**Response Success (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "articleNumber": 1,
      "title": "Ecuador, un Estado constitucional",
      "content": "El Ecuador es un Estado constitucional de derechos y justicia...",
      "chapter": "Principios fundamentales",
      "status": "active"
    },
    {
      "id": 2,
      "articleNumber": 2,
      "title": null,
      "content": "Todos los ecuatorianos y ecuatorianas son ciudadanos...",
      "chapter": "Principios fundamentales",
      "status": "active"
    }
  ],
  "meta": {
    "total": 100,
    "page": 1,
    "limit": 20,
    "pages": 5
  }
}
```

**Response Body Schema - Article (list):**
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `id` | integer | ID único del artículo |
| `articleNumber` | integer | Número de artículo (1-467) |
| `title` | string\|null | Título del artículo |
| `content` | string | Contenido completo |
| `chapter` | string | Capítulo al que pertenece |
| `status` | string | Estado (active, inactive) |

**Meta Object:**
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `total` | integer | Total de artículos accesibles |
| `page` | integer | Página actual |
| `limit` | integer | Artículos por página |
| `pages` | integer | Total de páginas |

**Response Error (401 Unauthorized):**
```json
{
  "code": 401,
  "message": "JWT Token not found"
}
```

**Ejemplo cURL:**
```bash
curl -X GET "https://api.lexecuador.com/api/v1/articles?page=1&limit=20" \
  -H "Authorization: Bearer $TOKEN"
```

**Ejemplo JavaScript:**
```javascript
const token = localStorage.getItem('token');

const response = await fetch('https://api.lexecuador.com/api/v1/articles?page=1&limit=20', {
  headers: {
    'Authorization': `Bearer ${token}`,
  },
});

const { data, meta } = await response.json();
console.log(`Mostrando ${data.length} de ${meta.total} artículos`);
```

**Notas:**
- FREE users ven máximo 100 artículos (1-100)
- PREMIUM users ven todos los 467 artículos
- Default: 20 artículos por página
- Máximo: 100 artículos por página

---

## 2. Obtener Artículo por ID

### GET `/api/v1/articles/{id}`

**Descripción:** Retorna detalles completos de un artículo específico.

**Autenticación:** Requerida (Bearer token)

**Control de Acceso:**
- FREE: Solo artículos con número 1-100
- PREMIUM+: Todos los artículos
- Concordances: Solo visible para PREMIUM+

**Path Parameters:**
| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `id` | integer | ID del artículo |

**Request Headers:**
```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

**Response Success (200 OK) - Usuario FREE:**
```json
{
  "data": {
    "id": 1,
    "articleNumber": 1,
    "title": "Ecuador, un Estado constitucional",
    "content": "El Ecuador es un Estado constitucional de derechos y justicia, social, democrático, soberano, independiente, unitario, intercultural, plurinacional y laico...",
    "chapter": "Principios fundamentales",
    "notes": null,
    "status": "active",
    "createdAt": "2024-01-01T00:00:00+00:00",
    "updatedAt": "2024-01-01T00:00:00+00:00"
  }
}
```

**Response Success (200 OK) - Usuario PREMIUM (con concordances):**
```json
{
  "data": {
    "id": 1,
    "articleNumber": 1,
    "title": "Ecuador, un Estado constitucional",
    "content": "El Ecuador es un Estado constitucional de derechos y justicia...",
    "chapter": "Principios fundamentales",
    "notes": null,
    "status": "active",
    "createdAt": "2024-01-01T00:00:00+00:00",
    "updatedAt": "2024-01-01T00:00:00+00:00",
    "concordances": [
      {
        "referencedLaw": "Código Civil",
        "referencedArticles": [10, 20, 30]
      },
      {
        "referencedLaw": "Ley Orgánica de Participación Ciudadana",
        "referencedArticles": [5]
      }
    ]
  }
}
```

**Response Error (403 Forbidden) - Usuario FREE accediendo a artículo >100:**
```json
{
  "type": "https://api.lexecuador.com/problems/premium-required",
  "title": "Premium Access Required",
  "status": 403,
  "detail": "Article 150 requires a Premium subscription. Upgrade your plan to access all articles.",
  "upgradeUrl": "https://app.lexecuador.com/subscribe"
}
```

**Response Error (404 Not Found):**
```json
{
  "type": "https://api.lexecuador.com/problems/not-found",
  "title": "Article Not Found",
  "status": 404,
  "detail": "Article with ID 999 not found"
}
```

**Ejemplo cURL:**
```bash
# Obtener artículo 1 (FREE users pueden acceder)
curl -X GET "https://api.lexecuador.com/api/v1/articles/1" \
  -H "Authorization: Bearer $TOKEN"

# Obtener artículo 150 (requiere PREMIUM)
curl -X GET "https://api.lexecuador.com/api/v1/articles/150" \
  -H "Authorization: Bearer $PREMIUM_TOKEN"
```

**Ejemplo JavaScript:**
```javascript
async function getArticle(id) {
  const token = localStorage.getItem('token');

  try {
    const response = await fetch(`https://api.lexecuador.com/api/v1/articles/${id}`, {
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    });

    if (response.status === 403) {
      // Mostrar modal de upgrade a Premium
      showUpgradeModal();
      return null;
    }

    if (!response.ok) {
      throw new Error('Article not found');
    }

    const { data } = await response.json();
    return data;

  } catch (error) {
    console.error('Error fetching article:', error);
    return null;
  }
}
```

**Campos adicionales para PREMIUM:**
- `concordances`: Array de referencias legales

---

## 3. Obtener Artículo por Número

### GET `/api/v1/articles/number/{number}`

**Descripción:** Busca un artículo por su número (1-467).

**Autenticación:** Requerida (Bearer token)

**Control de Acceso:**
- FREE: Solo números 1-100
- PREMIUM+: Todos los números (1-467)

**Path Parameters:**
| Parámetro | Tipo | Rango | Descripción |
|-----------|------|-------|-------------|
| `number` | integer | 1-467 | Número de artículo |

**Request Headers:**
```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

**Response Success (200 OK):**
```json
{
  "data": {
    "id": 50,
    "articleNumber": 50,
    "title": "Derecho a la educación",
    "content": "El Estado garantizará a toda persona, en forma individual o colectiva...",
    "chapter": "Derechos",
    "notes": null,
    "status": "active",
    "createdAt": "2024-01-01T00:00:00+00:00",
    "updatedAt": "2024-01-01T00:00:00+00:00",
    "concordances": []
  }
}
```

**Response Error (400 Bad Request) - Número inválido:**
```json
{
  "type": "https://api.lexecuador.com/problems/validation-error",
  "title": "Validation Error",
  "status": 400,
  "detail": "Article number must be between 1 and 467, got 500"
}
```

**Response Error (403 Forbidden) - FREE user accediendo a número >100:**
```json
{
  "type": "https://api.lexecuador.com/problems/premium-required",
  "title": "Premium Access Required",
  "status": 403,
  "detail": "Article 150 requires a Premium subscription. Upgrade your plan to access all articles.",
  "upgradeUrl": "https://app.lexecuador.com/subscribe"
}
```

**Response Error (404 Not Found):**
```json
{
  "type": "https://api.lexecuador.com/problems/not-found",
  "title": "Article Not Found",
  "status": 404,
  "detail": "Article number 999 not found"
}
```

**Ejemplo cURL:**
```bash
# Buscar artículo 1
curl -X GET "https://api.lexecuador.com/api/v1/articles/number/1" \
  -H "Authorization: Bearer $TOKEN"

# Buscar artículo 200 (requiere PREMIUM)
curl -X GET "https://api.lexecuador.com/api/v1/articles/number/200" \
  -H "Authorization: Bearer $PREMIUM_TOKEN"
```

**Ejemplo JavaScript con validación:**
```javascript
async function getArticleByNumber(number) {
  // Validar número en frontend
  if (number < 1 || number > 467) {
    alert('El número de artículo debe estar entre 1 y 467');
    return null;
  }

  const token = localStorage.getItem('token');

  try {
    const response = await fetch(
      `https://api.lexecuador.com/api/v1/articles/number/${number}`,
      {
        headers: {
          'Authorization': `Bearer ${token}`,
        },
      }
    );

    if (response.status === 403) {
      // Usuario FREE intentando acceder a artículo premium
      const error = await response.json();
      showUpgradeModal(error.detail);
      return null;
    }

    if (!response.ok) {
      throw new Error('Article not found');
    }

    const { data } = await response.json();
    return data;

  } catch (error) {
    console.error('Error:', error);
    return null;
  }
}
```

**Notas:**
- Validación de rango: 1-467
- FREE users bloqueados en números >100
- Respuesta idéntica a `/articles/{id}` pero busca por número

---

## 4. Buscar Artículos

### GET `/api/v1/articles/search`

**Descripción:** Busca artículos por palabra clave en título y contenido.

**Autenticación:** Requerida (Bearer token)

**Control de Acceso:**
- FREE: Busca solo en artículos 1-100
- PREMIUM+: Busca en todos los artículos (1-467)

**Request Headers:**
```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

**Query Parameters:**
| Parámetro | Tipo | Requerido | Default | Validación | Descripción |
|-----------|------|-----------|---------|------------|-------------|
| `q` | string | Sí | - | Min 2 chars, max 200 chars | Término de búsqueda |
| `page` | integer | No | 1 | Min 1 | Número de página |
| `limit` | integer | No | 20 | Min 1, max 100 | Resultados por página |

**Response Success (200 OK):**
```json
{
  "data": [
    {
      "id": 10,
      "articleNumber": 10,
      "title": "Derechos fundamentales",
      "content": "Las personas, comunidades, pueblos, nacionalidades y colectivos son titulares y gozarán de los derechos...",
      "chapter": "Derechos",
      "status": "active"
    },
    {
      "id": 66,
      "articleNumber": 66,
      "title": null,
      "content": "Se reconoce y garantizará a las personas: ... 2. El derecho a una vida digna...",
      "chapter": "Derechos",
      "status": "active"
    }
  ],
  "meta": {
    "query": "derechos",
    "total": 45,
    "page": 1,
    "limit": 20,
    "pages": 3
  }
}
```

**Response Error (400 Bad Request) - Query muy corto:**
```json
{
  "type": "https://api.lexecuador.com/problems/validation-error",
  "title": "Validation Error",
  "status": 400,
  "detail": "Search query must be at least 2 characters"
}
```

**Response Error (422 Unprocessable Entity) - Query vacío:**
```json
{
  "type": "https://api.lexecuador.com/problems/validation-error",
  "title": "Validation Error",
  "status": 422,
  "detail": "Search query is required",
  "errors": {
    "q": ["Search query is required"]
  }
}
```

**Ejemplo cURL:**
```bash
# Buscar "derechos"
curl -X GET "https://api.lexecuador.com/api/v1/articles/search?q=derechos&page=1&limit=10" \
  -H "Authorization: Bearer $TOKEN"

# Buscar con espacios (URL encode)
curl -X GET "https://api.lexecuador.com/api/v1/articles/search?q=derechos%20humanos" \
  -H "Authorization: Bearer $TOKEN"
```

**Ejemplo JavaScript con debounce:**
```javascript
// Debounce para evitar requests excesivos
function debounce(func, wait) {
  let timeout;
  return function(...args) {
    clearTimeout(timeout);
    timeout = setTimeout(() => func.apply(this, args), wait);
  };
}

const searchArticles = debounce(async (query, page = 1) => {
  if (query.length < 2) {
    return;
  }

  const token = localStorage.getItem('token');

  try {
    const url = new URL('https://api.lexecuador.com/api/v1/articles/search');
    url.searchParams.set('q', query);
    url.searchParams.set('page', page);
    url.searchParams.set('limit', 20);

    const response = await fetch(url, {
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    });

    if (!response.ok) {
      throw new Error('Search failed');
    }

    const { data, meta } = await response.json();

    displaySearchResults(data);
    updatePagination(meta);

  } catch (error) {
    console.error('Search error:', error);
  }
}, 300); // Esperar 300ms después de que el usuario deje de escribir

// Uso en input
document.getElementById('searchInput').addEventListener('input', (e) => {
  searchArticles(e.target.value);
});
```

**Notas:**
- Búsqueda case-insensitive
- Busca en campos `title` y `content`
- Usa LIKE en SQL (no full-text search)
- FREE users: resultados limitados a artículos 1-100
- PREMIUM users: resultados de todos los artículos

---

## 5. Listar Capítulos

### GET `/api/v1/articles/chapters`

**Descripción:** Retorna lista de capítulos con conteo de artículos.

**Autenticación:** Requerida (Bearer token)

**Control de Acceso:**
- FREE: Capítulos con artículos 1-100
- PREMIUM+: Todos los capítulos

**Request Headers:**
```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

**Response Success (200 OK):**
```json
{
  "data": [
    {
      "name": "Principios fundamentales",
      "count": 9
    },
    {
      "name": "Derechos",
      "count": 130
    },
    {
      "name": "Garantías constitucionales",
      "count": 25
    },
    {
      "name": "Participación y organización del poder",
      "count": 156
    },
    {
      "name": "Régimen de desarrollo",
      "count": 50
    }
  ]
}
```

**Response Body Schema:**
| Campo | Tipo | Descripción |
|-------|------|-------------|
| `name` | string | Nombre del capítulo |
| `count` | integer | Cantidad de artículos en ese capítulo |

**Ejemplo cURL:**
```bash
curl -X GET "https://api.lexecuador.com/api/v1/articles/chapters" \
  -H "Authorization: Bearer $TOKEN"
```

**Ejemplo JavaScript - Generar navegación:**
```javascript
async function loadChapters() {
  const token = localStorage.getItem('token');

  const response = await fetch('https://api.lexecuador.com/api/v1/articles/chapters', {
    headers: {
      'Authorization': `Bearer ${token}`,
    },
  });

  const { data: chapters } = await response.json();

  // Generar lista de navegación
  const nav = document.getElementById('chapterNav');
  nav.innerHTML = chapters
    .map(chapter => `
      <li>
        <a href="#" data-chapter="${chapter.name}">
          ${chapter.name} (${chapter.count})
        </a>
      </li>
    `)
    .join('');

  // Añadir event listeners
  nav.querySelectorAll('a').forEach(link => {
    link.addEventListener('click', (e) => {
      e.preventDefault();
      const chapterName = e.target.dataset.chapter;
      loadArticlesByChapter(chapterName);
    });
  });
}

async function loadArticlesByChapter(chapter) {
  const token = localStorage.getItem('token');

  // Usar endpoint de listado con filtro de capítulo
  const response = await fetch(
    `https://api.lexecuador.com/api/v1/articles?chapter=${encodeURIComponent(chapter)}`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
      },
    }
  );

  const { data: articles } = await response.json();
  displayArticles(articles);
}
```

**Notas:**
- Capítulos ordenados según jerarquía constitucional (no alfabéticamente)
- FREE users solo ven capítulos con artículos en rango 1-100
- El conteo refleja solo artículos accesibles al usuario

---

## 🔐 Control de Acceso por Rol

### Matriz de Acceso

| Endpoint | FREE | PREMIUM | ENTERPRISE |
|----------|------|---------|------------|
| Listar artículos | 1-100 | 1-467 | 1-467 |
| Ver artículo | 1-100 | 1-467 | 1-467 |
| Buscar | 1-100 | 1-467 | 1-467 |
| Concordances | ❌ No | ✅ Sí | ✅ Sí |

### Campos Sensibles por Rol

**FREE users NO ven:**
- `concordances` (referencias legales)
- `notes` extendidas

**PREMIUM+ users ven:**
- Todos los campos
- Concordances completas

---

## 📊 Paginación

### Estructura

Todos los endpoints paginados retornan:

```json
{
  "data": [...],
  "meta": {
    "total": 467,
    "page": 1,
    "limit": 20,
    "pages": 24
  }
}
```

### Límites

- Default: 20 items por página
- Máximo: 100 items por página
- Mínimo: 1 item por página

### Navegación

```javascript
// Página siguiente
if (meta.page < meta.pages) {
  const nextPage = meta.page + 1;
  fetchArticles(nextPage);
}

// Página anterior
if (meta.page > 1) {
  const prevPage = meta.page - 1;
  fetchArticles(prevPage);
}

// Última página
const lastPage = meta.pages;

// Generar números de página
const pageNumbers = [];
for (let i = 1; i <= meta.pages; i++) {
  pageNumbers.push(i);
}
```

---

## 🚦 Rate Limiting

### Límites por Rol

| Rol | Requests/Día | Requests/Minuto |
|-----|--------------|-----------------|
| FREE | 100 | 10 |
| PREMIUM | 10,000 | 100 |
| ENTERPRISE | Ilimitado | 500 |

### Headers de Rate Limit

```http
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1640000000
```

### Response cuando se excede (429)

```json
{
  "type": "https://api.lexecuador.com/problems/rate-limit-exceeded",
  "title": "Rate Limit Exceeded",
  "status": 429,
  "detail": "Daily request limit reached for Free users. Upgrade to Premium for more requests.",
  "retryAfter": 3600
}
```

**Header adicional:**
```http
Retry-After: 3600
```

---

## 🧪 Testing

### Escenarios de Prueba

**1. Usuario FREE accede a artículo permitido:**
```bash
# Debe retornar 200 OK
curl -X GET "https://api.lexecuador.com/api/v1/articles/number/50" \
  -H "Authorization: Bearer $FREE_TOKEN"
```

**2. Usuario FREE accede a artículo premium:**
```bash
# Debe retornar 403 Forbidden
curl -X GET "https://api.lexecuador.com/api/v1/articles/number/150" \
  -H "Authorization: Bearer $FREE_TOKEN"
```

**3. Usuario PREMIUM accede a cualquier artículo:**
```bash
# Debe retornar 200 OK con concordances
curl -X GET "https://api.lexecuador.com/api/v1/articles/number/250" \
  -H "Authorization: Bearer $PREMIUM_TOKEN"
```

**4. Búsqueda con query corto:**
```bash
# Debe retornar 400 Bad Request
curl -X GET "https://api.lexecuador.com/api/v1/articles/search?q=a" \
  -H "Authorization: Bearer $TOKEN"
```

**5. Paginación:**
```bash
# Página 1
curl -X GET "https://api.lexecuador.com/api/v1/articles?page=1&limit=10" \
  -H "Authorization: Bearer $TOKEN"

# Página 2 (debe retornar artículos 11-20)
curl -X GET "https://api.lexecuador.com/api/v1/articles?page=2&limit=10" \
  -H "Authorization: Bearer $TOKEN"
```

---

## 📝 Notas de Implementación

### Optimizaciones Recomendadas

**1. Caché:**
```javascript
// Cachear lista de capítulos (no cambia frecuentemente)
const CACHE_KEY = 'chapters';
const CACHE_TTL = 3600000; // 1 hora

async function getChapters() {
  const cached = localStorage.getItem(CACHE_KEY);
  const cacheTime = localStorage.getItem(`${CACHE_KEY}_time`);

  if (cached && cacheTime && Date.now() - cacheTime < CACHE_TTL) {
    return JSON.parse(cached);
  }

  const response = await fetch('/api/v1/articles/chapters', {
    headers: { 'Authorization': `Bearer ${token}` },
  });

  const { data } = await response.json();

  localStorage.setItem(CACHE_KEY, JSON.stringify(data));
  localStorage.setItem(`${CACHE_KEY}_time`, Date.now());

  return data;
}
```

**2. Infinite Scroll:**
```javascript
let currentPage = 1;
let loading = false;
let hasMore = true;

window.addEventListener('scroll', async () => {
  if (loading || !hasMore) return;

  const scrollPosition = window.innerHeight + window.scrollY;
  const threshold = document.body.offsetHeight - 500;

  if (scrollPosition >= threshold) {
    loading = true;
    currentPage++;

    const { data, meta } = await loadArticles(currentPage);

    appendArticles(data);
    hasMore = meta.page < meta.pages;
    loading = false;
  }
});
```

**3. Prefetch:**
```javascript
// Prefetch siguiente página cuando el usuario está en la actual
async function prefetchNextPage(currentPage) {
  const nextPage = currentPage + 1;

  fetch(`/api/v1/articles?page=${nextPage}`, {
    headers: { 'Authorization': `Bearer ${token}` },
  });
}
```

---

**Archivo generado:** `08_ENDPOINTS_CONSTITUTION.md`
**Siguiente:** `09_ENDPOINTS_SUBSCRIPTIONS.md` (Endpoints de Suscripciones)
