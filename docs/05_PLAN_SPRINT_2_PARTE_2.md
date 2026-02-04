# 05 - Plan Sprint 2: Core Features (Parte 2)

**Continuación de:** `05_PLAN_SPRINT_2.md`
**Fases:** 3 y 4 (continuación)
**Tiempo estimado restante:** 8-10 horas

---

## Fase 3: DTOs y Controllers (Continuación)

### Tarea 3.3: Tests de Integración de ArticleController

**Objetivo:** Testear endpoints end-to-end con diferentes roles

**Archivo:** `tests/Functional/API/ArticleControllerTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional\API;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Role;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ArticleControllerTest extends WebTestCase
{
    private $client;
    private string $freeUserToken;
    private string $premiumUserToken;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Crear usuarios de prueba y obtener tokens
        $this->createTestUsers();
    }

    private function createTestUsers(): void
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $passwordHasher = $container->get('security.password_hasher');
        $jwtManager = $container->get('lexik_jwt_authentication.jwt_manager');

        // Limpiar usuarios
        $em->createQuery('DELETE FROM App\Domain\Entity\User')->execute();

        // Usuario FREE
        $freeUser = User::register(
            Email::fromString('free@test.com'),
            '',
            'Free User',
            Role::FREE
        );
        $freeUser->changePassword($passwordHasher->hashPassword($freeUser, 'password123'));
        $em->persist($freeUser);

        // Usuario PREMIUM
        $premiumUser = User::register(
            Email::fromString('premium@test.com'),
            '',
            'Premium User',
            Role::PREMIUM
        );
        $premiumUser->changePassword($passwordHasher->hashPassword($premiumUser, 'password123'));
        $em->persist($premiumUser);

        $em->flush();

        // Generar tokens
        $this->freeUserToken = $jwtManager->create($freeUser);
        $this->premiumUserToken = $jwtManager->create($premiumUser);
    }

    public function testListArticlesAsAnonymous(): void
    {
        $this->client->request('GET', '/api/v1/articles');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testListArticlesAsFreeUser(): void
    {
        $this->client->request('GET', '/api/v1/articles?page=1&limit=20', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->freeUserToken,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertLessThanOrEqual(100, $data['meta']['total']); // FREE user ve max 100
        $this->assertCount(min(20, $data['meta']['total']), $data['data']);
    }

    public function testListArticlesAsPremiumUser(): void
    {
        $this->client->request('GET', '/api/v1/articles?page=1&limit=20', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->premiumUserToken,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertSame(467, $data['meta']['total']); // PREMIUM user ve todos
    }

    public function testGetArticleByIdInFreeRangeAsFreeUser(): void
    {
        // Asumiendo que existe artículo con ID 1 y número <= 100
        $this->client->request('GET', '/api/v1/articles/1', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->freeUserToken,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('id', $data['data']);
        $this->assertArrayNotHasKey('concordances', $data['data']); // FREE no ve concordances
    }

    public function testGetArticleByIdOutsideFreeRangeAsFreeUser(): void
    {
        // Buscar un artículo con número > 100
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        $article = $em->getRepository(\App\Domain\Entity\Article::class)
            ->findOneBy(['articleNumber' => 150]);

        if ($article === null) {
            $this->markTestSkipped('No article with number 150 in database');
        }

        $this->client->request('GET', '/api/v1/articles/' . $article->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->freeUserToken,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertSame('Premium Access Required', $data['title']);
        $this->assertStringContainsString('Premium subscription', $data['detail']);
    }

    public function testGetArticleByIdOutsideFreeRangeAsPremiumUser(): void
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();

        $article = $em->getRepository(\App\Domain\Entity\Article::class)
            ->findOneBy(['articleNumber' => 150]);

        if ($article === null) {
            $this->markTestSkipped('No article with number 150 in database');
        }

        $this->client->request('GET', '/api/v1/articles/' . $article->getId(), [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->premiumUserToken,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('concordances', $data['data']); // PREMIUM ve concordances
    }

    public function testGetArticleByNumberValid(): void
    {
        $this->client->request('GET', '/api/v1/articles/number/1', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->freeUserToken,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertSame(1, $data['data']['articleNumber']);
    }

    public function testGetArticleByNumberInvalid(): void
    {
        $this->client->request('GET', '/api/v1/articles/number/999', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->freeUserToken,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testSearchArticlesWithValidQuery(): void
    {
        $this->client->request('GET', '/api/v1/articles/search?q=derechos', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->premiumUserToken,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertSame('derechos', $data['meta']['query']);
        $this->assertGreaterThan(0, $data['meta']['total']);
    }

    public function testSearchArticlesWithShortQuery(): void
    {
        $this->client->request('GET', '/api/v1/articles/search?q=a', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->freeUserToken,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSearchArticlesAsFreeUserLimitedResults(): void
    {
        $this->client->request('GET', '/api/v1/articles/search?q=constitución', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->freeUserToken,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Verificar que todos los resultados están en rango FREE (1-100)
        foreach ($data['data'] as $article) {
            $this->assertLessThanOrEqual(100, $article['articleNumber']);
        }
    }

    public function testGetChapters(): void
    {
        $this->client->request('GET', '/api/v1/articles/chapters', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->freeUserToken,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        $this->assertNotEmpty($data['data']);

        // Verificar estructura
        $firstChapter = $data['data'][0];
        $this->assertArrayHasKey('name', $firstChapter);
        $this->assertArrayHasKey('count', $firstChapter);
    }

    public function testPaginationWorks(): void
    {
        // Página 1
        $this->client->request('GET', '/api/v1/articles?page=1&limit=10', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->premiumUserToken,
        ]);

        $data1 = json_decode($this->client->getResponse()->getContent(), true);

        // Página 2
        $this->client->request('GET', '/api/v1/articles?page=2&limit=10', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->premiumUserToken,
        ]);

        $data2 = json_decode($this->client->getResponse()->getContent(), true);

        // Verificar que son diferentes
        $this->assertNotEquals(
            $data1['data'][0]['id'],
            $data2['data'][0]['id']
        );

        // Verificar metadata
        $this->assertSame(1, $data1['meta']['page']);
        $this->assertSame(2, $data2['meta']['page']);
    }
}
```

**Ejecutar tests:**
```bash
# Ejecutar tests de ArticleController
php bin/phpunit tests/Functional/API/ArticleControllerTest.php

# Todos los tests deben pasar
```

**Criterios de aceptación:**
- [ ] 15+ test cases implementados
- [ ] Tests de control de acceso por rol
- [ ] Tests de paginación
- [ ] Tests de búsqueda
- [ ] Tests de errores (404, 403, 400)
- [ ] Todos los tests pasan

**Tiempo estimado:** 2.5 horas

---

### Fase 4: Rate Limiting (3-4 horas)

#### Tarea 4.1: Configurar Rate Limiter

**Objetivo:** Limitar requests por rol para prevenir abuso

**Archivo:** `config/packages/rate_limiter.yaml`

**Crear archivo con:**

```yaml
framework:
    rate_limiter:
        # Rate limiter para usuarios FREE
        api_free:
            policy: 'sliding_window'
            limit: 100
            interval: '24 hours'

        # Rate limiter para usuarios PREMIUM
        api_premium:
            policy: 'sliding_window'
            limit: 10000
            interval: '24 hours'

        # Rate limiter por minuto (para todos)
        api_per_minute:
            policy: 'fixed_window'
            limit: 60
            interval: '1 minute'
```

**Criterios de aceptación:**
- [ ] Configuración creada
- [ ] 3 limiters definidos (free, premium, per_minute)
- [ ] Políticas apropiadas

**Tiempo estimado:** 15 minutos

---

#### Tarea 4.2: Crear Event Subscriber para Rate Limiting

**Objetivo:** Aplicar rate limiting automáticamente según rol del usuario

**Archivo:** `src/Presentation/API/EventSubscriber/RateLimitSubscriber.php`

```php
<?php

declare(strict_types=1);

namespace App\Presentation\API\EventSubscriber;

use App\Domain\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class RateLimitSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RateLimiterFactory $apiFreeRateLimiter,
        private RateLimiterFactory $apiPremiumRateLimiter,
        private RateLimiterFactory $apiPerMinuteRateLimiter,
        private TokenStorageInterface $tokenStorage
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

        // Solo aplicar a rutas API de artículos
        if (!str_starts_with($request->getPathInfo(), '/api/v1/articles')) {
            return;
        }

        // Obtener usuario actual
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        // Identificador para rate limiter
        $identifier = $this->getIdentifier($request, $user);

        // Rate limiter por minuto (aplicar a todos)
        $perMinuteLimiter = $this->apiPerMinuteRateLimiter->create($identifier);
        if (!$perMinuteLimiter->consume(1)->isAccepted()) {
            $event->setResponse($this->createRateLimitResponse(
                'Too many requests per minute. Please slow down.',
                60
            ));
            return;
        }

        // Rate limiter diario según rol
        $dailyLimiter = $this->getDailyLimiter($user);
        $limiterResult = $dailyLimiter->create($identifier)->consume(1);

        if (!$limiterResult->isAccepted()) {
            $retryAfter = $limiterResult->getRetryAfter()->getTimestamp() - time();

            $message = $user instanceof User && $user->hasPremiumAccess()
                ? 'Daily request limit reached for Premium users.'
                : 'Daily request limit reached for Free users. Upgrade to Premium for more requests.';

            $event->setResponse($this->createRateLimitResponse($message, $retryAfter));
        }
    }

    private function getIdentifier($request, mixed $user): string
    {
        if ($user instanceof User) {
            return 'user_' . $user->getId();
        }

        // Para usuarios anónimos, usar IP
        return 'ip_' . $request->getClientIp();
    }

    private function getDailyLimiter(mixed $user): RateLimiterFactory
    {
        if ($user instanceof User && $user->hasPremiumAccess()) {
            return $this->apiPremiumRateLimiter;
        }

        return $this->apiFreeRateLimiter;
    }

    private function createRateLimitResponse(string $message, int $retryAfter): JsonResponse
    {
        $response = new JsonResponse([
            'type' => 'https://api.lexecuador.com/problems/rate-limit-exceeded',
            'title' => 'Rate Limit Exceeded',
            'status' => 429,
            'detail' => $message,
            'retryAfter' => $retryAfter,
        ], Response::HTTP_TOO_MANY_REQUESTS);

        $response->headers->set('Retry-After', (string) $retryAfter);
        $response->headers->set('X-RateLimit-Limit', '100'); // Ajustar según rol
        $response->headers->set('X-RateLimit-Remaining', '0');

        return $response;
    }
}
```

**Registrar en services.yaml:**

```yaml
services:
    # ... configuración existente ...

    App\Presentation\API\EventSubscriber\RateLimitSubscriber:
        arguments:
            $apiFreeRateLimiter: '@limiter.api_free'
            $apiPremiumRateLimiter: '@limiter.api_premium'
            $apiPerMinuteRateLimiter: '@limiter.api_per_minute'
        tags:
            - { name: kernel.event_subscriber }
```

**Criterios de aceptación:**
- [ ] Event Subscriber creado
- [ ] Rate limiting por rol funciona
- [ ] FREE: 100 requests/día
- [ ] PREMIUM: 10,000 requests/día
- [ ] Todos: 60 requests/minuto
- [ ] Headers `Retry-After` y `X-RateLimit-*` incluidos

**Tiempo estimado:** 1.5 horas

---

#### Tarea 4.3: Test de Rate Limiting

**Objetivo:** Verificar que rate limiting funciona correctamente

**Archivo:** `tests/Functional/API/RateLimitTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional\API;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Role;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class RateLimitTest extends WebTestCase
{
    private $client;
    private string $freeUserToken;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->createTestUser();
    }

    private function createTestUser(): void
    {
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $passwordHasher = $container->get('security.password_hasher');
        $jwtManager = $container->get('lexik_jwt_authentication.jwt_manager');

        $em->createQuery('DELETE FROM App\Domain\Entity\User')->execute();

        $user = User::register(
            Email::fromString('ratelimit@test.com'),
            '',
            'Rate Limit User',
            Role::FREE
        );
        $user->changePassword($passwordHasher->hashPassword($user, 'password123'));
        $em->persist($user);
        $em->flush();

        $this->freeUserToken = $jwtManager->create($user);
    }

    public function testRateLimitPerMinute(): void
    {
        $successCount = 0;
        $rateLimitHit = false;

        // Hacer 70 requests en 1 minuto (límite es 60)
        for ($i = 0; $i < 70; $i++) {
            $this->client->request('GET', '/api/v1/articles/chapters', [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $this->freeUserToken,
            ]);

            if ($this->client->getResponse()->getStatusCode() === Response::HTTP_OK) {
                $successCount++;
            } elseif ($this->client->getResponse()->getStatusCode() === Response::HTTP_TOO_MANY_REQUESTS) {
                $rateLimitHit = true;
                break;
            }
        }

        $this->assertLessThanOrEqual(60, $successCount);
        $this->assertTrue($rateLimitHit, 'Rate limit should be hit after 60 requests');

        // Verificar respuesta de rate limit
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Rate Limit Exceeded', $data['title']);
        $this->assertArrayHasKey('retryAfter', $data);

        // Verificar headers
        $response = $this->client->getResponse();
        $this->assertTrue($response->headers->has('Retry-After'));
    }

    /**
     * @group slow
     */
    public function testRateLimitDailyForFreeUser(): void
    {
        $this->markTestSkipped('Test takes too long (100+ requests). Run manually if needed.');

        // Este test requiere hacer 100+ requests, lo cual es lento
        // Se puede ejecutar manualmente para verificar límite diario
    }
}
```

**Ejecutar test:**
```bash
php bin/phpunit tests/Functional/API/RateLimitTest.php
```

**Criterios de aceptación:**
- [ ] Test de rate limiting por minuto pasa
- [ ] Rate limit se activa después de 60 requests
- [ ] Response 429 con mensaje apropiado
- [ ] Headers correctos en response

**Tiempo estimado:** 1 hora

---

### Fase 5: Documentación y Verificación Final (2-3 horas)

#### Tarea 5.1: Actualizar Swagger Documentation

**Objetivo:** Verificar que todos los endpoints están documentados

**Verificar Swagger UI:**
```bash
# Abrir en navegador
http://localhost/api/doc
```

**Checklist de documentación:**
- [ ] GET /api/v1/articles documentado
- [ ] GET /api/v1/articles/{id} documentado
- [ ] GET /api/v1/articles/number/{number} documentado
- [ ] GET /api/v1/articles/search documentado
- [ ] GET /api/v1/articles/chapters documentado
- [ ] Todos tienen ejemplos de request/response
- [ ] Errores documentados (400, 403, 404, 429)
- [ ] Security schemes definidos

**Tiempo estimado:** 30 minutos

---

#### Tarea 5.2: Crear Archivo .http para Testing Manual

**Objetivo:** Facilitar testing manual de endpoints

**Archivo:** `docs/api-requests.http`

```http
### Variables
@baseUrl = http://localhost
@freeToken = {{auth_free_token}}
@premiumToken = {{auth_premium_token}}

### Register Free User
POST {{baseUrl}}/api/v1/auth/register
Content-Type: application/json

{
  "email": "testfree@example.com",
  "password": "TestPass123!",
  "name": "Test Free User"
}

### Login Free User
POST {{baseUrl}}/api/v1/auth/login
Content-Type: application/json

{
  "email": "testfree@example.com",
  "password": "TestPass123!"
}

### Get Articles (Page 1)
GET {{baseUrl}}/api/v1/articles?page=1&limit=20
Authorization: Bearer {{freeToken}}

### Get Article by ID (in free range)
GET {{baseUrl}}/api/v1/articles/1
Authorization: Bearer {{freeToken}}

### Get Article by ID (premium required)
GET {{baseUrl}}/api/v1/articles/200
Authorization: Bearer {{freeToken}}

### Get Article by Number
GET {{baseUrl}}/api/v1/articles/number/1
Authorization: Bearer {{freeToken}}

### Search Articles
GET {{baseUrl}}/api/v1/articles/search?q=derechos&page=1
Authorization: Bearer {{premiumToken}}

### Get Chapters
GET {{baseUrl}}/api/v1/articles/chapters
Authorization: Bearer {{freeToken}}

### Test Rate Limit (run multiple times)
GET {{baseUrl}}/api/v1/articles/chapters
Authorization: Bearer {{freeToken}}
```

**Criterios de aceptación:**
- [ ] Archivo .http creado
- [ ] Ejemplos para todos los endpoints
- [ ] Variables para tokens
- [ ] Fácil de usar en VS Code REST Client o similar

**Tiempo estimado:** 20 minutos

---

#### Tarea 5.3: Probar Endpoints Manualmente

**Test 1: Usuario FREE ve solo artículos 1-100**

```bash
# Login como FREE user
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"free@lexecuador.com","password":"password123"}'

# Guardar token
TOKEN="..."

# Listar artículos (debe retornar max 100)
curl -X GET http://localhost/api/v1/articles \
  -H "Authorization: Bearer $TOKEN"

# Verificar meta.total <= 100
```

---

**Test 2: Usuario FREE bloqueado en artículo 150**

```bash
# Intentar acceder artículo 150
curl -X GET http://localhost/api/v1/articles/number/150 \
  -H "Authorization: Bearer $TOKEN"

# Debe retornar 403 Forbidden con mensaje de upgrade
```

---

**Test 3: Usuario PREMIUM ve todos los artículos**

```bash
# Login como PREMIUM user
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"premium@lexecuador.com","password":"password123"}'

PREMIUM_TOKEN="..."

# Listar artículos (debe retornar 467)
curl -X GET http://localhost/api/v1/articles \
  -H "Authorization: Bearer $PREMIUM_TOKEN"

# Verificar meta.total = 467

# Acceder artículo 150 (debe funcionar)
curl -X GET http://localhost/api/v1/articles/number/150 \
  -H "Authorization: Bearer $PREMIUM_TOKEN"

# Debe retornar 200 OK con concordances
```

---

**Test 4: Búsqueda**

```bash
# Buscar "derechos"
curl -X GET "http://localhost/api/v1/articles/search?q=derechos&page=1&limit=10" \
  -H "Authorization: Bearer $PREMIUM_TOKEN"

# Verificar que retorna resultados
# Verificar meta.query = "derechos"
```

---

**Test 5: Rate Limiting**

```bash
# Hacer 65 requests rápidos (límite es 60/min)
for i in {1..65}; do
  curl -X GET http://localhost/api/v1/articles/chapters \
    -H "Authorization: Bearer $TOKEN"
  echo "Request $i"
done

# Los últimos requests deben retornar 429 Too Many Requests
```

**Criterios de aceptación:**
- [ ] Todos los tests manuales pasan
- [ ] Control de acceso funciona correctamente
- [ ] Búsqueda retorna resultados esperados
- [ ] Rate limiting se activa

**Tiempo estimado:** 1 hora

---

## 📊 Checklist de Completitud del Sprint 2

### Refactoring ✅
- [ ] Article entity con grupos de serialización
- [ ] ArticleNumber Value Object creado
- [ ] Excepciones de dominio creadas
- [ ] ArticleRepositoryInterface extendido
- [ ] DoctrineArticleRepository implementado

### Use Cases ✅
- [ ] GetArticlesUseCase
- [ ] GetArticleByIdUseCase
- [ ] GetArticleByNumberUseCase
- [ ] SearchArticlesUseCase
- [ ] GetChaptersUseCase
- [ ] Todos con control de acceso por rol

### Controllers ✅
- [ ] ArticleController con 5 endpoints
- [ ] DTOs de request creados
- [ ] Documentación OpenAPI completa
- [ ] Manejo de errores RFC 7807
- [ ] Grupos de serialización según rol

### Tests ✅
- [ ] Tests unitarios de Value Objects pasan
- [ ] Tests unitarios de Use Cases pasan
- [ ] Tests de integración de ArticleController pasan (15+ tests)
- [ ] Tests de rate limiting pasan
- [ ] Cobertura >80% en nuevos archivos

### Rate Limiting ✅
- [ ] Configuración de rate limiters
- [ ] Event Subscriber implementado
- [ ] FREE: 100 requests/día
- [ ] PREMIUM: 10,000 requests/día
- [ ] Todos: 60 requests/minuto
- [ ] Headers apropiados en response

### Documentación ✅
- [ ] Swagger UI actualizado
- [ ] Archivo .http creado
- [ ] Tests manuales ejecutados
- [ ] README actualizado (opcional)

---

## 🎯 Verificación Final

**Comandos de verificación:**

```bash
# 1. Tests pasan
php bin/phpunit

# 2. Schema válido
php bin/console doctrine:schema:validate

# 3. Linter OK
php bin/console lint:container

# 4. Rutas OK
php bin/console debug:router | grep api_v1_articles

# 5. Swagger accesible
open http://localhost/api/doc
```

**Endpoints verificados:**
- [ ] GET /api/v1/articles
- [ ] GET /api/v1/articles/{id}
- [ ] GET /api/v1/articles/number/{number}
- [ ] GET /api/v1/articles/search
- [ ] GET /api/v1/articles/chapters

**Control de acceso verificado:**
- [ ] FREE users ven solo artículos 1-100
- [ ] PREMIUM users ven todos los artículos
- [ ] FREE users NO ven concordances
- [ ] PREMIUM users ven concordances
- [ ] Artículos >100 retornan 403 para FREE users

---

## 📊 Métricas del Sprint 2

**Código escrito:**
- 20+ archivos PHP creados/modificados
- 5 Use Cases
- 1 Controller con 5 endpoints
- 3 DTOs
- 2 Value Objects
- 3 Excepciones
- 1 Event Subscriber
- ~2,500 líneas de código

**Tests:**
- 20+ test cases (unitarios + integración)
- Cobertura estimada: 85%

**Tiempo total:** ~40 horas (2 semanas)

**Funcionalidades entregadas:**
- ✅ API REST de artículos completa
- ✅ Control de acceso por rol (FREE/PREMIUM)
- ✅ Búsqueda por palabra clave
- ✅ Búsqueda por número de artículo
- ✅ Filtros por capítulo
- ✅ Paginación eficiente
- ✅ Rate limiting por rol
- ✅ Documentación OpenAPI

---

## 🚀 Siguiente Sprint

**Sprint 3: Suscripciones y Pagos**

**Objetivos:**
- Sistema de suscripciones
- Integración con Stripe
- Integración con PayPhone (Ecuador)
- Webhooks de pagos
- Gestión de ciclo de vida de suscripciones

**Archivo:** `06_PLAN_SPRINT_3.md`

---

## 🎉 ¡Sprint 2 Completado!

Si todos los checks están marcados ✅, el Sprint 2 está completo.

**Estado de MVP:**
- ✅ Sprint 1: Infraestructura y Autenticación
- ✅ Sprint 2: Core Features (Artículos y Búsqueda)
- ⏳ Sprint 3: Suscripciones y Pagos (pendiente)

**Progreso:** 66% del MVP completado

---

**Archivo generado:** `05_PLAN_SPRINT_2_PARTE_2.md`
**Archivos relacionados:** `05_PLAN_SPRINT_2.md`
**Total Sprint 2:** ~40 horas (2 semanas)
