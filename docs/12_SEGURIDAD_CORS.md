# 12 - SEGURIDAD, CORS Y VALIDACIÓN

**Proyecto:** LexEcuador - API REST para Constitución de Ecuador
**Propósito:** Guía completa de configuración de seguridad, CORS, validación y protección contra ataques
**Audiencia:** Desarrollador PHP 3+ años con conocimiento de SOLID y Clean Architecture

---

## 📋 ÍNDICE

1. [Configuración de Seguridad](#configuración-de-seguridad)
2. [CORS para Angular](#cors-para-angular)
3. [Validación de Datos](#validación-de-datos)
4. [Rate Limiting](#rate-limiting)
5. [Protección contra Ataques](#protección-contra-ataques)
6. [Autenticación JWT](#autenticación-jwt)
7. [Auditoría y Logging](#auditoría-y-logging)

---

## 🔒 CONFIGURACIÓN DE SEGURIDAD

### 1. Instalación de Bundles de Seguridad

```bash
# Security Bundle (ya viene con Symfony 7)
composer require symfony/security-bundle

# CORS Bundle
composer require nelmio/cors-bundle

# Rate Limiter
composer require symfony/rate-limiter

# Validator
composer require symfony/validator
```

---

### 2. Configuración Principal de Security

```yaml
# config/packages/security.yaml
security:
    # Password hashers
    password_hashers:
        App\Domain\Entity\User:
            algorithm: auto  # bcrypt con cost automático
            cost: 12         # Mayor seguridad (default: 10)

    # Providers
    providers:
        app_user_provider:
            entity:
                class: App\Domain\Entity\User
                property: email

    # Firewalls
    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        # Endpoints públicos (login, register)
        public:
            pattern: ^/api/v1/(auth/login|auth/register)
            stateless: true
            security: false

        # Webhooks (autenticación por firma, no JWT)
        webhooks:
            pattern: ^/api/v1/webhooks
            stateless: true
            security: false

        # API protegida (requiere JWT)
        api:
            pattern: ^/api/v1
            stateless: true
            jwt: ~
            entry_point: jwt

    # Access control
    access_control:
        # Rutas públicas
        - { path: ^/api/v1/auth/login, roles: PUBLIC_ACCESS }
        - { path: ^/api/v1/auth/register, roles: PUBLIC_ACCESS }
        - { path: ^/api/v1/webhooks, roles: PUBLIC_ACCESS }

        # Endpoints de artículos (autenticado o anónimo)
        - { path: ^/api/v1/articles, roles: PUBLIC_ACCESS }

        # Endpoints de suscripciones (requieren autenticación)
        - { path: ^/api/v1/subscriptions, roles: IS_AUTHENTICATED_FULLY }
        - { path: ^/api/v1/payments, roles: IS_AUTHENTICATED_FULLY }

        # Admin endpoints
        - { path: ^/api/v1/admin, roles: ROLE_ADMIN }

        # Swagger (solo en dev)
        - { path: ^/api/doc, roles: PUBLIC_ACCESS }

    # Role hierarchy
    role_hierarchy:
        ROLE_PREMIUM: ROLE_FREE
        ROLE_ENTERPRISE: ROLE_PREMIUM
        ROLE_ADMIN: [ROLE_ENTERPRISE, ROLE_USER]
```

---

### 3. Configuración de JWT

```yaml
# config/packages/lexik_jwt_authentication.yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'

    token_ttl: 3600  # 1 hora

    # User identity field
    user_identity_field: email

    # Token extractor
    token_extractors:
        authorization_header:
            enabled: true
            prefix: Bearer
            name: Authorization

        # También permitir en query string (para WebSockets, etc.)
        query_parameter:
            enabled: false  # Deshabilitado por seguridad
            name: token

    # Encoder
    encoder:
        service: lexik_jwt_authentication.encoder.lcobucci
        signature_algorithm: RS256
```

---

### 4. Forzar HTTPS en Producción

```yaml
# config/packages/framework.yaml
framework:
    # ...

    # Forzar HTTPS en producción
    http_method_override: false  # Prevenir HTTP method spoofing

    # Configuración de sesiones (no usamos, pero por si acaso)
    session:
        cookie_secure: auto
        cookie_samesite: lax
```

```apache
# public/.htaccess (Apache)
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Redirigir HTTP a HTTPS en producción
    RewriteCond %{HTTPS} !=on
    RewriteCond %{ENV:SYMFONY_ENV} =prod
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # ...resto de reglas
</IfModule>
```

---

## 🌐 CORS PARA ANGULAR

### 1. Configuración de CORS

```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
        allow_headers: ['Content-Type', 'Authorization', 'X-Requested-With']
        expose_headers: ['Content-Length', 'X-Total-Count', 'X-Page', 'X-Per-Page']
        max_age: 3600  # Cache preflight por 1 hora
        allow_credentials: true

    paths:
        # Configuración específica para la API
        '^/api':
            allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
            allow_methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
            allow_headers: ['*']
            max_age: 3600
```

---

### 2. Variables de Entorno para CORS

```bash
# .env.local (Desarrollo)
CORS_ALLOW_ORIGIN=^http://localhost:[0-9]+$

# .env.prod (Producción)
CORS_ALLOW_ORIGIN=^https://app\.lexecuador\.com$
```

---

### 3. Manejo de Preflight Requests

Symfony automáticamente maneja las peticiones `OPTIONS` (preflight), pero podemos optimizar:

```php
<?php
// src/Infrastructure/Presentation/EventListener/CorsListener.php

namespace App\Infrastructure\Presentation\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;

final class CorsListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 9999],
            KernelEvents::RESPONSE => ['onKernelResponse', 9999],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Responder inmediatamente a OPTIONS requests
        if ('OPTIONS' === $event->getRequest()->getMethod()) {
            $response = new Response();
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            $response->headers->set('Access-Control-Max-Age', '3600');

            $event->setResponse($response);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // Añadir headers CORS a todas las respuestas
        $response = $event->getResponse();
        $response->headers->set('Access-Control-Allow-Origin', $_ENV['CORS_ALLOW_ORIGIN'] ?? '*');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
    }
}
```

---

## ✅ VALIDACIÓN DE DATOS

### 1. Validación de Entidades

```php
<?php
// src/Domain/Entity/User.php

namespace App\Domain\Entity;

use Symfony\Component\Validator\Constraints as Assert;

class User
{
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Invalid email format')]
    #[Assert\Length(max: 180)]
    private string $email;

    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(
        min: 8,
        max: 255,
        minMessage: 'Password must be at least {{ limit }} characters',
        maxMessage: 'Password cannot be longer than {{ limit }} characters'
    )]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
        message: 'Password must contain at least one uppercase letter, one lowercase letter, and one number'
    )]
    private string $password;

    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Name must be at least {{ limit }} characters',
        maxMessage: 'Name cannot be longer than {{ limit }} characters'
    )]
    private string $name;
}
```

---

### 2. Validación en DTOs

```php
<?php
// src/Application/DTO/RegisterUserRequest.php

namespace App\Application\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class RegisterUserRequest
{
    #[Assert\NotBlank]
    #[Assert\Email]
    public string $email;

    #[Assert\NotBlank]
    #[Assert\Length(min: 8, max: 255)]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
        message: 'Password must contain uppercase, lowercase, and number'
    )]
    public string $password;

    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    public string $name;

    #[Assert\Choice(choices: ['ROLE_FREE', 'ROLE_PREMIUM', 'ROLE_ENTERPRISE'])]
    public string $role = 'ROLE_FREE';
}
```

---

### 3. Validación en Controller

```php
<?php
// src/Infrastructure/Presentation/Controller/AuthController.php

namespace App\Infrastructure\Presentation\Controller;

use App\Application\DTO\RegisterUserRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class AuthController extends AbstractController
{
    #[Route('/api/v1/auth/register', methods: ['POST'])]
    public function register(
        Request $request,
        ValidatorInterface $validator
    ): JsonResponse {
        // Deserializar request
        $dto = $this->serializer->deserialize(
            $request->getContent(),
            RegisterUserRequest::class,
            'json'
        );

        // Validar DTO
        $errors = $validator->validate($dto);

        if (count($errors) > 0) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/validation-failed',
                'title' => 'Validation Failed',
                'status' => 400,
                'detail' => 'The request contains invalid data',
                'violations' => array_map(
                    fn($error) => [
                        'field' => $error->getPropertyPath(),
                        'message' => $error->getMessage(),
                    ],
                    iterator_to_array($errors)
                ),
            ], 400);
        }

        // Procesar registro...
    }
}
```

---

### 4. Custom Validators

```php
<?php
// src/Infrastructure/Validator/UniqueEmailValidator.php

namespace App\Infrastructure\Validator;

use App\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

#[\Attribute]
class UniqueEmail extends Constraint
{
    public string $message = 'The email "{{ value }}" is already registered.';
}

class UniqueEmailValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {}

    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        $user = $this->userRepository->findByEmail($value);

        if ($user !== null) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}
```

Uso:

```php
#[Assert\NotBlank]
#[Assert\Email]
#[UniqueEmail]  // ← Custom validator
private string $email;
```

---

## 🚦 RATE LIMITING

### 1. Configuración de Rate Limiter

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        # Login attempts (prevenir brute force)
        login:
            policy: 'sliding_window'
            limit: 5
            interval: '15 minutes'

        # API requests - FREE users
        api_free:
            policy: 'fixed_window'
            limit: 100
            interval: '1 day'

        # API requests - PREMIUM users
        api_premium:
            policy: 'fixed_window'
            limit: 10000
            interval: '1 day'

        # Registration (prevenir spam)
        registration:
            policy: 'sliding_window'
            limit: 3
            interval: '1 hour'

        # Password reset
        password_reset:
            policy: 'sliding_window'
            limit: 3
            interval: '1 hour'

        # Payment attempts
        payment:
            policy: 'sliding_window'
            limit: 5
            interval: '1 hour'
```

---

### 2. Aplicar Rate Limit en Controllers

```php
<?php
// src/Infrastructure/Presentation/Controller/AuthController.php

namespace App\Infrastructure\Presentation\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class AuthController extends AbstractController
{
    #[Route('/api/v1/auth/login', methods: ['POST'])]
    public function login(
        Request $request,
        RateLimiterFactory $loginLimiter
    ): JsonResponse {
        // Rate limiting por IP
        $limiter = $loginLimiter->create($request->getClientIp());

        if (false === $limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(
                900,  // Retry after 900 seconds (15 min)
                'Too many login attempts. Please try again in 15 minutes.'
            );
        }

        // Procesar login...
    }
}
```

---

### 3. Rate Limit por Usuario (no por IP)

```php
<?php
// src/Infrastructure/Presentation/EventListener/RateLimitListener.php

namespace App\Infrastructure\Presentation\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class RateLimitListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly RateLimiterFactory $apiFree,
        private readonly RateLimiterFactory $apiPremium,
        private readonly Security $security
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Solo aplicar a rutas de API (no a auth, webhooks, etc.)
        if (!str_starts_with($request->getPathInfo(), '/api/v1/articles')) {
            return;
        }

        $user = $this->security->getUser();

        if (!$user) {
            // Usuario anónimo - permitir acceso limitado
            return;
        }

        // Seleccionar limiter según rol
        $limiter = match ($user->getRole()->value) {
            'ROLE_FREE' => $this->apiFree->create($user->getId()),
            'ROLE_PREMIUM' => $this->apiPremium->create($user->getId()),
            'ROLE_ENTERPRISE', 'ROLE_ADMIN' => null,  // Sin límite
        };

        if ($limiter && !$limiter->consume(1)->isAccepted()) {
            throw new TooManyRequestsHttpException(
                86400,  // 1 día
                'API rate limit exceeded. Upgrade to PREMIUM for higher limits.'
            );
        }
    }
}
```

---

### 4. Headers de Rate Limit

```php
<?php
// Añadir headers informativos en las respuestas

$limit = $limiter->consume(1);

$response->headers->set('X-RateLimit-Limit', $limit->getLimit());
$response->headers->set('X-RateLimit-Remaining', $limit->getRemainingTokens());
$response->headers->set('X-RateLimit-Reset', $limit->getRetryAfter()->getTimestamp());
```

---

## 🛡️ PROTECCIÓN CONTRA ATAQUES

### 1. SQL Injection

**Prevención:** Usar Doctrine ORM con parámetros preparados.

```php
// ❌ MAL - Vulnerable a SQL injection
$sql = "SELECT * FROM users WHERE email = '" . $email . "'";
$users = $conn->query($sql);

// ✅ BIEN - Parámetros preparados
$query = $entityManager->createQuery(
    'SELECT u FROM App\Domain\Entity\User u WHERE u.email = :email'
);
$query->setParameter('email', $email);
$user = $query->getOneOrNullResult();

// ✅ MEJOR - Usar Repository
$user = $userRepository->findByEmail($email);
```

---

### 2. XSS (Cross-Site Scripting)

**Prevención:** Sanitizar output (ya lo hace Symfony Serializer).

```php
// En entidades, usar grupos de serialización
use Symfony\Component\Serializer\Annotation\Groups;

class Article
{
    #[Groups(['article:read'])]
    private string $content;  // ← Serializer automáticamente escapa HTML
}

// Además, validar input
#[Assert\NotBlank]
#[Assert\Length(max: 10000)]
#[Assert\Regex(
    pattern: '/^[^<>]*$/',  // No permitir < o >
    message: 'Content cannot contain HTML tags'
)]
private string $content;
```

---

### 3. CSRF (Cross-Site Request Forgery)

**Prevención:** Como la API es stateless (JWT), no necesitamos tokens CSRF. Sin embargo:

```yaml
# config/packages/framework.yaml
framework:
    csrf_protection:
        enabled: false  # API REST stateless no necesita CSRF
```

**Importante:** Si en el futuro se añaden endpoints con sesiones, habilitar CSRF.

---

### 4. Directory Traversal

**Prevención:** Validar paths de archivos.

```php
<?php
// Si se permite subir archivos (no aplicable ahora, pero por si acaso)

use Symfony\Component\HttpFoundation\File\UploadedFile;

public function upload(UploadedFile $file): string
{
    // ❌ MAL - Vulnerable a directory traversal
    $filename = $file->getClientOriginalName();
    move_uploaded_file($file->getPathname(), '/uploads/' . $filename);

    // ✅ BIEN - Generar nombre seguro
    $filename = bin2hex(random_bytes(16)) . '.' . $file->guessExtension();
    $file->move('/uploads', $filename);

    return $filename;
}
```

---

### 5. Mass Assignment

**Prevención:** Usar DTOs explícitos en lugar de `Request::all()`.

```php
// ❌ MAL - Vulnerable a mass assignment
$user = new User();
$user->fromArray($request->request->all());  // Podría incluir "role" = "ROLE_ADMIN"

// ✅ BIEN - Solo asignar campos permitidos
$user = User::register(
    email: new Email($data['email']),
    hashedPassword: $hashedPassword,
    name: $data['name'],
    role: Role::FREE  // ← Hardcoded, no del request
);
```

---

### 6. Insecure Deserialization

**Prevención:** No usar `unserialize()` con input de usuario.

```php
// ❌ MAL - Nunca hacer esto
$data = unserialize($request->getContent());

// ✅ BIEN - Usar JSON
$data = json_decode($request->getContent(), true);

// ✅ MEJOR - Usar Serializer de Symfony
$dto = $this->serializer->deserialize(
    $request->getContent(),
    RegisterUserRequest::class,
    'json'
);
```

---

### 7. Information Disclosure

**Prevención:** No exponer stack traces en producción.

```yaml
# config/packages/framework.yaml
when@prod:
    framework:
        php_errors:
            log: true  # Loguear errores
```

```php
<?php
// src/Infrastructure/Presentation/EventListener/ExceptionListener.php

namespace App\Infrastructure\Presentation\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ExceptionListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $environment
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        $response = new JsonResponse([
            'type' => 'https://api.lexecuador.com/problems/internal-error',
            'title' => 'Internal Server Error',
            'status' => 500,
            'detail' => $this->environment === 'prod'
                ? 'An unexpected error occurred'  // ← No exponer detalles en prod
                : $exception->getMessage(),        // ← Solo en dev
        ], 500);

        $event->setResponse($response);
    }
}
```

---

## 🔐 AUTENTICACIÓN JWT

Ver archivo `07_ENDPOINTS_AUTH.md` para documentación completa de autenticación JWT.

### Resumen de Seguridad JWT:

1. ✅ Usar RS256 (clave pública/privada)
2. ✅ TTL corto (1 hora)
3. ✅ Refresh tokens con rotación
4. ✅ Blacklist de tokens revocados
5. ✅ Validar claims (iss, aud, exp)

---

## 📝 AUDITORÍA Y LOGGING

### 1. Configuración de Monolog

```yaml
# config/packages/monolog.yaml
monolog:
    channels:
        - security
        - payment
        - api

    handlers:
        # Logs de seguridad (login, logout, cambios de rol)
        security:
            type: stream
            path: '%kernel.logs_dir%/security.log'
            level: info
            channels: ['security']

        # Logs de pagos
        payment:
            type: stream
            path: '%kernel.logs_dir%/payment.log'
            level: info
            channels: ['payment']

        # Logs de API (requests)
        api:
            type: stream
            path: '%kernel.logs_dir%/api.log'
            level: info
            channels: ['api']

        # Errores críticos
        critical:
            type: fingers_crossed
            action_level: critical
            handler: grouped

        # Agrupar errores y enviar por email
        grouped:
            type: group
            members: [streamed, emailed]

        streamed:
            type: stream
            path: '%kernel.logs_dir%/critical.log'
            level: critical

        emailed:
            type: native_mailer
            to: admin@lexecuador.com
            subject: '[LexEcuador] Critical Error'
            level: critical
            formatter: monolog.formatter.html
            content_type: text/html
```

---

### 2. Logging de Eventos de Seguridad

```php
<?php
// src/Infrastructure/Presentation/EventListener/SecurityEventListener.php

namespace App\Infrastructure\Presentation\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Psr\Log\LoggerInterface;

final class SecurityEventListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $securityLogger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LoginFailureEvent::class => 'onLoginFailure',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        $this->securityLogger->info('User logged in successfully', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'ip' => $event->getRequest()->getClientIp(),
            'user_agent' => $event->getRequest()->headers->get('User-Agent'),
            'timestamp' => time(),
        ]);
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $this->securityLogger->warning('Failed login attempt', [
            'email' => $event->getRequest()->request->get('email'),
            'ip' => $event->getRequest()->getClientIp(),
            'reason' => $event->getException()->getMessage(),
            'timestamp' => time(),
        ]);
    }
}
```

---

### 3. Logging de Cambios Sensibles

```php
<?php
// Loguear cambios de suscripción

public function execute(string $userId, array $data): array
{
    // ... lógica de upgrade

    $this->logger->info('Subscription upgraded', [
        'user_id' => $userId,
        'old_plan' => $oldPlan->value,
        'new_plan' => $newPlan->value,
        'amount_charged' => $proration['amount'],
        'timestamp' => time(),
    ]);

    return $result;
}
```

---

## 🔍 HEADERS DE SEGURIDAD

### Configuración de Headers HTTP

```php
<?php
// src/Infrastructure/Presentation/EventListener/SecurityHeadersListener.php

namespace App\Infrastructure\Presentation\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class SecurityHeadersListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        // Prevenir clickjacking
        $response->headers->set('X-Frame-Options', 'DENY');

        // Prevenir MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // XSS Protection (legacy, pero no hace daño)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Content Security Policy
        $response->headers->set('Content-Security-Policy', "default-src 'self'");

        // Referrer Policy
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Strict Transport Security (HSTS) - Solo en producción con HTTPS
        if ($event->getRequest()->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        // Permissions Policy (antes Feature-Policy)
        $response->headers->set(
            'Permissions-Policy',
            'geolocation=(), microphone=(), camera=()'
        );
    }
}
```

---

## ✅ CHECKLIST DE SEGURIDAD

### Configuración

- [ ] Instalar bundles de seguridad
- [ ] Configurar security.yaml con JWT
- [ ] Configurar CORS para Angular
- [ ] Generar claves JWT (RS256)
- [ ] Configurar rate limiting
- [ ] Forzar HTTPS en producción
- [ ] Configurar headers de seguridad

### Validación

- [ ] Añadir constraints a todas las entidades
- [ ] Crear DTOs para requests
- [ ] Validar en controllers
- [ ] Crear custom validators (UniqueEmail, etc.)
- [ ] Sanitizar output

### Protección

- [ ] Prevenir SQL Injection (usar ORM)
- [ ] Prevenir XSS (validar input, escapar output)
- [ ] Prevenir CSRF (stateless API)
- [ ] Prevenir Directory Traversal
- [ ] Prevenir Mass Assignment
- [ ] Prevenir Insecure Deserialization
- [ ] No exponer stack traces en producción

### Rate Limiting

- [ ] Configurar limiters para login, API, registro
- [ ] Aplicar en controllers críticos
- [ ] Implementar por usuario (no solo IP)
- [ ] Añadir headers X-RateLimit-*

### Auditoría

- [ ] Configurar Monolog con canales separados
- [ ] Loguear eventos de seguridad
- [ ] Loguear cambios sensibles (pagos, roles)
- [ ] Configurar alertas críticas por email
- [ ] Rotar logs regularmente

### Testing

- [ ] Test de autenticación JWT
- [ ] Test de rate limiting
- [ ] Test de validación
- [ ] Test de CORS
- [ ] Penetration testing básico

---

## 🔒 PASSWORDS Y SECRETOS

### Hasheo de Passwords

```php
// Symfony automáticamente hashea con bcrypt (cost 12)
$hashedPassword = $passwordHasher->hashPassword($user, 'plain-password');

// Verificar password
$isValid = $passwordHasher->isPasswordValid($user, 'plain-password');
```

### Gestión de Secretos

```bash
# NO commitear secretos en .env
# Usar .env.local (gitignored)

# Para producción, usar variables de entorno del servidor
export JWT_SECRET_KEY=/path/to/private.pem
export JWT_PUBLIC_KEY=/path/to/public.pem
export STRIPE_SECRET_KEY=sk_live_xxx
```

---

**Archivo generado:** `12_SEGURIDAD_CORS.md`
**Siguiente:** `13_TESTING_STRATEGY.md` (Estrategia de Testing)
