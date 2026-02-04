# 03 - MVP Features (Minimum Viable Product)

**Proyecto:** LexEcuador API - SaaS LegalTech
**Objetivo MVP:** Generar primeros ingresos en 6-8 semanas
**Estrategia:** Enfoque en monetización rápida con features esenciales
**Target:** Abogados, estudiantes de derecho, empresas en Ecuador
**Fecha:** 2025-12-19

---

## 🎯 Filosofía del MVP

> "El MVP debe resolver UN problema específico MUY BIEN, no muchos problemas mediocremente"

**Problema a resolver:**
Abogados y estudiantes necesitan acceso rápido y confiable a la Constitución del Ecuador con búsqueda avanzada y acceso multiplataforma (web, mobile).

**Propuesta de valor:**
- ✅ Búsqueda instantánea de artículos
- ✅ Acceso desde cualquier dispositivo (API + Angular SPA)
- ✅ Contenido siempre actualizado
- ✅ Sin instalar apps, solo navegador
- ✅ Planes accesibles ($5-50/mes)

---

## ✅ Features INCLUIDAS en el MVP

### 1. Sistema de Autenticación y Autorización ⭐ CRÍTICO

**User Story:**
> Como usuario, quiero registrarme con email y contraseña para acceder a la plataforma y gestionar mi suscripción.

**Incluye:**

#### 1.1 Registro de Usuarios
- Registro con email + password
- Validación de email (formato, unicidad)
- Validación de password (min 8 chars, uppercase, lowercase, número)
- Creación automática de usuario con rol `ROLE_FREE`
- Generación de JWT token al registrarse
- Email de bienvenida (opcional en MVP)

**Endpoints:**
- `POST /api/v1/auth/register`

**Criterios de aceptación:**
- [ ] Usuario puede registrarse con email/password válidos
- [ ] Email duplicado retorna error 409 Conflict
- [ ] Password débil retorna error 400 con mensaje descriptivo
- [ ] Respuesta incluye JWT token válido
- [ ] Usuario tiene rol `ROLE_FREE` por defecto
- [ ] Password se almacena hasheado (bcrypt)

---

#### 1.2 Login de Usuarios
- Login con email + password
- Validación de credenciales
- Generación de JWT token (ttl: 1 hora)
- Generación de refresh token (ttl: 7 días)
- Rate limiting: 5 intentos fallidos = bloqueo temporal 15 min

**Endpoints:**
- `POST /api/v1/auth/login`
- `POST /api/v1/auth/refresh`

**Criterios de aceptación:**
- [ ] Usuario puede hacer login con credenciales correctas
- [ ] Credenciales incorrectas retornan error 401 Unauthorized
- [ ] Respuesta incluye `access_token` y `refresh_token`
- [ ] Access token expira en 1 hora
- [ ] Refresh token permite obtener nuevo access token
- [ ] Después de 5 intentos fallidos, usuario bloqueado 15 min

---

#### 1.3 Logout
- Invalidación de token (opcional: blacklist en Redis)
- Limpieza de sesión en cliente

**Endpoints:**
- `POST /api/v1/auth/logout`

**Criterios de aceptación:**
- [ ] Token se invalida correctamente
- [ ] Request posterior con token invalidado retorna 401

---

#### 1.4 Perfil de Usuario
- Ver datos del usuario autenticado
- Actualizar nombre
- Cambiar contraseña

**Endpoints:**
- `GET /api/v1/users/me`
- `PATCH /api/v1/users/me`
- `POST /api/v1/users/me/change-password`

**Criterios de aceptación:**
- [ ] Usuario autenticado puede ver su perfil
- [ ] Usuario puede actualizar su nombre
- [ ] Cambio de password requiere password actual
- [ ] Usuario NO autenticado recibe 401

---

### 2. Sistema de Roles y Permisos ⭐ CRÍTICO

**User Story:**
> Como administrador del sistema, quiero definir diferentes niveles de acceso para monetizar el contenido.

**Roles definidos:**

| Rol | Precio | Descripción | Acceso |
|-----|--------|-------------|--------|
| `ROLE_FREE` | $0/mes | Usuario gratuito | - Ver primeros 100 artículos<br>- Búsqueda básica<br>- 10 requests/día |
| `ROLE_PREMIUM` | $9.99/mes | Profesional individual | - Ver todos los 467 artículos<br>- Búsqueda avanzada<br>- Concordancias<br>- 1000 requests/día |
| `ROLE_ENTERPRISE` | $49.99/mes | Equipos y empresas | - Todo de Premium<br>- API key para integración<br>- Requests ilimitados<br>- Soporte prioritario |
| `ROLE_ADMIN` | N/A | Administrador | - Acceso total<br>- Gestión de usuarios<br>- Dashboard de métricas |

**Jerarquía de roles:**
```
ROLE_ADMIN > ROLE_ENTERPRISE > ROLE_PREMIUM > ROLE_FREE > ROLE_USER
```

**Criterios de aceptación:**
- [ ] Usuarios FREE solo ven artículos 1-100
- [ ] Usuarios PREMIUM ven todos los artículos
- [ ] Usuarios ENTERPRISE tienen API key personal
- [ ] Request a artículo 101+ con rol FREE retorna 403 Forbidden con mensaje "Upgrade to Premium"
- [ ] Jerarquía de roles funciona correctamente

---

### 3. Visualización de Artículos (Refactorizado) ⭐ CORE FEATURE

**User Story:**
> Como usuario, quiero consultar artículos de la Constitución de forma rápida y organizada.

**Incluye:**

#### 3.1 Listar Artículos
- Paginación (20 artículos por página)
- Ordenados por número de artículo
- Agrupación por capítulo (opcional)
- Total de artículos y páginas

**Endpoints:**
- `GET /api/v1/articles?page=1&limit=20`
- `GET /api/v1/articles?chapter=Derechos&page=1`

**Respuesta:**
```json
{
  "data": [
    {
      "id": 1,
      "articleNumber": 1,
      "title": "Ecuador, un Estado constitucional",
      "content": "El Ecuador es un Estado...",
      "chapter": "Principios fundamentales",
      "status": "active"
    }
  ],
  "meta": {
    "total": 467,
    "page": 1,
    "limit": 20,
    "pages": 24
  }
}
```

**Criterios de aceptación:**
- [ ] Usuario puede listar artículos paginados
- [ ] Parámetros `page` y `limit` funcionan correctamente
- [ ] Usuarios FREE solo reciben artículos 1-100
- [ ] Usuarios PREMIUM reciben todos los artículos
- [ ] Response incluye metadata de paginación

---

#### 3.2 Obtener Artículo por ID
- Ver artículo específico
- Incluye concordancias (solo PREMIUM+)
- Incluye capítulo y sección

**Endpoints:**
- `GET /api/v1/articles/{id}`
- `GET /api/v1/articles/number/{articleNumber}`

**Respuesta:**
```json
{
  "data": {
    "id": 1,
    "articleNumber": 1,
    "title": "Ecuador, un Estado constitucional",
    "content": "El Ecuador es un Estado constitucional...",
    "chapter": "Principios fundamentales",
    "status": "active",
    "concordances": [
      {
        "referencedLaw": "Código Civil",
        "referencedArticles": [10, 20, 30]
      }
    ],
    "createdAt": "2024-01-01T00:00:00Z",
    "updatedAt": "2024-01-01T00:00:00Z"
  }
}
```

**Criterios de aceptación:**
- [ ] Usuario puede obtener artículo por ID
- [ ] Usuario puede obtener artículo por número
- [ ] Artículo inexistente retorna 404
- [ ] Usuarios FREE NO ven concordancias
- [ ] Usuarios PREMIUM ven concordancias completas
- [ ] Artículo 101+ con rol FREE retorna 403

---

### 4. Búsqueda de Artículos ⭐ CORE FEATURE

**User Story:**
> Como abogado, quiero buscar artículos por palabra clave o número para encontrar información rápidamente.

**Incluye:**

#### 4.1 Búsqueda por Palabra Clave
- Busca en título + contenido
- Paginación
- Mínimo 2 caracteres
- Ordenado por relevancia (LIKE por ahora, FULLTEXT en v2)

**Endpoints:**
- `GET /api/v1/articles/search?q=derechos&page=1&limit=20`

**Respuesta:**
```json
{
  "data": [
    {
      "id": 10,
      "articleNumber": 10,
      "title": "Derechos fundamentales",
      "content": "Las personas, comunidades, pueblos...",
      "chapter": "Derechos",
      "matchedIn": "title"
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

**Criterios de aceptación:**
- [ ] Búsqueda requiere mínimo 2 caracteres
- [ ] Busca en título y contenido (OR condition)
- [ ] Usuarios FREE solo buscan en artículos 1-100
- [ ] Usuarios PREMIUM buscan en todos los artículos
- [ ] Query vacío retorna 400 Bad Request
- [ ] Response incluye metadata de búsqueda

---

#### 4.2 Búsqueda por Número de Artículo
- Búsqueda exacta por número
- Respuesta rápida (índice en DB)

**Endpoints:**
- `GET /api/v1/articles/number/{number}`

**Criterios de aceptación:**
- [ ] Usuario puede buscar artículo por número exacto
- [ ] Número inválido retorna 404
- [ ] Número fuera de rango FREE retorna 403 para usuarios FREE

---

### 5. Filtros por Capítulo ⭐ CORE FEATURE

**User Story:**
> Como usuario, quiero filtrar artículos por capítulo para navegar de forma organizada.

**Incluye:**

#### 5.1 Listar Capítulos
- Retorna todos los capítulos únicos
- Ordenados según jerarquía constitucional (no alfabético)

**Endpoints:**
- `GET /api/v1/articles/chapters`

**Respuesta:**
```json
{
  "data": [
    {
      "name": "Principios fundamentales",
      "articleCount": 9
    },
    {
      "name": "Derechos",
      "articleCount": 130
    },
    {
      "name": "Garantías constitucionales",
      "articleCount": 25
    }
  ]
}
```

**Criterios de aceptación:**
- [ ] Retorna lista de capítulos únicos
- [ ] Orden respeta jerarquía constitucional
- [ ] Incluye cantidad de artículos por capítulo
- [ ] Usuarios FREE ven solo capítulos con artículos 1-100

---

### 6. Sistema de Suscripciones ⭐ CRÍTICO

**User Story:**
> Como usuario FREE, quiero suscribirme a un plan PREMIUM para acceder a todos los artículos.

**Incluye:**

#### 6.1 Crear Suscripción
- Elegir plan (PREMIUM o ENTERPRISE)
- Procesar pago con Stripe o PayPhone
- Activar suscripción inmediatamente
- Actualizar rol del usuario
- Enviar email de confirmación

**Endpoints:**
- `POST /api/v1/subscriptions`

**Request:**
```json
{
  "plan": "PREMIUM",
  "paymentMethod": "stripe",
  "paymentToken": "tok_visa"
}
```

**Respuesta:**
```json
{
  "data": {
    "id": "sub_123456",
    "userId": "user_789",
    "plan": "PREMIUM",
    "status": "active",
    "currentPeriodStart": "2024-12-19T00:00:00Z",
    "currentPeriodEnd": "2025-01-19T00:00:00Z",
    "cancelAtPeriodEnd": false,
    "amount": 9.99,
    "currency": "USD"
  }
}
```

**Criterios de aceptación:**
- [ ] Usuario puede crear suscripción PREMIUM o ENTERPRISE
- [ ] Pago se procesa correctamente con Stripe
- [ ] Pago se procesa correctamente con PayPhone
- [ ] Rol del usuario se actualiza a `ROLE_PREMIUM` o `ROLE_ENTERPRISE`
- [ ] Suscripción se activa inmediatamente
- [ ] Usuario recibe email de confirmación
- [ ] Error en pago retorna 402 Payment Required con mensaje

---

#### 6.2 Ver Suscripción Actual
- Ver detalles de la suscripción activa
- Fecha de renovación
- Método de pago
- Historial de pagos

**Endpoints:**
- `GET /api/v1/subscriptions/current`

**Criterios de aceptación:**
- [ ] Usuario puede ver su suscripción activa
- [ ] Usuario sin suscripción recibe 404
- [ ] Response incluye fecha de próxima renovación

---

#### 6.3 Cancelar Suscripción
- Cancelar al final del período
- Mantener acceso hasta fin de período
- Cambiar rol a FREE al expirar

**Endpoints:**
- `POST /api/v1/subscriptions/cancel`

**Criterios de aceptación:**
- [ ] Usuario puede cancelar su suscripción
- [ ] Suscripción se marca como `cancelAtPeriodEnd: true`
- [ ] Usuario mantiene acceso hasta fin de período
- [ ] Al expirar, rol cambia a `ROLE_FREE`
- [ ] Usuario recibe email de confirmación de cancelación

---

### 7. Integración de Pagos ⭐ CRÍTICO

**User Story:**
> Como usuario, quiero pagar mi suscripción con tarjeta de crédito o métodos locales de Ecuador.

**Incluye:**

#### 7.1 Stripe (Internacional)
- Checkout con tarjetas de crédito/débito
- Webhooks para eventos (payment_succeeded, payment_failed, subscription_updated)
- Manejo de errores de pago
- Reintentos automáticos

**Endpoints:**
- `POST /api/v1/payments/stripe/checkout`
- `POST /api/v1/webhooks/stripe` (webhook listener)

**Criterios de aceptación:**
- [ ] Usuario puede pagar con tarjeta via Stripe
- [ ] Webhook de Stripe actualiza estado de suscripción
- [ ] Pago fallido envía email al usuario
- [ ] Suscripción se cancela después de 3 fallos de pago

---

#### 7.2 PayPhone (Ecuador)
- Checkout con métodos locales
- QR code para pago móvil
- Confirmación automática

**Endpoints:**
- `POST /api/v1/payments/payphone/checkout`
- `POST /api/v1/webhooks/payphone` (webhook listener)

**Criterios de aceptación:**
- [ ] Usuario puede pagar con PayPhone
- [ ] Webhook de PayPhone actualiza estado
- [ ] QR code se genera correctamente
- [ ] Pago confirmado activa suscripción

---

### 8. Rate Limiting ⭐ IMPORTANTE

**User Story:**
> Como administrador, quiero limitar el uso de la API para prevenir abuso y garantizar calidad de servicio.

**Límites por rol:**

| Rol | Requests/día | Requests/minuto |
|-----|--------------|-----------------|
| FREE | 100 | 10 |
| PREMIUM | 10,000 | 100 |
| ENTERPRISE | Ilimitado | 500 |

**Endpoints afectados:**
- Todos los endpoints de `/api/v1/articles/*`

**Respuesta cuando se excede:**
```json
{
  "type": "https://api.lexecuador.com/problems/rate-limit-exceeded",
  "title": "Rate Limit Exceeded",
  "status": 429,
  "detail": "You have exceeded your rate limit of 100 requests per day. Upgrade to Premium for more requests.",
  "retryAfter": 3600
}
```

**Criterios de aceptación:**
- [ ] Usuarios FREE tienen límite de 100 requests/día
- [ ] Usuarios PREMIUM tienen límite de 10,000 requests/día
- [ ] Usuarios ENTERPRISE no tienen límite
- [ ] Response 429 incluye header `Retry-After`
- [ ] Response incluye mensaje para upgrade a plan superior

---

### 9. Documentación de API (Swagger) ⭐ IMPORTANTE

**User Story:**
> Como desarrollador frontend, quiero documentación interactiva de la API para integrar fácilmente.

**Incluye:**
- Swagger UI en `/api/doc`
- OpenAPI 3.0 spec
- Todos los endpoints documentados
- Ejemplos de request/response
- Esquemas de validación
- Try-it-out funcional

**Endpoints:**
- `GET /api/doc` (Swagger UI)
- `GET /api/doc.json` (OpenAPI spec JSON)

**Criterios de aceptación:**
- [ ] Swagger UI accesible en `/api/doc`
- [ ] Todos los endpoints están documentados
- [ ] Cada endpoint tiene ejemplos de request/response
- [ ] Try-it-out funciona correctamente
- [ ] Autenticación JWT funciona en Swagger

---

### 10. Manejo de Errores (RFC 7807) ⭐ IMPORTANTE

**User Story:**
> Como desarrollador frontend, quiero errores consistentes y descriptivos para manejar casos edge.

**Formato de error estándar:**
```json
{
  "type": "https://api.lexecuador.com/problems/validation-error",
  "title": "Validation Error",
  "status": 400,
  "detail": "The request contains invalid data",
  "errors": {
    "email": ["Email is already registered"],
    "password": ["Password must contain at least one uppercase letter"]
  },
  "instance": "/api/v1/auth/register"
}
```

**Códigos de error:**
- `400` - Bad Request (validación fallida)
- `401` - Unauthorized (no autenticado)
- `403` - Forbidden (sin permisos, upgrade required)
- `404` - Not Found (recurso no encontrado)
- `409` - Conflict (email duplicado)
- `422` - Unprocessable Entity (lógica de negocio)
- `429` - Too Many Requests (rate limit)
- `500` - Internal Server Error (error del servidor)

**Criterios de aceptación:**
- [ ] Todos los errores siguen formato RFC 7807
- [ ] Errores de validación incluyen campo específico
- [ ] Errores incluyen URL de documentación en `type`
- [ ] Errores 500 NO exponen stack traces en producción
- [ ] Errores se loggean correctamente

---

## ❌ Features EXCLUIDAS del MVP (v2+)

### 1. IA y ChatGPT Integration ❌ v2

**Razón:** No es esencial para MVP, añade complejidad y costos

**Descripción:**
- Chatbot para consultas legales
- Resúmenes automáticos de artículos
- Análisis jurídico asistido por IA
- Búsqueda semántica

**Por qué NO incluir:**
- Requiere integración con OpenAI API (costo adicional)
- Necesita fine-tuning y validación legal
- No es diferenciador crítico en MVP
- Se puede añadir en v2 con feedback de usuarios

**Esfuerzo estimado:** 40-60 horas

---

### 2. Otras Leyes Ecuatorianas ❌ v2

**Razón:** Enfocarse en UN documento muy bien hecho

**Descripción:**
- Código Civil
- Código Penal
- Código del Trabajo
- Ley Orgánica de Salud
- Etc.

**Por qué NO incluir:**
- Aumenta complejidad de búsqueda
- Requiere importar y validar múltiples documentos
- Dificulta marketing (mensaje menos claro)
- Mejor dominar Constitución primero

**Esfuerzo estimado:** 20 horas por ley adicional

---

### 3. Análisis Jurídico Avanzado ❌ v2

**Razón:** Feature premium que requiere validación legal

**Descripción:**
- Comparación de artículos
- Análisis de cambios históricos
- Casos de jurisprudencia relacionados
- Comentarios doctrinarios

**Por qué NO incluir:**
- Requiere equipo legal para validar contenido
- Necesita base de datos de jurisprudencia
- Complejidad técnica alta
- Mejor como feature diferenciadora en v2

**Esfuerzo estimado:** 80-100 horas

---

### 4. Exportación a PDF ❌ v2

**Razón:** No crítico para MVP, fácil de añadir después

**Descripción:**
- Exportar artículos a PDF
- Exportar búsquedas a PDF
- Personalizar diseño de PDF

**Por qué NO incluir:**
- Requiere librería de generación de PDF
- Necesita diseño de templates
- Los usuarios pueden usar "Imprimir página" del navegador
- No bloquea monetización

**Esfuerzo estimado:** 8-12 horas

---

### 5. Comentarios y Anotaciones ❌ v2

**Razón:** Feature social que requiere moderación

**Descripción:**
- Usuarios pueden comentar artículos
- Anotar y destacar texto
- Compartir anotaciones públicamente

**Por qué NO incluir:**
- Requiere moderación de contenido
- Aumenta complejidad de BD
- Posible responsabilidad legal
- No es core para búsqueda legal

**Esfuerzo estimado:** 30-40 horas

---

### 6. Favoritos / Bookmarks ❌ v2

**Razón:** Nice-to-have, no esencial

**Descripción:**
- Guardar artículos favoritos
- Organizar en carpetas
- Compartir colecciones

**Por qué NO incluir:**
- Los usuarios pueden usar bookmarks del navegador
- No bloquea monetización
- Fácil de añadir después con feedback

**Esfuerzo estimado:** 6-8 horas

---

### 7. Historial de Búsquedas ❌ v2

**Razón:** Feature de UX, no crítica

**Descripción:**
- Ver búsquedas recientes
- Buscar en historial
- Exportar historial

**Por qué NO incluir:**
- Requiere tracking adicional
- Preocupaciones de privacidad
- No añade valor en MVP

**Esfuerzo estimado:** 6-8 horas

---

### 8. Notificaciones ❌ v3

**Razón:** Complejidad alta, valor bajo en MVP

**Descripción:**
- Email notifications
- Push notifications
- Notificaciones de cambios en artículos

**Por qué NO incluir:**
- Requiere servicio de email (SendGrid, etc.)
- Push notifications requiere PWA o mobile app
- Constitución cambia raramente
- No crítico para monetización

**Esfuerzo estimado:** 20-30 horas

---

### 9. Dashboard con Analytics ❌ v2

**Razón:** Útil pero no esencial para usuarios finales

**Descripción:**
- Estadísticas de uso personal
- Artículos más consultados
- Tiempo de lectura
- Gráficos de búsquedas

**Por qué NO incluir:**
- Requiere tracking complejo
- No bloquea funcionalidad core
- Mejor enfocarse en búsqueda

**Esfuerzo estimado:** 16-24 horas

---

### 10. Webhooks para Integraciones ❌ v3

**Razón:** Feature enterprise para v3

**Descripción:**
- Webhooks cuando cambian artículos
- Webhooks de eventos de usuario
- Integración con sistemas externos

**Por qué NO incluir:**
- Solo útil para clientes Enterprise
- Constitución cambia raramente
- Añade complejidad de seguridad
- Mejor validar demand primero

**Esfuerzo estimado:** 24-32 horas

---

### 11. Multi-idioma (i18n) ❌ v2

**Razón:** Mercado inicial es Ecuador (español)

**Descripción:**
- Interfaz en inglés
- Traducción de artículos

**Por qué NO incluir:**
- Constitución está en español
- Target inicial es Ecuador
- Traducción legal requiere profesionales
- MVP puede ser solo español

**Esfuerzo estimado:** 30-40 horas

---

### 12. Mobile Apps (iOS/Android) ❌ v3

**Razón:** Angular SPA + API cubre mobile web

**Descripción:**
- App nativa iOS
- App nativa Android
- PWA (Progressive Web App)

**Por qué NO incluir:**
- Angular SPA es responsive y funciona en mobile
- Apps nativas requieren $99/año (Apple) + Google Play
- Doble esfuerzo de desarrollo
- PWA puede ser v2

**Esfuerzo estimado:** 120-160 horas (ambas plataformas)

---

## 📊 Matriz de Priorización (Valor vs Esfuerzo)

```
ALTO VALOR
│
│  [Auth/JWT]        [Suscripciones]
│  [Roles]           [Pagos Stripe]
│  [Búsqueda]        [Pagos PayPhone]
│     ↑                    ↑
│     │ PRIORIDAD 1 (MVP)  │
│     │                    │
│  [Rate Limit]      [Swagger Docs]
│  [Error Handling]  [CORS]
│     ↑                    ↑
│     │ PRIORIDAD 2 (MVP)  │
├─────┼────────────────────┼─────────────────→ BAJO ESFUERZO
│     │                    │
│  [Favoritos]       [Exportar PDF]
│  [Historial]       [Comentarios]
│     ↓                    ↓
│     │ PRIORIDAD 3 (v2)   │
│     │                    │
│  [IA ChatGPT]      [Mobile Apps]
│  [Analytics]       [Multi-idioma]
│     ↓                    ↓
BAJO VALOR
```

**Leyenda:**
- 🔴 Prioridad 1 (MVP): Alto valor, bajo-medio esfuerzo → IMPLEMENTAR YA
- 🟡 Prioridad 2 (MVP): Medio valor, bajo esfuerzo → IMPLEMENTAR EN MVP
- 🟢 Prioridad 3 (v2): Alto valor, alto esfuerzo → POSTPONER
- ⚪ Prioridad 4 (v3+): Bajo valor → NO HACER

---

## 🎯 User Stories Completas del MVP

### Epic 1: Gestión de Usuarios

#### US-1.1: Registro de Usuario
**Como** visitante
**Quiero** registrarme con email y contraseña
**Para** acceder a la plataforma y comenzar a buscar artículos

**Criterios de aceptación:**
- [ ] Formulario de registro solicita: email, password, nombre
- [ ] Email debe ser único en el sistema
- [ ] Password debe tener min 8 chars, 1 mayúscula, 1 minúscula, 1 número
- [ ] Al registrarse, usuario recibe JWT token
- [ ] Rol por defecto es `ROLE_FREE`
- [ ] Email de bienvenida se envía (opcional en MVP)

**Prioridad:** 🔴 CRÍTICA
**Estimación:** 4 horas

---

#### US-1.2: Login de Usuario
**Como** usuario registrado
**Quiero** hacer login con mis credenciales
**Para** acceder a mi cuenta y suscripción

**Criterios de aceptación:**
- [ ] Usuario ingresa email y password
- [ ] Credenciales correctas retornan JWT token
- [ ] Credenciales incorrectas retornan error 401
- [ ] Después de 5 intentos fallidos, cuenta bloqueada 15 min
- [ ] Token expira en 1 hora
- [ ] Refresh token válido por 7 días

**Prioridad:** 🔴 CRÍTICA
**Estimación:** 3 horas

---

#### US-1.3: Ver Perfil
**Como** usuario autenticado
**Quiero** ver y editar mi perfil
**Para** mantener mis datos actualizados

**Criterios de aceptación:**
- [ ] Usuario ve: email, nombre, rol, fecha de registro
- [ ] Usuario puede editar: nombre
- [ ] Usuario puede cambiar contraseña (requiere password actual)
- [ ] Cambios se persisten correctamente

**Prioridad:** 🟡 MEDIA
**Estimación:** 2 horas

---

### Epic 2: Consulta de Artículos

#### US-2.1: Listar Artículos
**Como** usuario
**Quiero** ver un listado paginado de artículos
**Para** navegar por la Constitución

**Criterios de aceptación:**
- [ ] Lista muestra 20 artículos por página
- [ ] Artículos ordenados por número
- [ ] Usuarios FREE ven solo artículos 1-100
- [ ] Usuarios PREMIUM ven todos los 467 artículos
- [ ] Response incluye metadata de paginación

**Prioridad:** 🔴 CRÍTICA
**Estimación:** 3 horas

---

#### US-2.2: Ver Artículo Completo
**Como** usuario
**Quiero** ver el contenido completo de un artículo
**Para** leer su texto y referencias

**Criterios de aceptación:**
- [ ] Artículo muestra: número, título, contenido, capítulo
- [ ] Usuarios PREMIUM ven concordancias
- [ ] Usuarios FREE NO ven concordancias
- [ ] Artículo inexistente retorna 404
- [ ] Artículo fuera de rango FREE retorna 403 para usuario FREE

**Prioridad:** 🔴 CRÍTICA
**Estimación:** 2 horas

---

#### US-2.3: Buscar Artículos por Palabra Clave
**Como** usuario
**Quiero** buscar artículos por palabra clave
**Para** encontrar información específica rápidamente

**Criterios de aceptación:**
- [ ] Búsqueda requiere mínimo 2 caracteres
- [ ] Busca en título y contenido
- [ ] Resultados paginados (20 por página)
- [ ] Usuarios FREE buscan solo en artículos 1-100
- [ ] Usuarios PREMIUM buscan en todos los artículos
- [ ] Query vacío retorna 400

**Prioridad:** 🔴 CRÍTICA
**Estimación:** 4 horas

---

#### US-2.4: Buscar Artículo por Número
**Como** usuario
**Quiero** buscar un artículo por su número exacto
**Para** ir directamente al artículo que necesito

**Criterios de aceptación:**
- [ ] Input acepta número de artículo (1-467)
- [ ] Retorna artículo exacto
- [ ] Número inválido retorna 404
- [ ] Número fuera de rango FREE retorna 403 para usuario FREE

**Prioridad:** 🔴 CRÍTICA
**Estimación:** 2 horas

---

#### US-2.5: Filtrar por Capítulo
**Como** usuario
**Quiero** filtrar artículos por capítulo
**Para** navegar por temas específicos

**Criterios de aceptación:**
- [ ] Dropdown muestra todos los capítulos
- [ ] Capítulos ordenados según jerarquía constitucional
- [ ] Al seleccionar capítulo, muestra solo artículos de ese capítulo
- [ ] Paginación funciona con filtro activo

**Prioridad:** 🔴 CRÍTICA
**Estimación:** 3 horas

---

### Epic 3: Suscripciones y Pagos

#### US-3.1: Suscribirse a Plan PREMIUM
**Como** usuario FREE
**Quiero** suscribirme al plan PREMIUM por $9.99/mes
**Para** acceder a todos los artículos y concordancias

**Criterios de aceptación:**
- [ ] Usuario elige plan PREMIUM
- [ ] Usuario ingresa datos de tarjeta (Stripe)
- [ ] Pago se procesa correctamente
- [ ] Rol cambia a `ROLE_PREMIUM` inmediatamente
- [ ] Usuario recibe email de confirmación
- [ ] Error en pago muestra mensaje claro

**Prioridad:** 🔴 CRÍTICA
**Estimación:** 8 horas

---

#### US-3.2: Pagar con PayPhone (Ecuador)
**Como** usuario en Ecuador
**Quiero** pagar con métodos locales
**Para** evitar comisiones de tarjetas internacionales

**Criterios de aceptación:**
- [ ] Usuario elige PayPhone como método de pago
- [ ] Se genera QR code para pago
- [ ] Usuario escanea QR y confirma pago
- [ ] Webhook de PayPhone activa suscripción
- [ ] Rol cambia a `ROLE_PREMIUM`

**Prioridad:** 🔴 CRÍTICA
**Estimación:** 10 horas

---

#### US-3.3: Ver Suscripción Actual
**Como** usuario PREMIUM
**Quiero** ver detalles de mi suscripción
**Para** saber cuándo se renueva y cuánto pago

**Criterios de aceptación:**
- [ ] Usuario ve: plan, precio, fecha de inicio, fecha de renovación
- [ ] Usuario ve método de pago actual
- [ ] Usuario ve historial de pagos
- [ ] Usuario sin suscripción ve mensaje para suscribirse

**Prioridad:** 🟡 MEDIA
**Estimación:** 3 horas

---

#### US-3.4: Cancelar Suscripción
**Como** usuario PREMIUM
**Quiero** cancelar mi suscripción
**Para** dejar de pagar cuando no la necesite

**Criterios de aceptación:**
- [ ] Usuario puede cancelar desde perfil
- [ ] Suscripción se marca como `cancelAtPeriodEnd: true`
- [ ] Usuario mantiene acceso PREMIUM hasta fin de período
- [ ] Al expirar, rol cambia a `ROLE_FREE`
- [ ] Usuario recibe email de confirmación de cancelación

**Prioridad:** 🟡 MEDIA
**Estimación:** 4 horas

---

### Epic 4: Seguridad y Calidad

#### US-4.1: Rate Limiting
**Como** administrador
**Quiero** limitar requests por usuario
**Para** prevenir abuso de la API

**Criterios de aceptación:**
- [ ] FREE: 100 requests/día
- [ ] PREMIUM: 10,000 requests/día
- [ ] ENTERPRISE: ilimitado
- [ ] Al exceder límite, retorna 429 con mensaje
- [ ] Response incluye header `Retry-After`

**Prioridad:** 🟡 ALTA
**Estimación:** 4 horas

---

#### US-4.2: Documentación Interactiva
**Como** desarrollador frontend
**Quiero** documentación interactiva de la API
**Para** integrar fácilmente con Angular

**Criterios de aceptación:**
- [ ] Swagger UI accesible en `/api/doc`
- [ ] Todos los endpoints documentados
- [ ] Ejemplos de request/response
- [ ] Try-it-out funciona con JWT
- [ ] OpenAPI spec descargable en JSON

**Prioridad:** 🟡 ALTA
**Estimación:** 6 horas

---

## ⏱️ Estimación de Tiempo Total del MVP

### Resumen por Epic

| Epic | User Stories | Estimación |
|------|--------------|------------|
| **1. Gestión de Usuarios** | 3 | 9 horas |
| **2. Consulta de Artículos** | 5 | 14 horas |
| **3. Suscripciones y Pagos** | 4 | 25 horas |
| **4. Seguridad y Calidad** | 2 | 10 horas |
| **Infraestructura** | Config, migrations, tests | 20 horas |
| **Refactoring** | Clean Architecture migration | 12 horas |
| **Testing** | Unit + Integration + E2E | 16 horas |
| **Deployment** | Apache config, CI/CD | 8 horas |
| **Buffer (20%)** | Imprevistos | 23 horas |

**TOTAL:** ~137 horas (~3.5 semanas para 1 dev senior a tiempo completo)

---

### Desglose por Semana (Plan Agresivo)

**Semana 1-2: Infraestructura Base**
- Instalación de bundles
- Configuración JWT, CORS, Security
- Migraciones de BD
- Refactoring a Clean Architecture
- **Entregable:** API funcional con auth

**Semana 3-4: Core Features**
- Endpoints de artículos
- Búsqueda y filtros
- Sistema de roles
- Rate limiting
- **Entregable:** API completa sin pagos

**Semana 5-6: Monetización**
- Integración Stripe
- Integración PayPhone
- Sistema de suscripciones
- Webhooks
- **Entregable:** MVP completo

**Semana 7: Testing y Deploy**
- Tests E2E
- Documentación Swagger
- Deployment a producción
- Ajustes finales
- **Entregable:** MVP en producción

---

## ✅ Definición de "Done" para el MVP

Un feature está **DONE** cuando:
- [ ] Código implementado y funcional
- [ ] Tests unitarios escritos (cobertura >80%)
- [ ] Tests de integración pasando
- [ ] Documentado en Swagger
- [ ] Code review aprobado
- [ ] Sin bugs conocidos
- [ ] Desplegado en staging
- [ ] Aprobado por stakeholder

El MVP está **COMPLETO** cuando:
- [ ] Todas las user stories de Epic 1-4 están DONE
- [ ] Tests E2E pasando al 100%
- [ ] Documentación Swagger completa
- [ ] Desplegado en producción
- [ ] Al menos 10 usuarios beta testearon exitosamente
- [ ] Pagos funcionando en modo LIVE (no TEST)

---

## 🎯 Métricas de Éxito del MVP

**Objetivo:** Validar product-market fit en 3 meses

**Métricas clave:**

| Métrica | Target 1 mes | Target 3 meses |
|---------|-------------|----------------|
| Usuarios registrados | 50 | 500 |
| Conversión FREE → PREMIUM | 5% | 10% |
| MRR (Monthly Recurring Revenue) | $50 | $500 |
| Tasa de cancelación (churn) | <20% | <10% |
| NPS (Net Promoter Score) | >30 | >50 |
| Uptime de API | >99% | >99.9% |

**Si NO se alcanzan estas métricas:**
- Revisar pricing
- Mejorar onboarding
- Añadir features v2 (IA, PDF export)
- Pivotar a otro mercado

---

**Archivo generado:** `03_MVP_FEATURES.md`
**Siguiente:** Esperar aprobación para generar `04_PLAN_SPRINT_1.md`
