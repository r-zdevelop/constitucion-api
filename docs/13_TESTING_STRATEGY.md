# 13 - ESTRATEGIA DE TESTING

**Proyecto:** LexEcuador - API REST para Constitución de Ecuador
**Propósito:** Guía completa de testing (Unit, Integration, E2E) con PHPUnit y herramientas modernas
**Audiencia:** Desarrollador PHP 3+ años con conocimiento de SOLID y Clean Architecture

---

## 📋 ÍNDICE

1. [Pirámide de Testing](#pirámide-de-testing)
2. [Configuración de PHPUnit](#configuración-de-phpunit)
3. [Tests Unitarios](#tests-unitarios)
4. [Tests de Integración](#tests-de-integración)
5. [Tests E2E (API)](#tests-e2e-api)
6. [Tests de Base de Datos](#tests-de-base-de-datos)
7. [Mocking y Fixtures](#mocking-y-fixtures)
8. [Coverage y CI/CD](#coverage-y-cicd)

---

## 🔺 PIRÁMIDE DE TESTING

```
         /\
        /  \      E2E Tests (10%)
       /    \     - Tests completos de API
      /------\    - Flujos de usuario completos
     /        \
    /          \  Integration Tests (30%)
   /            \ - Tests de Use Cases
  /--------------\- Tests de Repositories
 /                \
/                  \ Unit Tests (60%)
--------------------
- Tests de Value Objects
- Tests de Entities
- Tests de Services
```

### Objetivos de Coverage

- **Unit Tests:** >80% coverage
- **Integration Tests:** >70% coverage de Use Cases
- **E2E Tests:** Cubrir todos los endpoints críticos

---

## ⚙️ CONFIGURACIÓN DE PHPUNIT

### 1. Instalación

```bash
# PHPUnit
composer require --dev phpunit/phpunit

# Extensiones útiles
composer require --dev symfony/phpunit-bridge
composer require --dev doctrine/doctrine-fixtures-bundle
composer require --dev dama/doctrine-test-bundle
```

---

### 2. Configuración de PHPUnit

```xml
<!-- phpunit.xml.dist -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         executionOrder="random"
         beStrictAboutCoverageMetadata="true"
         beStrictAboutOutputDuringTests="true"
         failOnRisky="true"
         failOnWarning="true">

    <php>
        <ini name="display_errors" value="1" />
        <ini name="error_reporting" value="-1" />
        <server name="APP_ENV" value="test" force="true" />
        <server name="SHELL_VERBOSITY" value="-1" />
        <server name="SYMFONY_PHPUNIT_REMOVE" value="" />
        <server name="SYMFONY_PHPUNIT_VERSION" value="10.5" />

        <!-- Base de datos de test -->
        <env name="DATABASE_URL" value="mysql://root:root@127.0.0.1:3306/lexecuador_test"/>

        <!-- Stripe test keys -->
        <env name="STRIPE_SECRET_KEY" value="sk_test_51QR3sT4uVwXyZaBc"/>
        <env name="STRIPE_PUBLIC_KEY" value="pk_test_51QR3sT4uVwXyZaBc"/>
    </php>

    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
        <testsuite name="E2E">
            <directory>tests/E2E</directory>
        </testsuite>
    </testsuites>

    <coverage cacheDirectory=".phpunit.cache/code-coverage"
              processUncoveredFiles="true">
        <include>
            <directory suffix=".php">src</directory>
        </include>
        <exclude>
            <directory>src/Infrastructure/Presentation</directory>
            <file>src/Kernel.php</file>
        </exclude>
        <report>
            <html outputDirectory=".phpunit.cache/coverage-html"/>
            <text outputFile="php://stdout" showUncoveredFiles="false"/>
        </report>
    </coverage>

    <extensions>
        <!-- Reset DB entre tests -->
        <extension class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
    </extensions>

    <listeners>
        <listener class="Symfony\Bridge\PhpUnit\SymfonyTestsListener"/>
    </listeners>
</phpunit>
```

---

### 3. Bootstrap de Tests

```php
<?php
// tests/bootstrap.php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Configurar timezone
date_default_timezone_set('UTC');

// Limpiar base de datos de test antes de todos los tests
passthru(sprintf(
    'php "%s/bin/console" doctrine:database:drop --force --env=test --quiet 2>/dev/null',
    dirname(__DIR__)
));

passthru(sprintf(
    'php "%s/bin/console" doctrine:database:create --env=test --quiet',
    dirname(__DIR__)
));

passthru(sprintf(
    'php "%s/bin/console" doctrine:migrations:migrate --no-interaction --env=test --quiet',
    dirname(__DIR__)
));

echo "Test database initialized\n";
```

---

## 🧪 TESTS UNITARIOS

Los tests unitarios se enfocan en probar clases individuales de forma aislada.

### 1. Test de Value Objects

```php
<?php
// tests/Unit/Domain/ValueObject/EmailTest.php

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Email;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    public function testCreateValidEmail(): void
    {
        // Arrange & Act
        $email = new Email('user@example.com');

        // Assert
        $this->assertEquals('user@example.com', $email->getValue());
        $this->assertEquals('user@example.com', (string) $email);
    }

    public function testEmailIsNormalizedToLowercase(): void
    {
        $email = new Email('USER@EXAMPLE.COM');

        $this->assertEquals('user@example.com', $email->getValue());
    }

    public function testInvalidEmailThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email');

        new Email('invalid-email');
    }

    /**
     * @dataProvider invalidEmailProvider
     */
    public function testVariousInvalidEmails(string $invalidEmail): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Email($invalidEmail);
    }

    public static function invalidEmailProvider(): array
    {
        return [
            [''],
            ['   '],
            ['not-an-email'],
            ['missing@domain'],
            ['@nodomain.com'],
            ['spaces in@email.com'],
        ];
    }
}
```

---

### 2. Test de ArticleNumber Value Object

```php
<?php
// tests/Unit/Domain/ValueObject/ArticleNumberTest.php

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\ArticleNumber;
use PHPUnit\Framework\TestCase;

final class ArticleNumberTest extends TestCase
{
    public function testCreateValidArticleNumber(): void
    {
        $number = new ArticleNumber(1);

        $this->assertEquals(1, $number->getValue());
        $this->assertFalse($number->isPremium());
    }

    public function testArticleOver100IsPremium(): void
    {
        $number = new ArticleNumber(150);

        $this->assertTrue($number->isPremium());
    }

    public function testArticleNumberBelowMinThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Article number must be between 1 and 467');

        new ArticleNumber(0);
    }

    public function testArticleNumberAboveMaxThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ArticleNumber(468);
    }

    /**
     * @dataProvider validArticleNumberProvider
     */
    public function testVariousValidArticleNumbers(int $number, bool $expectedPremium): void
    {
        $articleNumber = new ArticleNumber($number);

        $this->assertEquals($number, $articleNumber->getValue());
        $this->assertEquals($expectedPremium, $articleNumber->isPremium());
    }

    public static function validArticleNumberProvider(): array
    {
        return [
            [1, false],      // Primer artículo
            [50, false],     // Artículo gratuito
            [100, false],    // Último artículo gratuito
            [101, true],     // Primer artículo premium
            [200, true],     // Artículo premium
            [467, true],     // Último artículo
        ];
    }
}
```

---

### 3. Test de Entidades (Domain Logic)

```php
<?php
// tests/Unit/Domain/Entity/UserTest.php

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Role;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testRegisterNewUser(): void
    {
        // Arrange & Act
        $user = User::register(
            email: new Email('test@example.com'),
            hashedPassword: 'hashed_password',
            name: 'Test User',
            role: Role::FREE
        );

        // Assert
        $this->assertNotEmpty($user->getId());
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('Test User', $user->getName());
        $this->assertEquals(Role::FREE, $user->getRole());
        $this->assertTrue($user->isActive());
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->getCreatedAt());
    }

    public function testUpgradeToPremium(): void
    {
        // Arrange
        $user = User::register(
            email: new Email('test@example.com'),
            hashedPassword: 'hashed',
            name: 'Test',
            role: Role::FREE
        );

        // Act
        $user->upgradeToPlan(Role::PREMIUM);

        // Assert
        $this->assertEquals(Role::PREMIUM, $user->getRole());
    }

    public function testCannotDowngradeWithUpgradeMethod(): void
    {
        $user = User::register(
            email: new Email('test@example.com'),
            hashedPassword: 'hashed',
            name: 'Test',
            role: Role::PREMIUM
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Cannot downgrade or set same role');

        $user->upgradeToPlan(Role::FREE);
    }

    public function testHasPremiumAccessForPremiumUser(): void
    {
        $user = User::register(
            email: new Email('test@example.com'),
            hashedPassword: 'hashed',
            name: 'Test',
            role: Role::PREMIUM
        );

        $this->assertTrue($user->hasPremiumAccess());
    }

    public function testHasNoPremiumAccessForFreeUser(): void
    {
        $user = User::register(
            email: new Email('test@example.com'),
            hashedPassword: 'hashed',
            name: 'Test',
            role: Role::FREE
        );

        $this->assertFalse($user->hasPremiumAccess());
    }
}
```

---

### 4. Test de Subscription Entity

```php
<?php
// tests/Unit/Domain/Entity/SubscriptionTest.php

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Entity\Subscription;
use App\Domain\ValueObject\SubscriptionPlan;
use PHPUnit\Framework\TestCase;

final class SubscriptionTest extends TestCase
{
    public function testCreateSubscription(): void
    {
        $subscription = Subscription::create(
            userId: 'user-123',
            plan: SubscriptionPlan::PREMIUM,
            stripeSubscriptionId: 'sub_123',
            stripeCustomerId: 'cus_123',
            currentPeriodEnd: new \DateTimeImmutable('+30 days')
        );

        $this->assertNotEmpty($subscription->getId());
        $this->assertEquals('user-123', $subscription->getUserId());
        $this->assertEquals(SubscriptionPlan::PREMIUM, $subscription->getPlan());
        $this->assertTrue($subscription->isActive());
    }

    public function testScheduleCancel(): void
    {
        $currentPeriodEnd = new \DateTimeImmutable('+30 days');

        $subscription = Subscription::create(
            userId: 'user-123',
            plan: SubscriptionPlan::PREMIUM,
            currentPeriodEnd: $currentPeriodEnd
        );

        $subscription->scheduleCancel();

        $this->assertTrue($subscription->isActive());
        $this->assertNotNull($subscription->getCancelAt());
        $this->assertEquals($currentPeriodEnd, $subscription->getCancelAt());
    }

    public function testCancelImmediately(): void
    {
        $subscription = Subscription::create(
            userId: 'user-123',
            plan: SubscriptionPlan::PREMIUM
        );

        $subscription->cancelImmediately();

        $this->assertFalse($subscription->isActive());
        $this->assertNotNull($subscription->getCanceledAt());
        $this->assertNotNull($subscription->getEndedAt());
    }
}
```

---

## 🔗 TESTS DE INTEGRACIÓN

Los tests de integración prueban la interacción entre múltiples componentes.

### 1. Test de Repository

```php
<?php
// tests/Integration/Infrastructure/Repository/UserRepositoryTest.php

namespace App\Tests\Integration\Infrastructure\Repository;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Role;
use App\Infrastructure\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserRepositoryTest extends KernelTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->repository = self::getContainer()->get(UserRepository::class);
    }

    public function testSaveAndFindUser(): void
    {
        // Arrange
        $user = User::register(
            email: new Email('test@example.com'),
            hashedPassword: 'hashed',
            name: 'Test User',
            role: Role::FREE
        );

        // Act
        $this->repository->save($user);

        $foundUser = $this->repository->findById($user->getId());

        // Assert
        $this->assertNotNull($foundUser);
        $this->assertEquals($user->getId(), $foundUser->getId());
        $this->assertEquals('test@example.com', $foundUser->getEmail());
    }

    public function testFindByEmail(): void
    {
        $user = User::register(
            email: new Email('find@example.com'),
            hashedPassword: 'hashed',
            name: 'Find Me',
            role: Role::FREE
        );

        $this->repository->save($user);

        $foundUser = $this->repository->findByEmail('find@example.com');

        $this->assertNotNull($foundUser);
        $this->assertEquals($user->getId(), $foundUser->getId());
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $foundUser = $this->repository->findByEmail('nonexistent@example.com');

        $this->assertNull($foundUser);
    }
}
```

---

### 2. Test de Use Case (con Mocks)

```php
<?php
// tests/Integration/Application/UseCase/RegisterUserUseCaseTest.php

namespace App\Tests\Integration\Application\UseCase;

use App\Application\UseCase\Auth\RegisterUserUseCase;
use App\Domain\Repository\UserRepositoryInterface;
use App\Infrastructure\Service\PasswordHasher;
use PHPUnit\Framework\TestCase;

final class RegisterUserUseCaseTest extends TestCase
{
    public function testExecuteRegistersNewUser(): void
    {
        // Arrange - Crear mocks
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $passwordHasher = $this->createMock(PasswordHasher::class);

        // Configurar comportamiento de los mocks
        $userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('new@example.com')
            ->willReturn(null);  // Email no existe

        $passwordHasher
            ->expects($this->once())
            ->method('hash')
            ->with('password123')
            ->willReturn('hashed_password');

        $userRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function ($user) {
                return $user->getEmail() === 'new@example.com'
                    && $user->getName() === 'New User';
            }));

        $useCase = new RegisterUserUseCase($userRepository, $passwordHasher);

        // Act
        $result = $useCase->execute([
            'email' => 'new@example.com',
            'password' => 'password123',
            'name' => 'New User',
        ]);

        // Assert
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals('new@example.com', $result['user']->getEmail());
    }

    public function testExecuteThrowsExceptionWhenEmailExists(): void
    {
        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $passwordHasher = $this->createMock(PasswordHasher::class);

        // Email ya existe
        $existingUser = $this->createMock(User::class);
        $userRepository->method('findByEmail')->willReturn($existingUser);

        $useCase = new RegisterUserUseCase($userRepository, $passwordHasher);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Email already registered');

        $useCase->execute([
            'email' => 'existing@example.com',
            'password' => 'password123',
            'name' => 'Test',
        ]);
    }
}
```

---

### 3. Test de Use Case (con Base de Datos Real)

```php
<?php
// tests/Integration/Application/UseCase/GetArticleByNumberUseCaseTest.php

namespace App\Tests\Integration\Application\UseCase;

use App\Application\UseCase\Article\GetArticleByNumberUseCase;
use App\Domain\Entity\Article;
use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Role;
use App\Infrastructure\Repository\ArticleRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GetArticleByNumberUseCaseTest extends KernelTestCase
{
    private GetArticleByNumberUseCase $useCase;
    private ArticleRepository $articleRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->useCase = self::getContainer()->get(GetArticleByNumberUseCase::class);
        $this->articleRepository = self::getContainer()->get(ArticleRepository::class);

        // Crear artículos de prueba
        $this->createTestArticles();
    }

    private function createTestArticles(): void
    {
        $article1 = Article::create(
            number: 1,
            title: 'Artículo 1',
            content: 'Contenido del artículo 1'
        );

        $article150 = Article::create(
            number: 150,
            title: 'Artículo 150',
            content: 'Contenido del artículo 150 (premium)'
        );

        $this->articleRepository->save($article1);
        $this->articleRepository->save($article150);
    }

    public function testFreeUserCanAccessArticle1(): void
    {
        $freeUser = User::register(
            email: new Email('free@example.com'),
            hashedPassword: 'hashed',
            name: 'Free User',
            role: Role::FREE
        );

        $result = $this->useCase->execute(1, $freeUser);

        $this->assertArrayHasKey('article', $result);
        $this->assertEquals(1, $result['article']->getNumber());
    }

    public function testFreeUserCannotAccessArticle150(): void
    {
        $freeUser = User::register(
            email: new Email('free@example.com'),
            hashedPassword: 'hashed',
            name: 'Free User',
            role: Role::FREE
        );

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Premium access required');

        $this->useCase->execute(150, $freeUser);
    }

    public function testPremiumUserCanAccessArticle150(): void
    {
        $premiumUser = User::register(
            email: new Email('premium@example.com'),
            hashedPassword: 'hashed',
            name: 'Premium User',
            role: Role::PREMIUM
        );

        $result = $this->useCase->execute(150, $premiumUser);

        $this->assertArrayHasKey('article', $result);
        $this->assertEquals(150, $result['article']->getNumber());
    }
}
```

---

## 🌐 TESTS E2E (API)

Los tests E2E prueban el flujo completo desde HTTP request hasta response.

### 1. Test de Autenticación

```php
<?php
// tests/E2E/Auth/RegistrationTest.php

namespace App\Tests\E2E\Auth;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationTest extends WebTestCase
{
    public function testSuccessfulRegistration(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'newuser@example.com',
            'password' => 'Password123!',
            'name' => 'New User',
        ]));

        $this->assertResponseIsSuccessful();
        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('token', $data);
        $this->assertEquals('newuser@example.com', $data['user']['email']);
    }

    public function testRegistrationWithInvalidEmail(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'invalid-email',
            'password' => 'Password123!',
            'name' => 'Test',
        ]));

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('validation-failed', $data['type']);
        $this->assertArrayHasKey('violations', $data);
    }

    public function testRegistrationWithDuplicateEmail(): void
    {
        $client = static::createClient();

        // Primera registro
        $client->request('POST', '/api/v1/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'duplicate@example.com',
            'password' => 'Password123!',
            'name' => 'First User',
        ]));

        $this->assertResponseIsSuccessful();

        // Segundo registro (debería fallar)
        $client->request('POST', '/api/v1/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'duplicate@example.com',
            'password' => 'Password123!',
            'name' => 'Second User',
        ]));

        $this->assertResponseStatusCodeSame(400);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('already registered', $data['detail']);
    }
}
```

---

### 2. Test de Login

```php
<?php
// tests/E2E/Auth/LoginTest.php

namespace App\Tests\E2E\Auth;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginTest extends WebTestCase
{
    private function registerTestUser(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'logintest@example.com',
            'password' => 'Password123!',
            'name' => 'Login Test',
        ]));
    }

    public function testSuccessfulLogin(): void
    {
        $this->registerTestUser();

        $client = static::createClient();

        $client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'logintest@example.com',
            'password' => 'Password123!',
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('token', $data);
        $this->assertArrayHasKey('refreshToken', $data);
        $this->assertArrayHasKey('user', $data);
        $this->assertNotEmpty($data['token']);
    }

    public function testLoginWithInvalidPassword(): void
    {
        $this->registerTestUser();

        $client = static::createClient();

        $client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'logintest@example.com',
            'password' => 'WrongPassword',
        ]));

        $this->assertResponseStatusCodeSame(401);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertStringContainsString('Invalid credentials', $data['detail']);
    }

    public function testLoginWithNonExistentEmail(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'nonexistent@example.com',
            'password' => 'Password123!',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }
}
```

---

### 3. Test de Endpoints de Artículos

```php
<?php
// tests/E2E/Article/GetArticlesTest.php

namespace App\Tests\E2E\Article;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GetArticlesTest extends WebTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Login y obtener token
        $this->token = $this->getAuthToken();
    }

    private function getAuthToken(): string
    {
        $client = static::createClient();

        // Registrar usuario
        $client->request('POST', '/api/v1/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'articlestest@example.com',
            'password' => 'Password123!',
            'name' => 'Articles Test',
        ]));

        $data = json_decode($client->getResponse()->getContent(), true);

        return $data['token'];
    }

    public function testGetArticles(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/articles', [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$this->token}",
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('articles', $data);
        $this->assertArrayHasKey('meta', $data);
        $this->assertIsArray($data['articles']);
    }

    public function testGetArticlesPagination(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/articles?page=1&limit=10', [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$this->token}",
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals(1, $data['meta']['page']);
        $this->assertEquals(10, $data['meta']['limit']);
        $this->assertCount(10, $data['articles']);
    }

    public function testGetArticleById(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/articles/1', [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$this->token}",
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('article', $data);
        $this->assertEquals(1, $data['article']['number']);
    }

    public function testGetArticleWithoutAuthToken(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/articles/1');

        // Artículos públicos son accesibles sin autenticación
        $this->assertResponseIsSuccessful();
    }
}
```

---

## 💾 TESTS DE BASE DE DATOS

### 1. Test de Fixtures

```php
<?php
// tests/Integration/DataFixtures/UserFixturesTest.php

namespace App\Tests\Integration\DataFixtures;

use App\DataFixtures\UserFixtures;
use App\Infrastructure\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Doctrine\Persistence\ObjectManager;

final class UserFixturesTest extends KernelTestCase
{
    public function testFixturesLoadAdminUser(): void
    {
        self::bootKernel();

        $container = self::getContainer();
        $manager = $container->get('doctrine')->getManager();
        $userRepository = $container->get(UserRepository::class);

        // Cargar fixtures
        $fixtures = new UserFixtures($container->get('security.user_password_hasher'));
        $fixtures->load($manager);

        // Verificar que el admin existe
        $admin = $userRepository->findByEmail('admin@lexecuador.com');

        $this->assertNotNull($admin);
        $this->assertEquals('ROLE_ADMIN', $admin->getRole()->value);
    }
}
```

---

### 2. Test de Migrations

```php
<?php
// tests/Integration/Doctrine/MigrationsTest.php

namespace App\Tests\Integration\Doctrine;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class MigrationsTest extends KernelTestCase
{
    public function testMigrationsExecuteSuccessfully(): void
    {
        self::bootKernel();

        $application = new Application(self::$kernel);

        $command = $application->find('doctrine:migrations:migrate');
        $commandTester = new CommandTester($command);

        $commandTester->execute([
            '--no-interaction' => true,
            '--env' => 'test',
        ]);

        $output = $commandTester->getDisplay();

        $this->assertStringContainsString('successfully migrated', $output);
        $this->assertEquals(0, $commandTester->getStatusCode());
    }
}
```

---

## 🎭 MOCKING Y FIXTURES

### 1. Trait para Crear Mocks

```php
<?php
// tests/Support/MockBuilder.php

namespace App\Tests\Support;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Role;

trait MockBuilder
{
    protected function createFreeUser(): User
    {
        return User::register(
            email: new Email('free@example.com'),
            hashedPassword: 'hashed',
            name: 'Free User',
            role: Role::FREE
        );
    }

    protected function createPremiumUser(): User
    {
        return User::register(
            email: new Email('premium@example.com'),
            hashedPassword: 'hashed',
            name: 'Premium User',
            role: Role::PREMIUM
        );
    }

    protected function createEnterpriseUser(): User
    {
        return User::register(
            email: new Email('enterprise@example.com'),
            hashedPassword: 'hashed',
            name: 'Enterprise User',
            role: Role::ENTERPRISE
        );
    }
}
```

Uso:

```php
use App\Tests\Support\MockBuilder;

final class SomeTest extends TestCase
{
    use MockBuilder;

    public function testSomething(): void
    {
        $user = $this->createPremiumUser();

        // ... test logic
    }
}
```

---

## 📊 COVERAGE Y CI/CD

### 1. Generar Reporte de Coverage

```bash
# HTML coverage report
XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-html .phpunit.cache/coverage-html

# Abrir en navegador
open .phpunit.cache/coverage-html/index.html

# Coverage en consola
XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-text
```

---

### 2. GitHub Actions CI

```yaml
# .github/workflows/tests.yml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  tests:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: lexecuador_test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=3

    steps:
      - uses: actions/checkout@v3

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, xml, ctype, iconv, intl, pdo_mysql
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run migrations
        run: php bin/console doctrine:migrations:migrate --no-interaction --env=test

      - name: Run tests
        run: XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-text --coverage-clover=coverage.xml

      - name: Upload coverage to Codecov
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
          fail_ci_if_error: true
```

---

## ✅ CHECKLIST DE TESTING

### Configuración

- [ ] Instalar PHPUnit y extensiones
- [ ] Configurar phpunit.xml.dist
- [ ] Crear bootstrap.php para tests
- [ ] Configurar base de datos de test
- [ ] Instalar DAMA Doctrine Test Bundle

### Tests Unitarios

- [ ] Tests de Value Objects (Email, ArticleNumber, Money)
- [ ] Tests de Entities (User, Article, Subscription)
- [ ] Tests de métodos de negocio en entidades
- [ ] Coverage >80%

### Tests de Integración

- [ ] Tests de Repositories
- [ ] Tests de Use Cases (con mocks)
- [ ] Tests de Use Cases (con DB real)
- [ ] Tests de Services

### Tests E2E

- [ ] Tests de autenticación (register, login)
- [ ] Tests de endpoints de artículos
- [ ] Tests de endpoints de suscripciones
- [ ] Tests de pagos (con Stripe test mode)
- [ ] Tests de webhooks

### CI/CD

- [ ] Configurar GitHub Actions
- [ ] Ejecutar tests en cada PR
- [ ] Generar coverage reports
- [ ] Subir coverage a Codecov
- [ ] Bloquear merge si tests fallan

---

## 🚀 COMANDOS ÚTILES

```bash
# Ejecutar todos los tests
php vendor/bin/phpunit

# Solo tests unitarios
php vendor/bin/phpunit --testsuite Unit

# Solo tests de integración
php vendor/bin/phpunit --testsuite Integration

# Solo tests E2E
php vendor/bin/phpunit --testsuite E2E

# Con coverage
XDEBUG_MODE=coverage php vendor/bin/phpunit --coverage-html .phpunit.cache/coverage-html

# Un test específico
php vendor/bin/phpunit tests/Unit/Domain/ValueObject/EmailTest.php

# Con output verbose
php vendor/bin/phpunit --testdox

# Filtrar por nombre
php vendor/bin/phpunit --filter testCreateValidEmail
```

---

**Archivo generado:** `13_TESTING_STRATEGY.md`
**Siguiente:** `14_DEPLOYMENT_GUIDE.md` (Guía de Deployment en Ubuntu + Apache)
