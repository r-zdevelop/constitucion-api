# 06 - Plan Sprint 3: Suscripciones y Pagos

**Sprint:** 3 de 3
**Duración:** 2 semanas (Semana 5-6)
**Objetivo:** Implementar sistema de suscripciones con integración de pagos (Stripe + PayPhone)
**Fecha inicio:** 2026-01-17
**Fecha fin:** 2026-01-30

---

## 🎯 Objetivo del Sprint

Completar la monetización del MVP:
- ✅ Sistema de suscripciones (FREE, PREMIUM, ENTERPRISE)
- ✅ Integración con Stripe (internacional)
- ✅ Integración con PayPhone (Ecuador)
- ✅ Webhooks para eventos de pago
- ✅ Gestión de ciclo de vida de suscripciones
- ✅ Actualización automática de roles
- ✅ Emails transaccionales

**Entregable:** MVP completo con monetización funcional

---

## 📋 Tareas del Sprint 3

### Fase 1: Entidades de Dominio (4-5 horas)

#### Tarea 1.1: Crear Value Object SubscriptionPlan

**Objetivo:** Encapsular lógica de planes de suscripción

**Archivo:** `src/Domain/ValueObject/SubscriptionPlan.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

enum SubscriptionPlan: string
{
    case FREE = 'free';
    case PREMIUM = 'premium';
    case ENTERPRISE = 'enterprise';

    public function getPrice(): Money
    {
        return match($this) {
            self::FREE => Money::fromCents(0, 'USD'),
            self::PREMIUM => Money::fromCents(999, 'USD'), // $9.99
            self::ENTERPRISE => Money::fromCents(4999, 'USD'), // $49.99
        };
    }

    public function getRole(): Role
    {
        return match($this) {
            self::FREE => Role::FREE,
            self::PREMIUM => Role::PREMIUM,
            self::ENTERPRISE => Role::ENTERPRISE,
        };
    }

    public function getDisplayName(): string
    {
        return match($this) {
            self::FREE => 'Free Plan',
            self::PREMIUM => 'Premium Plan',
            self::ENTERPRISE => 'Enterprise Plan',
        };
    }

    public function getFeatures(): array
    {
        return match($this) {
            self::FREE => [
                'Access to first 100 articles',
                'Basic search',
                '100 API requests per day',
            ],
            self::PREMIUM => [
                'Access to all 467 articles',
                'Advanced search',
                'Concordances',
                '10,000 API requests per day',
            ],
            self::ENTERPRISE => [
                'All Premium features',
                'Personal API key',
                'Unlimited API requests',
                'Priority support',
                'Team management',
            ],
        };
    }

    public function isFree(): bool
    {
        return $this === self::FREE;
    }

    public function isPaid(): bool
    {
        return $this !== self::FREE;
    }
}
```

**Criterios de aceptación:**
- [ ] Enum creado con 3 planes
- [ ] Método `getPrice()` retorna Money
- [ ] Método `getRole()` retorna Role correspondiente
- [ ] Método `getFeatures()` retorna array de features

**Tiempo estimado:** 45 minutos

---

#### Tarea 1.2: Crear Value Object Money

**Objetivo:** Representar cantidades monetarias de forma segura

**Archivo:** `src/Domain/ValueObject/Money.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

final readonly class Money implements \Stringable
{
    private function __construct(
        private int $amountInCents,
        private string $currency
    ) {}

    public static function fromCents(int $cents, string $currency = 'USD'): self
    {
        if ($cents < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }

        return new self($cents, strtoupper($currency));
    }

    public static function fromDollars(float $dollars, string $currency = 'USD'): self
    {
        return self::fromCents((int) round($dollars * 100), $currency);
    }

    public function getAmountInCents(): int
    {
        return $this->amountInCents;
    }

    public function getAmountInDollars(): float
    {
        return $this->amountInCents / 100;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function equals(self $other): bool
    {
        return $this->amountInCents === $other->amountInCents
            && $this->currency === $other->currency;
    }

    public function isZero(): bool
    {
        return $this->amountInCents === 0;
    }

    public function isPositive(): bool
    {
        return $this->amountInCents > 0;
    }

    public function __toString(): string
    {
        return sprintf('$%.2f %s', $this->getAmountInDollars(), $this->currency);
    }

    public function toStripeAmount(): int
    {
        return $this->amountInCents;
    }
}
```

**Test:** `tests/Unit/Domain/ValueObject/MoneyTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testCreateFromCents(): void
    {
        $money = Money::fromCents(999, 'USD');

        $this->assertSame(999, $money->getAmountInCents());
        $this->assertSame(9.99, $money->getAmountInDollars());
        $this->assertSame('USD', $money->getCurrency());
    }

    public function testCreateFromDollars(): void
    {
        $money = Money::fromDollars(9.99, 'USD');

        $this->assertSame(999, $money->getAmountInCents());
        $this->assertSame(9.99, $money->getAmountInDollars());
    }

    public function testZeroMoney(): void
    {
        $money = Money::fromCents(0);

        $this->assertTrue($money->isZero());
        $this->assertFalse($money->isPositive());
    }

    public function testNegativeAmountThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Money::fromCents(-100);
    }

    public function testToString(): void
    {
        $money = Money::fromCents(4999, 'USD');

        $this->assertSame('$49.99 USD', (string) $money);
    }
}
```

**Criterios de aceptación:**
- [ ] Value Object creado
- [ ] Inmutable (readonly)
- [ ] Conversión cents ↔ dollars
- [ ] Tests pasan al 100%

**Tiempo estimado:** 1 hora

---

#### Tarea 1.3: Crear Entidad Subscription

**Objetivo:** Representar suscripciones de usuarios

**Archivo:** `src/Domain/Entity/Subscription.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\SubscriptionPlan;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'subscriptions')]
class Subscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['subscription:read'])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 32)]
    #[Groups(['subscription:read'])]
    private string $plan;

    #[ORM\Column(type: 'string', length: 32)]
    #[Groups(['subscription:read'])]
    private string $status; // active, canceled, expired, past_due

    #[ORM\Column(type: 'integer')]
    #[Groups(['subscription:read'])]
    private int $amountInCents;

    #[ORM\Column(type: 'string', length: 3)]
    #[Groups(['subscription:read'])]
    private string $currency;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $stripeSubscriptionId = null;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['subscription:read'])]
    private \DateTimeImmutable $currentPeriodStart;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['subscription:read'])]
    private \DateTimeImmutable $currentPeriodEnd;

    #[ORM\Column(type: 'boolean')]
    #[Groups(['subscription:read'])]
    private bool $cancelAtPeriodEnd;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['subscription:read'])]
    private ?\DateTimeImmutable $canceledAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['subscription:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['subscription:read'])]
    private \DateTimeImmutable $updatedAt;

    private function __construct(
        string $id,
        User $user,
        SubscriptionPlan $plan,
        Money $amount,
        \DateTimeImmutable $currentPeriodStart,
        \DateTimeImmutable $currentPeriodEnd,
        string $status = 'active'
    ) {
        $this->id = $id;
        $this->user = $user;
        $this->plan = $plan->value;
        $this->status = $status;
        $this->amountInCents = $amount->getAmountInCents();
        $this->currency = $amount->getCurrency();
        $this->currentPeriodStart = $currentPeriodStart;
        $this->currentPeriodEnd = $currentPeriodEnd;
        $this->cancelAtPeriodEnd = false;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public static function create(
        User $user,
        SubscriptionPlan $plan,
        \DateTimeImmutable $startDate
    ): self {
        $endDate = $startDate->modify('+1 month');

        return new self(
            id: Uuid::v4()->toString(),
            user: $user,
            plan: $plan,
            amount: $plan->getPrice(),
            currentPeriodStart: $startDate,
            currentPeriodEnd: $endDate,
            status: 'active'
        );
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::from($this->plan);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAmount(): Money
    {
        return Money::fromCents($this->amountInCents, $this->currency);
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function getCurrentPeriodStart(): \DateTimeImmutable
    {
        return $this->currentPeriodStart;
    }

    public function getCurrentPeriodEnd(): \DateTimeImmutable
    {
        return $this->currentPeriodEnd;
    }

    public function isCancelAtPeriodEnd(): bool
    {
        return $this->cancelAtPeriodEnd;
    }

    public function getCanceledAt(): ?\DateTimeImmutable
    {
        return $this->canceledAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // Business logic
    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->currentPeriodEnd > new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        $this->cancelAtPeriodEnd = true;
        $this->canceledAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function expire(): void
    {
        $this->status = 'expired';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function renew(\DateTimeImmutable $newPeriodEnd): void
    {
        $this->currentPeriodStart = $this->currentPeriodEnd;
        $this->currentPeriodEnd = $newPeriodEnd;
        $this->status = 'active';
        $this->cancelAtPeriodEnd = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setStripeSubscriptionId(string $id): void
    {
        $this->stripeSubscriptionId = $id;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setStripeCustomerId(string $id): void
    {
        $this->stripeCustomerId = $id;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markAsPastDue(): void
    {
        $this->status = 'past_due';
        $this->updatedAt = new \DateTimeImmutable();
    }
}
```

**Criterios de aceptación:**
- [ ] Entidad creada con todos los campos
- [ ] Factory method `create()`
- [ ] Business logic encapsulada (cancel, renew, expire)
- [ ] Grupos de serialización definidos
- [ ] Soporte para Stripe IDs

**Tiempo estimado:** 1.5 horas

---

#### Tarea 1.4: Crear Entidad Payment

**Objetivo:** Registrar historial de pagos

**Archivo:** `src/Domain/Entity/Payment.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'payments')]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    #[Groups(['payment:read'])]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne(targetEntity: Subscription::class)]
    #[ORM\JoinColumn(name: 'subscription_id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['payment:read'])]
    private ?Subscription $subscription = null;

    #[ORM\Column(type: 'integer')]
    #[Groups(['payment:read'])]
    private int $amountInCents;

    #[ORM\Column(type: 'string', length: 3)]
    #[Groups(['payment:read'])]
    private string $currency;

    #[ORM\Column(type: 'string', length: 32)]
    #[Groups(['payment:read'])]
    private string $status; // pending, succeeded, failed, refunded

    #[ORM\Column(type: 'string', length: 32)]
    #[Groups(['payment:read'])]
    private string $provider; // stripe, payphone

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    private ?string $providerPaymentId = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    private ?string $providerCustomerId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['payment:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['payment:read'])]
    private ?\DateTimeImmutable $paidAt = null;

    private function __construct(
        string $id,
        User $user,
        Money $amount,
        string $provider,
        string $status = 'pending'
    ) {
        $this->id = $id;
        $this->user = $user;
        $this->amountInCents = $amount->getAmountInCents();
        $this->currency = $amount->getCurrency();
        $this->status = $status;
        $this->provider = $provider;
        $this->createdAt = new \DateTimeImmutable();
    }

    public static function create(User $user, Money $amount, string $provider): self
    {
        return new self(
            id: Uuid::v4()->toString(),
            user: $user,
            amount: $amount,
            provider: $provider
        );
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getSubscription(): ?Subscription
    {
        return $this->subscription;
    }

    public function getAmount(): Money
    {
        return Money::fromCents($this->amountInCents, $this->currency);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getProviderPaymentId(): ?string
    {
        return $this->providerPaymentId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    // Business logic
    public function markAsSucceeded(string $providerPaymentId): void
    {
        $this->status = 'succeeded';
        $this->providerPaymentId = $providerPaymentId;
        $this->paidAt = new \DateTimeImmutable();
    }

    public function markAsFailed(string $reason): void
    {
        $this->status = 'failed';
        $this->failureReason = $reason;
    }

    public function setSubscription(Subscription $subscription): void
    {
        $this->subscription = $subscription;
    }

    public function setProviderCustomerId(string $id): void
    {
        $this->providerCustomerId = $id;
    }

    public function isSucceeded(): bool
    {
        return $this->status === 'succeeded';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
```

**Criterios de aceptación:**
- [ ] Entidad creada
- [ ] Relación con User y Subscription
- [ ] Soporte para múltiples providers (Stripe, PayPhone)
- [ ] Estados de pago (pending, succeeded, failed, refunded)
- [ ] Business logic para transiciones de estado

**Tiempo estimado:** 1 hora

---

#### Tarea 1.5: Crear Migraciones

**Comandos:**
```bash
php bin/console make:migration
```

**Editar migración:** `migrations/Version*.php`

```php
public function up(Schema $schema): void
{
    // Tabla subscriptions
    $this->addSql('
        CREATE TABLE subscriptions (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            plan VARCHAR(32) NOT NULL,
            status VARCHAR(32) NOT NULL,
            amount_in_cents INT NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT "USD",
            stripe_subscription_id VARCHAR(64) NULL,
            stripe_customer_id VARCHAR(64) NULL,
            current_period_start DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            current_period_end DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            cancel_at_period_end BOOLEAN NOT NULL DEFAULT FALSE,
            canceled_at DATETIME NULL COMMENT "(DC2Type:datetime_immutable)",
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            updated_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX idx_user (user_id),
            INDEX idx_status (status),
            INDEX idx_stripe_subscription (stripe_subscription_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');

    // Tabla payments
    $this->addSql('
        CREATE TABLE payments (
            id VARCHAR(36) NOT NULL PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            subscription_id VARCHAR(36) NULL,
            amount_in_cents INT NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT "USD",
            status VARCHAR(32) NOT NULL,
            provider VARCHAR(32) NOT NULL,
            provider_payment_id VARCHAR(128) NULL,
            provider_customer_id VARCHAR(128) NULL,
            failure_reason TEXT NULL,
            created_at DATETIME NOT NULL COMMENT "(DC2Type:datetime_immutable)",
            paid_at DATETIME NULL COMMENT "(DC2Type:datetime_immutable)",
            INDEX idx_user (user_id),
            INDEX idx_subscription (subscription_id),
            INDEX idx_status (status),
            INDEX idx_provider_payment (provider_payment_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ');
}

public function down(Schema $schema): void
{
    $this->addSql('DROP TABLE payments');
    $this->addSql('DROP TABLE subscriptions');
}
```

**Ejecutar migración:**
```bash
php bin/console doctrine:migrations:migrate --no-interaction

# Verificar
php bin/console doctrine:schema:validate
```

**Criterios de aceptación:**
- [ ] Migración creada
- [ ] Tablas creadas correctamente
- [ ] Índices creados
- [ ] Foreign keys configuradas
- [ ] Schema validation OK

**Tiempo estimado:** 30 minutos

---

### Fase 2: Repositorios (2-3 horas)

#### Tarea 2.1: Crear SubscriptionRepository

**Interfaz:** `src/Domain/Repository/SubscriptionRepositoryInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Subscription;
use App\Domain\Entity\User;

interface SubscriptionRepositoryInterface
{
    public function findById(string $id): ?Subscription;
    public function findActiveByUser(User $user): ?Subscription;
    public function findByStripeSubscriptionId(string $stripeId): ?Subscription;
    public function save(Subscription $subscription): void;
    public function remove(Subscription $subscription): void;
}
```

**Implementación:** `src/Infrastructure/Persistence/Doctrine/Repository/DoctrineSubscriptionRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Subscription;
use App\Domain\Entity\User;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DoctrineSubscriptionRepository extends ServiceEntityRepository implements SubscriptionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    public function findById(string $id): ?Subscription
    {
        return $this->find($id);
    }

    public function findActiveByUser(User $user): ?Subscription
    {
        return $this->findOneBy([
            'user' => $user,
            'status' => 'active',
        ]);
    }

    public function findByStripeSubscriptionId(string $stripeId): ?Subscription
    {
        return $this->findOneBy(['stripeSubscriptionId' => $stripeId]);
    }

    public function save(Subscription $subscription): void
    {
        $this->getEntityManager()->persist($subscription);
        $this->getEntityManager()->flush();
    }

    public function remove(Subscription $subscription): void
    {
        $this->getEntityManager()->remove($subscription);
        $this->getEntityManager()->flush();
    }
}
```

**Registrar en services.yaml:**
```yaml
services:
    App\Domain\Repository\SubscriptionRepositoryInterface:
        class: App\Infrastructure\Persistence\Doctrine\Repository\DoctrineSubscriptionRepository
```

**Criterios de aceptación:**
- [ ] Interfaz creada
- [ ] Implementación Doctrine creada
- [ ] Repository registrado
- [ ] Métodos básicos funcionan

**Tiempo estimado:** 45 minutos

---

#### Tarea 2.2: Crear PaymentRepository

**Interfaz:** `src/Domain/Repository/PaymentRepositoryInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Payment;
use App\Domain\Entity\User;

interface PaymentRepositoryInterface
{
    public function findById(string $id): ?Payment;
    public function findByUser(User $user): array;
    public function findByProviderPaymentId(string $providerId): ?Payment;
    public function save(Payment $payment): void;
}
```

**Implementación:** `src/Infrastructure/Persistence/Doctrine/Repository/DoctrinePaymentRepository.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Payment;
use App\Domain\Entity\User;
use App\Domain\Repository\PaymentRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class DoctrinePaymentRepository extends ServiceEntityRepository implements PaymentRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    public function findById(string $id): ?Payment
    {
        return $this->find($id);
    }

    public function findByUser(User $user): array
    {
        return $this->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );
    }

    public function findByProviderPaymentId(string $providerId): ?Payment
    {
        return $this->findOneBy(['providerPaymentId' => $providerId]);
    }

    public function save(Payment $payment): void
    {
        $this->getEntityManager()->persist($payment);
        $this->getEntityManager()->flush();
    }
}
```

**Registrar en services.yaml:**
```yaml
services:
    App\Domain\Repository\PaymentRepositoryInterface:
        class: App\Infrastructure\Persistence\Doctrine\Repository\DoctrinePaymentRepository
```

**Criterios de aceptación:**
- [ ] Interfaz creada
- [ ] Implementación creada
- [ ] Repository registrado

**Tiempo estimado:** 30 minutos

---

**[ARCHIVO INCOMPLETO - Continúa en 06_PLAN_SPRINT_3_PARTE_2.md]**

---

## 📊 Resumen del Sprint 3 (Parte 1)

**Fases completadas:**
- ✅ Fase 1: Entidades de dominio (5 tareas)
- ✅ Fase 2: Repositorios (2 tareas)

**Pendiente:**
- Fase 3: Integración Stripe
- Fase 4: Integración PayPhone
- Fase 5: Use Cases y Controllers
- Fase 6: Webhooks
- Fase 7: Tests y verificación

**Tiempo estimado total:** ~50 horas (2 semanas)

---

**Archivo generado:** `06_PLAN_SPRINT_3.md` (Parte 1)
**Siguiente:** Generar parte 2 con integraciones de pago
