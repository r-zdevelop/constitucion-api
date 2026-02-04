# 11 - INTEGRACIÓN DE PAGOS (STRIPE + PAYPHONE)

**Proyecto:** LexEcuador - API REST para Constitución de Ecuador
**Propósito:** Guía completa de integración de pagos con Stripe (internacional) y PayPhone (Ecuador)
**Audiencia:** Desarrollador PHP 3+ años con conocimiento de SOLID y Clean Architecture

---

## 📋 ÍNDICE

1. [Visión General](#visión-general)
2. [Integración con Stripe](#integración-con-stripe)
3. [Integración con PayPhone](#integración-con-payphone)
4. [Arquitectura de Pagos](#arquitectura-de-pagos)
5. [Webhooks y Eventos](#webhooks-y-eventos)
6. [Testing de Pagos](#testing-de-pagos)
7. [Seguridad y PCI Compliance](#seguridad-y-pci-compliance)

---

## 🎯 VISIÓN GENERAL

### Estrategia de Pagos

**LexEcuador** soporta dos procesadores de pago:

1. **Stripe** (Internacional) - Para usuarios fuera de Ecuador y con tarjetas internacionales
2. **PayPhone** (Ecuador) - Para usuarios ecuatorianos con métodos de pago locales

### Planes y Precios

| Plan       | Precio Mensual | Stripe Price ID           | PayPhone SKU    |
|------------|----------------|---------------------------|-----------------|
| FREE       | $0.00          | -                         | -               |
| PREMIUM    | $9.99          | price_1QR3sT4uVwXyZaBc    | LEX_PREMIUM     |
| ENTERPRISE | $49.99         | price_2AB4cD5eFgHiJkLm    | LEX_ENTERPRISE  |

### Flow de Pago

```
┌──────────────┐
│   Frontend   │
│  (Angular)   │
└──────┬───────┘
       │
       │ 1. POST /subscriptions {plan, paymentMethodId}
       ▼
┌──────────────────────┐
│   API LexEcuador     │
│  SubscriptionController│
└──────┬───────────────┘
       │
       │ 2. Determinar gateway según país
       ▼
┌──────────────────────┐     ┌──────────────────────┐
│  StripePaymentGateway│  O  │ PayPhonePaymentGateway│
└──────┬───────────────┘     └──────┬───────────────┘
       │                            │
       │ 3. Crear suscripción       │ 3. Crear transacción
       ▼                            ▼
┌──────────────────────┐     ┌──────────────────────┐
│   Stripe API         │     │   PayPhone API       │
└──────┬───────────────┘     └──────┬───────────────┘
       │                            │
       │ 4. Webhook: payment_succeeded
       ▼                            ▼
┌──────────────────────────────────────┐
│   WebhookController                  │
│   - Actualizar suscripción           │
│   - Registrar pago                   │
│   - Cambiar rol del usuario          │
└──────────────────────────────────────┘
```

---

## 💳 INTEGRACIÓN CON STRIPE

### 1. Instalación y Configuración

#### Instalar SDK de Stripe

```bash
composer require stripe/stripe-php
```

#### Variables de Entorno

```bash
# .env
STRIPE_PUBLIC_KEY=pk_test_51QR3sT4uVwXyZaBc...
STRIPE_SECRET_KEY=sk_test_51QR3sT4uVwXyZaBc...
STRIPE_WEBHOOK_SECRET=whsec_...

# Price IDs (crear en Dashboard de Stripe)
STRIPE_PRICE_PREMIUM=price_1QR3sT4uVwXyZaBc
STRIPE_PRICE_ENTERPRISE=price_2AB4cD5eFgHiJkLm
```

#### Configuración del Servicio

```yaml
# config/services.yaml
services:
    App\Infrastructure\Payment\StripePaymentGateway:
        arguments:
            $secretKey: '%env(STRIPE_SECRET_KEY)%'
            $premiumPriceId: '%env(STRIPE_PRICE_PREMIUM)%'
            $enterprisePriceId: '%env(STRIPE_PRICE_ENTERPRISE)%'
```

---

### 2. Implementación del Gateway de Stripe

```php
<?php
// src/Infrastructure/Payment/StripePaymentGateway.php

namespace App\Infrastructure\Payment;

use App\Domain\Entity\User;
use App\Domain\ValueObject\SubscriptionPlan;
use Stripe\StripeClient;
use Stripe\Exception\CardException;
use Psr\Log\LoggerInterface;

final class StripePaymentGateway implements PaymentGatewayInterface
{
    private StripeClient $stripe;

    public function __construct(
        string $secretKey,
        private readonly string $premiumPriceId,
        private readonly string $enterprisePriceId,
        private readonly LoggerInterface $logger
    ) {
        $this->stripe = new StripeClient($secretKey);
    }

    public function createSubscription(
        User $user,
        SubscriptionPlan $plan,
        string $paymentMethodId
    ): array {
        try {
            // 1. Crear o recuperar Customer en Stripe
            $customerId = $user->getStripeCustomerId() ?? $this->createCustomer($user);

            // 2. Adjuntar Payment Method al Customer
            $this->stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $customerId,
            ]);

            // 3. Establecer como método de pago predeterminado
            $this->stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            // 4. Crear suscripción
            $subscription = $this->stripe->subscriptions->create([
                'customer' => $customerId,
                'items' => [[
                    'price' => $this->getPriceIdForPlan($plan),
                ]],
                'expand' => ['latest_invoice.payment_intent'],
                'metadata' => [
                    'user_id' => $user->getId(),
                    'plan' => $plan->value,
                ],
            ]);

            $invoice = $subscription->latest_invoice;
            $paymentIntent = $invoice->payment_intent;

            // 5. Retornar datos
            return [
                'subscriptionId' => $subscription->id,
                'customerId' => $customerId,
                'status' => $subscription->status,
                'currentPeriodEnd' => $subscription->current_period_end,
                'payment' => [
                    'id' => $paymentIntent->id,
                    'status' => $paymentIntent->status,
                    'amount' => $paymentIntent->amount / 100,
                    'currency' => strtoupper($paymentIntent->currency),
                ],
                'invoice' => [
                    'invoiceUrl' => $invoice->hosted_invoice_url,
                    'invoicePdf' => $invoice->invoice_pdf,
                ],
            ];
        } catch (CardException $e) {
            $this->logger->error('Stripe card error', [
                'error' => $e->getMessage(),
                'code' => $e->getError()->code,
            ]);

            throw new PaymentFailedException(
                message: $e->getMessage(),
                code: $e->getError()->code,
                declineCode: $e->getError()->decline_code
            );
        } catch (\Exception $e) {
            $this->logger->error('Stripe general error', [
                'error' => $e->getMessage(),
            ]);

            throw new PaymentFailedException('Payment processing failed: ' . $e->getMessage());
        }
    }

    public function upgradeSubscription(
        string $subscriptionId,
        SubscriptionPlan $newPlan
    ): array {
        try {
            $subscription = $this->stripe->subscriptions->retrieve($subscriptionId);

            // Actualizar a nuevo plan
            $updatedSubscription = $this->stripe->subscriptions->update($subscriptionId, [
                'items' => [[
                    'id' => $subscription->items->data[0]->id,
                    'price' => $this->getPriceIdForPlan($newPlan),
                ]],
                'proration_behavior' => 'create_prorations', // Prorrateo automático
                'metadata' => [
                    'plan' => $newPlan->value,
                ],
            ]);

            // Calcular prorrateo
            $upcomingInvoice = $this->stripe->invoices->upcoming([
                'customer' => $subscription->customer,
                'subscription' => $subscriptionId,
            ]);

            return [
                'subscriptionId' => $updatedSubscription->id,
                'plan' => $newPlan->value,
                'status' => $updatedSubscription->status,
                'proration' => [
                    'amount' => $upcomingInvoice->amount_due / 100,
                    'currency' => strtoupper($upcomingInvoice->currency),
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('Stripe upgrade error', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
            ]);

            throw new PaymentFailedException('Upgrade failed: ' . $e->getMessage());
        }
    }

    public function cancelSubscription(
        string $subscriptionId,
        bool $immediately = false
    ): void {
        try {
            if ($immediately) {
                // Cancelar inmediatamente
                $this->stripe->subscriptions->cancel($subscriptionId);
            } else {
                // Cancelar al final del período
                $this->stripe->subscriptions->update($subscriptionId, [
                    'cancel_at_period_end' => true,
                ]);
            }

            $this->logger->info('Subscription canceled', [
                'subscription_id' => $subscriptionId,
                'immediately' => $immediately,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Stripe cancellation error', [
                'error' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
            ]);

            throw new PaymentFailedException('Cancellation failed: ' . $e->getMessage());
        }
    }

    public function createPaymentMethod(
        User $user,
        string $paymentMethodId
    ): array {
        try {
            $customerId = $user->getStripeCustomerId() ?? $this->createCustomer($user);

            // Adjuntar Payment Method
            $paymentMethod = $this->stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $customerId,
            ]);

            return [
                'id' => $paymentMethod->id,
                'type' => $paymentMethod->type,
                'card' => [
                    'brand' => $paymentMethod->card->brand,
                    'last4' => $paymentMethod->card->last4,
                    'expiryMonth' => $paymentMethod->card->exp_month,
                    'expiryYear' => $paymentMethod->card->exp_year,
                ],
            ];
        } catch (\Exception $e) {
            throw new PaymentFailedException('Failed to add payment method: ' . $e->getMessage());
        }
    }

    private function createCustomer(User $user): string
    {
        $customer = $this->stripe->customers->create([
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'metadata' => [
                'user_id' => $user->getId(),
            ],
        ]);

        return $customer->id;
    }

    private function getPriceIdForPlan(SubscriptionPlan $plan): string
    {
        return match ($plan) {
            SubscriptionPlan::PREMIUM => $this->premiumPriceId,
            SubscriptionPlan::ENTERPRISE => $this->enterprisePriceId,
            SubscriptionPlan::FREE => throw new \InvalidArgumentException('FREE plan has no price'),
        };
    }
}
```

---

### 3. Crear Productos y Precios en Stripe

#### Opción A: Desde el Dashboard de Stripe

1. Ir a https://dashboard.stripe.com/products
2. Crear producto "LexEcuador PREMIUM"
   - Precio: $9.99 USD
   - Facturación: Mensual (recurring)
   - Copiar Price ID: `price_1QR3sT4uVwXyZaBc`

3. Crear producto "LexEcuador ENTERPRISE"
   - Precio: $49.99 USD
   - Facturación: Mensual (recurring)
   - Copiar Price ID: `price_2AB4cD5eFgHiJkLm`

#### Opción B: Mediante CLI de Stripe

```bash
# Crear producto PREMIUM
stripe products create \
  --name="LexEcuador PREMIUM" \
  --description="Acceso completo a todos los artículos"

# Crear precio para PREMIUM
stripe prices create \
  --product=prod_XXX \
  --currency=usd \
  --unit-amount=999 \
  --recurring[interval]=month

# Crear producto ENTERPRISE
stripe products create \
  --name="LexEcuador ENTERPRISE" \
  --description="Acceso API + Soporte prioritario"

# Crear precio para ENTERPRISE
stripe prices create \
  --product=prod_YYY \
  --currency=usd \
  --unit-amount=4999 \
  --recurring[interval]=month
```

---

### 4. Webhooks de Stripe

#### Configuración del Webhook

```bash
# Desarrollo local (usar Stripe CLI)
stripe listen --forward-to localhost:8000/api/v1/webhooks/stripe

# Producción (configurar en Dashboard)
# URL: https://api.lexecuador.com/api/v1/webhooks/stripe
# Eventos:
# - invoice.payment_succeeded
# - invoice.payment_failed
# - customer.subscription.updated
# - customer.subscription.deleted
```

#### Implementación del Webhook Handler

Ver archivo `09_ENDPOINTS_SUBSCRIPTIONS.md` líneas 850-1050 para código completo del webhook.

---

## 📱 INTEGRACIÓN CON PAYPHONE

PayPhone es el procesador de pagos líder en Ecuador. Soporta:
- Tarjetas de crédito/débito locales
- Transferencias bancarias
- Pagos QR

### 1. Instalación y Configuración

#### Instalar SDK de PayPhone (Manual)

```bash
# PayPhone no tiene SDK oficial, usar HTTP Client
composer require symfony/http-client
```

#### Variables de Entorno

```bash
# .env
PAYPHONE_TOKEN=your_token_here
PAYPHONE_CLIENT_ID=your_client_id_here
PAYPHONE_API_URL=https://pay.payphonetodoesposible.com/api
PAYPHONE_STORE_ID=1234

# SKUs de productos
PAYPHONE_SKU_PREMIUM=LEX_PREMIUM
PAYPHONE_SKU_ENTERPRISE=LEX_ENTERPRISE
```

#### Configuración del Servicio

```yaml
# config/services.yaml
services:
    App\Infrastructure\Payment\PayPhonePaymentGateway:
        arguments:
            $token: '%env(PAYPHONE_TOKEN)%'
            $clientId: '%env(PAYPHONE_CLIENT_ID)%'
            $apiUrl: '%env(PAYPHONE_API_URL)%'
            $storeId: '%env(PAYPHONE_STORE_ID)%'
```

---

### 2. Implementación del Gateway de PayPhone

```php
<?php
// src/Infrastructure/Payment/PayPhonePaymentGateway.php

namespace App\Infrastructure\Payment;

use App\Domain\Entity\User;
use App\Domain\ValueObject\SubscriptionPlan;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

final class PayPhonePaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $token,
        private readonly string $clientId,
        private readonly string $apiUrl,
        private readonly string $storeId,
        private readonly LoggerInterface $logger
    ) {}

    public function createSubscription(
        User $user,
        SubscriptionPlan $plan,
        string $paymentMethodId
    ): array {
        try {
            // 1. Preparar datos de la transacción
            $amount = $plan->getPrice();
            $orderId = $this->generateOrderId($user->getId(), $plan);

            // 2. Crear solicitud de pago
            $response = $this->httpClient->request('POST', $this->apiUrl . '/Sale', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'amount' => $amount,
                    'amountWithoutTax' => $amount / 1.12, // IVA 12% en Ecuador
                    'clientTransactionId' => $orderId,
                    'currency' => 'USD',
                    'email' => $user->getEmail(),
                    'phone' => $user->getPhone() ?? '0999999999',
                    'documentId' => $user->getDocumentId() ?? '9999999999',
                    'service' => 'LexEcuador',
                    'tip' => 0,
                    'tax' => $amount - ($amount / 1.12),
                    'storeId' => $this->storeId,
                    'clientId' => $this->clientId,
                    'metadata' => [
                        'user_id' => $user->getId(),
                        'plan' => $plan->value,
                    ],
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['transactionId'])) {
                throw new PaymentFailedException('Invalid response from PayPhone');
            }

            // 3. Retornar datos
            return [
                'transactionId' => $data['transactionId'],
                'paymentUrl' => $data['paymentUrl'] ?? null,
                'status' => $data['transactionStatus'] ?? 'pending',
                'orderId' => $orderId,
                'payment' => [
                    'amount' => $amount,
                    'currency' => 'USD',
                    'status' => 'pending',
                ],
            ];
        } catch (\Exception $e) {
            $this->logger->error('PayPhone payment error', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);

            throw new PaymentFailedException('PayPhone payment failed: ' . $e->getMessage());
        }
    }

    public function verifyTransaction(string $transactionId): array
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl . '/Confirm', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'id' => $transactionId,
                    'clientTxId' => $transactionId,
                ],
            ]);

            $data = $response->toArray();

            return [
                'transactionId' => $data['transactionId'] ?? $transactionId,
                'status' => $data['transactionStatus'] ?? 'unknown',
                'statusCode' => $data['statusCode'] ?? 0,
                'amount' => $data['amount'] ?? 0,
                'currency' => 'USD',
            ];
        } catch (\Exception $e) {
            $this->logger->error('PayPhone verification error', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);

            throw new PaymentFailedException('Verification failed: ' . $e->getMessage());
        }
    }

    public function cancelSubscription(
        string $transactionId,
        bool $immediately = false
    ): void {
        // PayPhone no tiene suscripciones recurrentes automáticas
        // Las suscripciones se manejan manualmente en nuestra DB
        $this->logger->info('PayPhone subscription canceled manually', [
            'transaction_id' => $transactionId,
        ]);
    }

    private function generateOrderId(string $userId, SubscriptionPlan $plan): string
    {
        return 'LEX_' . substr($userId, 0, 8) . '_' . $plan->value . '_' . time();
    }
}
```

---

### 3. Webhook de PayPhone

```php
<?php
// src/Infrastructure/Presentation/Controller/PayPhoneWebhookController.php

namespace App\Infrastructure\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Application\UseCase\Subscription\HandlePayPhoneWebhookUseCase;

#[Route('/api/v1/webhooks', name: 'api_webhooks_')]
class PayPhoneWebhookController extends AbstractController
{
    public function __construct(
        private readonly HandlePayPhoneWebhookUseCase $handleWebhook
    ) {}

    #[Route('/payphone', name: 'payphone', methods: ['POST'])]
    public function payphone(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        // Validar firma de PayPhone
        $signature = $request->headers->get('X-PayPhone-Signature');

        if (!$this->validateSignature($payload, $signature)) {
            return $this->json(['error' => 'Invalid signature'], 400);
        }

        // Procesar evento
        try {
            $this->handleWebhook->execute($payload);

            return $this->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], 200);
        }
    }

    private function validateSignature(array $payload, ?string $signature): bool
    {
        // Validar según documentación de PayPhone
        $expectedSignature = hash_hmac(
            'sha256',
            json_encode($payload),
            $_ENV['PAYPHONE_SECRET']
        );

        return hash_equals($expectedSignature, $signature ?? '');
    }
}
```

---

## 🏗️ ARQUITECTURA DE PAGOS

### Interface PaymentGatewayInterface

```php
<?php
// src/Domain/Contract/PaymentGatewayInterface.php

namespace App\Domain\Contract;

use App\Domain\Entity\User;
use App\Domain\ValueObject\SubscriptionPlan;

interface PaymentGatewayInterface
{
    /**
     * Crear nueva suscripción
     */
    public function createSubscription(
        User $user,
        SubscriptionPlan $plan,
        string $paymentMethodId
    ): array;

    /**
     * Cancelar suscripción
     */
    public function cancelSubscription(
        string $subscriptionId,
        bool $immediately = false
    ): void;
}
```

### Factory para seleccionar Gateway

```php
<?php
// src/Application/Service/PaymentGatewayFactory.php

namespace App\Application\Service;

use App\Domain\Contract\PaymentGatewayInterface;
use App\Infrastructure\Payment\StripePaymentGateway;
use App\Infrastructure\Payment\PayPhonePaymentGateway;

final class PaymentGatewayFactory
{
    public function __construct(
        private readonly StripePaymentGateway $stripeGateway,
        private readonly PayPhonePaymentGateway $payPhoneGateway
    ) {}

    public function getGateway(string $country): PaymentGatewayInterface
    {
        return match ($country) {
            'EC' => $this->payPhoneGateway,  // Ecuador → PayPhone
            default => $this->stripeGateway,  // Resto del mundo → Stripe
        };
    }
}
```

### Uso en el Use Case

```php
<?php
// src/Application/UseCase/Subscription/CreateSubscriptionUseCase.php

namespace App\Application\UseCase\Subscription;

use App\Application\Service\PaymentGatewayFactory;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Domain\ValueObject\SubscriptionPlan;

final readonly class CreateSubscriptionUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PaymentGatewayFactory $gatewayFactory
    ) {}

    public function execute(string $userId, array $data): array
    {
        // 1. Obtener usuario
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new \DomainException('User not found.');
        }

        // 2. Validar plan
        $plan = SubscriptionPlan::from($data['plan']);

        // 3. Seleccionar gateway según país del usuario
        $gateway = $this->gatewayFactory->getGateway($user->getCountry() ?? 'US');

        // 4. Crear suscripción en el gateway
        $paymentData = $gateway->createSubscription(
            user: $user,
            plan: $plan,
            paymentMethodId: $data['paymentMethodId']
        );

        // 5. Guardar en base de datos
        $subscription = Subscription::create(
            userId: $userId,
            plan: $plan,
            stripeSubscriptionId: $paymentData['subscriptionId'] ?? null,
            stripeCustomerId: $paymentData['customerId'] ?? null,
            currentPeriodEnd: isset($paymentData['currentPeriodEnd'])
                ? new \DateTimeImmutable('@' . $paymentData['currentPeriodEnd'])
                : null
        );

        $this->subscriptionRepository->save($subscription);

        // 6. Actualizar rol del usuario
        $user->upgradeToPlan(Role::from('ROLE_' . $plan->value));
        $this->userRepository->save($user);

        return [
            'subscription' => $subscription,
            'payment' => $paymentData['payment'],
            'message' => "Successfully subscribed to {$plan->value}!",
        ];
    }
}
```

---

## 🔔 WEBHOOKS Y EVENTOS

### Eventos de Stripe

| Evento                          | Descripción                        | Acción                          |
|---------------------------------|------------------------------------|---------------------------------|
| `invoice.payment_succeeded`     | Pago exitoso                       | Renovar suscripción             |
| `invoice.payment_failed`        | Pago fallido                       | Notificar al usuario            |
| `customer.subscription.updated` | Suscripción actualizada            | Actualizar estado en DB         |
| `customer.subscription.deleted` | Suscripción cancelada              | Downgrade a FREE                |

### Eventos de PayPhone

| Evento         | `statusCode` | Descripción                | Acción                          |
|----------------|--------------|----------------------------|---------------------------------|
| `Approved`     | 3            | Pago aprobado              | Activar suscripción             |
| `Pending`      | 1            | Pago pendiente             | Esperar confirmación            |
| `Rejected`     | 2            | Pago rechazado             | Notificar rechazo               |
| `Cancelled`    | 5            | Pago cancelado por usuario | Cancelar transacción            |

---

## 🧪 TESTING DE PAGOS

### Tarjetas de Prueba de Stripe

```php
// Tarjetas de prueba
const STRIPE_TEST_CARDS = [
    'success' => '4242424242424242',           // Pago exitoso
    'declined' => '4000000000000002',          // Tarjeta rechazada
    'insufficient_funds' => '4000000000009995', // Fondos insuficientes
    'expired' => '4000000000000069',           // Tarjeta expirada
    'incorrect_cvc' => '4000000000000127',     // CVC incorrecto
    '3d_secure' => '4000002500003155',         // Requiere 3D Secure
];
```

### Test de Integración con Stripe

```php
<?php
// tests/Infrastructure/Payment/StripePaymentGatewayTest.php

namespace App\Tests\Infrastructure\Payment;

use App\Infrastructure\Payment\StripePaymentGateway;
use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\SubscriptionPlan;
use PHPUnit\Framework\TestCase;

final class StripePaymentGatewayTest extends TestCase
{
    private StripePaymentGateway $gateway;

    protected function setUp(): void
    {
        $this->gateway = new StripePaymentGateway(
            secretKey: $_ENV['STRIPE_SECRET_KEY'],
            premiumPriceId: $_ENV['STRIPE_PRICE_PREMIUM'],
            enterprisePriceId: $_ENV['STRIPE_PRICE_ENTERPRISE'],
            logger: $this->createMock(LoggerInterface::class)
        );
    }

    public function testCreateSubscriptionWithValidCard(): void
    {
        // Arrange
        $user = User::register(
            email: new Email('test@example.com'),
            hashedPassword: 'hashed',
            name: 'Test User'
        );

        // Act
        $result = $this->gateway->createSubscription(
            user: $user,
            plan: SubscriptionPlan::PREMIUM,
            paymentMethodId: 'pm_card_visa' // Token de prueba de Stripe
        );

        // Assert
        $this->assertArrayHasKey('subscriptionId', $result);
        $this->assertArrayHasKey('customerId', $result);
        $this->assertEquals('succeeded', $result['payment']['status']);
        $this->assertEquals(9.99, $result['payment']['amount']);
    }

    public function testCreateSubscriptionWithDeclinedCard(): void
    {
        $this->expectException(PaymentFailedException::class);

        $user = User::register(
            email: new Email('test@example.com'),
            hashedPassword: 'hashed',
            name: 'Test User'
        );

        $this->gateway->createSubscription(
            user: $user,
            plan: SubscriptionPlan::PREMIUM,
            paymentMethodId: 'pm_card_chargeDeclined'
        );
    }
}
```

### Test con Stripe CLI

```bash
# 1. Instalar Stripe CLI
brew install stripe/stripe-cli/stripe

# 2. Login
stripe login

# 3. Escuchar webhooks
stripe listen --forward-to localhost:8000/api/v1/webhooks/stripe

# 4. Simular evento de pago exitoso
stripe trigger invoice.payment_succeeded

# 5. Simular evento de pago fallido
stripe trigger invoice.payment_failed
```

---

## 🔒 SEGURIDAD Y PCI COMPLIANCE

### PCI Compliance

**Importante:** LexEcuador NO almacena datos de tarjetas de crédito. Toda la información sensible es manejada por Stripe y PayPhone (ambos son PCI compliant).

### Buenas Prácticas

#### 1. NUNCA almacenar datos de tarjetas

```php
// ❌ MAL - NUNCA hacer esto
$user->setCreditCardNumber('4242424242424242');
$user->setCvv('123');

// ✅ BIEN - Solo almacenar IDs de referencia
$user->setStripeCustomerId('cus_123');
$subscription->setStripeSubscriptionId('sub_123');
```

#### 2. Usar HTTPS en producción

```yaml
# config/packages/security.yaml
security:
    # Forzar HTTPS en producción
    access_control:
        - { path: ^/api, roles: PUBLIC_ACCESS, requires_channel: https }
```

#### 3. Validar firma de webhooks

```php
// Stripe
try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sigHeader,
        $_ENV['STRIPE_WEBHOOK_SECRET']
    );
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Firma inválida - rechazar
    return new JsonResponse(['error' => 'Invalid signature'], 400);
}

// PayPhone
$expectedSignature = hash_hmac('sha256', json_encode($payload), $_ENV['PAYPHONE_SECRET']);
if (!hash_equals($expectedSignature, $signature)) {
    return new JsonResponse(['error' => 'Invalid signature'], 400);
}
```

#### 4. Logs de auditoría

```php
// Registrar todos los eventos de pago
$this->logger->info('Payment created', [
    'user_id' => $user->getId(),
    'amount' => $amount,
    'currency' => 'USD',
    'status' => 'succeeded',
    'gateway' => 'stripe',
    'timestamp' => time(),
]);
```

#### 5. Rate limiting en endpoints de pago

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        payment_creation:
            policy: 'fixed_window'
            limit: 5            # Máximo 5 intentos
            interval: '1 hour'  # Por hora
```

---

## 📊 MONITOREO Y ALERTAS

### Métricas a monitorear

```php
// Métricas clave
- Tasa de éxito de pagos (goal: >95%)
- Tiempo promedio de procesamiento
- Tasa de rechazo por gateway
- Revenue mensual por plan
- Churm rate (cancelaciones)
```

### Alertas críticas

```php
// Configurar alertas para:
- Pago rechazado >3 veces consecutivas
- Webhook no procesado en >5 minutos
- Tasa de éxito <90% en última hora
- Error de gateway de pago
```

---

## ✅ CHECKLIST DE IMPLEMENTACIÓN

- [ ] Instalar Stripe SDK: `composer require stripe/stripe-php`
- [ ] Crear cuenta en Stripe (https://dashboard.stripe.com)
- [ ] Crear productos y precios en Stripe Dashboard
- [ ] Configurar variables de entorno (.env)
- [ ] Implementar StripePaymentGateway
- [ ] Implementar PayPhonePaymentGateway (si aplica)
- [ ] Implementar PaymentGatewayFactory
- [ ] Configurar webhooks en Stripe Dashboard
- [ ] Implementar WebhookController
- [ ] Probar con Stripe CLI: `stripe listen`
- [ ] Probar tarjetas de prueba
- [ ] Implementar tests unitarios
- [ ] Configurar alertas de monitoreo
- [ ] Validar PCI compliance
- [ ] Documentar en Swagger

---

**Archivo generado:** `11_INTEGRACION_PAGOS.md`
**Siguiente:** `12_SEGURIDAD_CORS.md` (Seguridad, CORS, Validación)
