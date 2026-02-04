# 09 - ENDPOINTS DE SUSCRIPCIONES

**Proyecto:** LexEcuador - API REST para Constitución de Ecuador
**Sprint:** Sprint 3 (Semanas 5-6)
**Propósito:** Especificación completa de todos los endpoints relacionados con suscripciones y pagos
**Audiencia:** Desarrollador PHP 3+ años con conocimiento de SOLID y Clean Architecture

---

## 📋 ÍNDICE

1. [Visión General](#visión-general)
2. [Endpoints de Suscripciones](#endpoints-de-suscripciones)
3. [Endpoints de Pagos](#endpoints-de-pagos)
4. [Webhooks de Stripe](#webhooks-de-stripe)
5. [Manejo de Errores](#manejo-de-errores)
6. [Testing](#testing)

---

## 🎯 VISIÓN GENERAL

### Planes de Suscripción

```php
enum SubscriptionPlan: string
{
    case FREE = 'FREE';           // $0/mes - Artículos 1-100
    case PREMIUM = 'PREMIUM';     // $9.99/mes - Todos los artículos
    case ENTERPRISE = 'ENTERPRISE'; // $49.99/mes - API key + soporte
}
```

### Base URL

```
https://api.lexecuador.com/api/v1
```

### Autenticación

Todos los endpoints de suscripciones requieren autenticación JWT:

```http
Authorization: Bearer {token}
```

### Rate Limiting

- **FREE:** No aplica (no puede acceder a estos endpoints)
- **PREMIUM:** 100 req/hora
- **ENTERPRISE:** 1000 req/hora

---

## 📌 ENDPOINTS DE SUSCRIPCIONES

### 1. GET /subscriptions/current

Obtiene la suscripción activa del usuario autenticado.

#### Request

```bash
curl -X GET https://api.lexecuador.com/api/v1/subscriptions/current \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLC..." \
  -H "Accept: application/json"
```

```javascript
// JavaScript (Fetch API)
const response = await fetch('https://api.lexecuador.com/api/v1/subscriptions/current', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
});

const data = await response.json();
```

#### Response 200 OK (Usuario FREE)

```json
{
  "subscription": {
    "id": "sub_free_550e8400",
    "userId": "550e8400-e29b-41d4-a716-446655440000",
    "plan": "FREE",
    "status": "active",
    "startedAt": "2024-01-15T10:00:00Z",
    "currentPeriodStart": "2024-01-15T10:00:00Z",
    "currentPeriodEnd": null,
    "cancelAt": null,
    "canceledAt": null,
    "features": {
      "articlesAccess": "1-100",
      "apiRequestsPerDay": 100,
      "concordancesAccess": false,
      "apiKeyAccess": false,
      "prioritySupport": false
    },
    "billing": {
      "amount": 0.00,
      "currency": "USD",
      "interval": "month",
      "nextBillingDate": null
    }
  },
  "upgradeOptions": [
    {
      "plan": "PREMIUM",
      "price": 9.99,
      "currency": "USD",
      "features": [
        "Acceso a todos los 467 artículos",
        "10,000 solicitudes API por día",
        "Acceso a concordancias",
        "Soporte prioritario"
      ],
      "upgradeUrl": "/api/v1/subscriptions/upgrade"
    },
    {
      "plan": "ENTERPRISE",
      "price": 49.99,
      "currency": "USD",
      "features": [
        "Todo lo de PREMIUM",
        "API Key para integraciones",
        "Solicitudes ilimitadas",
        "Soporte dedicado 24/7"
      ],
      "upgradeUrl": "/api/v1/subscriptions/upgrade"
    }
  ]
}
```

#### Response 200 OK (Usuario PREMIUM)

```json
{
  "subscription": {
    "id": "sub_1QR3sT4uVwXyZaBc",
    "userId": "550e8400-e29b-41d4-a716-446655440000",
    "plan": "PREMIUM",
    "status": "active",
    "stripeSubscriptionId": "sub_1QR3sT4uVwXyZaBc",
    "startedAt": "2024-02-01T14:30:00Z",
    "currentPeriodStart": "2024-03-01T14:30:00Z",
    "currentPeriodEnd": "2024-04-01T14:30:00Z",
    "cancelAt": null,
    "canceledAt": null,
    "features": {
      "articlesAccess": "all",
      "apiRequestsPerDay": 10000,
      "concordancesAccess": true,
      "apiKeyAccess": false,
      "prioritySupport": true
    },
    "billing": {
      "amount": 9.99,
      "currency": "USD",
      "interval": "month",
      "nextBillingDate": "2024-04-01T14:30:00Z",
      "paymentMethod": {
        "type": "card",
        "last4": "4242",
        "brand": "visa",
        "expiryMonth": 12,
        "expiryYear": 2025
      }
    }
  },
  "upgradeOptions": [
    {
      "plan": "ENTERPRISE",
      "price": 49.99,
      "currency": "USD",
      "features": [
        "API Key para integraciones",
        "Solicitudes ilimitadas",
        "Soporte dedicado 24/7"
      ],
      "upgradeUrl": "/api/v1/subscriptions/upgrade"
    }
  ]
}
```

#### Implementación en Controller

```php
<?php
// src/Infrastructure/Presentation/Controller/SubscriptionController.php

namespace App\Infrastructure\Presentation\Controller;

use App\Application\UseCase\Subscription\GetCurrentSubscriptionUseCase;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/subscriptions', name: 'api_subscriptions_')]
class SubscriptionController extends AbstractController
{
    #[Route('/current', name: 'current', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getCurrent(
        GetCurrentSubscriptionUseCase $useCase
    ): JsonResponse {
        $user = $this->getUser();

        $result = $useCase->execute($user->getId());

        return $this->json($result, 200, [], [
            'groups' => ['subscription:read']
        ]);
    }
}
```

---

### 2. POST /subscriptions

Crea una nueva suscripción (upgrade de FREE a PREMIUM/ENTERPRISE).

#### Request

```bash
curl -X POST https://api.lexecuador.com/api/v1/subscriptions \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLC..." \
  -H "Content-Type: application/json" \
  -d '{
    "plan": "PREMIUM",
    "paymentMethodId": "pm_1QR3sT4uVwXyZaBc"
  }'
```

```javascript
// JavaScript (Fetch API)
const response = await fetch('https://api.lexecuador.com/api/v1/subscriptions', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    plan: 'PREMIUM',
    paymentMethodId: 'pm_1QR3sT4uVwXyZaBc'
  })
});

const data = await response.json();
```

#### Request Body Schema

```json
{
  "plan": "PREMIUM | ENTERPRISE",  // ← Required
  "paymentMethodId": "pm_xxxxx",   // ← Required (Stripe Payment Method ID)
  "billingCycle": "monthly"         // ← Optional (default: monthly)
}
```

#### Response 201 Created

```json
{
  "subscription": {
    "id": "sub_1QR3sT4uVwXyZaBc",
    "userId": "550e8400-e29b-41d4-a716-446655440000",
    "plan": "PREMIUM",
    "status": "active",
    "stripeSubscriptionId": "sub_1QR3sT4uVwXyZaBc",
    "stripeCustomerId": "cus_QR3sT4uVwXyZaBc",
    "startedAt": "2024-03-15T16:45:00Z",
    "currentPeriodStart": "2024-03-15T16:45:00Z",
    "currentPeriodEnd": "2024-04-15T16:45:00Z",
    "createdAt": "2024-03-15T16:45:00Z"
  },
  "payment": {
    "id": "pay_550e8400",
    "amount": 9.99,
    "currency": "USD",
    "status": "succeeded",
    "stripePaymentIntentId": "pi_1QR3sT4uVwXyZaBc",
    "paidAt": "2024-03-15T16:45:01Z"
  },
  "invoice": {
    "invoiceUrl": "https://invoice.stripe.com/i/acct_xxx/invst_xxx",
    "invoicePdf": "https://pay.stripe.com/invoice/acct_xxx/invst_xxx/pdf"
  },
  "message": "Subscription created successfully. Welcome to PREMIUM!"
}
```

#### Response 400 Bad Request (Usuario ya tiene suscripción activa)

```json
{
  "type": "https://api.lexecuador.com/problems/subscription-already-exists",
  "title": "Subscription Already Exists",
  "status": 400,
  "detail": "You already have an active PREMIUM subscription. Use /upgrade or /cancel instead.",
  "currentSubscription": {
    "id": "sub_1QR3sT4uVwXyZaBc",
    "plan": "PREMIUM",
    "status": "active"
  }
}
```

#### Response 402 Payment Required (Pago fallido)

```json
{
  "type": "https://api.lexecuador.com/problems/payment-failed",
  "title": "Payment Failed",
  "status": 402,
  "detail": "Your card was declined. Please use a different payment method.",
  "stripeError": {
    "code": "card_declined",
    "declineCode": "insufficient_funds",
    "message": "Your card has insufficient funds."
  },
  "retryUrl": "/api/v1/subscriptions"
}
```

#### Implementación del Use Case

```php
<?php
// src/Application/UseCase/Subscription/CreateSubscriptionUseCase.php

namespace App\Application\UseCase\Subscription;

use App\Domain\Entity\User;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Domain\ValueObject\SubscriptionPlan;
use App\Infrastructure\Payment\StripePaymentGateway;
use App\Infrastructure\Presentation\Exception\ValidationException;

final readonly class CreateSubscriptionUseCase
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private StripePaymentGateway $paymentGateway
    ) {}

    public function execute(string $userId, array $data): array
    {
        // 1. Validar request
        if (empty($data['plan']) || empty($data['paymentMethodId'])) {
            throw new ValidationException('Plan and paymentMethodId are required.');
        }

        // 2. Validar plan
        $plan = SubscriptionPlan::from($data['plan']);

        if ($plan === SubscriptionPlan::FREE) {
            throw new ValidationException('Cannot subscribe to FREE plan.');
        }

        // 3. Obtener usuario
        $user = $this->userRepository->findById($userId);

        if (!$user) {
            throw new \DomainException('User not found.');
        }

        // 4. Verificar que no tenga suscripción activa
        $existingSubscription = $this->subscriptionRepository->findActiveByUserId($userId);

        if ($existingSubscription && $existingSubscription->getPlan() !== SubscriptionPlan::FREE) {
            throw new ValidationException('User already has an active subscription.');
        }

        // 5. Crear suscripción en Stripe
        try {
            $stripeData = $this->paymentGateway->createSubscription(
                user: $user,
                plan: $plan,
                paymentMethodId: $data['paymentMethodId']
            );
        } catch (\Stripe\Exception\CardException $e) {
            throw new PaymentFailedException($e->getMessage(), $e->getError()->code);
        }

        // 6. Crear entidad Subscription
        $subscription = Subscription::create(
            userId: $userId,
            plan: $plan,
            stripeSubscriptionId: $stripeData['subscriptionId'],
            stripeCustomerId: $stripeData['customerId'],
            currentPeriodEnd: new \DateTimeImmutable($stripeData['currentPeriodEnd'])
        );

        // 7. Guardar en base de datos
        $this->subscriptionRepository->save($subscription);

        // 8. Actualizar rol del usuario
        $user->upgradeToPlan($plan);
        $this->userRepository->save($user);

        // 9. Retornar resultado
        return [
            'subscription' => $subscription,
            'payment' => $stripeData['payment'],
            'invoice' => $stripeData['invoice'],
            'message' => "Subscription created successfully. Welcome to {$plan->value}!"
        ];
    }
}
```

---

### 3. POST /subscriptions/upgrade

Actualiza la suscripción actual a un plan superior.

#### Request

```bash
curl -X POST https://api.lexecuador.com/api/v1/subscriptions/upgrade \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLC..." \
  -H "Content-Type: application/json" \
  -d '{
    "newPlan": "ENTERPRISE"
  }'
```

#### Request Body Schema

```json
{
  "newPlan": "PREMIUM | ENTERPRISE"  // ← Required
}
```

#### Response 200 OK

```json
{
  "subscription": {
    "id": "sub_1QR3sT4uVwXyZaBc",
    "userId": "550e8400-e29b-41d4-a716-446655440000",
    "plan": "ENTERPRISE",
    "status": "active",
    "upgradedAt": "2024-03-20T10:15:00Z",
    "currentPeriodEnd": "2024-04-15T16:45:00Z"
  },
  "proration": {
    "amount": 32.67,
    "currency": "USD",
    "description": "Prorated upgrade from PREMIUM to ENTERPRISE",
    "proratedDays": 26
  },
  "message": "Successfully upgraded to ENTERPRISE plan!"
}
```

#### Response 400 Bad Request (Downgrade no permitido)

```json
{
  "type": "https://api.lexecuador.com/problems/invalid-upgrade",
  "title": "Invalid Upgrade",
  "status": 400,
  "detail": "Cannot downgrade from ENTERPRISE to PREMIUM. Use /cancel instead.",
  "currentPlan": "ENTERPRISE",
  "requestedPlan": "PREMIUM"
}
```

---

### 4. POST /subscriptions/cancel

Cancela la suscripción actual (aplica al final del período de facturación).

#### Request

```bash
curl -X POST https://api.lexecuador.com/api/v1/subscriptions/cancel \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLC..." \
  -H "Content-Type: application/json" \
  -d '{
    "reason": "Too expensive",
    "cancelImmediately": false
  }'
```

#### Request Body Schema

```json
{
  "reason": "string",              // ← Optional (feedback)
  "cancelImmediately": false       // ← Optional (default: false)
}
```

**Comportamiento:**
- `cancelImmediately: false` → La suscripción sigue activa hasta el final del período pagado
- `cancelImmediately: true` → Cancela inmediatamente y devuelve rol FREE

#### Response 200 OK (Cancelación al final del período)

```json
{
  "subscription": {
    "id": "sub_1QR3sT4uVwXyZaBc",
    "userId": "550e8400-e29b-41d4-a716-446655440000",
    "plan": "PREMIUM",
    "status": "active",
    "cancelAt": "2024-04-15T16:45:00Z",
    "canceledAt": "2024-03-20T12:00:00Z",
    "currentPeriodEnd": "2024-04-15T16:45:00Z"
  },
  "message": "Subscription will be canceled on 2024-04-15. You'll keep access until then.",
  "accessUntil": "2024-04-15T16:45:00Z"
}
```

#### Response 200 OK (Cancelación inmediata)

```json
{
  "subscription": {
    "id": "sub_1QR3sT4uVwXyZaBc",
    "userId": "550e8400-e29b-41d4-a716-446655440000",
    "plan": "FREE",
    "status": "canceled",
    "canceledAt": "2024-03-20T12:00:00Z",
    "endedAt": "2024-03-20T12:00:00Z"
  },
  "message": "Subscription canceled immediately. You now have FREE access.",
  "newRole": "ROLE_FREE"
}
```

#### Implementación

```php
<?php
// src/Application/UseCase/Subscription/CancelSubscriptionUseCase.php

namespace App\Application\UseCase\Subscription;

use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Infrastructure\Payment\StripePaymentGateway;

final readonly class CancelSubscriptionUseCase
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private StripePaymentGateway $paymentGateway
    ) {}

    public function execute(string $userId, array $data): array
    {
        // 1. Obtener suscripción activa
        $subscription = $this->subscriptionRepository->findActiveByUserId($userId);

        if (!$subscription || $subscription->getPlan() === SubscriptionPlan::FREE) {
            throw new \DomainException('No active subscription to cancel.');
        }

        // 2. Cancelar en Stripe
        $cancelImmediately = $data['cancelImmediately'] ?? false;

        $this->paymentGateway->cancelSubscription(
            subscriptionId: $subscription->getStripeSubscriptionId(),
            immediately: $cancelImmediately
        );

        // 3. Actualizar entidad
        if ($cancelImmediately) {
            $subscription->cancelImmediately();
            $message = 'Subscription canceled immediately. You now have FREE access.';
        } else {
            $subscription->scheduleCancel();
            $cancelAt = $subscription->getCancelAt()->format('Y-m-d');
            $message = "Subscription will be canceled on {$cancelAt}. You'll keep access until then.";
        }

        // 4. Guardar motivo si existe
        if (!empty($data['reason'])) {
            $subscription->setCancelReason($data['reason']);
        }

        $this->subscriptionRepository->save($subscription);

        return [
            'subscription' => $subscription,
            'message' => $message,
            'accessUntil' => $subscription->getCancelAt() ?? new \DateTimeImmutable()
        ];
    }
}
```

---

### 5. GET /subscriptions/history

Obtiene el historial de pagos del usuario.

#### Request

```bash
curl -X GET "https://api.lexecuador.com/api/v1/subscriptions/history?page=1&limit=10" \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLC..." \
  -H "Accept: application/json"
```

#### Query Parameters

| Parámetro | Tipo    | Default | Descripción                |
|-----------|---------|---------|----------------------------|
| page      | integer | 1       | Número de página           |
| limit     | integer | 10      | Resultados por página      |
| status    | string  | all     | all, succeeded, failed     |

#### Response 200 OK

```json
{
  "payments": [
    {
      "id": "pay_550e8400",
      "subscriptionId": "sub_1QR3sT4uVwXyZaBc",
      "amount": 9.99,
      "currency": "USD",
      "status": "succeeded",
      "description": "PREMIUM subscription - Monthly billing",
      "stripePaymentIntentId": "pi_1QR3sT4uVwXyZaBc",
      "stripeInvoiceId": "in_1QR3sT4uVwXyZaBc",
      "paidAt": "2024-03-15T16:45:01Z",
      "invoiceUrl": "https://invoice.stripe.com/i/acct_xxx/invst_xxx",
      "receiptUrl": "https://pay.stripe.com/receipts/acct_xxx/ch_xxx"
    },
    {
      "id": "pay_440d7300",
      "subscriptionId": "sub_1QR3sT4uVwXyZaBc",
      "amount": 9.99,
      "currency": "USD",
      "status": "succeeded",
      "description": "PREMIUM subscription - Monthly billing",
      "paidAt": "2024-02-15T16:45:01Z",
      "invoiceUrl": "https://invoice.stripe.com/i/acct_xxx/invst_xxx"
    },
    {
      "id": "pay_330c6200",
      "subscriptionId": "sub_1QR3sT4uVwXyZaBc",
      "amount": 9.99,
      "currency": "USD",
      "status": "failed",
      "description": "PREMIUM subscription - Monthly billing",
      "failureReason": "Card was declined (insufficient_funds)",
      "failedAt": "2024-01-15T16:45:01Z"
    }
  ],
  "meta": {
    "page": 1,
    "limit": 10,
    "total": 3,
    "totalPages": 1
  },
  "summary": {
    "totalPaid": 19.98,
    "totalFailed": 9.99,
    "currency": "USD",
    "firstPaymentDate": "2024-02-15T16:45:01Z",
    "lastPaymentDate": "2024-03-15T16:45:01Z"
  }
}
```

---

## 💳 ENDPOINTS DE PAGOS

### 6. POST /payments/methods

Añade un nuevo método de pago (tarjeta de crédito).

#### Request

```bash
curl -X POST https://api.lexecuador.com/api/v1/payments/methods \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLC..." \
  -H "Content-Type: application/json" \
  -d '{
    "paymentMethodId": "pm_1QR3sT4uVwXyZaBc",
    "setAsDefault": true
  }'
```

#### Request Body Schema

```json
{
  "paymentMethodId": "pm_xxxxx",  // ← Required (Stripe Payment Method ID)
  "setAsDefault": true             // ← Optional (default: false)
}
```

#### Response 201 Created

```json
{
  "paymentMethod": {
    "id": "pm_1QR3sT4uVwXyZaBc",
    "type": "card",
    "card": {
      "brand": "visa",
      "last4": "4242",
      "expiryMonth": 12,
      "expiryYear": 2025,
      "country": "US"
    },
    "isDefault": true,
    "createdAt": "2024-03-20T14:30:00Z"
  },
  "message": "Payment method added successfully and set as default."
}
```

---

### 7. GET /payments/methods

Lista todos los métodos de pago del usuario.

#### Request

```bash
curl -X GET https://api.lexecuador.com/api/v1/payments/methods \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLC..." \
  -H "Accept: application/json"
```

#### Response 200 OK

```json
{
  "paymentMethods": [
    {
      "id": "pm_1QR3sT4uVwXyZaBc",
      "type": "card",
      "card": {
        "brand": "visa",
        "last4": "4242",
        "expiryMonth": 12,
        "expiryYear": 2025
      },
      "isDefault": true,
      "createdAt": "2024-03-20T14:30:00Z"
    },
    {
      "id": "pm_2AB4cD5eFgHiJkLm",
      "type": "card",
      "card": {
        "brand": "mastercard",
        "last4": "5555",
        "expiryMonth": 8,
        "expiryYear": 2026
      },
      "isDefault": false,
      "createdAt": "2024-02-10T09:15:00Z"
    }
  ]
}
```

---

### 8. DELETE /payments/methods/{id}

Elimina un método de pago.

#### Request

```bash
curl -X DELETE https://api.lexecuador.com/api/v1/payments/methods/pm_1QR3sT4uVwXyZaBc \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLC..." \
  -H "Accept: application/json"
```

#### Response 204 No Content

(Respuesta vacía - método eliminado exitosamente)

#### Response 400 Bad Request (Método de pago es el predeterminado y único)

```json
{
  "type": "https://api.lexecuador.com/problems/cannot-delete-default-payment-method",
  "title": "Cannot Delete Default Payment Method",
  "status": 400,
  "detail": "This is your only payment method and is being used for active subscriptions. Add another payment method first.",
  "activeSubscriptions": ["sub_1QR3sT4uVwXyZaBc"]
}
```

---

## 🔔 WEBHOOKS DE STRIPE

### 9. POST /webhooks/stripe

Endpoint para recibir eventos de Stripe.

**Importante:** Este endpoint NO requiere autenticación JWT, pero sí requiere validación de firma de Stripe.

#### Eventos soportados:

1. **invoice.payment_succeeded** - Pago exitoso
2. **invoice.payment_failed** - Pago fallido
3. **customer.subscription.updated** - Suscripción actualizada
4. **customer.subscription.deleted** - Suscripción cancelada

#### Implementación del Webhook Controller

```php
<?php
// src/Infrastructure/Presentation/Controller/WebhookController.php

namespace App\Infrastructure\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;
use App\Application\UseCase\Subscription\HandleStripeWebhookUseCase;

#[Route('/api/v1/webhooks', name: 'api_webhooks_')]
class WebhookController extends AbstractController
{
    private const WEBHOOK_SECRET = 'whsec_...'; // ← Desde .env

    public function __construct(
        private readonly HandleStripeWebhookUseCase $handleWebhook
    ) {}

    #[Route('/stripe', name: 'stripe', methods: ['POST'])]
    public function stripe(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->headers->get('Stripe-Signature');

        // 1. Verificar firma de Stripe
        try {
            $event = Webhook::constructEvent(
                $payload,
                $sigHeader,
                self::WEBHOOK_SECRET
            );
        } catch (SignatureVerificationException $e) {
            return $this->json(['error' => 'Invalid signature'], 400);
        }

        // 2. Procesar evento
        try {
            $this->handleWebhook->execute($event);

            return $this->json(['status' => 'success'], 200);
        } catch (\Exception $e) {
            // Log error pero retornar 200 para evitar reintentos
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], 200);
        }
    }
}
```

#### Use Case para manejar Webhooks

```php
<?php
// src/Application/UseCase/Subscription/HandleStripeWebhookUseCase.php

namespace App\Application\UseCase\Subscription;

use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Domain\Repository\PaymentRepositoryInterface;
use Psr\Log\LoggerInterface;

final readonly class HandleStripeWebhookUseCase
{
    public function __construct(
        private SubscriptionRepositoryInterface $subscriptionRepository,
        private PaymentRepositoryInterface $paymentRepository,
        private LoggerInterface $logger
    ) {}

    public function execute(\Stripe\Event $event): void
    {
        $this->logger->info('Stripe webhook received', [
            'event_type' => $event->type,
            'event_id' => $event->id
        ]);

        match ($event->type) {
            'invoice.payment_succeeded' => $this->handlePaymentSucceeded($event->data->object),
            'invoice.payment_failed' => $this->handlePaymentFailed($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            default => $this->logger->warning('Unhandled webhook event type', ['type' => $event->type])
        };
    }

    private function handlePaymentSucceeded(\Stripe\Invoice $invoice): void
    {
        $subscriptionId = $invoice->subscription;

        $subscription = $this->subscriptionRepository->findByStripeId($subscriptionId);

        if (!$subscription) {
            $this->logger->warning('Subscription not found for invoice', [
                'stripe_subscription_id' => $subscriptionId
            ]);
            return;
        }

        // Crear registro de pago
        $payment = Payment::create(
            subscriptionId: $subscription->getId(),
            amount: $invoice->amount_paid / 100, // Stripe usa centavos
            currency: strtoupper($invoice->currency),
            stripePaymentIntentId: $invoice->payment_intent,
            stripeInvoiceId: $invoice->id,
            status: 'succeeded'
        );

        $this->paymentRepository->save($payment);

        // Actualizar período actual de la suscripción
        $subscription->renewPeriod(
            new \DateTimeImmutable('@' . $invoice->period_end)
        );

        $this->subscriptionRepository->save($subscription);

        $this->logger->info('Payment succeeded', [
            'subscription_id' => $subscription->getId(),
            'amount' => $invoice->amount_paid / 100
        ]);
    }

    private function handlePaymentFailed(\Stripe\Invoice $invoice): void
    {
        $subscriptionId = $invoice->subscription;

        $subscription = $this->subscriptionRepository->findByStripeId($subscriptionId);

        if (!$subscription) {
            return;
        }

        // Marcar pago como fallido
        $payment = Payment::create(
            subscriptionId: $subscription->getId(),
            amount: $invoice->amount_due / 100,
            currency: strtoupper($invoice->currency),
            stripePaymentIntentId: $invoice->payment_intent,
            stripeInvoiceId: $invoice->id,
            status: 'failed',
            failureReason: 'Payment failed - card declined'
        );

        $this->paymentRepository->save($payment);

        // TODO: Enviar email al usuario notificando el fallo

        $this->logger->warning('Payment failed', [
            'subscription_id' => $subscription->getId(),
            'invoice_id' => $invoice->id
        ]);
    }

    private function handleSubscriptionUpdated(\Stripe\Subscription $stripeSubscription): void
    {
        $subscription = $this->subscriptionRepository->findByStripeId($stripeSubscription->id);

        if (!$subscription) {
            return;
        }

        // Actualizar estado
        $subscription->updateFromStripe([
            'status' => $stripeSubscription->status,
            'current_period_end' => new \DateTimeImmutable('@' . $stripeSubscription->current_period_end),
            'cancel_at' => $stripeSubscription->cancel_at
                ? new \DateTimeImmutable('@' . $stripeSubscription->cancel_at)
                : null
        ]);

        $this->subscriptionRepository->save($subscription);

        $this->logger->info('Subscription updated', [
            'subscription_id' => $subscription->getId(),
            'status' => $stripeSubscription->status
        ]);
    }

    private function handleSubscriptionDeleted(\Stripe\Subscription $stripeSubscription): void
    {
        $subscription = $this->subscriptionRepository->findByStripeId($stripeSubscription->id);

        if (!$subscription) {
            return;
        }

        // Cancelar suscripción
        $subscription->cancelImmediately();
        $this->subscriptionRepository->save($subscription);

        // Actualizar rol del usuario a FREE
        $user = $subscription->getUser();
        $user->downgradeToPlan(SubscriptionPlan::FREE);

        $this->logger->info('Subscription deleted', [
            'subscription_id' => $subscription->getId()
        ]);
    }
}
```

---

## ⚠️ MANEJO DE ERRORES

### Errores Comunes

#### 1. Usuario no tiene suscripción activa (404)

```json
{
  "type": "https://api.lexecuador.com/problems/subscription-not-found",
  "title": "Subscription Not Found",
  "status": 404,
  "detail": "No active subscription found for this user."
}
```

#### 2. Plan inválido (400)

```json
{
  "type": "https://api.lexecuador.com/problems/invalid-plan",
  "title": "Invalid Plan",
  "status": 400,
  "detail": "The plan 'INVALID' does not exist. Valid plans: FREE, PREMIUM, ENTERPRISE."
}
```

#### 3. Pago fallido (402)

```json
{
  "type": "https://api.lexecuador.com/problems/payment-failed",
  "title": "Payment Failed",
  "status": 402,
  "detail": "Your card was declined. Please use a different payment method.",
  "stripeError": {
    "code": "card_declined",
    "declineCode": "insufficient_funds"
  }
}
```

#### 4. Método de pago inválido (400)

```json
{
  "type": "https://api.lexecuador.com/problems/invalid-payment-method",
  "title": "Invalid Payment Method",
  "status": 400,
  "detail": "The payment method 'pm_xxxxx' is invalid or has expired."
}
```

#### 5. Rate limit excedido (429)

```json
{
  "type": "https://api.lexecuador.com/problems/rate-limit-exceeded",
  "title": "Too Many Requests",
  "status": 429,
  "detail": "You have exceeded 100 requests per hour. Upgrade to ENTERPRISE for unlimited requests.",
  "retryAfter": 3600,
  "upgradeUrl": "https://app.lexecuador.com/subscribe"
}
```

---

## 🧪 TESTING

### Test del Controller

```php
<?php
// tests/Infrastructure/Presentation/Controller/SubscriptionControllerTest.php

namespace App\Tests\Infrastructure\Presentation\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SubscriptionControllerTest extends WebTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Login y obtener token
        $client = static::createClient();
        $client->request('POST', '/api/v1/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'email' => 'test@example.com',
            'password' => 'password123'
        ]));

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->token = $data['token'];
    }

    public function testGetCurrentSubscription(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/subscriptions/current', [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$this->token}",
            'CONTENT_TYPE' => 'application/json'
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('subscription', $data);
        $this->assertArrayHasKey('plan', $data['subscription']);
        $this->assertEquals('FREE', $data['subscription']['plan']);
    }

    public function testCreateSubscriptionWithValidPaymentMethod(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/subscriptions', [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$this->token}",
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'plan' => 'PREMIUM',
            'paymentMethodId' => 'pm_card_visa' // ← Test token de Stripe
        ]));

        $this->assertResponseStatusCodeSame(201);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('PREMIUM', $data['subscription']['plan']);
        $this->assertEquals('active', $data['subscription']['status']);
        $this->assertArrayHasKey('payment', $data);
        $this->assertEquals('succeeded', $data['payment']['status']);
    }

    public function testCreateSubscriptionWithDeclinedCard(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/subscriptions', [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$this->token}",
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'plan' => 'PREMIUM',
            'paymentMethodId' => 'pm_card_chargeDeclined' // ← Test token de Stripe
        ]));

        $this->assertResponseStatusCodeSame(402);

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('payment-failed', $data['type']);
        $this->assertStringContainsString('declined', $data['detail']);
    }

    public function testCancelSubscriptionScheduled(): void
    {
        $client = static::createClient();

        // Primero crear suscripción
        // ... (código omitido)

        // Luego cancelar
        $client->request('POST', '/api/v1/subscriptions/cancel', [], [], [
            'HTTP_AUTHORIZATION' => "Bearer {$this->token}",
            'CONTENT_TYPE' => 'application/json'
        ], json_encode([
            'reason' => 'Testing cancellation',
            'cancelImmediately' => false
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('active', $data['subscription']['status']);
        $this->assertNotNull($data['subscription']['cancelAt']);
        $this->assertStringContainsString('will be canceled', $data['message']);
    }
}
```

### Test del Use Case

```php
<?php
// tests/Application/UseCase/Subscription/CreateSubscriptionUseCaseTest.php

namespace App\Tests\Application\UseCase\Subscription;

use App\Application\UseCase\Subscription\CreateSubscriptionUseCase;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Repository\SubscriptionRepositoryInterface;
use App\Infrastructure\Payment\StripePaymentGateway;
use PHPUnit\Framework\TestCase;

final class CreateSubscriptionUseCaseTest extends TestCase
{
    public function testExecuteCreatesSubscriptionSuccessfully(): void
    {
        // Arrange
        $userRepo = $this->createMock(UserRepositoryInterface::class);
        $subscriptionRepo = $this->createMock(SubscriptionRepositoryInterface::class);
        $paymentGateway = $this->createMock(StripePaymentGateway::class);

        $user = $this->createUserWithFreeRole();

        $userRepo->method('findById')->willReturn($user);
        $subscriptionRepo->method('findActiveByUserId')->willReturn(null);

        $paymentGateway->method('createSubscription')->willReturn([
            'subscriptionId' => 'sub_123',
            'customerId' => 'cus_123',
            'currentPeriodEnd' => time() + 2592000,
            'payment' => ['status' => 'succeeded'],
            'invoice' => ['invoiceUrl' => 'https://...']
        ]);

        $useCase = new CreateSubscriptionUseCase($userRepo, $subscriptionRepo, $paymentGateway);

        // Act
        $result = $useCase->execute('user-id-123', [
            'plan' => 'PREMIUM',
            'paymentMethodId' => 'pm_123'
        ]);

        // Assert
        $this->assertEquals('PREMIUM', $result['subscription']->getPlan()->value);
        $this->assertEquals('succeeded', $result['payment']['status']);
        $this->assertStringContainsString('Welcome to PREMIUM', $result['message']);
    }
}
```

---

## 📝 NOTAS DE IMPLEMENTACIÓN

### 1. Variables de Entorno

```bash
# .env
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Price IDs de Stripe
STRIPE_PRICE_PREMIUM=price_1QR3sT4uVwXyZaBc
STRIPE_PRICE_ENTERPRISE=price_2AB4cD5eFgHiJkLm
```

### 2. Configuración de Rate Limiting

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        subscription_api:
            policy: 'sliding_window'
            limit: 100
            interval: '1 hour'

        enterprise_api:
            policy: 'sliding_window'
            limit: 1000
            interval: '1 hour'
```

### 3. Seguridad en Webhooks

**Importante:** SIEMPRE verificar la firma de Stripe antes de procesar eventos:

```php
try {
    $event = \Stripe\Webhook::constructEvent(
        $payload,
        $sigHeader,
        $_ENV['STRIPE_WEBHOOK_SECRET']
    );
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    // Firma inválida - rechazar evento
    return new JsonResponse(['error' => 'Invalid signature'], 400);
}
```

### 4. Reintentos de Pagos

Stripe automáticamente reintenta cobros fallidos:
- 1er reintento: 3 días después
- 2do reintento: 5 días después
- 3er reintento: 7 días después
- Si todos fallan: cancelar suscripción

### 5. Prorrateo (Proration)

Al hacer upgrade de PREMIUM → ENTERPRISE, Stripe calcula automáticamente el costo prorrateado:

```php
$subscription = $this->stripe->subscriptions->update($subscriptionId, [
    'items' => [[
        'id' => $subscriptionItemId,
        'price' => $newPriceId,
    ]],
    'proration_behavior' => 'create_prorations', // ← Crear prorrateo
]);
```

---

## ✅ CHECKLIST DE IMPLEMENTACIÓN

- [ ] Crear entidades: Subscription, Payment
- [ ] Crear repositorios: SubscriptionRepository, PaymentRepository
- [ ] Instalar Stripe SDK: `composer require stripe/stripe-php`
- [ ] Crear StripePaymentGateway service
- [ ] Implementar 5 Use Cases: GetCurrent, Create, Upgrade, Cancel, GetHistory
- [ ] Crear SubscriptionController con 5 endpoints
- [ ] Crear WebhookController para eventos de Stripe
- [ ] Configurar rutas en routes.yaml
- [ ] Añadir serialization groups en entidades
- [ ] Configurar rate limiting para suscripciones
- [ ] Escribir tests unitarios e integración
- [ ] Probar con Stripe CLI: `stripe listen --forward-to localhost:8000/api/v1/webhooks/stripe`
- [ ] Documentar en Swagger con anotaciones
- [ ] Configurar variables de entorno (.env)

---

**Archivo generado:** `09_ENDPOINTS_SUBSCRIPTIONS.md`
**Siguiente:** `10_MODELO_DATOS.md` (Modelo de Datos Completo)
