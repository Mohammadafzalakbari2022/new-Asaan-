# Referral Shop Credit

> **Status:** working code, with real gaps. The money rules, the ledger, the award
> path and the guards are built and covered by tests. The customer screens exist for
> **one theme only**, the strings are **English only**, and a few rough edges are
> listed in [§9](#9-known-limitations--not-done-yet). Read that section before
> promising anybody a launch date.
> **Package:** `packages/Cartxis/Referral` (`Cartxis\Referral\`)
> **Admin menu:** Marketing → Referrals (icon `users-round`)
> **Admin routes:** `/admin/marketing/referrals/*` — behind `auth:admin`, which
> **redirects** a non-staff visitor; it does not return a 403.
> **Customer page:** `/account/referrals` — resolved through the **active theme**, and
> only `cartxis-default` currently has the file.
> **Share landing page:** `/referral/{code?}` — public, no account needed.

---

## 1. The rule

1. Every customer has a referral code and a share link.
2. A customer who signs up through that link is permanently linked to the person who sent them.
3. When the **lifetime total** of a linked customer reaches the threshold (default **500**), the person
   who referred them is awarded the reward (default **10**) as **shop credit**.
4. The reward fires **once per linked person**, on the order where their lifetime total first crosses
   the line. Later orders by the same person do not pay again.
5. **Only the referrer earns.** The invited person earns nothing at all — not for signing up, not ever
   for being invited. A second level, if the owner configures one, pays the *referrer's* referrer, never
   the invited customer. The share text the customer copies says exactly this, so nobody is promised a
   reward that will not arrive.
6. The credit is **shop credit only**. There is no cash and no withdrawal anywhere in the system.
7. The credit cannot be **spent** until the lock period passes (default **180 days**). After that it is
   spendable at checkout, as an **opt-in tick box** — see
   [§3.5](#35-the-opt-in-the-cap-and-what-credit-may-pay-for).
8. Every number in (3), (4) and (7) is editable from **Admin → Marketing → Referrals → Settings**.
9. Settings are changed by accounts whose `role` column is exactly `admin`. Everyone else, staff included,
   is refused — the three form requests behind those buttons return 403 unless
   `role === 'admin'`. Shoppers can only read the numbers and use their credit.

---

## 2. Settings

Stored through `Cartxis\Core\Services\SettingService` with group `referral` and keys prefixed
`referral.`. Defaults are baked into `ReferralSettings` so a missing row can never crash the app, and are
mirrored by the seed migration `2026_09_29_000007_seed_referral_settings.php`.

| Key | Default | Type | Notes |
|---|---|---|---|
| `referral.enabled` | `1` | boolean | Master switch. When off, code capture, linking and awarding all stop. |
| `referral.reward_amount` | `10` | float | Credit awarded per qualifying referred person. |
| `referral.threshold_amount` | `500` | float | Lifetime spend required, in real money only. |
| `referral.lock_days` | `180` | integer | Days before earned credit can be spent. `0` = immediately. |
| `referral.level_shares` | `[100]` | json | Percent per level. **Must sum to exactly 100.** 1 or 2 entries. |
| `referral.reward_mode` | `once_per_person` | string | `once_per_person` is the **only** value the form accepts. |
| `referral.allow_admin_credit` | `1` | boolean | Master switch for hand-issued credit. |
| `referral.block_account_deletion` | `1` | boolean | Block account deletion while credit is held. |
| `referral.credit_max_percent_of_order` | `100` | integer | Cap on how much of one order credit may cover. Measured against goods + tax − discount, so shipping is outside the cap by construction. |

Changing a setting affects **future** awards only. Existing commissions keep the `reward_snapshot`
and `share_snapshot` taken when they were granted. Money already earned never changes because the
owner edited a box.

The form refuses, with a message next to the field: a level split that does not add to 100, a negative
reward, a lock period that is not a whole number of days, a cap above 100, and a `reward_mode` other
than `once_per_person`. A split that arrives from anywhere else is silently **rescaled** to 100 by
`ReferralSettings::normaliseShares()` rather than rejected.

---

## 3. Database

### 3.1 `referral_codes`

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `user_id` | FK users, **unique**, cascade delete | One code per person, forever. |
| `code` | string(20), **unique** | Generated uppercase, e.g. `AHMAD-7K2QX`. Looked up case-insensitively. |
| `status` | enum `active`,`disabled`, default `active` | A disabled code stops matching, so no new links form. |
| `clicks` | unsigned int default 0 | Link opens counted. |
| timestamps | | |
| index `status` | | |

### 3.2 `referrals`

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `referrer_user_id` | FK users, indexed, cascade delete | Who sent them. |
| `referred_user_id` | FK users, **unique**, cascade delete | One row per person, forever. This is what makes the reward fire once. |
| `referral_code_id` | FK referral_codes, **nullable**, indexed | The code used, if any. |
| `level` | unsigned tinyint, default 1 | 1 or 2. |
| `status` | enum `active`,`voided`, default `active` | Admin can void a link, with a reason. |
| `rewarded_at` | timestamp nullable | The "once per person" lock. |
| `voided_reason` | text nullable | |
| `voided_by` | FK users nullable | |
| `voided_at` | timestamp nullable | |
| timestamps | | |
| indexes `referrer_user_id`, (`status`,`level`) | | |

### 3.3 `referral_commissions`

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `referral_id` | FK referrals, indexed | |
| `referrer_user_id` | FK users, indexed | The earner. |
| `order_id` | FK orders, indexed | The order that triggered it. |
| `level` | unsigned tinyint, default 1 | |
| `amount` | decimal(12,2), default 0 | What this level actually received. |
| `reward_snapshot` | decimal(12,2), default 0 | Reward amount at award time. |
| `share_snapshot` | decimal(5,2), default 100 | Level percentage at award time. |
| `status` | enum `active`,`reversed`, default `active` | |
| `unlocks_at` | timestamp **nullable** | `now() + lock_days`. |
| `reversed_at` | timestamp nullable | |
| `reversal_reason` | text nullable | |
| timestamps | | |
| **UNIQUE** (`order_id`,`referral_id`,`level`) | | **Idempotency guarantee.** Double-marking an order paid any number of times still produces one commission. The database refuses the second. |
| indexes `referrer_user_id`, `status`, `unlocks_at` | | |

### 3.4 `referral_ledger`

The money record. Nothing changes a balance without a row here.

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `user_id` | FK users, indexed, cascade delete | |
| `type` | enum `earned`,`spent`,`reversed`,`admin_credit`,`admin_debit` | |
| `amount` | decimal(12,2), default 0, signed | Negative for spends, refunds and hand-debits. |
| `available_from` | timestamp **nullable** | For `earned` = the commission's `unlocks_at`. For everything else = now. |
| `balance_after` | decimal(12,2), default 0 | Running snapshot, for auditing. |
| `commission_id` | FK referral_commissions nullable, indexed | |
| `order_id` | FK orders nullable, indexed | |
| `admin_id` | FK users nullable | Set on `admin_credit` / `admin_debit`. |
| `reason` | string(500) nullable | **Required** on admin rows by the form request. |
| timestamps | | |
| indexes (`user_id`,`available_from`), `type` | | |

Balances are **computed from the ledger, never stored**. A stored balance drifts; a ledger cannot.

- **available** = `SUM(amount) WHERE available_from <= now()`
- **locked** = `SUM(amount) WHERE type='earned' AND available_from > now()`
- **lifetime earned** = `earned + admin_credit + reversed + admin_debit`, all summed. It is *not* just
  `type='earned'`, and it is *not* money still held — it is everything ever put in, less what was taken
  back. See `ReferralCreditService::lifetimeEarned()`.

### 3.5 The opt-in, the cap, and what credit may pay for

This is the part that is easiest to state wrongly, so it is spelled out.

- `orders.total` is written **net of both coupons and referral credit**, so it is the cash the store
  actually received. That has three consequences:
  - The referral threshold counts `SUM(orders.total)` over paid, non-cancelled orders. It does **not**
    subtract `credit_applied` again, because that money was already removed. Subtracting it a second
    time would double-count and let two customers trade credit back and forth to push each other over
    the threshold for free.
  - The pre-credit value of the order is `total + credit_applied`.
  - Existing revenue reports that sum `total` now exclude credit, which is the correct figure. No
    existing report was changed.
- Credit is **never applied on its own.** `CheckoutController` sends a `referralCredit` Inertia prop with
  `available`, `max_usable` and `can_use`; the theme's checkout page renders a tick box that starts
  unchecked, and the customer has to tick it. An account with a balance gets no automatic discount.
- The tick box is hidden entirely when `can_use` is false, so signed-out shoppers and customers with no
  usable credit never see a dead control.
- When it is ticked, the amount taken is
  `min(available balance, cap, what the order still owes)`, where
  `cap = (subtotal + tax − coupon discount) × credit_max_percent_of_order ÷ 100`.
- **Shipping is never paid for with credit.** Shipping is not in that base at all, so the cap can never
  reach it. With the default cap of **100%**, credit *can* cover tax — goods plus tax, discount
  subtracted. With the cap set to 50, credit covers half of goods-plus-tax-minus-discount and nothing
  else.
- The order total is then reduced by exactly the amount taken and floored at zero
  (`max(0, total − take)`), so an order can never go below zero.
- The take happens **after the order row exists but before payment is attempted**, so the same credit
  cannot be spent on two orders placed at the same moment.

### 3.6 `orders`

Added column `credit_applied` decimal(12,2) default 0, placed after `discount` — how much of the order
was paid with referral credit. It is kept separate from `discount` so a refund or a report can tell a
coupon apart from the customer's own credit.

---

## 4. Integration surface

Nothing in the payment flow changes: no gateway file, no webhook and no callback was edited. What was
edited, or added, is listed below. **This is more than the four points an earlier draft of this document
claimed.**

| # | File | What it does |
|---|---|---|
| 1 | `packages\Cartxis\Referral\src\Observers\OrderObserver.php` | `created`: if the order arrives already `paid`, run the earning check — some paths create orders paid, and `updated` never fires for those. `updated`: if `wasChanged('payment_status')` and the new value is `paid` → `ReferralEarningService::onOrderPaid()`; if it becomes `refunded` → reverse. Separately, if `wasChanged('status')` and it becomes `cancelled` → release reserved credit. Both branches are wrapped in `try/catch` that logs and swallows, so a referral fault can never take a paid order down. |
| 2 | `packages\Cartxis\Referral\src\Observers\UserObserver.php` | On `created`: mint the code, and if a referral code is remembered in the session, create the `referrals` row. Both steps are individually wrapped so a referral fault cannot break signup. |
| 3 | `packages\Cartxis\Referral\src\Http\Middleware\CaptureReferralCode.php` | Reads `?ref=CODE` on any page, counts the click, and remembers the code in the session for **30 days**. Registered in the `web` group in `bootstrap\app.php:40`. Refuses to remember the code if the visitor is already signed in as the code's owner. |
| 4 | `packages\Cartxis\Referral\src\Http\Middleware\ShareReferralData.php` | Shares one lazy Inertia prop, `referralEnabled`, so the header and account page can hide the referral links when the programme is off. Registered in the `web` group in `bootstrap\app.php:41`. |
| 5 | `packages\Cartxis\Shop\src\Http\Controllers\Checkout\CheckoutController.php` | Constructor now takes `ReferralCheckoutService`. Sends the `referralCredit` prop (`:230-237`) and, when the request carries `use_referral_credit` (`:358-364`), reserves the credit, which rewrites `orders.credit_applied` and `orders.total`. |
| 6 | `packages\Cartxis\Shop\src\Http\Controllers\Account\ProfileController.php` | `destroy()` checks available + locked credit first (`:148-160`) and returns a friendly message instead of deleting. This is the *friendly* layer only. |
| 7 | `app\Models\User.php` | A `deleting` hook in `booted()` (`:170-197`) throws `ReferralBalanceOutstanding` if the account still holds credit. Escape hatch: `User::allowDeletingWithReferralHistory()`. |
| 8 | `app\Models\Concerns\ReferralAwareUserBuilder.php` | The same guard for bulk deletes. `User::query()->delete()` is a plain SQL statement and fires no model events, so without this the rule would only apply on the one path nobody uses to bulk-delete customers. |
| 9 | `packages\Cartxis\Shop\src\Routes\web.php` | `require`s the referral route file from **inside** the existing `account` group (`:100`), so the pages inherit the same prefix, auth middleware and theme layout, and adds the public landing route `/referral/{code?}` (`:112`). |

### 4.1 Why an observer and not a service call

Fifteen separate code locations **write** `payment_status = 'paid'`:

| File | Lines |
|---|---|
| `packages\Cartxis\Shop\src\Http\Controllers\Checkout\CheckoutController.php` | 437 |
| `packages\Cartxis\API\Http\Controllers\V1\CheckoutController.php` | 819, 854, 885, 926 |
| `packages\Cartxis\Stripe\src\Http\Controllers\StripeController.php` | 61, 216 |
| `packages\Cartxis\RazorPay\src\Services\RazorpayGateway.php` | 319 |
| `packages\Cartxis\RazorPay\src\Http\Controllers\RazorpayController.php` | 61 |
| `packages\Cartxis\PhonePe\src\Http\Controllers\PhonePeController.php` | 68, 248 |
| `packages\Cartxis\PayUMoney\src\Http\Controllers\PayUMoneyController.php` | 71, 149 |
| `packages\Cartxis\PayPal\src\Http\Controllers\PayPalController.php` | 74 |
| `packages\Cartxis\Sales\src\Services\InvoiceService.php` | 121 |

(`API\...\CheckoutController.php:781` and `PhonePeController.php:246` also mention `PAYMENT_PAID`, but
both are *reads* of an already-paid order, not writes.)

`packages\Cartxis\Sales\src\Services\OrderService.php` is the only shared service among them and is
reached solely from the admin's manual "Update Payment Status" dropdown. Awarding from any single one of
these would miss the rest. A model observer catches all fifteen, including any added later.

### 4.2 Things deliberately not used

- **The `do_action` hook system.** The theme file
  `templates\storefront\general\cartxis-default\hooks.php:43` registers a listener for
  `checkout.order.placed`. Nothing in the codebase ever fires that event — the only place the app *calls*
  `do_action()` is `packages\Cartxis\Core\src\Services\ThemeService.php:175` (the other match,
  `packages\Cartxis\Core\src\Helpers\hooks.php:34`, is the function's own definition). Building on the
  hook would register cleanly, look correct, and never run.
- **`Cartxis\Shop\Repositories\OrderRepository::markAsPaid()` / `updatePaymentStatus()`** at lines 221
  and 88. Dead code, no callers. Note there are **two classes both named `OrderRepository`**
  (`Cartxis\Shop\Repositories\OrderRepository`, dead, and `Cartxis\Sales\Repositories\OrderRepository`,
  live). The observer touches neither.

---

## 5. Lifetime spend calculation

`ReferralEarningService::lifetimeQualifyingSpend()` sums `orders.total` and **nothing else**. It does
**not** subtract `credit_applied`, because `total` is already net of it. Subtracting again is the
double-count that would let two people trade credit between themselves and push each other over the
threshold for free.

The filter is: `payment_status = 'paid'` and `status NOT IN ('cancelled','refunded')`, matched to the
referred person by `user_id` **or** by `customer_id IN (…)` where the ids come from that user's rows in
`customers`. The second half matters: guest orders carry no `user_id` but do carry `customer_id`, and
must still count.

---

## 6. Guard rails

| Rule | Enforced in |
|---|---|
| Cannot use your own code | `ReferralLinkService::linkToCode()` |
| Cannot use a code belonging to the same email or the same phone | `ReferralLinkService::linkToCode()` |
| Nobody is linked twice, even by two codes | `UNIQUE referred_user_id` + a re-check inside the award transaction |
| Nobody joins the tree if the level above would be paid nothing | `ReferralLinkService::linkToCode()` against `maxLevels()` |
| A code can never be changed after signup | No update path exists; written once in `UserObserver` |
| A remembered code is used once, then forgotten | `ReferralLinkService::forgetPendingCode()` after a successful link |
| A remembered code expires after 30 days | `expires_at` in the session payload |
| Credit-paid money does not count toward the threshold | `orders.total` is written net of credit |
| Credit-paid money does not earn new credit | The threshold sums `total` only |
| Total can never go below zero | `max(0, ...)` in `ReferralCheckoutService::reserve()` — the existing convention |
| Credit cannot pay for shipping | Shipping is outside the cap's base in `reserve()` |
| Credit cannot be spent before it unlocks | `available_from` filter in `ReferralCreditService` |
| The same order cannot pay twice | UNIQUE (`order_id`,`referral_id`,`level`) |
| Balance can never go negative | `spend()` clamps inside a `DB::transaction` that row-locks the customer's ledger rows |
| Refund takes the credit back | `OrderObserver` on `refunded`, and only once per commission |
| Cancelled or unpaid order releases reserved credit | `OrderObserver` on `cancelled` |
| Account deletion blocked while credit is held | **Three places**: `Account\ProfileController::destroy()` (friendly message), the `User` model's `deleting` hook (throws), and `ReferralAwareUserBuilder` (covers bulk `delete()`, which fires no model events) |
| Escape hatch for a deliberate erasure | `User::allowDeletingWithReferralHistory(true)`; returns the previous value so a caller can restore it |
| Admin hand-issues need a written reason | `StoreReferralCreditRequest` |
| Reward reversal needs a written reason | `ReverseReferralCommissionRequest` |
| Settings changes are admin-only | `SaveReferralSettingsRequest::authorize()` — `role === 'admin'`, exactly |
| Referrals off? Nothing captured, nothing linked, nothing awarded | `referral.enabled` checked in the middleware, in `linkFor()` and in `onOrderPaid()` |
| Non-staff cannot read or change anything | `auth:admin` on the route group. It **redirects**; it does not return 403 |

**Not enforced:** there is no rate limit on referral code lookups. An earlier draft of this document
claimed `throttle` on a public validate endpoint. There is no such endpoint, and no `throttle` anywhere
in the package. `?ref=CODE` is read on every page request by `CaptureReferralCode`, unthrottled; it does
one indexed lookup and returns immediately, but it is unbounded by design.

---

## 7. Signup paths

All four are covered by `UserObserver` on `created`, not by editing each controller:

| Path | Location |
|---|---|
| Register page | `app\Http\Controllers\Auth\RegisteredUserController.php` |
| Account created while buying | `packages\Cartxis\Shop\src\Services\CheckoutService.php` |
| Admin creates a customer | `packages\Cartxis\Customer\src\Http\Controllers\CustomerController.php` |
| API register | `packages\Cartxis\API\Routes\api.php` → `AuthController::register` |

Exact line numbers are not given here because these files move; the observer means the locations do not
have to be tracked.

---

## 8. Screens, and what they format

### Admin

Six pages under `resources/js/pages/Admin/Marketing/Referrals/`, each with a matching controller:

| Screen | Route | Page |
|---|---|---|
| Overview | `GET /admin/marketing/referrals` | `Index.vue` |
| Top referrers | `GET …/top-referrers` | `TopReferrers.vue` |
| People | `GET …/people` | `People.vue` |
| One person | `GET …/people/{user}` | `PersonShow.vue` |
| Commissions | `GET …/commissions` | `Commissions.vue` |
| Settings | `GET`/`PUT …/settings` | `Settings.vue` |

All six use `useCurrency()` → `formatPrice()`.

### Customer

Two pages, resolved through `ThemeViewResolver` — so they live in the **active theme**, not in
`resources/js/pages/`:

- `Account/Referrals/Index.vue` — the customer's own code, share text, balances and invited list. Names
  are masked to first letter plus asterisks so the page is safe to show in public.
- `Account/Referrals/Landing.vue` — the public page someone lands on after following a link.

The checkout opt-in tick box lives in the theme's `pages/Checkout/Index.vue` and is driven entirely by
the backend `referralCredit` prop.

Entry points into the customer's page were added in two places, both hidden when `referralEnabled` is
false: the Quick Actions card in `Account/Dashboard.vue:266-282` and the header dropdown in
`components/ThemeHeader.vue:661-662`.

### Currency — read this before you translate any of it

- The **Vue pages and the admin screens** format money through `resources/js/composables/useCurrency.ts`,
  which reads the `currency` prop shared by `app\Http/Middleware/HandleInertiaRequests.php:210`. That
  follows whatever currency the store is configured with.
- Two **plain-text server strings** do not. `ReferralDashboardController::shareText()` and
  `Account\ProfileController::referralBalanceMessage()` both call
  `config('currency.symbol', 'AFN')`, and **there is no `config/currency.php` in this repository**, so
  the fallback always wins. The share message and the account-deletion warning will say **AFN** on a
  store configured for anything else. The numbers are right; the label is not.
- Amounts are stored as `decimal(12,2)` and passed to Inertia as floats, matching the `CustomerResource`
  convention.

---

## 9. Known limitations / not done yet

Each of these is verified against the code, not inferred.

1. **The customer referral pages exist in `cartxis-default` only.** They are resolved through
   `ThemeViewResolver`, so they are looked up in the active theme. Activate any other theme and
   `/account/referrals` and `/referral/{code}` will fail — the files are not there. There is **no
   generic `resources/js/pages/Frontend/` fallback** in this repo; `resources/js/pages/` contains only
   `Admin`, `auth`, `Delivery`, `settings` and `Setup`. Copying the two `.vue` files into the new theme
   is the whole fix, and it has to be repeated for every future theme.
2. **Dari and Pashto translations do not exist.** Every user-facing string — admin and customer — is
   written as the English source string and passed through `$t()` or `__()`. Nothing under `lang/fa/`
   or `lang/ps/` mentions the programme. The passthrough will render correctly in English; a Dari or
   Pashto visitor gets the English text until someone writes the translations.
3. **The admin menu icon is `users-round`.** Hard-coded in the seed migration
   `2026_09_29_000006_seed_referral_menu_item.php`. If the icon set shipped with the admin theme does not
   include it, the menu row renders with a blank or fallback glyph. It was not picked against the
   theme's actual icon list.
4. **Refused referral links are never logged.** `ReferralLinkService::linkFor()` wraps its "Referral
   link refused" log in `if (false) { … }`, so the block is dead. The refusal still happens and is still
   correct; the operator just gets no line in the log explaining why a code did not take.
5. **`config/currency.php` does not exist**, so the two hard-coded `AFN` strings described in §8 are
   wrong on any non-AFGH store.
6. **Admin access is `role === 'admin'`, not "staff".** The route group's `auth:admin` lets other staff
   in, but the three form requests behind Settings, hand-issued credit and reward reversal require
   `role` to be exactly `admin`. A `staff` or `manager` account will get a 403 on those three forms.
7. **No rate limiting on code capture** (see §6).
8. **The menu item carries `permission => null`.** Visibility of the Marketing → Referrals row is decided
   by the parent Marketing item, not by a permission of its own.
9. **Laravel Pint has reformatted the referral package.** Whitespace and import ordering only — no
   behaviour changed. `vendor/bin/pint --test packages/Cartxis/Referral` passes. The commit diff is noisy
   for that reason.
10. **The referral package's composer constraint was corrected to `illuminate/support: ^13.0`.** The
    other packages in this monorepo still carry stale constraints and were **not** fixed:
    `Cart` `^12.0`, `Customer` `^11.0`, `Marketing` `^12.0`, `Sales` `^11.0`, `Service` `^12.0`,
    `System` `^11.0`.
11. **The feature is uncommitted.** `packages/Cartxis/Referral/`, the admin pages, the theme pages and
    the tests all show as untracked in git. Nothing here is in a commit yet.

---

## 10. Pre-existing issues this feature works around

**Customer account pages show the wrong currency.**
`templates/storefront/general/cartxis-default/resources/views/pages/Account/Dashboard.vue:36-39` defines a
local `formatPrice` hardcoded to `currency: 'INR'`, so the account pages render ₹ on an Afghan store. The
referral pages use the shared `resources/js/composables/useCurrency.ts` composable instead. The rest of the
account pages are untouched, so the bug is still there on the other account screens.

**`InvoiceService` writes `payment_status` directly.**
`packages\Cartxis\Sales\src\Services\InvoiceService.php:121` does a bare
`$invoice->order->update(['payment_status' => 'paid'])`, which skips the order-history record and the
automatic pending → processing move. The observer catches it, so credit is safe. The missing history
entry is a separate pre-existing bug, deliberately not fixed here.

**Revenue reports are untouched.**
`orders.total` is written net of referral credit, so existing revenue figures that sum `total` now exclude
credit without any code change. That is the correct number. `credit_applied` is there for anyone who needs
the pre-credit value, which is `total + credit_applied`.

---

## 11. Tests

`tests/Feature/Referral/`. `RefreshDatabase` is applied to everything under `Feature` by
`tests/Pest.php:15-16`. **131 tests, 337 assertions, all passing** at the time of writing.

| File | Covers |
|---|---|
| `ReferralEarningTest.php` | Award fires once on the storefront path, once on a gateway path, once for an order created already paid, and only once when an order is marked paid repeatedly. 499 earns nothing, 500 earns, one large order still earns once, a second order by the same person earns nothing. |
| `ReferralLinkTest.php` | Own code refused, same email and same phone refused, first referrer kept when a second code arrives, already-linked refused, too-deep refused, two levels paid when configured, disabled code refused, voided link stops paying, session code used once then forgotten, code expires after 30 days, off means nobody linked, split rescaled. |
| `ReferralCheckoutTest.php` | Opt-in is required, cap applied, shipping never covered, tax covered at the default cap, total floored at zero, no credit before unlock, `orders.total` written net. |
| `ReferralCreditTest.php` | Credit-paid money neither counts toward the threshold nor earns new credit; balance never negative; two orders cannot race for one balance; admin issue needs a reason; admin debit cannot exceed the balance. |
| `ReferralReversalTest.php` | Refund takes the reward back and records why, never twice; cancellation releases reserved credit, never twice, and the order can be retried with the same credit; a paid-then-cancelled order keeps no reward. |
| `ReferralAccountDeletionTest.php` | Deletion blocked while credit is held, blocked for locked credit too, blocked on bulk delete, allowed once credit is spent, and the escape hatch works. |
| `ReferralAdminAccessTest.php` | Guests and ordinary customers are **redirected** from every programme screen and can change no settings, no credit and no reversal; admin reaches all six screens; bad settings refused (bad split, negative reward, fractional lock days, cap over 100, unknown reward mode); valid settings save and apply at once; the customer page shows code, credit and inviter and masks names; the landing page reads the code from both the path and the query string. |

`tests/Feature/Services/ServiceBookingFlowTest.php` also contains one referral test — a booked service
order never earns a commission.
