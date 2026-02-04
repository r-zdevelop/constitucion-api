# 10 - MODELO DE DATOS COMPLETO (MongoDB)

**Proyecto:** LexEcuador - API REST para Constitucion de Ecuador
**Proposito:** Especificacion completa del modelo de datos con colecciones MongoDB, esquemas, indices y migraciones
**Audiencia:** Desarrollador PHP 3+ anos con conocimiento de SOLID y Clean Architecture
**Base de datos:** MongoDB 7.0+

---

## INDICE

1. [Diagrama de Colecciones](#diagrama-de-colecciones)
2. [Colecciones del Dominio](#colecciones-del-dominio)
3. [Esquemas JSON](#esquemas-json)
4. [Indices y Rendimiento](#indices-y-rendimiento)
5. [Migraciones y Seeds](#migraciones-y-seeds)
6. [Relaciones entre Documentos](#relaciones-entre-documentos)

---

## DIAGRAMA DE COLECCIONES

```
+------------------------------------------+
|              MongoDB Database            |
|            "lexecuador_db"               |
+------------------------------------------+

+------------------------+     +------------------------+
|        users           |     |    subscriptions       |
+------------------------+     +------------------------+
| _id: ObjectId          |<--->| _id: ObjectId          |
| email: String (unique) |     | user_id: ObjectId (FK) |
| password: String       |     | plan: String           |
| name: String           |     | status: String         |
| role: String           |     | stripe_subscription_id |
| stripe_customer_id     |     | current_period_start   |
| is_active: Boolean     |     | current_period_end     |
| created_at: Date       |     | cancel_at: Date        |
| updated_at: Date       |     | created_at: Date       |
+------------------------+     +------------------------+
         |                              |
         | 1:N                          | 1:N
         v                              v
+------------------------+     +------------------------+
|       api_keys         |     |       payments         |
+------------------------+     +------------------------+
| _id: ObjectId          |     | _id: ObjectId          |
| user_id: ObjectId (FK) |     | subscription_id (FK)   |
| key: String (unique)   |     | amount: Decimal128     |
| name: String           |     | currency: String       |
| is_active: Boolean     |     | status: String         |
| last_used_at: Date     |     | stripe_payment_intent  |
| expires_at: Date       |     | paid_at: Date          |
| created_at: Date       |     | created_at: Date       |
+------------------------+     +------------------------+


+------------------------+     +------------------------+
|    legal_documents     |     |        titles          |
+------------------------+     +------------------------+
| _id: ObjectId          |     | _id: ObjectId          |
| name: String           |     | legal_document_id (FK) |
| year: Number           |     | number: Number         |
| last_modified: Date    |     | name: String           |
| total_articles: Number |     | description: String    |
| slug: String (unique)  |     | article_range: Object  |
| is_active: Boolean     |     | order: Number          |
| created_at: Date       |     | created_at: Date       |
+------------------------+     +------------------------+
         |                              |
         | 1:N                          | 1:N
         v                              v
+------------------------+     +------------------------+
|       chapters         |     |       articles         |
+------------------------+     +------------------------+
| _id: ObjectId          |     | _id: ObjectId          |
| legal_document_id (FK) |     | legal_document_id (FK) |
| title_id: ObjectId(FK) |     | title_id: ObjectId(FK) |
| number: Number         |     | chapter_id: ObjectId   |
| name: String           |     | number: Number (unique)|
| description: String    |     | content: String        |
| article_range: Object  |     | is_premium: Boolean    |
| order: Number          |     | concordances: [...]    |
| created_at: Date       |     | search_text: String    |
+------------------------+     | created_at: Date       |
                               | updated_at: Date       |
                               +------------------------+

+------------------------+     +------------------------+
|         laws           |     |      audit_logs        |
+------------------------+     +------------------------+
| _id: ObjectId          |     | _id: ObjectId          |
| name: String (unique)  |     | user_id: ObjectId      |
| abbreviation: String   |     | action: String         |
| category: String       |     | resource: String       |
| is_active: Boolean     |     | resource_id: ObjectId  |
| created_at: Date       |     | ip_address: String     |
+------------------------+     | user_agent: String     |
                               | metadata: Object       |
                               | created_at: Date       |
                               +------------------------+

+------------------------+
|      rate_limits       |
+------------------------+
| _id: ObjectId          |
| user_id: ObjectId      |
| endpoint: String       |
| requests_count: Number |
| window_start: Date     |
| window_end: Date       |
| created_at: Date       |
+------------------------+
```

---

## COLECCIONES DEL DOMINIO

### 1. users

Almacena los usuarios del sistema con autenticacion y roles.

```javascript
// Coleccion: users
{
  _id: ObjectId("..."),
  email: "usuario@ejemplo.com",           // String, unique, required
  password: "$2y$10$...",                 // String, bcrypt hash, required
  name: "Juan Perez",                     // String, required
  role: "ROLE_FREE",                      // Enum: ROLE_FREE, ROLE_PREMIUM, ROLE_ENTERPRISE, ROLE_ADMIN
  stripe_customer_id: "cus_abc123",       // String, nullable
  is_active: true,                        // Boolean, default: true
  email_verified_at: ISODate("..."),      // Date, nullable
  last_login_at: ISODate("..."),          // Date, nullable
  login_attempts: 0,                      // Number, default: 0
  locked_until: null,                     // Date, nullable (bloqueo por intentos fallidos)
  preferences: {                          // Embedded document
    theme: "light",
    language: "es",
    notifications_enabled: true
  },
  created_at: ISODate("2025-01-01T00:00:00Z"),
  updated_at: ISODate("2025-01-01T00:00:00Z")
}
```

**Indices:**
```javascript
db.users.createIndex({ "email": 1 }, { unique: true })
db.users.createIndex({ "stripe_customer_id": 1 }, { sparse: true })
db.users.createIndex({ "role": 1 })
db.users.createIndex({ "is_active": 1, "role": 1 })
db.users.createIndex({ "created_at": -1 })
```

---

### 2. subscriptions

Gestiona las suscripciones de pago de los usuarios.

```javascript
// Coleccion: subscriptions
{
  _id: ObjectId("..."),
  user_id: ObjectId("..."),               // Reference to users
  plan: "PREMIUM",                        // Enum: FREE, PREMIUM, ENTERPRISE
  status: "active",                       // Enum: active, canceled, past_due, unpaid, trialing
  payment_provider: "stripe",             // Enum: stripe, payphone
  stripe_subscription_id: "sub_abc123",   // String, nullable
  stripe_customer_id: "cus_abc123",       // String, nullable
  payphone_subscription_id: null,         // String, nullable
  price: {                                // Embedded document
    amount: NumberDecimal("9.99"),
    currency: "USD"
  },
  billing_cycle: "monthly",               // Enum: monthly, yearly
  started_at: ISODate("..."),
  current_period_start: ISODate("..."),
  current_period_end: ISODate("..."),
  trial_start: null,                      // Date, nullable
  trial_end: null,                        // Date, nullable
  cancel_at: null,                        // Date, nullable (cancelacion programada)
  canceled_at: null,                      // Date, nullable
  ended_at: null,                         // Date, nullable
  cancel_reason: null,                    // String, nullable
  metadata: {},                           // Object, flexible data
  created_at: ISODate("..."),
  updated_at: ISODate("...")
}
```

**Indices:**
```javascript
db.subscriptions.createIndex({ "user_id": 1 })
db.subscriptions.createIndex({ "user_id": 1, "status": 1 })
db.subscriptions.createIndex({ "stripe_subscription_id": 1 }, { sparse: true })
db.subscriptions.createIndex({ "status": 1 })
db.subscriptions.createIndex({ "current_period_end": 1 })  // Para jobs de renovacion
db.subscriptions.createIndex({ "cancel_at": 1 }, { sparse: true })
```

---

### 3. payments

Registra todos los pagos realizados en la plataforma.

```javascript
// Coleccion: payments
{
  _id: ObjectId("..."),
  subscription_id: ObjectId("..."),       // Reference to subscriptions
  user_id: ObjectId("..."),               // Reference to users (denormalized)
  amount: NumberDecimal("9.99"),
  currency: "USD",
  status: "succeeded",                    // Enum: pending, succeeded, failed, refunded, disputed
  payment_provider: "stripe",             // Enum: stripe, payphone
  stripe_payment_intent_id: "pi_abc123",  // String, nullable
  stripe_invoice_id: "in_abc123",         // String, nullable
  stripe_charge_id: "ch_abc123",          // String, nullable
  payphone_transaction_id: null,          // String, nullable
  payment_method: {                       // Embedded document
    type: "card",
    last_four: "4242",
    brand: "visa",
    exp_month: 12,
    exp_year: 2025
  },
  failure_reason: null,                   // String, nullable
  failure_code: null,                     // String, nullable
  refund_reason: null,                    // String, nullable
  refunded_amount: null,                  // Decimal128, nullable
  paid_at: ISODate("..."),               // Date, nullable
  failed_at: null,                        // Date, nullable
  refunded_at: null,                      // Date, nullable
  metadata: {},
  created_at: ISODate("...")
}
```

**Indices:**
```javascript
db.payments.createIndex({ "subscription_id": 1 })
db.payments.createIndex({ "user_id": 1, "created_at": -1 })
db.payments.createIndex({ "status": 1 })
db.payments.createIndex({ "stripe_payment_intent_id": 1 }, { sparse: true })
db.payments.createIndex({ "stripe_invoice_id": 1 }, { sparse: true })
db.payments.createIndex({ "created_at": -1 })
```

---

### 4. legal_documents

Almacena los documentos legales (Constitucion, Codigo Civil, etc.).

```javascript
// Coleccion: legal_documents
{
  _id: ObjectId("..."),
  name: "Constitucion de la Republica del Ecuador",
  short_name: "Constitucion",
  slug: "constitucion-ecuador",           // String, unique, URL-friendly
  year: 2008,
  country: "EC",                          // ISO 3166-1 alpha-2
  last_modified: ISODate("2021-01-25"),   // Ultima reforma
  extracted_at: ISODate("2025-11-19T14:03:44Z"),
  total_articles: 467,
  total_titles: 9,
  total_chapters: 40,
  version: "2021-01-25",                  // Version del documento
  source_url: "https://...",              // URL de origen
  is_active: true,
  is_premium_only: false,                 // Si todo el documento es premium
  free_article_limit: 100,                // Articulos gratuitos (1-100)
  metadata: {
    publisher: "Asamblea Nacional",
    language: "es",
    jurisdiction: "Nacional"
  },
  created_at: ISODate("..."),
  updated_at: ISODate("...")
}
```

**Indices:**
```javascript
db.legal_documents.createIndex({ "slug": 1 }, { unique: true })
db.legal_documents.createIndex({ "country": 1, "is_active": 1 })
db.legal_documents.createIndex({ "year": -1 })
```

---

### 5. titles (Titulos de la Constitucion)

Representa los titulos/secciones principales del documento legal.

```javascript
// Coleccion: titles
{
  _id: ObjectId("..."),
  legal_document_id: ObjectId("..."),     // Reference to legal_documents
  number: 1,                              // Numero romano: I, II, III...
  roman_numeral: "I",
  name: "ELEMENTOS CONSTITUTIVOS DEL ESTADO",
  description: "Define los principios fundamentales del Estado ecuatoriano",
  article_range: {
    start: 1,
    end: 9
  },
  order: 1,                               // Para ordenamiento
  created_at: ISODate("..."),
  updated_at: ISODate("...")
}
```

**Indices:**
```javascript
db.titles.createIndex({ "legal_document_id": 1, "number": 1 }, { unique: true })
db.titles.createIndex({ "legal_document_id": 1, "order": 1 })
```

---

### 6. chapters (Capitulos)

Representa los capitulos dentro de cada titulo.

```javascript
// Coleccion: chapters
{
  _id: ObjectId("..."),
  legal_document_id: ObjectId("..."),     // Reference to legal_documents
  title_id: ObjectId("..."),              // Reference to titles
  number: 1,
  name: "Principios fundamentales",
  description: null,
  article_range: {
    start: 1,
    end: 9
  },
  order: 1,
  created_at: ISODate("..."),
  updated_at: ISODate("...")
}
```

**Indices:**
```javascript
db.chapters.createIndex({ "legal_document_id": 1, "order": 1 })
db.chapters.createIndex({ "title_id": 1, "order": 1 })
db.chapters.createIndex({ "name": "text" })  // Para busqueda
```

---

### 7. articles (Articulos)

Almacena los articulos de la constitucion con concordancias embebidas.

```javascript
// Coleccion: articles
{
  _id: ObjectId("..."),
  legal_document_id: ObjectId("..."),     // Reference to legal_documents
  title_id: ObjectId("..."),              // Reference to titles
  chapter_id: ObjectId("..."),            // Reference to chapters
  number: 1,                              // Numero de articulo, unique por documento
  content: "El Ecuador es un Estado constitucional de derechos y justicia...",
  title_name: "ELEMENTOS CONSTITUTIVOS DEL ESTADO",  // Denormalized
  chapter_name: "Principios fundamentales",          // Denormalized
  is_premium: false,                      // true si number > 100
  is_active: true,

  // Concordancias embebidas (PREMIUM feature)
  concordances: [
    {
      law_id: ObjectId("..."),            // Reference to laws
      law_name: "CONSTITUCION DE LA REPUBLICA DEL ECUADOR",  // Denormalized
      articles: ["96", "227", "276", "317", "408"],
      type: "internal"                    // internal (misma ley) o external
    },
    {
      law_id: ObjectId("..."),
      law_name: "LEY DE MINERIA",
      articles: ["16"],
      type: "external"
    },
    {
      law_id: ObjectId("..."),
      law_name: "CODIGO CIVIL (LIBRO II)",
      articles: ["605", "606", "607", "609", "610"],
      type: "external"
    }
  ],

  // Campos para busqueda full-text
  search_text: "el ecuador es un estado constitucional de derechos y justicia social democratico soberano independiente unitario intercultural plurinacional laico...",

  // Keywords extraidas (para sugerencias de busqueda)
  keywords: ["estado", "constitucional", "derechos", "justicia", "soberania", "democracia"],

  // Estadisticas de uso (para analytics)
  stats: {
    views: 0,
    searches: 0,
    last_viewed_at: null
  },

  // Historial de cambios (para versioning)
  version: 1,
  previous_versions: [],                  // Array de versiones anteriores

  created_at: ISODate("..."),
  updated_at: ISODate("...")
}
```

**Indices:**
```javascript
// Indice unico por documento y numero
db.articles.createIndex(
  { "legal_document_id": 1, "number": 1 },
  { unique: true }
)

// Indices para busqueda
db.articles.createIndex({ "number": 1 })
db.articles.createIndex({ "chapter_id": 1, "number": 1 })
db.articles.createIndex({ "title_id": 1, "number": 1 })
db.articles.createIndex({ "is_premium": 1, "number": 1 })

// Indice de texto para busqueda full-text
db.articles.createIndex(
  {
    "content": "text",
    "title_name": "text",
    "chapter_name": "text",
    "keywords": "text"
  },
  {
    weights: {
      "title_name": 10,
      "chapter_name": 5,
      "keywords": 8,
      "content": 1
    },
    name: "articles_text_search",
    default_language: "spanish"
  }
)

// Indice para concordancias
db.articles.createIndex({ "concordances.law_name": 1 })
db.articles.createIndex({ "concordances.law_id": 1 })
```

---

### 8. laws (Catalogo de Leyes)

Catalogo de leyes referenciadas en las concordancias.

```javascript
// Coleccion: laws
{
  _id: ObjectId("..."),
  name: "CODIGO CIVIL (LIBRO II)",
  normalized_name: "codigo civil libro ii",  // Para matching
  abbreviation: "CC-L2",
  full_name: "Codigo Civil de la Republica del Ecuador - Libro II: De los bienes y de su dominio, posesion, uso, goce y limitaciones",
  category: "civil",                      // Enum: civil, penal, laboral, administrativo, constitucional, etc.
  country: "EC",
  year: null,                             // Ano de publicacion si se conoce
  is_internal: false,                     // true si es la constitucion
  is_active: true,
  article_count: 0,                       // Conteo de referencias
  source_url: null,
  metadata: {},
  created_at: ISODate("..."),
  updated_at: ISODate("...")
}
```

**Indices:**
```javascript
db.laws.createIndex({ "name": 1 }, { unique: true })
db.laws.createIndex({ "normalized_name": 1 })
db.laws.createIndex({ "category": 1 })
db.laws.createIndex({ "abbreviation": 1 }, { sparse: true })
```

---

### 9. api_keys (Claves API para Enterprise)

Claves API para usuarios enterprise con acceso programatico.

```javascript
// Coleccion: api_keys
{
  _id: ObjectId("..."),
  user_id: ObjectId("..."),               // Reference to users
  key: "lex_live_abc123def456...",        // String, 64 chars, unique
  key_hash: "$2y$10$...",                 // Hash del key para validacion
  name: "Production API Key",
  description: "Key para integracion con sistema interno",
  is_active: true,
  permissions: ["read:articles", "read:chapters"],  // Permisos especificos
  rate_limit: {
    requests_per_minute: 100,
    requests_per_day: 10000
  },
  allowed_ips: [],                        // Array de IPs permitidas (whitelist)
  allowed_origins: [],                    // Array de origenes CORS permitidos
  last_used_at: ISODate("..."),
  usage_count: 0,
  expires_at: null,                       // Date, nullable
  revoked_at: null,                       // Date, nullable
  revoke_reason: null,
  created_at: ISODate("..."),
  updated_at: ISODate("...")
}
```

**Indices:**
```javascript
db.api_keys.createIndex({ "key": 1 }, { unique: true })
db.api_keys.createIndex({ "key_hash": 1 })
db.api_keys.createIndex({ "user_id": 1 })
db.api_keys.createIndex({ "user_id": 1, "is_active": 1 })
db.api_keys.createIndex({ "expires_at": 1 }, { sparse: true })
```

---

### 10. rate_limits (Control de Rate Limiting)

Control de limites de peticiones por usuario.

```javascript
// Coleccion: rate_limits
{
  _id: ObjectId("..."),
  user_id: ObjectId("..."),               // Reference to users (o null para IP)
  ip_address: "192.168.1.1",              // Para usuarios no autenticados
  api_key_id: null,                       // Reference to api_keys (si aplica)
  endpoint: "/api/v1/articles",           // Endpoint o "*" para global
  window_type: "day",                     // Enum: minute, hour, day
  window_start: ISODate("..."),
  window_end: ISODate("..."),
  requests_count: 45,
  limit: 100,                             // Limite para este usuario/rol
  created_at: ISODate("..."),
  updated_at: ISODate("...")
}
```

**Indices:**
```javascript
// Indice compuesto para busqueda rapida
db.rate_limits.createIndex(
  { "user_id": 1, "endpoint": 1, "window_type": 1, "window_start": 1 },
  { unique: true }
)
db.rate_limits.createIndex({ "ip_address": 1, "window_start": 1 })
db.rate_limits.createIndex({ "window_end": 1 })  // Para cleanup con TTL

// TTL index para limpieza automatica (expira 1 dia despues de window_end)
db.rate_limits.createIndex(
  { "window_end": 1 },
  { expireAfterSeconds: 86400 }
)
```

---

### 11. audit_logs (Logs de Auditoria)

Registro de acciones importantes para auditoria y analytics.

```javascript
// Coleccion: audit_logs
{
  _id: ObjectId("..."),
  user_id: ObjectId("..."),               // Reference to users (nullable para anonimos)
  action: "article.view",                 // Enum de acciones
  resource_type: "article",               // Tipo de recurso
  resource_id: ObjectId("..."),           // ID del recurso afectado
  ip_address: "192.168.1.1",
  user_agent: "Mozilla/5.0...",
  request_method: "GET",
  request_path: "/api/v1/articles/42",
  response_status: 200,
  duration_ms: 45,                        // Tiempo de respuesta
  metadata: {
    article_number: 42,
    search_query: null,
    role_at_time: "ROLE_FREE"
  },
  created_at: ISODate("...")
}
```

**Indices:**
```javascript
db.audit_logs.createIndex({ "user_id": 1, "created_at": -1 })
db.audit_logs.createIndex({ "action": 1, "created_at": -1 })
db.audit_logs.createIndex({ "resource_type": 1, "resource_id": 1 })
db.audit_logs.createIndex({ "created_at": -1 })

// TTL index para limpieza automatica (90 dias)
db.audit_logs.createIndex(
  { "created_at": 1 },
  { expireAfterSeconds: 7776000 }  // 90 dias
)
```

---

### 12. search_history (Historial de Busquedas - Opcional)

Historial de busquedas para sugerencias y analytics.

```javascript
// Coleccion: search_history
{
  _id: ObjectId("..."),
  user_id: ObjectId("..."),               // nullable para anonimos
  session_id: "sess_abc123",
  query: "derechos humanos",
  normalized_query: "derechos humanos",   // Lowercase, trimmed
  filters: {
    chapter: "Derechos",
    article_range: { start: 1, end: 100 }
  },
  results_count: 45,
  clicked_results: [
    { article_id: ObjectId("..."), position: 1 },
    { article_id: ObjectId("..."), position: 3 }
  ],
  created_at: ISODate("...")
}
```

**Indices:**
```javascript
db.search_history.createIndex({ "user_id": 1, "created_at": -1 })
db.search_history.createIndex({ "normalized_query": 1 })
db.search_history.createIndex({ "created_at": -1 })

// TTL para limpieza (30 dias)
db.search_history.createIndex(
  { "created_at": 1 },
  { expireAfterSeconds: 2592000 }
)
```

---

## ESQUEMAS JSON (Validacion MongoDB)

### Schema Validation para users

```javascript
db.createCollection("users", {
  validator: {
    $jsonSchema: {
      bsonType: "object",
      required: ["email", "password", "name", "role", "is_active", "created_at"],
      properties: {
        email: {
          bsonType: "string",
          pattern: "^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,}$",
          description: "Email valido requerido"
        },
        password: {
          bsonType: "string",
          minLength: 60,
          description: "Password hash requerido"
        },
        name: {
          bsonType: "string",
          minLength: 2,
          maxLength: 100
        },
        role: {
          enum: ["ROLE_FREE", "ROLE_PREMIUM", "ROLE_ENTERPRISE", "ROLE_ADMIN"]
        },
        is_active: {
          bsonType: "bool"
        },
        login_attempts: {
          bsonType: "int",
          minimum: 0,
          maximum: 10
        }
      }
    }
  }
})
```

### Schema Validation para articles

```javascript
db.createCollection("articles", {
  validator: {
    $jsonSchema: {
      bsonType: "object",
      required: ["legal_document_id", "number", "content", "is_premium", "created_at"],
      properties: {
        number: {
          bsonType: "int",
          minimum: 1,
          maximum: 500,
          description: "Numero de articulo valido"
        },
        content: {
          bsonType: "string",
          minLength: 10,
          description: "Contenido del articulo"
        },
        is_premium: {
          bsonType: "bool"
        },
        concordances: {
          bsonType: "array",
          items: {
            bsonType: "object",
            required: ["law_name", "articles"],
            properties: {
              law_name: { bsonType: "string" },
              articles: {
                bsonType: "array",
                items: { bsonType: "string" }
              }
            }
          }
        }
      }
    }
  }
})
```

---

## INDICES Y RENDIMIENTO

### Resumen de Indices por Coleccion

| Coleccion | Indices | Proposito |
|-----------|---------|-----------|
| users | 5 | Busqueda por email, role, autenticacion |
| subscriptions | 6 | Estado de suscripcion, renovaciones |
| payments | 6 | Historial de pagos, reconciliacion |
| articles | 7 | Busqueda full-text, filtros por capitulo |
| chapters | 3 | Navegacion por estructura |
| titles | 2 | Navegacion por estructura |
| laws | 4 | Catalogo de leyes referenciadas |
| api_keys | 5 | Autenticacion API Enterprise |
| rate_limits | 4 | Control de limites con TTL |
| audit_logs | 5 | Auditoria con TTL 90 dias |

### Estrategias de Indexacion

```javascript
// 1. Indices compuestos para queries frecuentes
db.articles.createIndex({ "legal_document_id": 1, "is_premium": 1, "number": 1 })

// 2. Partial indexes para optimizar espacio
db.subscriptions.createIndex(
  { "status": 1, "current_period_end": 1 },
  { partialFilterExpression: { status: "active" } }
)

// 3. Covered queries para maxima velocidad
// Query: db.articles.find({ number: 42 }, { number: 1, title_name: 1, is_premium: 1 })
db.articles.createIndex({ "number": 1, "title_name": 1, "is_premium": 1 })
```

---

## MIGRACIONES Y SEEDS

### Script de Creacion de Base de Datos

```javascript
// migrations/001_create_database.js
use lexecuador_db;

// Crear colecciones con validacion
db.createCollection("users");
db.createCollection("subscriptions");
db.createCollection("payments");
db.createCollection("legal_documents");
db.createCollection("titles");
db.createCollection("chapters");
db.createCollection("articles");
db.createCollection("laws");
db.createCollection("api_keys");
db.createCollection("rate_limits");
db.createCollection("audit_logs");

print("Base de datos creada exitosamente");
```

### Script de Creacion de Indices

```javascript
// migrations/002_create_indexes.js
use lexecuador_db;

// Users indexes
db.users.createIndex({ "email": 1 }, { unique: true });
db.users.createIndex({ "stripe_customer_id": 1 }, { sparse: true });
db.users.createIndex({ "role": 1 });
db.users.createIndex({ "is_active": 1, "role": 1 });

// Articles indexes con text search
db.articles.createIndex(
  { "content": "text", "title_name": "text", "chapter_name": "text" },
  { weights: { "title_name": 10, "chapter_name": 5, "content": 1 }, default_language: "spanish" }
);
db.articles.createIndex({ "legal_document_id": 1, "number": 1 }, { unique: true });
db.articles.createIndex({ "chapter_id": 1, "number": 1 });
db.articles.createIndex({ "is_premium": 1, "number": 1 });

// ... resto de indices

print("Indices creados exitosamente");
```

### Script de Seed de Datos Iniciales

```javascript
// migrations/003_seed_initial_data.js
use lexecuador_db;

// 1. Crear documento legal (Constitucion)
const constitucionId = db.legal_documents.insertOne({
  name: "Constitucion de la Republica del Ecuador",
  short_name: "Constitucion",
  slug: "constitucion-ecuador",
  year: 2008,
  country: "EC",
  last_modified: ISODate("2021-01-25"),
  total_articles: 467,
  free_article_limit: 100,
  is_active: true,
  created_at: new Date(),
  updated_at: new Date()
}).insertedId;

// 2. Crear usuario admin
db.users.insertOne({
  email: "admin@lexecuador.com",
  password: "$2y$10$...",  // bcrypt hash de "Admin123!"
  name: "Administrador",
  role: "ROLE_ADMIN",
  is_active: true,
  created_at: new Date(),
  updated_at: new Date()
});

// 3. Crear leyes del catalogo (desde concordancias)
const laws = [
  { name: "CONSTITUCION DE LA REPUBLICA DEL ECUADOR", abbreviation: "CRE", category: "constitucional", is_internal: true },
  { name: "LEY DE MINERIA", abbreviation: "LM", category: "administrativo", is_internal: false },
  { name: "CODIGO CIVIL (LIBRO I)", abbreviation: "CC-L1", category: "civil", is_internal: false },
  { name: "CODIGO CIVIL (LIBRO II)", abbreviation: "CC-L2", category: "civil", is_internal: false },
  { name: "CODIGO ORGÁNICO INTEGRAL PENAL, COIP", abbreviation: "COIP", category: "penal", is_internal: false },
  { name: "CODIGO ORGANICO GENERAL DE PROCESOS, COGEP", abbreviation: "COGEP", category: "procesal", is_internal: false },
  { name: "LEY ORGANICA DE SALUD", abbreviation: "LOS", category: "salud", is_internal: false },
  { name: "LEY ORGANICA DE EDUCACION INTERCULTURAL", abbreviation: "LOEI", category: "educacion", is_internal: false },
  { name: "LEY ORGANICA DE LA DEFENSA NACIONAL", abbreviation: "LODN", category: "defensa", is_internal: false },
  { name: "LEY ORGANICA DE MOVILIDAD HUMANA", abbreviation: "LOMH", category: "migracion", is_internal: false },
  { name: "CODIGO DE DERECHO INTERNACIONAL PRIVADO SANCHEZ DE BUSTAMANTE", abbreviation: "CDIP", category: "internacional", is_internal: false }
];

laws.forEach(law => {
  db.laws.insertOne({
    ...law,
    normalized_name: law.name.toLowerCase(),
    is_active: true,
    article_count: 0,
    created_at: new Date(),
    updated_at: new Date()
  });
});

print("Datos iniciales insertados");
```

### Script de Importacion de Articulos

```javascript
// migrations/004_import_articles.js
// Este script se ejecutaria con los datos del JSON

// Ejemplo de estructura para importar un articulo
function importArticle(articleData, constitucionId, chapterMap, lawMap) {
  const isPremium = articleData.number > 100;

  // Procesar concordancias
  const concordances = (articleData.concordancias || []).map(conc => ({
    law_id: lawMap[conc.law] || null,
    law_name: conc.law,
    articles: conc.articles,
    type: conc.law.includes("CONSTITUCION") ? "internal" : "external"
  }));

  return {
    legal_document_id: constitucionId,
    title_id: chapterMap[articleData.title]?.title_id || null,
    chapter_id: chapterMap[articleData.chapter]?._id || null,
    number: articleData.number,
    content: articleData.content,
    title_name: articleData.title,
    chapter_name: articleData.chapter,
    is_premium: isPremium,
    is_active: true,
    concordances: concordances,
    search_text: articleData.content.toLowerCase(),
    keywords: extractKeywords(articleData.content),
    version: 1,
    stats: { views: 0, searches: 0 },
    created_at: new Date(),
    updated_at: new Date()
  };
}
```

---

## RELACIONES ENTRE DOCUMENTOS

### Diagrama de Relaciones

```
users (1) -----> (N) subscriptions
users (1) -----> (N) api_keys
users (1) -----> (N) audit_logs
users (1) -----> (N) rate_limits

subscriptions (1) -----> (N) payments

legal_documents (1) -----> (N) titles
legal_documents (1) -----> (N) chapters
legal_documents (1) -----> (N) articles

titles (1) -----> (N) chapters
titles (1) -----> (N) articles

chapters (1) -----> (N) articles

laws (1) -----> (N) articles.concordances (embedded)
```

### Estrategia de Referencias vs Embedding

| Relacion | Estrategia | Justificacion |
|----------|------------|---------------|
| User -> Subscription | Referencia | Subscripciones cambian frecuentemente |
| Article -> Concordances | Embedded | Concordancias se leen siempre con articulo |
| Article -> Chapter | Referencia + Denormalized | Necesitamos el nombre pero tambien queries por chapter |
| Payment -> PaymentMethod | Embedded | Datos historicos que no cambian |
| User -> Preferences | Embedded | Datos pequenos, siempre se leen juntos |

---

## CHECKLIST DE IMPLEMENTACION

- [ ] Crear base de datos MongoDB `lexecuador_db`
- [ ] Crear todas las colecciones con schema validation
- [ ] Crear todos los indices
- [ ] Configurar TTL indexes para rate_limits y audit_logs
- [ ] Importar datos de constitucion.json
- [ ] Crear catalogo de leyes desde concordancias
- [ ] Crear usuario admin inicial
- [ ] Verificar indices con `db.collection.getIndexes()`
- [ ] Probar queries de busqueda full-text
- [ ] Configurar replica set (produccion)
- [ ] Configurar backups automaticos

---

**Archivo generado:** `10_MODELO_DATOS.md`
**Base de datos:** MongoDB 7.0+
**Total colecciones:** 12
**Siguiente:** Actualizar arquitectura para usar MongoDB ODM (Doctrine MongoDB o similar)
