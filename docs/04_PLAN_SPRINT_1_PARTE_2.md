# 04 - Plan Sprint 1: Infraestructura Base (Parte 2)

**Continuación de:** `04_PLAN_SPRINT_1.md`
**Fase:** 5 - Implementar Autenticación (continuación)
**Tiempo estimado restante:** 6-8 horas

---

## Fase 5: Implementar Autenticación (Continuación)

### Tarea 5.2: Crear Use Case RegisterUserUseCase

**Objetivo:** Encapsular lógica de negocio del registro de usuarios

**Archivo:** `src/Application/UseCase/Auth/RegisterUserUseCase.php`

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCase\Auth;

use App\Domain\Entity\User;
use App\Domain\Exception\DuplicateEmailException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Role;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class RegisterUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $users,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    /**
     * @throws DuplicateEmailException
     */
    public function execute(string $email, string $plainPassword, string $name): User
    {
        // 1. Validar que el email no exista
        $emailVO = Email::fromString($email);

        if ($this->users->findByEmail($emailVO->toString()) !== null) {
            throw new DuplicateEmailException(
                sprintf('Email "%s" is already registered', $email)
            );
        }

        // 2. Crear usuario
        $user = User::register(
            email: $emailVO,
            hashedPassword: '', // Temporal, se hashea después
            name: $name,
            role: Role::FREE
        );

        // 3. Hashear password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
        $user->changePassword($hashedPassword);

        // 4. Persistir
        $this->users->save($user);

        return $user;
    }
}
```

**Crear excepción:** `src/Domain/Exception/DuplicateEmailException.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class DuplicateEmailException extends \DomainException
{
}
```

**Test unitario:** `tests/Unit/Application/UseCase/Auth/RegisterUserUseCaseTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\UseCase\Auth;

use App\Application\UseCase\Auth\RegisterUserUseCase;
use App\Domain\Entity\User;
use App\Domain\Exception\DuplicateEmailException;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Role;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class RegisterUserUseCaseTest extends TestCase
{
    private UserRepositoryInterface $userRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private RegisterUserUseCase $useCase;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $this->useCase = new RegisterUserUseCase($this->userRepository, $this->passwordHasher);
    }

    public function testRegisterNewUser(): void
    {
        // Given: Email no existe
        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('user@example.com')
            ->willReturn(null);

        // Given: Password se hashea
        $this->passwordHasher
            ->expects($this->once())
            ->method('hashPassword')
            ->willReturn('$2y$12$hashed_password');

        // Given: Usuario se guarda
        $this->userRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (User $user) {
                return $user->getEmail() === 'user@example.com'
                    && $user->getName() === 'John Doe'
                    && $user->getRole() === Role::FREE;
            }));

        // When: Registro de usuario
        $user = $this->useCase->execute('user@example.com', 'SecurePass123!', 'John Doe');

        // Then: Usuario creado correctamente
        $this->assertSame('user@example.com', $user->getEmail());
        $this->assertSame('John Doe', $user->getName());
        $this->assertSame(Role::FREE, $user->getRole());
    }

    public function testRegisterWithDuplicateEmailThrowsException(): void
    {
        // Given: Email ya existe
        $existingUser = $this->createMock(User::class);
        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('existing@example.com')
            ->willReturn($existingUser);

        // Then: Debe lanzar excepción
        $this->expectException(DuplicateEmailException::class);
        $this->expectExceptionMessage('Email "existing@example.com" is already registered');

        // When: Intento de registro
        $this->useCase->execute('existing@example.com', 'SecurePass123!', 'John Doe');
    }
}
```

**Ejecutar test:**
```bash
php bin/phpunit tests/Unit/Application/UseCase/Auth/RegisterUserUseCaseTest.php
```

**Criterios de aceptación:**
- [ ] Use Case creado
- [ ] Validación de email duplicado funciona
- [ ] Password se hashea correctamente
- [ ] Usuario se persiste con rol FREE
- [ ] Tests pasan al 100%

**Tiempo estimado:** 1 hora

---

### Tarea 5.3: Crear Use Case LoginUserUseCase

**Objetivo:** Validar credenciales y generar JWT token

**Archivo:** `src/Application/UseCase/Auth/LoginUserUseCase.php`

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCase\Auth;

use App\Domain\Entity\User;
use App\Domain\Exception\InvalidCredentialsException;
use App\Domain\Repository\UserRepositoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final readonly class LoginUserUseCase
{
    public function __construct(
        private UserRepositoryInterface $users,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    /**
     * @throws InvalidCredentialsException
     */
    public function execute(string $email, string $plainPassword): User
    {
        // 1. Buscar usuario por email
        $user = $this->users->findByEmail($email);

        if ($user === null) {
            throw new InvalidCredentialsException('Invalid credentials');
        }

        // 2. Verificar que esté activo
        if (!$user->isActive()) {
            throw new InvalidCredentialsException('Account is deactivated');
        }

        // 3. Verificar password
        if (!$this->passwordHasher->isPasswordValid($user, $plainPassword)) {
            throw new InvalidCredentialsException('Invalid credentials');
        }

        return $user;
    }
}
```

**Crear excepción:** `src/Domain/Exception/InvalidCredentialsException.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Exception;

final class InvalidCredentialsException extends \DomainException
{
}
```

**Criterios de aceptación:**
- [ ] Use Case creado
- [ ] Validación de email funciona
- [ ] Validación de password funciona
- [ ] Verificación de cuenta activa funciona
- [ ] Mensaje de error genérico (seguridad)

**Tiempo estimado:** 45 minutos

---

### Tarea 5.4: Crear DTOs Adicionales

#### DTO LoginRequest

**Archivo:** `src/Presentation/API/Request/LoginRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Presentation\API\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class LoginRequest
{
    public function __construct(
        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(message: 'Invalid email format')]
        public string $email,

        #[Assert\NotBlank(message: 'Password is required')]
        public string $password,
    ) {}
}
```

---

#### DTO UserResponse

**Archivo:** `src/Presentation/API/Response/UserResponse.php`

```php
<?php

declare(strict_types=1);

namespace App\Presentation\API\Response;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Role;

final readonly class UserResponse
{
    public function __construct(
        public string $id,
        public string $email,
        public string $name,
        public Role $role,
        public bool $isActive,
        public \DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntity(User $user): self
    {
        return new self(
            id: $user->getId(),
            email: $user->getEmail(),
            name: $user->getName(),
            role: $user->getRole(),
            isActive: $user->isActive(),
            createdAt: $user->getCreatedAt()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'role' => $this->role->value,
            'isActive' => $this->isActive,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
```

---

#### DTO AuthResponse

**Archivo:** `src/Presentation/API/Response/AuthResponse.php`

```php
<?php

declare(strict_types=1);

namespace App\Presentation\API\Response;

final readonly class AuthResponse
{
    public function __construct(
        public UserResponse $user,
        public string $token,
        public ?string $refreshToken = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'user' => $this->user->toArray(),
            'token' => $this->token,
        ];

        if ($this->refreshToken !== null) {
            $data['refreshToken'] = $this->refreshToken;
        }

        return $data;
    }
}
```

**Criterios de aceptación:**
- [ ] DTOs creados correctamente
- [ ] LoginRequest con validaciones
- [ ] UserResponse mapea desde entidad
- [ ] AuthResponse incluye user + token

**Tiempo estimado:** 30 minutos

---

### Tarea 5.5: Crear AuthController

**Objetivo:** Endpoints de autenticación (register, login)

**Archivo:** `src/Presentation/API/Controller/AuthController.php`

```php
<?php

declare(strict_types=1);

namespace App\Presentation\API\Controller;

use App\Application\UseCase\Auth\LoginUserUseCase;
use App\Application\UseCase\Auth\RegisterUserUseCase;
use App\Domain\Exception\DuplicateEmailException;
use App\Domain\Exception\InvalidCredentialsException;
use App\Presentation\API\Request\LoginRequest;
use App\Presentation\API\Request\RegisterRequest;
use App\Presentation\API\Response\AuthResponse;
use App\Presentation\API\Response\UserResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[Route('/api/v1/auth', name: 'api_v1_auth_')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager
    ) {}

    #[Route('/register', name: 'register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'Register a new user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'name'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'SecurePass123!'),
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                ]
            )
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'User registered successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', type: 'object'),
                        new OA\Property(property: 'token', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 409, description: 'Email already exists'),
        ]
    )]
    public function register(
        #[MapRequestPayload] RegisterRequest $request,
        RegisterUserUseCase $registerUser
    ): JsonResponse {
        try {
            $user = $registerUser->execute(
                $request->email,
                $request->password,
                $request->name
            );

            $token = $this->jwtManager->create($user);

            $response = new AuthResponse(
                user: UserResponse::fromEntity($user),
                token: $token
            );

            return $this->json($response->toArray(), Response::HTTP_CREATED);

        } catch (DuplicateEmailException $e) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/duplicate-email',
                'title' => 'Duplicate Email',
                'status' => 409,
                'detail' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/validation-error',
                'title' => 'Validation Error',
                'status' => 400,
                'detail' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'Login with email and password',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'SecurePass123!'),
                ]
            )
        ),
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'user', type: 'object'),
                        new OA\Property(property: 'token', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid credentials'),
        ]
    )]
    public function login(
        #[MapRequestPayload] LoginRequest $request,
        LoginUserUseCase $loginUser
    ): JsonResponse {
        try {
            $user = $loginUser->execute(
                $request->email,
                $request->password
            );

            $token = $this->jwtManager->create($user);

            $response = new AuthResponse(
                user: UserResponse::fromEntity($user),
                token: $token
            );

            return $this->json($response->toArray());

        } catch (InvalidCredentialsException $e) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/invalid-credentials',
                'title' => 'Invalid Credentials',
                'status' => 401,
                'detail' => $e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'Get current authenticated user',
        security: [['bearerAuth' => []]],
        tags: ['Authentication'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string'),
                        new OA\Property(property: 'email', type: 'string'),
                        new OA\Property(property: 'name', type: 'string'),
                        new OA\Property(property: 'role', type: 'string'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
        ]
    )]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/unauthorized',
                'title' => 'Unauthorized',
                'status' => 401,
                'detail' => 'Authentication required',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $response = UserResponse::fromEntity($user);

        return $this->json($response->toArray());
    }
}
```

**Criterios de aceptación:**
- [ ] Controller creado con 3 endpoints
- [ ] POST /api/v1/auth/register funciona
- [ ] POST /api/v1/auth/login funciona
- [ ] GET /api/v1/auth/me funciona (requiere JWT)
- [ ] Documentación OpenAPI completa
- [ ] Manejo de errores RFC 7807

**Tiempo estimado:** 2 horas

---

### Tarea 5.6: Tests de Integración

**Objetivo:** Testear endpoints de autenticación end-to-end

**Archivo:** `tests/Functional/API/AuthControllerTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional\API;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Role;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AuthControllerTest extends WebTestCase
{
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Limpiar base de datos
        $container = static::getContainer();
        $em = $container->get('doctrine')->getManager();
        $em->createQuery('DELETE FROM App\Domain\Entity\User')->execute();
    }

    public function testRegisterWithValidData(): void
    {
        $this->client->request('POST', '/api/v1/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'newuser@example.com',
            'password' => 'SecurePass123!',
            'name' => 'John Doe',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('token', $data);
        $this->assertSame('newuser@example.com', $data['user']['email']);
        $this->assertSame('John Doe', $data['user']['name']);
        $this->assertSame('ROLE_FREE', $data['user']['role']);
    }

    public function testRegisterWithDuplicateEmail(): void
    {
        // Primer registro
        $this->client->request('POST', '/api/v1/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'duplicate@example.com',
            'password' => 'SecurePass123!',
            'name' => 'First User',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Segundo registro con mismo email
        $this->client->request('POST', '/api/v1/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'duplicate@example.com',
            'password' => 'AnotherPass123!',
            'name' => 'Second User',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Duplicate Email', $data['title']);
    }

    public function testRegisterWithInvalidEmail(): void
    {
        $this->client->request('POST', '/api/v1/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'invalid-email',
            'password' => 'SecurePass123!',
            'name' => 'John Doe',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRegisterWithWeakPassword(): void
    {
        $this->client->request('POST', '/api/v1/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'user@example.com',
            'password' => 'weak',
            'name' => 'John Doe',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testLoginWithValidCredentials(): void
    {
        // Registrar usuario primero
        $this->client->request('POST', '/api/v1/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'loginuser@example.com',
            'password' => 'SecurePass123!',
            'name' => 'Login User',
        ]));

        // Login
        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'loginuser@example.com',
            'password' => 'SecurePass123!',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('token', $data);
        $this->assertSame('loginuser@example.com', $data['user']['email']);
    }

    public function testLoginWithInvalidPassword(): void
    {
        // Registrar usuario
        $this->client->request('POST', '/api/v1/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'user@example.com',
            'password' => 'CorrectPass123!',
            'name' => 'User',
        ]));

        // Login con password incorrecto
        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'user@example.com',
            'password' => 'WrongPassword',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testLoginWithNonExistentEmail(): void
    {
        $this->client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'nonexistent@example.com',
            'password' => 'SomePassword123!',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetCurrentUserWithValidToken(): void
    {
        // Registrar y obtener token
        $this->client->request('POST', '/api/v1/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'tokenuser@example.com',
            'password' => 'SecurePass123!',
            'name' => 'Token User',
        ]));

        $registerData = json_decode($this->client->getResponse()->getContent(), true);
        $token = $registerData['token'];

        // GET /me con token
        $this->client->request('GET', '/api/v1/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('tokenuser@example.com', $data['email']);
        $this->assertSame('Token User', $data['name']);
    }

    public function testGetCurrentUserWithoutToken(): void
    {
        $this->client->request('GET', '/api/v1/auth/me');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetCurrentUserWithInvalidToken(): void
    {
        $this->client->request('GET', '/api/v1/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer invalid_token_here',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
```

**Ejecutar tests:**
```bash
# Crear base de datos de test
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction

# Ejecutar tests
php bin/phpunit tests/Functional/API/AuthControllerTest.php
```

**Criterios de aceptación:**
- [ ] Todos los tests pasan (11 tests)
- [ ] Cobertura de casos exitosos y errores
- [ ] Tests independientes (setUp limpia BD)

**Tiempo estimado:** 1.5 horas

---

### Tarea 5.7: Crear Fixtures de Datos de Prueba

**Objetivo:** Datos de ejemplo para desarrollo

**Archivo:** `src/Infrastructure/DataFixtures/UserFixtures.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\DataFixtures;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Usuario FREE
        $freeUser = User::register(
            Email::fromString('free@lexecuador.com'),
            '', // Se hashea después
            'Free User',
            Role::FREE
        );
        $freeUser->changePassword($this->passwordHasher->hashPassword($freeUser, 'password123'));
        $manager->persist($freeUser);

        // Usuario PREMIUM
        $premiumUser = User::register(
            Email::fromString('premium@lexecuador.com'),
            '',
            'Premium User',
            Role::PREMIUM
        );
        $premiumUser->changePassword($this->passwordHasher->hashPassword($premiumUser, 'password123'));
        $manager->persist($premiumUser);

        // Usuario ENTERPRISE
        $enterpriseUser = User::register(
            Email::fromString('enterprise@lexecuador.com'),
            '',
            'Enterprise User',
            Role::ENTERPRISE
        );
        $enterpriseUser->changePassword($this->passwordHasher->hashPassword($enterpriseUser, 'password123'));
        $manager->persist($enterpriseUser);

        // Usuario ADMIN
        $adminUser = User::register(
            Email::fromString('admin@lexecuador.com'),
            '',
            'Admin User',
            Role::ADMIN
        );
        $adminUser->changePassword($this->passwordHasher->hashPassword($adminUser, 'password123'));
        $manager->persist($adminUser);

        $manager->flush();
    }
}
```

**Cargar fixtures:**
```bash
php bin/console doctrine:fixtures:load --no-interaction
```

**Verificar:**
```bash
mysql -u admin -padmin constitucion_ec -e "SELECT email, name, role FROM users;"

# Debe mostrar:
# +---------------------------+------------------+------------------+
# | email                     | name             | role             |
# +---------------------------+------------------+------------------+
# | free@lexecuador.com       | Free User        | ROLE_FREE        |
# | premium@lexecuador.com    | Premium User     | ROLE_PREMIUM     |
# | enterprise@lexecuador.com | Enterprise User  | ROLE_ENTERPRISE  |
# | admin@lexecuador.com      | Admin User       | ROLE_ADMIN       |
# +---------------------------+------------------+------------------+
```

**Criterios de aceptación:**
- [ ] Fixtures creadas con 4 usuarios
- [ ] Passwords hasheados correctamente
- [ ] Comando de carga funciona

**Tiempo estimado:** 30 minutos

---

### Tarea 5.8: Probar Endpoints Manualmente

**Objetivo:** Verificar que todo funciona end-to-end

#### Test 1: Registro de Usuario

```bash
curl -X POST http://localhost/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePass123!",
    "name": "Test User"
  }'
```

**Respuesta esperada (201):**
```json
{
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "test@example.com",
    "name": "Test User",
    "role": "ROLE_FREE",
    "isActive": true,
    "createdAt": "2025-12-19T10:00:00+00:00"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

---

#### Test 2: Login

```bash
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "SecurePass123!"
  }'
```

**Respuesta esperada (200):**
```json
{
  "user": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "test@example.com",
    "name": "Test User",
    "role": "ROLE_FREE",
    "isActive": true,
    "createdAt": "2025-12-19T10:00:00+00:00"
  },
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

---

#### Test 3: Obtener Usuario Actual (con token)

```bash
# Guardar token de respuesta anterior
TOKEN="eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."

curl -X GET http://localhost/api/v1/auth/me \
  -H "Authorization: Bearer $TOKEN"
```

**Respuesta esperada (200):**
```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "email": "test@example.com",
  "name": "Test User",
  "role": "ROLE_FREE",
  "isActive": true,
  "createdAt": "2025-12-19T10:00:00+00:00"
}
```

---

#### Test 4: Error - Email Duplicado

```bash
curl -X POST http://localhost/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "AnotherPass123!",
    "name": "Duplicate User"
  }'
```

**Respuesta esperada (409):**
```json
{
  "type": "https://api.lexecuador.com/problems/duplicate-email",
  "title": "Duplicate Email",
  "status": 409,
  "detail": "Email \"test@example.com\" is already registered"
}
```

---

#### Test 5: Error - Credenciales Inválidas

```bash
curl -X POST http://localhost/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "WrongPassword"
  }'
```

**Respuesta esperada (401):**
```json
{
  "type": "https://api.lexecuador.com/problems/invalid-credentials",
  "title": "Invalid Credentials",
  "status": 401,
  "detail": "Invalid credentials"
}
```

---

#### Test 6: Error - Sin Token

```bash
curl -X GET http://localhost/api/v1/auth/me
```

**Respuesta esperada (401):**
```json
{
  "code": 401,
  "message": "JWT Token not found"
}
```

---

#### Test 7: Swagger UI

```bash
# Abrir navegador en:
http://localhost/api/doc

# Verificar que aparecen los 3 endpoints:
# - POST /api/v1/auth/register
# - POST /api/v1/auth/login
# - GET /api/v1/auth/me

# Probar "Try it out" en cada endpoint
```

**Criterios de aceptación:**
- [ ] Todos los tests manuales funcionan
- [ ] Respuestas tienen formato correcto
- [ ] Errores siguen RFC 7807
- [ ] JWT tokens son válidos
- [ ] Swagger UI funciona

**Tiempo estimado:** 45 minutos

---

## 🎯 Verificación Final del Sprint 1

### Checklist de Completitud

#### Instalación y Configuración ✅
- [ ] Bundles instalados: Security, JWT, Serializer, Validator, CORS, API Doc
- [ ] Claves JWT generadas y protegidas
- [ ] security.yaml configurado (firewalls, roles, access control)
- [ ] CORS configurado para localhost
- [ ] Swagger UI accesible en `/api/doc`
- [ ] Serializer configurado (ISO 8601, camelCase)
- [ ] Validator configurado con auto-mapping

#### Clean Architecture ✅
- [ ] Estructura de directorios creada
- [ ] Entidades existentes movidas a `Domain/Entity/`
- [ ] Repositorios movidos a `Infrastructure/Persistence/`
- [ ] Servicios movidos a `Application/Service/`
- [ ] Namespaces actualizados en todos los archivos
- [ ] Doctrine mapea desde `Domain/Entity/`
- [ ] DI container configurado correctamente

#### Entidades de Dominio ✅
- [ ] Value Object: Email (con tests)
- [ ] Value Object: Role (enum con jerarquía)
- [ ] Entidad: User (con factory methods)
- [ ] Migración de User ejecutada
- [ ] Tabla `users` creada en BD
- [ ] UserRepositoryInterface creado
- [ ] DoctrineUserRepository implementado

#### Autenticación ✅
- [ ] RegisterRequest DTO creado
- [ ] LoginRequest DTO creado
- [ ] UserResponse DTO creado
- [ ] AuthResponse DTO creado
- [ ] RegisterUserUseCase implementado
- [ ] LoginUserUseCase implementado
- [ ] AuthController con 3 endpoints
- [ ] POST /api/v1/auth/register funciona
- [ ] POST /api/v1/auth/login funciona
- [ ] GET /api/v1/auth/me funciona
- [ ] JWT tokens se generan correctamente
- [ ] Rutas protegidas requieren Bearer token

#### Tests ✅
- [ ] Tests unitarios de Email pasan
- [ ] Tests unitarios de RegisterUserUseCase pasan
- [ ] Tests funcionales de AuthController pasan (11 tests)
- [ ] Cobertura >80% en Value Objects y Use Cases
- [ ] Base de datos de test configurada

#### Fixtures ✅
- [ ] UserFixtures creadas (4 usuarios)
- [ ] Comando de fixtures funciona
- [ ] Usuarios de prueba disponibles

#### Documentación ✅
- [ ] Endpoints documentados en Swagger
- [ ] OpenAPI annotations en controller
- [ ] Try-it-out funciona en Swagger
- [ ] Descripción de API clara

#### Pruebas Manuales ✅
- [ ] Registro funciona via curl
- [ ] Login funciona via curl
- [ ] GET /me funciona con token
- [ ] Errores tienen formato RFC 7807
- [ ] Email duplicado retorna 409
- [ ] Credenciales inválidas retornan 401
- [ ] Sin token retorna 401

---

## 📊 Métricas del Sprint 1

**Código escrito:**
- 15 archivos PHP creados
- 10 archivos de configuración YAML
- 3 tests unitarios
- 1 test funcional (11 test cases)
- ~1,800 líneas de código

**Tests:**
- 14+ test cases
- Cobertura estimada: 85%

**Tiempo total:** ~40 horas

**Funcionalidades entregadas:**
- ✅ Infraestructura base configurada
- ✅ Clean Architecture implementada
- ✅ Sistema de autenticación JWT completo
- ✅ Registro de usuarios
- ✅ Login con validación
- ✅ Endpoint de perfil protegido
- ✅ Documentación Swagger

---

## 🚀 Siguiente Sprint

**Sprint 2: Core Features - Artículos y Búsqueda**

**Objetivos:**
- Refactorizar ArticleController a API REST
- Implementar búsqueda con control de acceso por roles
- Sistema de paginación mejorado
- Filtros por capítulo
- Rate limiting básico

**Archivo:** `05_PLAN_SPRINT_2.md`

---

## 🎉 ¡Sprint 1 Completado!

Si todos los checks están marcados ✅, el Sprint 1 está completo y listo para producción.

**Para verificar:**
```bash
# 1. Tests pasan
php bin/phpunit

# 2. Schema válido
php bin/console doctrine:schema:validate

# 3. Linter OK
php bin/console lint:container
php bin/console lint:yaml config/

# 4. Endpoints funcionan
curl http://localhost/api/v1/auth/register -X POST -H "Content-Type: application/json" -d '{"email":"test@test.com","password":"Test1234!","name":"Test"}'

# 5. Swagger accesible
open http://localhost/api/doc
```

**¡Excelente trabajo! 🎊**

---

**Archivo generado:** `04_PLAN_SPRINT_1_PARTE_2.md`
**Archivos relacionados:** `04_PLAN_SPRINT_1.md`
**Total Sprint 1:** ~40 horas (2 semanas)
