# 06 - Plan Sprint 3: Suscripciones y Pagos (Parte 2)

**Continuación de:** `06_PLAN_SPRINT_3.md`
**Fases:** 3-7
**Tiempo estimado:** ~30 horas

---

## Fase 3: Integración con Stripe (8-10 horas)

### Tarea 3.1: Instalar y Configurar Stripe SDK

**Comandos:**
```bash
composer require stripe/stripe-php
```

**Variables de entorno (.env):**
```env
###> stripe ###
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
###< stripe ###
```

**Configuración:** `config/packages/stripe.yaml`

```yaml
parameters:
    stripe.secret_key: '%env(STRIPE_SECRET_KEY)%'
    stripe.public_key: '%env(STRIPE_PUBLIC_KEY)%'
    stripe.webhook_secret: '%env(STRIPE_WEBHOOK_SECRET)%'
```

**Criterios de aceptación:**
- [ ] Stripe SDK instalado
- [ ] Variables de entorno configuradas
- [ ] Claves de TEST mode configuradas

**Tiempo estimado:** 20 minutos

---

### Tarea 3.2: Crear Servicio StripePaymentGateway

**Archivo:** `src/Infrastructure/Payment/StripePaymentGateway.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment;

use App\Domain\Entity\Payment;
use App\Domain\Entity\Subscription;
use App\Domain\Entity\User;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\SubscriptionPlan;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

final readonly class StripePaymentGateway implements PaymentGatewayInterface
{
    private StripeClient $stripe;

    public function __construct(
        string $secretKey
    ) {
        $this->stripe = new StripeClient($secretKey);
    }

    public function createCustomer(User $user): string
    {
        try {
            $customer = $this->stripe->customers->create([
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'metadata' => [
                    'user_id' => $user->getId(),
                ],
            ]);

            return $customer->id;

        } catch (ApiErrorException $e) {
            throw new PaymentException(
                'Failed to create Stripe customer: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function createSubscription(
        User $user,
        SubscriptionPlan $plan,
        string $paymentMethodId
    ): array {
        try {
            // Crear o obtener customer
            $customerId = $user->getStripeCustomerId();
            if ($customerId === null) {
                $customerId = $this->createCustomer($user);
            }

            // Adjuntar payment method al customer
            $this->stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $customerId,
            ]);

            // Establecer como default payment method
            $this->stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            // Crear suscripción
            $subscription = $this->stripe->subscriptions->create([
                'customer' => $customerId,
                'items' => [
                    ['price' => $this->getPriceIdForPlan($plan)],
                ],
                'payment_behavior' => 'default_incomplete',
                'payment_settings' => ['save_default_payment_method' => 'on_subscription'],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'user_id' => $user->getId(),
                    'plan' => $plan->value,
                ],
            ]);

            return [
                'subscriptionId' => $subscription->id,
                'customerId' => $customerId,
                'clientSecret' => $subscription->latest_invoice->payment_intent->client_secret,
                'status' => $subscription->status,
            ];

        } catch (ApiErrorException $e) {
            throw new PaymentException(
                'Failed to create Stripe subscription: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        try {
            $this->stripe->subscriptions->update($subscriptionId, [
                'cancel_at_period_end' => true,
            ]);

        } catch (ApiErrorException $e) {
            throw new PaymentException(
                'Failed to cancel Stripe subscription: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function resumeSubscription(string $subscriptionId): void
    {
        try {
            $this->stripe->subscriptions->update($subscriptionId, [
                'cancel_at_period_end' => false,
            ]);

        } catch (ApiErrorException $e) {
            throw new PaymentException(
                'Failed to resume Stripe subscription: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    public function getSubscription(string $subscriptionId): array
    {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);

            return [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'currentPeriodStart' => $subscription->current_period_start,
                'currentPeriodEnd' => $subscription->current_period_end,
                'cancelAtPeriodEnd' => $subscription->cancel_at_period_end,
            ];

        } catch (ApiErrorException $e) {
            throw new PaymentException(
                'Failed to retrieve Stripe subscription: ' . $e->getMessage(),
                previous: $e
            );
        }
    }

    private function getPriceIdForPlan(SubscriptionPlan $plan): string
    {
        // IDs de precios de Stripe (crear en dashboard de Stripe)
        return match($plan) {
            SubscriptionPlan::PREMIUM => 'price_premium_monthly',
            SubscriptionPlan::ENTERPRISE => 'price_enterprise_monthly',
            default => throw new \InvalidArgumentException('Invalid plan for Stripe'),
        };
    }
}
```

**Interfaz:** `src/Infrastructure/Payment/PaymentGatewayInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment;

use App\Domain\Entity\User;
use App\Domain\ValueObject\SubscriptionPlan;

interface PaymentGatewayInterface
{
    public function createCustomer(User $user): string;

    public function createSubscription(
        User $user,
        SubscriptionPlan $plan,
        string $paymentMethodId
    ): array;

    public function cancelSubscription(string $subscriptionId): void;

    public function resumeSubscription(string $subscriptionId): void;

    public function getSubscription(string $subscriptionId): array;
}
```

**Excepción:** `src/Infrastructure/Payment/PaymentException.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Payment;

final class PaymentException extends \RuntimeException
{
}
```

**Registrar en services.yaml:**
```yaml
services:
    App\Infrastructure\Payment\StripePaymentGateway:
        arguments:
            $secretKey: '%stripe.secret_key%'

    # Alias para inyectar como interfaz
    App\Infrastructure\Payment\PaymentGatewayInterface:
        alias: App\Infrastructure\Payment\StripePaymentGateway
```

**Criterios de aceptación:**
- [ ] Servicio creado
- [ ] Métodos implementados (create, cancel, resume, get)
- [ ] Manejo de errores con excepciones
- [ ] Metadata incluida para rastreo
- [ ] Registrado como servicio

**Tiempo estimado:** 3 horas

---

### Tarea 3.3: Crear Precios en Stripe Dashboard

**Pasos manuales:**

1. Ir a https://dashboard.stripe.com/test/products
2. Crear producto "Premium Plan"
   - Precio: $9.99 USD
   - Recurring: Monthly
   - Copiar Price ID (ej: `price_1234...`)
3. Crear producto "Enterprise Plan"
   - Precio: $49.99 USD
   - Recurring: Monthly
   - Copiar Price ID

**Actualizar StripePaymentGateway:**
```php
private function getPriceIdForPlan(SubscriptionPlan $plan): string
{
    return match($plan) {
        SubscriptionPlan::PREMIUM => 'price_1234...', // ID real
        SubscriptionPlan::ENTERPRISE => 'price_5678...', // ID real
        default => throw new \InvalidArgumentException('Invalid plan'),
    };
}
```

**Criterios de aceptación:**
- [ ] Productos creados en Stripe
- [ ] Price IDs copiados
- [ ] IDs configurados en código

**Tiempo estimado:** 30 minutos

---

### Tarea 3.4: Configurar Webhook de Stripe

**Pasos:**

1. **Desarrollo local con Stripe CLI:**
```bash
# Instalar Stripe CLI
# https://stripe.com/docs/stripe-cli

# Login
stripe login

# Escuchar webhooks
stripe listen --forward-to localhost/api/v1/webhooks/stripe

# Copiar webhook signing secret (whsec_...)
```

2. **Producción:**
- Ir a https://dashboard.stripe.com/webhooks
- Añadir endpoint: `https://api.lexecuador.com/api/v1/webhooks/stripe`
- Seleccionar eventos:
  - `invoice.payment_succeeded`
  - `invoice.payment_failed`
  - `customer.subscription.updated`
  - `customer.subscription.deleted`
- Copiar signing secret

3. **Configurar en .env:**
```env
STRIPE_WEBHOOK_SECRET=whsec_...
```

**Criterios de aceptación:**
- [ ] Webhook configurado en Stripe
- [ ] Signing secret guardado
- [ ] Stripe CLI funcionando (dev)

**Tiempo estimado:** 45 minutos

---

## Fase 4: Use Cases de Suscripciones (6-8 horas)

### Tarea 4.1: Crear Use Case CreateSubscriptionUseCase

**Archivo:** `src/Application/UseCase/Subscription/CreateSubscriptionUseCase.php`

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCase\Subscription;

use App\Domain\Entity\Payment;
use App\Domain\Entity\Subscription;
use App\Domain\Entity\User;
use App\Domain\Repository\PaymentRepositoryInterface;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\SubscriptionPlan;
use App\Infrastructure\Payment\PaymentGatewayInterface;

final readonly class CreateSubscriptionUseCase
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptions,
        private PaymentRepositoryInterface $payments,
        private UserRepositoryInterface $users,
        private PaymentGatewayInterface $paymentGateway
    ) {}

    public function execute(
        User $user,
        SubscriptionPlan $plan,
        string $paymentMethodId
    ): Subscription {
        // 1. Verificar que no tenga suscripción activa
        $existingSubscription = $this->subscriptions->findActiveByUser($user);
        if ($existingSubscription !== null) {
            throw new \DomainException('User already has an active subscription');
        }

        // 2. Crear suscripción en Stripe
        $stripeResult = $this->paymentGateway->createSubscription(
            $user,
            $plan,
            $paymentMethodId
        );

        // 3. Crear registro de pago
        $payment = Payment::create($user, $plan->getPrice(), 'stripe');
        $payment->setProviderCustomerId($stripeResult['customerId']);
        $this->payments->save($payment);

        // 4. Crear suscripción local
        $subscription = Subscription::create($user, $plan, new \DateTimeImmutable());
        $subscription->setStripeSubscriptionId($stripeResult['subscriptionId']);
        $subscription->setStripeCustomerId($stripeResult['customerId']);

        // 5. Actualizar rol del usuario
        $user->upgradeToRole($plan->getRole());

        // 6. Persistir
        $this->subscriptions->save($subscription);
        $this->users->save($user);

        return $subscription;
    }
}
```

**Criterios de aceptación:**
- [ ] Use Case creado
- [ ] Validación de suscripción existente
- [ ] Integración con Stripe
- [ ] Creación de Payment y Subscription
- [ ] Actualización de rol del usuario
- [ ] Transaccional (todo o nada)

**Tiempo estimado:** 2 horas

---

### Tarea 4.2: Crear Use Case CancelSubscriptionUseCase

**Archivo:** `src/Application/UseCase/Subscription/CancelSubscriptionUseCase.php`

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCase\Subscription;

use App\Domain\Entity\Subscription;
use App\Domain\Entity\User;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Infrastructure\Payment\PaymentGatewayInterface;

final readonly class CancelSubscriptionUseCase
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptions,
        private PaymentGatewayInterface $paymentGateway
    ) {}

    public function execute(User $user): Subscription
    {
        // 1. Obtener suscripción activa
        $subscription = $this->subscriptions->findActiveByUser($user);

        if ($subscription === null) {
            throw new \DomainException('User does not have an active subscription');
        }

        // 2. Cancelar en Stripe
        if ($subscription->getStripeSubscriptionId() !== null) {
            $this->paymentGateway->cancelSubscription(
                $subscription->getStripeSubscriptionId()
            );
        }

        // 3. Marcar como cancelada (mantiene acceso hasta fin de período)
        $subscription->cancel();

        // 4. Persistir
        $this->subscriptions->save($subscription);

        return $subscription;
    }
}
```

**Criterios de aceptación:**
- [ ] Use Case creado
- [ ] Cancela en Stripe
- [ ] Mantiene acceso hasta fin de período
- [ ] Usuario conserva rol hasta expiración

**Tiempo estimado:** 1 hora

---

### Tarea 4.3: Crear Use Case GetCurrentSubscriptionUseCase

**Archivo:** `src/Application/UseCase/Subscription/GetCurrentSubscriptionUseCase.php`

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCase\Subscription;

use App\Domain\Entity\Subscription;
use App\Domain\Entity\User;
use App\Domain\Repository\SubscriptionRepositoryInterface;

final readonly class GetCurrentSubscriptionUseCase
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptions
    ) {}

    public function execute(User $user): ?Subscription
    {
        return $this->subscriptions->findActiveByUser($user);
    }
}
```

**Tiempo estimado:** 15 minutos

---

### Tarea 4.4: Crear SubscriptionController

**Archivo:** `src/Presentation/API/Controller/SubscriptionController.php`

```php
<?php

declare(strict_types=1);

namespace App\Presentation\API\Controller;

use App\Application\UseCase\Subscription\CancelSubscriptionUseCase;
use App\Application\UseCase\Subscription\CreateSubscriptionUseCase;
use App\Application\UseCase\Subscription\GetCurrentSubscriptionUseCase;
use App\Domain\Entity\User;
use App\Domain\ValueObject\SubscriptionPlan;
use App\Infrastructure\Payment\PaymentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;

#[Route('/api/v1/subscriptions', name: 'api_v1_subscriptions_')]
#[IsGranted('ROLE_USER')]
class SubscriptionController extends AbstractController
{
    #[Route('/current', name: 'current', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/subscriptions/current',
        summary: 'Get current subscription',
        security: [['bearerAuth' => []]],
        tags: ['Subscriptions'],
        responses: [
            new OA\Response(response: 200, description: 'Current subscription'),
            new OA\Response(response: 404, description: 'No active subscription'),
        ]
    )]
    public function current(
        #[CurrentUser] User $user,
        GetCurrentSubscriptionUseCase $getSubscription
    ): JsonResponse {
        $subscription = $getSubscription->execute($user);

        if ($subscription === null) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/no-subscription',
                'title' => 'No Active Subscription',
                'status' => 404,
                'detail' => 'User does not have an active subscription',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json(
            ['data' => $subscription],
            context: ['groups' => ['subscription:read']]
        );
    }

    #[Route('', name: 'create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/subscriptions',
        summary: 'Create a new subscription',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['plan', 'paymentMethodId'],
                properties: [
                    new OA\Property(property: 'plan', type: 'string', enum: ['premium', 'enterprise']),
                    new OA\Property(property: 'paymentMethodId', type: 'string', example: 'pm_1234567890'),
                ]
            )
        ),
        tags: ['Subscriptions'],
        responses: [
            new OA\Response(response: 201, description: 'Subscription created'),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 402, description: 'Payment failed'),
            new OA\Response(response: 409, description: 'Already has subscription'),
        ]
    )]
    public function create(
        Request $request,
        #[CurrentUser] User $user,
        CreateSubscriptionUseCase $createSubscription
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        // Validar input
        if (!isset($data['plan'], $data['paymentMethodId'])) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/validation-error',
                'title' => 'Validation Error',
                'status' => 400,
                'detail' => 'Missing required fields: plan, paymentMethodId',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $plan = SubscriptionPlan::from($data['plan']);

            $subscription = $createSubscription->execute(
                $user,
                $plan,
                $data['paymentMethodId']
            );

            return $this->json(
                ['data' => $subscription],
                Response::HTTP_CREATED,
                context: ['groups' => ['subscription:read']]
            );

        } catch (\ValueError $e) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/validation-error',
                'title' => 'Validation Error',
                'status' => 400,
                'detail' => 'Invalid subscription plan',
            ], Response::HTTP_BAD_REQUEST);

        } catch (\DomainException $e) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/conflict',
                'title' => 'Conflict',
                'status' => 409,
                'detail' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);

        } catch (PaymentException $e) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/payment-failed',
                'title' => 'Payment Failed',
                'status' => 402,
                'detail' => $e->getMessage(),
            ], Response::HTTP_PAYMENT_REQUIRED);
        }
    }

    #[Route('/cancel', name: 'cancel', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/subscriptions/cancel',
        summary: 'Cancel subscription (keeps access until period end)',
        security: [['bearerAuth' => []]],
        tags: ['Subscriptions'],
        responses: [
            new OA\Response(response: 200, description: 'Subscription canceled'),
            new OA\Response(response: 404, description: 'No active subscription'),
        ]
    )]
    public function cancel(
        #[CurrentUser] User $user,
        CancelSubscriptionUseCase $cancelSubscription
    ): JsonResponse {
        try {
            $subscription = $cancelSubscription->execute($user);

            return $this->json(
                [
                    'data' => $subscription,
                    'message' => 'Subscription will be canceled at the end of the current period',
                ],
                context: ['groups' => ['subscription:read']]
            );

        } catch (\DomainException $e) {
            return $this->json([
                'type' => 'https://api.lexecuador.com/problems/no-subscription',
                'title' => 'No Active Subscription',
                'status' => 404,
                'detail' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
```

**Criterios de aceptación:**
- [ ] Controller creado con 3 endpoints
- [ ] GET /api/v1/subscriptions/current
- [ ] POST /api/v1/subscriptions (crear)
- [ ] POST /api/v1/subscriptions/cancel
- [ ] Documentación OpenAPI completa
- [ ] Manejo de errores (400, 402, 404, 409)
- [ ] Requiere autenticación

**Tiempo estimado:** 2.5 horas

---

## Fase 5: Webhooks de Stripe (4-6 horas)

### Tarea 5.1: Crear Webhook Controller

**Archivo:** `src/Presentation/API/Controller/StripeWebhookController.php`

```php
<?php

declare(strict_types=1);

namespace App\Presentation\API\Controller;

use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Role;
use Psr\Log\LoggerInterface;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/webhooks')]
class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly SubscriptionRepositoryInterface $subscriptions,
        private readonly UserRepositoryInterface $users,
        private readonly LoggerInterface $logger,
        private readonly string $webhookSecret
    ) {}

    #[Route('/stripe', name: 'api_webhook_stripe', methods: ['POST'])]
    public function handleStripeWebhook(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = $request->headers->get('Stripe-Signature');

        try {
            // Verificar firma del webhook
            $event = Webhook::constructEvent(
                $payload,
                $signature,
                $this->webhookSecret
            );

            $this->logger->info('Stripe webhook received', [
                'type' => $event->type,
                'id' => $event->id,
            ]);

            // Procesar evento
            match ($event->type) {
                'invoice.payment_succeeded' => $this->handlePaymentSucceeded($event->data->object),
                'invoice.payment_failed' => $this->handlePaymentFailed($event->data->object),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
                default => $this->logger->info('Unhandled webhook type: ' . $event->type),
            };

            return new Response('', Response::HTTP_OK);

        } catch (SignatureVerificationException $e) {
            $this->logger->error('Invalid webhook signature', [
                'error' => $e->getMessage(),
            ]);

            return new Response('Invalid signature', Response::HTTP_BAD_REQUEST);

        } catch (\Exception $e) {
            $this->logger->error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new Response('Webhook processing failed', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function handlePaymentSucceeded(object $invoice): void
    {
        $subscriptionId = $invoice->subscription;

        $subscription = $this->subscriptions->findByStripeSubscriptionId($subscriptionId);

        if ($subscription === null) {
            $this->logger->warning('Subscription not found for invoice', [
                'subscriptionId' => $subscriptionId,
            ]);
            return;
        }

        // Payment succeeded - subscription sigue activa
        $this->logger->info('Payment succeeded for subscription', [
            'subscriptionId' => $subscription->getId(),
        ]);

        // Aquí podrías enviar email de confirmación
    }

    private function handlePaymentFailed(object $invoice): void
    {
        $subscriptionId = $invoice->subscription;

        $subscription = $this->subscriptions->findByStripeSubscriptionId($subscriptionId);

        if ($subscription === null) {
            return;
        }

        // Marcar suscripción como past_due
        $subscription->markAsPastDue();
        $this->subscriptions->save($subscription);

        $this->logger->warning('Payment failed for subscription', [
            'subscriptionId' => $subscription->getId(),
        ]);

        // Aquí podrías enviar email de alerta
    }

    private function handleSubscriptionUpdated(object $stripeSubscription): void
    {
        $subscription = $this->subscriptions->findByStripeSubscriptionId($stripeSubscription->id);

        if ($subscription === null) {
            return;
        }

        // Actualizar fechas de período
        $newPeriodEnd = \DateTimeImmutable::createFromFormat('U', $stripeSubscription->current_period_end);
        $subscription->renew($newPeriodEnd);

        $this->subscriptions->save($subscription);

        $this->logger->info('Subscription updated', [
            'subscriptionId' => $subscription->getId(),
        ]);
    }

    private function handleSubscriptionDeleted(object $stripeSubscription): void
    {
        $subscription = $this->subscriptions->findByStripeSubscriptionId($stripeSubscription->id);

        if ($subscription === null) {
            return;
        }

        // Expirar suscripción y downgrade a FREE
        $subscription->expire();
        $user = $subscription->getUser();
        $user->upgradeToRole(Role::FREE);

        $this->subscriptions->save($subscription);
        $this->users->save($user);

        $this->logger->info('Subscription deleted', [
            'subscriptionId' => $subscription->getId(),
            'userId' => $user->getId(),
        ]);

        // Aquí podrías enviar email de cancelación
    }
}
```

**Registrar en services.yaml:**
```yaml
services:
    App\Presentation\API\Controller\StripeWebhookController:
        arguments:
            $webhookSecret: '%stripe.webhook_secret%'
        tags: ['controller.service_arguments']
```

**Criterios de aceptación:**
- [ ] Controller creado
- [ ] Verificación de firma de webhook
- [ ] Manejo de 4 eventos (payment_succeeded, payment_failed, subscription_updated, subscription_deleted)
- [ ] Logging de eventos
- [ ] Actualización de suscripciones y roles
- [ ] Error handling robusto

**Tiempo estimado:** 3 horas

---

### Tarea 5.2: Probar Webhooks Localmente

**Comandos:**
```bash
# Terminal 1: Servidor Symfony
symfony serve

# Terminal 2: Stripe CLI
stripe listen --forward-to localhost:8000/api/v1/webhooks/stripe

# Terminal 3: Trigger eventos de prueba
stripe trigger invoice.payment_succeeded
stripe trigger invoice.payment_failed
stripe trigger customer.subscription.updated
stripe trigger customer.subscription.deleted
```

**Verificar logs:**
```bash
tail -f var/log/dev.log | grep -i stripe
```

**Criterios de aceptación:**
- [ ] Webhooks se reciben correctamente
- [ ] Eventos se procesan sin errores
- [ ] Logs muestran información relevante
- [ ] Base de datos se actualiza correctamente

**Tiempo estimado:** 1 hora

---

## Fase 6: Tests de Suscripciones (4-5 horas)

### Tarea 6.1: Tests de Value Objects

**Archivo:** `tests/Unit/Domain/ValueObject/SubscriptionPlanTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\Role;
use App\Domain\ValueObject\SubscriptionPlan;
use PHPUnit\Framework\TestCase;

final class SubscriptionPlanTest extends TestCase
{
    public function testFreeePlanPrice(): void
    {
        $plan = SubscriptionPlan::FREE;

        $this->assertTrue($plan->getPrice()->isZero());
        $this->assertSame(Role::FREE, $plan->getRole());
        $this->assertTrue($plan->isFree());
    }

    public function testPremiumPlanPrice(): void
    {
        $plan = SubscriptionPlan::PREMIUM;

        $this->assertSame(999, $plan->getPrice()->getAmountInCents());
        $this->assertSame(Role::PREMIUM, $plan->getRole());
        $this->assertTrue($plan->isPaid());
    }

    public function testEnterprisePlanPrice(): void
    {
        $plan = SubscriptionPlan::ENTERPRISE;

        $this->assertSame(4999, $plan->getPrice()->getAmountInCents());
        $this->assertSame(Role::ENTERPRISE, $plan->getRole());
    }

    public function testGetFeatures(): void
    {
        $plan = SubscriptionPlan::PREMIUM;
        $features = $plan->getFeatures();

        $this->assertIsArray($features);
        $this->assertNotEmpty($features);
        $this->assertContains('Access to all 467 articles', $features);
    }
}
```

**Ejecutar:**
```bash
php bin/phpunit tests/Unit/Domain/ValueObject/SubscriptionPlanTest.php
```

**Tiempo estimado:** 30 minutos

---

### Tarea 6.2: Tests de Integration de SubscriptionController

**Archivo:** `tests/Functional/API/SubscriptionControllerTest.php`

```php
<?php

declare(strict_types=1);

namespace App\Tests\Functional\API;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Role;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class SubscriptionControllerTest extends WebTestCase
{
    private $client;
    private string $userToken;

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

        $em->createQuery('DELETE FROM App\Domain\Entity\Subscription')->execute();
        $em->createQuery('DELETE FROM App\Domain\Entity\User')->execute();

        $user = User::register(
            Email::fromString('subtest@test.com'),
            '',
            'Sub Test User',
            Role::FREE
        );
        $user->changePassword($passwordHasher->hashPassword($user, 'password123'));
        $em->persist($user);
        $em->flush();

        $this->userToken = $jwtManager->create($user);
    }

    public function testGetCurrentSubscriptionWhenNone(): void
    {
        $this->client->request('GET', '/api/v1/subscriptions/current', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->userToken,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    /**
     * @group integration
     */
    public function testCreateSubscriptionWithStripe(): void
    {
        $this->markTestSkipped('Requires Stripe test mode configuration');

        // Este test requiere configuración de Stripe en modo test
        // y un payment_method_id válido de Stripe
    }

    public function testCancelSubscriptionWhenNone(): void
    {
        $this->client->request('POST', '/api/v1/subscriptions/cancel', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->userToken,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }
}
```

**Tiempo estimado:** 1.5 horas

---

## Fase 7: Verificación Final del Sprint 3 (2-3 horas)

### Checklist de Completitud

#### Entidades y Value Objects ✅
- [ ] SubscriptionPlan enum creado
- [ ] Money Value Object creado (con tests)
- [ ] Subscription entity creada
- [ ] Payment entity creada
- [ ] Migraciones ejecutadas
- [ ] Tablas creadas correctamente

#### Repositorios ✅
- [ ] SubscriptionRepositoryInterface + implementación
- [ ] PaymentRepositoryInterface + implementación
- [ ] Repositories registrados como servicios

#### Integración Stripe ✅
- [ ] Stripe SDK instalado
- [ ] StripePaymentGateway implementado
- [ ] Precios creados en Stripe Dashboard
- [ ] Webhook configurado
- [ ] Webhook secret guardado

#### Use Cases ✅
- [ ] CreateSubscriptionUseCase
- [ ] CancelSubscriptionUseCase
- [ ] GetCurrentSubscriptionUseCase

#### Controllers ✅
- [ ] SubscriptionController (3 endpoints)
- [ ] StripeWebhookController
- [ ] Documentación OpenAPI completa

#### Tests ✅
- [ ] Tests unitarios de Value Objects
- [ ] Tests de SubscriptionController
- [ ] Webhooks testeados localmente

---

### Comandos de Verificación

```bash
# 1. Schema válido
php bin/console doctrine:schema:validate

# 2. Tests pasan
php bin/phpunit

# 3. Rutas de suscripciones
php bin/console debug:router | grep subscription

# 4. Swagger actualizado
open http://localhost/api/doc
```

---

### Pruebas Manuales

**Test 1: Crear suscripción PREMIUM**
```bash
# Obtener payment_method de Stripe test mode
# https://stripe.com/docs/testing#cards
# Usar: pm_card_visa (tarjeta de prueba)

curl -X POST http://localhost/api/v1/subscriptions \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "plan": "premium",
    "paymentMethodId": "pm_card_visa"
  }'

# Verificar respuesta 201 Created
# Verificar que rol del usuario cambió a ROLE_PREMIUM
```

**Test 2: Ver suscripción actual**
```bash
curl -X GET http://localhost/api/v1/subscriptions/current \
  -H "Authorization: Bearer $TOKEN"

# Debe retornar detalles de la suscripción
```

**Test 3: Cancelar suscripción**
```bash
curl -X POST http://localhost/api/v1/subscriptions/cancel \
  -H "Authorization: Bearer $TOKEN"

# Debe retornar subscription con cancelAtPeriodEnd: true
# Verificar que usuario mantiene acceso premium hasta fin de período
```

---

## 📊 Métricas del Sprint 3

**Código escrito:**
- 15+ archivos PHP creados
- 3 Value Objects
- 2 Entities
- 2 Repositories
- 1 Payment Gateway
- 3 Use Cases
- 2 Controllers
- ~3,000 líneas de código

**Tests:**
- 10+ test cases
- Cobertura estimada: 80%

**Tiempo total:** ~50 horas (2 semanas)

**Funcionalidades entregadas:**
- ✅ Sistema de suscripciones completo
- ✅ Integración con Stripe
- ✅ Webhooks de Stripe
- ✅ Gestión de ciclo de vida
- ✅ Actualización automática de roles
- ✅ 3 endpoints de suscripciones

---

## 🎉 ¡MVP COMPLETADO!

Con el Sprint 3 completo, el MVP de LexEcuador está LISTO para producción:

### ✅ Sprint 1: Infraestructura
- Autenticación JWT
- Clean Architecture
- Configuración de bundles

### ✅ Sprint 2: Core Features
- API de artículos
- Búsqueda y filtros
- Control de acceso por rol
- Rate limiting

### ✅ Sprint 3: Monetización
- Sistema de suscripciones
- Pagos con Stripe
- Webhooks
- Gestión de roles

---

**Total del MVP:** ~130 horas (3.5 semanas efectivas)
**Progreso:** 100% ✅

---

**Archivo generado:** `06_PLAN_SPRINT_3_PARTE_2.md`
**Siguiente:** Archivos de especificaciones detalladas (endpoints, modelo de datos, etc.)
