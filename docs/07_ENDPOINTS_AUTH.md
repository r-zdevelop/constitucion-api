# 07 - Especificación de Endpoints: Autenticación

**Módulo:** Authentication
**Base URL:** `/api/v1/auth`
**Autenticación:** No requerida (excepto `/me`)
**Versión:** 1.0.0

---

## 📋 Endpoints Disponibles

| Método | Endpoint | Descripción | Auth |
|--------|----------|-------------|------|
| POST | `/auth/register` | Registrar nuevo usuario | No |
| POST | `/auth/login` | Iniciar sesión | No |
| POST | `/auth/refresh` | Renovar token JWT | No |
| GET | `/auth/me` | Obtener perfil del usuario autenticado | Sí |
| PATCH | `/auth/me` | Actualizar perfil | Sí |
| POST | `/auth/me/change-password` | Cambiar contraseña | Sí |
| POST | `/auth/logout` | Cerrar sesión | Sí |

---

## 1. Registrar Usuario

### POST `/api/v1/auth/register`

**Descripción:** Crea una nueva cuenta de usuario con rol FREE por defecto.

**Autenticación:** No requerida

**Request Headers:**
```http
Content-Type: application/json
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "SecurePass123!",
  "name": "John Doe"
}
```

**Request Body Schema:**
| Campo | Tipo | Requerido | Validación | Descripción |
|-------|------|-----------|------------|-------------|
| `email` | string | Sí | Email válido, único | Email del usuario |
| `password` | string | Sí | Min 8 chars, 1 mayúscula, 1 minúscula, 1 número | Contraseña |
| `name` | string | Sí | Min 2 chars, max 100 chars | Nombre completo |

**Response Success (201 Created):**
```json
{
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "user@example.com",
    "name": "John Doe",
    "role": "ROLE_FREE",
    "isActive": true,
    "createdAt": "2025-12-19T10:00:00+00:00"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE2MzY0...",
  "refreshToken": "def50200abc123..."
}
```

**Response Error (400 Bad Request) - Validación:**
```json
{
  "type": "https://api.lexecuador.com/problems/validation-error",
  "title": "Validation Error",
  "status": 400,
  "detail": "The request contains invalid data",
  "errors": {
    "email": ["Email is required", "Invalid email format"],
    "password": ["Password must contain at least one uppercase letter"]
  }
}
```

**Response Error (409 Conflict) - Email duplicado:**
```json
{
  "type": "https://api.lexecuador.com/problems/duplicate-email",
  "title": "Duplicate Email",
  "status": 409,
  "detail": "Email \"user@example.com\" is already registered"
}
```

**Ejemplo cURL:**
```bash
curl -X POST https://api.lexecuador.com/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "SecurePass123!",
    "name": "John Doe"
  }'
```

**Ejemplo JavaScript (fetch):**
```javascript
const response = await fetch('https://api.lexecuador.com/api/v1/auth/register', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'SecurePass123!',
    name: 'John Doe',
  }),
});

const data = await response.json();
if (response.ok) {
  // Guardar token en localStorage
  localStorage.setItem('token', data.token);
  localStorage.setItem('refreshToken', data.refreshToken);
}
```

**Notas:**
- El token JWT expira en 1 hora
- El refreshToken expira en 7 días
- La contraseña se hashea con bcrypt (cost: 12)
- El email se normaliza a minúsculas
- El rol por defecto es `ROLE_FREE`

---

## 2. Iniciar Sesión

### POST `/api/v1/auth/login`

**Descripción:** Autentica un usuario y retorna tokens JWT.

**Autenticación:** No requerida

**Request Headers:**
```http
Content-Type: application/json
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "SecurePass123!"
}
```

**Request Body Schema:**
| Campo | Tipo | Requerido | Descripción |
|-------|------|-----------|-------------|
| `email` | string | Sí | Email del usuario |
| `password` | string | Sí | Contraseña |

**Response Success (200 OK):**
```json
{
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "user@example.com",
    "name": "John Doe",
    "role": "ROLE_PREMIUM",
    "isActive": true,
    "createdAt": "2025-12-19T10:00:00+00:00"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "refreshToken": "def50200abc123..."
}
```

**Response Error (401 Unauthorized) - Credenciales inválidas:**
```json
{
  "type": "https://api.lexecuador.com/problems/invalid-credentials",
  "title": "Invalid Credentials",
  "status": 401,
  "detail": "Invalid credentials"
}
```

**Response Error (401 Unauthorized) - Cuenta desactivada:**
```json
{
  "type": "https://api.lexecuador.com/problems/account-disabled",
  "title": "Account Disabled",
  "status": 401,
  "detail": "Account is deactivated"
}
```

**Response Error (429 Too Many Requests) - Rate limit:**
```json
{
  "type": "https://api.lexecuador.com/problems/rate-limit-exceeded",
  "title": "Rate Limit Exceeded",
  "status": 429,
  "detail": "Too many login attempts. Please try again in 15 minutes.",
  "retryAfter": 900
}
```

**Ejemplo cURL:**
```bash
curl -X POST https://api.lexecuador.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "SecurePass123!"
  }'
```

**Ejemplo JavaScript:**
```javascript
const response = await fetch('https://api.lexecuador.com/api/v1/auth/login', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'SecurePass123!',
  }),
});

const data = await response.json();
if (response.ok) {
  localStorage.setItem('token', data.token);
  localStorage.setItem('refreshToken', data.refreshToken);
}
```

**Rate Limiting:**
- Máximo 5 intentos fallidos por IP en 15 minutos
- Después del 5to intento, bloqueo de 15 minutos
- El contador se resetea después de un login exitoso

**Notas:**
- El mensaje de error es genérico por seguridad ("Invalid credentials")
- No se revela si el email existe o si la contraseña es incorrecta
- Los tokens se incluyen en la respuesta

---

## 3. Renovar Token

### POST `/api/v1/auth/refresh`

**Descripción:** Obtiene un nuevo access token usando el refresh token.

**Autenticación:** No requerida (usa refresh token)

**Request Headers:**
```http
Content-Type: application/json
```

**Request Body:**
```json
{
  "refreshToken": "def50200abc123..."
}
```

**Response Success (200 OK):**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "refreshToken": "def50200xyz789..."
}
```

**Response Error (401 Unauthorized) - Token inválido:**
```json
{
  "type": "https://api.lexecuador.com/problems/invalid-token",
  "title": "Invalid Token",
  "status": 401,
  "detail": "Invalid or expired refresh token"
}
```

**Ejemplo cURL:**
```bash
curl -X POST https://api.lexecuador.com/api/v1/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{
    "refreshToken": "def50200abc123..."
  }'
```

**Ejemplo JavaScript:**
```javascript
const refreshToken = localStorage.getItem('refreshToken');

const response = await fetch('https://api.lexecuador.com/api/v1/auth/refresh', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({ refreshToken }),
});

const data = await response.json();
if (response.ok) {
  localStorage.setItem('token', data.token);
  localStorage.setItem('refreshToken', data.refreshToken);
}
```

**Notas:**
- El refresh token se rota (se genera uno nuevo)
- El viejo refresh token se invalida
- TTL del nuevo token: 1 hora
- TTL del nuevo refresh token: 7 días

---

## 4. Obtener Perfil del Usuario

### GET `/api/v1/auth/me`

**Descripción:** Retorna información del usuario autenticado.

**Autenticación:** Requerida (Bearer token)

**Request Headers:**
```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

**Response Success (200 OK):**
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "email": "user@example.com",
  "name": "John Doe",
  "role": "ROLE_PREMIUM",
  "isActive": true,
  "createdAt": "2025-12-19T10:00:00+00:00"
}
```

**Response Error (401 Unauthorized) - Sin token:**
```json
{
  "code": 401,
  "message": "JWT Token not found"
}
```

**Response Error (401 Unauthorized) - Token expirado:**
```json
{
  "code": 401,
  "message": "Expired JWT Token"
}
```

**Response Error (401 Unauthorized) - Token inválido:**
```json
{
  "code": 401,
  "message": "Invalid JWT Token"
}
```

**Ejemplo cURL:**
```bash
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."

curl -X GET https://api.lexecuador.com/api/v1/auth/me \
  -H "Authorization: Bearer $TOKEN"
```

**Ejemplo JavaScript:**
```javascript
const token = localStorage.getItem('token');

const response = await fetch('https://api.lexecuador.com/api/v1/auth/me', {
  headers: {
    'Authorization': `Bearer ${token}`,
  },
});

const user = await response.json();
```

---

## 5. Actualizar Perfil

### PATCH `/api/v1/auth/me`

**Descripción:** Actualiza el nombre del usuario autenticado.

**Autenticación:** Requerida (Bearer token)

**Request Headers:**
```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "John Updated Doe"
}
```

**Request Body Schema:**
| Campo | Tipo | Requerido | Validación | Descripción |
|-------|------|-----------|------------|-------------|
| `name` | string | Sí | Min 2 chars, max 100 chars | Nuevo nombre |

**Response Success (200 OK):**
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "email": "user@example.com",
  "name": "John Updated Doe",
  "role": "ROLE_PREMIUM",
  "isActive": true,
  "createdAt": "2025-12-19T10:00:00+00:00"
}
```

**Response Error (400 Bad Request):**
```json
{
  "type": "https://api.lexecuador.com/problems/validation-error",
  "title": "Validation Error",
  "status": 400,
  "detail": "Invalid data provided",
  "errors": {
    "name": ["Name must be at least 2 characters"]
  }
}
```

**Ejemplo cURL:**
```bash
curl -X PATCH https://api.lexecuador.com/api/v1/auth/me \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Updated Doe"
  }'
```

**Notas:**
- Solo se puede actualizar el nombre
- El email NO se puede cambiar
- El rol NO se puede cambiar (se actualiza automáticamente con suscripciones)

---

## 6. Cambiar Contraseña

### POST `/api/v1/auth/me/change-password`

**Descripción:** Cambia la contraseña del usuario autenticado.

**Autenticación:** Requerida (Bearer token)

**Request Headers:**
```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
Content-Type: application/json
```

**Request Body:**
```json
{
  "currentPassword": "OldPass123!",
  "newPassword": "NewSecurePass456!"
}
```

**Request Body Schema:**
| Campo | Tipo | Requerido | Validación | Descripción |
|-------|------|-----------|------------|-------------|
| `currentPassword` | string | Sí | - | Contraseña actual |
| `newPassword` | string | Sí | Min 8 chars, 1 mayúscula, 1 minúscula, 1 número | Nueva contraseña |

**Response Success (200 OK):**
```json
{
  "message": "Password changed successfully"
}
```

**Response Error (401 Unauthorized) - Contraseña actual incorrecta:**
```json
{
  "type": "https://api.lexecuador.com/problems/invalid-password",
  "title": "Invalid Password",
  "status": 401,
  "detail": "Current password is incorrect"
}
```

**Response Error (400 Bad Request) - Nueva contraseña débil:**
```json
{
  "type": "https://api.lexecuador.com/problems/validation-error",
  "title": "Validation Error",
  "status": 400,
  "detail": "Invalid data provided",
  "errors": {
    "newPassword": ["Password must contain at least one uppercase letter"]
  }
}
```

**Ejemplo cURL:**
```bash
curl -X POST https://api.lexecuador.com/api/v1/auth/me/change-password \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "currentPassword": "OldPass123!",
    "newPassword": "NewSecurePass456!"
  }'
```

**Notas:**
- Se requiere la contraseña actual por seguridad
- La nueva contraseña NO puede ser igual a la actual
- Se invalidan todos los tokens existentes después del cambio (opcional)
- Se envía email de confirmación (opcional)

---

## 7. Cerrar Sesión

### POST `/api/v1/auth/logout`

**Descripción:** Invalida el token JWT actual.

**Autenticación:** Requerida (Bearer token)

**Request Headers:**
```http
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
```

**Response Success (200 OK):**
```json
{
  "message": "Logged out successfully"
}
```

**Ejemplo cURL:**
```bash
curl -X POST https://api.lexecuador.com/api/v1/auth/logout \
  -H "Authorization: Bearer $TOKEN"
```

**Ejemplo JavaScript:**
```javascript
const token = localStorage.getItem('token');

await fetch('https://api.lexecuador.com/api/v1/auth/logout', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
  },
});

// Limpiar tokens del almacenamiento
localStorage.removeItem('token');
localStorage.removeItem('refreshToken');
```

**Notas:**
- El token se añade a una blacklist (Redis) con TTL de 1 hora
- El refresh token también se invalida
- En frontend, siempre limpiar tokens del localStorage

---

## 🔐 Seguridad

### Headers de Seguridad

Todos los endpoints retornan estos headers:

```http
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000; includeSubDomains
```

### CORS

Configuración para frontend Angular:

```http
Access-Control-Allow-Origin: https://app.lexecuador.com
Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
Access-Control-Max-Age: 3600
```

### Rate Limiting

**Login:**
- 5 intentos por IP cada 15 minutos
- Después del 5to intento: bloqueo de 15 minutos

**Otros endpoints:**
- 100 requests/día (usuarios FREE)
- 10,000 requests/día (usuarios PREMIUM)

### Password Policy

- Mínimo 8 caracteres
- Al menos 1 letra mayúscula
- Al menos 1 letra minúscula
- Al menos 1 número
- Recomendado: símbolos especiales

---

## 📊 Códigos de Estado HTTP

| Código | Significado | Uso |
|--------|-------------|-----|
| 200 | OK | Request exitoso |
| 201 | Created | Usuario creado exitosamente |
| 400 | Bad Request | Datos de entrada inválidos |
| 401 | Unauthorized | No autenticado o token inválido |
| 409 | Conflict | Email ya registrado |
| 422 | Unprocessable Entity | Validación de negocio fallida |
| 429 | Too Many Requests | Rate limit excedido |
| 500 | Internal Server Error | Error del servidor |

---

## 🧪 Testing

### Usuarios de Prueba (Fixtures)

```
FREE User:
- Email: free@lexecuador.com
- Password: password123
- Role: ROLE_FREE

PREMIUM User:
- Email: premium@lexecuador.com
- Password: password123
- Role: ROLE_PREMIUM

ENTERPRISE User:
- Email: enterprise@lexecuador.com
- Password: password123
- Role: ROLE_ENTERPRISE

ADMIN User:
- Email: admin@lexecuador.com
- Password: password123
- Role: ROLE_ADMIN
```

### Colección Postman

Importar desde: `docs/postman/auth-endpoints.json`

---

## 📝 Notas de Implementación

### Flujo de Autenticación Recomendado

1. **Registro:**
   - Usuario se registra con email/password
   - Backend retorna token + refreshToken
   - Frontend guarda ambos en localStorage
   - Redirect a dashboard

2. **Login:**
   - Usuario ingresa credenciales
   - Backend valida y retorna tokens
   - Frontend guarda tokens
   - Redirect a dashboard

3. **Requests Autenticados:**
   - Frontend añade header `Authorization: Bearer {token}`
   - Si retorna 401, intentar refresh
   - Si refresh falla, redirect a login

4. **Refresh Token:**
   - Cuando token expira (401)
   - Llamar a `/auth/refresh` con refreshToken
   - Guardar nuevos tokens
   - Reintentar request original

5. **Logout:**
   - Llamar a `/auth/logout`
   - Limpiar localStorage
   - Redirect a landing page

### Manejo de Errores en Frontend

```javascript
async function fetchWithAuth(url, options = {}) {
  const token = localStorage.getItem('token');

  const response = await fetch(url, {
    ...options,
    headers: {
      ...options.headers,
      'Authorization': `Bearer ${token}`,
    },
  });

  // Si token expiró, intentar refresh
  if (response.status === 401) {
    const refreshed = await refreshToken();
    if (refreshed) {
      // Reintentar request con nuevo token
      return fetchWithAuth(url, options);
    } else {
      // Redirect a login
      window.location.href = '/login';
    }
  }

  return response;
}

async function refreshToken() {
  const refreshToken = localStorage.getItem('refreshToken');

  try {
    const response = await fetch('/api/v1/auth/refresh', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refreshToken }),
    });

    if (response.ok) {
      const data = await response.json();
      localStorage.setItem('token', data.token);
      localStorage.setItem('refreshToken', data.refreshToken);
      return true;
    }
  } catch (error) {
    console.error('Refresh failed:', error);
  }

  return false;
}
```

---

**Archivo generado:** `07_ENDPOINTS_AUTH.md`
**Siguiente:** `08_ENDPOINTS_CONSTITUTION.md` (Endpoints de Artículos)
