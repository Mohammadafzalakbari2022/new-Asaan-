# HesabPay Integration Plan

## What HesabPay actually offers (verified against official docs)

Source of truth: <https://docs.hesab.com/>

- Brand spelling: **HesabPay** (not "HesaabPay").
- Integration style: **hosted checkout**. We create a session, HesabPay returns a
  URL, the customer pays on HesabPay's own page.
- Covers HesabPay wallet, AFN payments, AfPay cards, and international cards in one
  integration. So one payment method covers all of them — no separate options needed.

### Base URLs

| Environment | Base URL |
|---|---|
| Sandbox | `https://api-sandbox.hesab.com` |
| Production | `https://api.hesab.com` |

### Authentication

Every server request carries:

```
Authorization: API-KEY your_api_key
Content-Type: application/json
```

The key must never reach frontend code. In this project it is stored in the
`payment_methods.configuration` JSON column, read only on the server.

### Create a session

`POST /api/v1/payment/create-session`

| Field | Required | Notes |
|---|---|---|
| `items` | **yes** | Array of `{ id, name, price }`. No quantity field — quantity must be multiplied out in the backend. Max 50 chars for `id`, 500 for `name`, 2 decimals for `price`. |
| `email` | no | Customer email; echoed back in webhook payloads. |
| `user_id` | no | **Our order reference.** Returned unchanged in the success webhook. Max 50 chars. This is how we find the order. |
| `redirect_success_url` | no | Where the customer lands after paying. |
| `redirect_failure_url` | no | Where the customer lands after failing/cancelling. |

Success response:

```json
{ "status_code": 10, "success": true, "message": "Payment session created successfully",
  "url": "https://checkout.example/checkout/session-id" }
```

We redirect the browser to `url`.

### Webhooks — the real source of truth

Registered in the HesabPay dashboard under Developer → Webhooks. Events:
`payment_success`, `payment_failure`.

Every payload carries `signature` and `timestamp`. **We must not trust a payload
before verifying it**, so we POST those two values to:

`POST /api/v1/hesab/webhooks/verify-signature` with `{ signature, timestamp }`

and only act when the response is `success: true`.

Success payload example:

```json
{
  "status_code": 10, "success": true,
  "sender_account": "793111222",
  "transaction_id": "0328379001707719668",
  "user_id": "order-1001",
  "amount": 65,
  "signature": "signature_value",
  "timestamp": "1707719607",
  "transaction_date": "2024-02-12 11:04:28",
  "items": [{ "id": "item1", "name": "Product 1", "price": 45 }],
  "email": "customer@example.com"
}
```

- `user_id` is **our** identifier and HesabPay does not enforce uniqueness.
- `transaction_id` is **theirs**, generated after payment. Used for idempotency.

### Docs' own recommended pattern

1. Show a pending state when the customer returns from checkout.
2. Wait for a verified webhook before fulfilling.
3. Make webhook handling idempotent.

We follow this exactly. A customer landing back on our site does **not** mean
they paid — only a verified webhook does.

### Refunds

The public API reference documents no refund endpoint. `refund()` therefore
returns an explicit failure telling the admin to refund from the HesabPay
dashboard, rather than silently reporting success.

## Key design decisions for this codebase

1. **Store our order reference in `user_id`.** We send `user_id = order_number`
   (human-readable, under 50 chars, unique). The webhook returns it unchanged, so
   the webhook looks the order up by `order_number`. This avoids depending on a
   numeric id and makes log inspection easy.

2. **Use the existing `orders.payment_data` JSON column.** Discovered while
   investigating: Razorpay, PayPal and PayUMoney write to
   `order->payment_gateway_data` and `order->payment_gateway_order_id`, but
   **those columns do not exist** in the orders migration and are not in the
   model's `$fillable`. HesabPay will not repeat that bug. Related latent bug,
   worth a separate fix pass.

3. **Return a `RedirectResponse` from `processPayment()`.**
   `Shop\Http\Controllers\Checkout\CheckoutController::store()` already handles
   `$response instanceof RedirectResponse` (CheckoutController.php:388), which is
   exactly the hosted-checkout shape HesabPay uses.

4. **Idempotency by `transaction_id`.** Before acting on a verified success
   webhook we record the transaction id. A repeat delivery is acknowledged with
   HTTP 200 and changes nothing, so retries cannot double-fulfil.

5. **Signature verification is mandatory and fail-closed.** No verified signature,
   no order update, HTTP 400.

6. **Amount is checked.** The webhook's `amount` must match the order total before
   we mark it paid, so a tampered or mismatched payload cannot mark an order paid.

## Admin configuration

`resources/js/pages/Admin/Settings/PaymentMethods/ConfigureHesabPay.vue`, following
the Razorpay/PayUMoney page pattern:

- Name, description, customer instructions
- Test / Production mode toggle
- Separate sandbox and live API keys
- Read-only display of the webhook URL to paste into the HesabPay dashboard
- Payment method is seeded **active** and is the default method at checkout.
  The keys are blank by default, so the storefront hides HesabPay until the
  owner has configured it; once a key is entered it is offered again.

## Storefront

The checkout controller lists every active `PaymentMethod` (CheckoutController.php:144)
and filters out any online gateway that is not yet configured, so an unconfigured
HesabPay is never shown as an option. The Inertia prop `paymentMethods` drives
the selection UI. Activating HesabPay in admin is sufficient.

## Mobile API

`packages/Cartxis/API/Http/Controllers/V1/CheckoutController.php` gets a
`hesabpay` branch that returns the checkout URL to the app, which opens it in a
system browser. Same pending-then-webhook confirmation as web. `hesabpay` is
included in the gateway-credential allow-lists so the app receives the same
`gateway_config` the other online methods get.

## What is blocked

- **Live activation needs the owner's HesabPay merchant API key.** The method
  ships active and default, but with no key `isConfigured()` returns false, so
  checkout does not offer it and the store keeps working on Cash on Delivery
  until the owner pastes a key into the admin page.
- **The webhook URL must be registered in the HesabPay dashboard** by the owner.
  The admin page shows the exact URL to copy.
