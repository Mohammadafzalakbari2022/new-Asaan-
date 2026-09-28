# Asaan Services & Jobs System — Implementation Plan

Status: **Draft — awaiting approval, nothing built yet**
Stack: Laravel 13 + Inertia/Vue 3 + Tailwind 4 (Cartxis), PostgreSQL on Render.

---

## 1. Goal

Give the store owner a place in the admin dashboard to publish the services he
sells — plumbing, deep cleaning, house shifting, electrical, pest control,
carpentry, painting, AC servicing, gardening, appliance repair, and anything
else he adds later — and let a customer book one from the website for a chosen
date and time. The owner assigns the booking to one of the staff he already has
(the existing delivery people), watches it move from **booked → finished**, and
collects the money when the job is done.

**This is a generic system, not a set of hardcoded trades.** Nothing in the
schema or code says "plumbing". The owner creates whatever categories and
services his business actually offers.

Deliberately small: no cart changes, no online payment for services, no second
worker app, no calendar/scheduling engine.

---

## 2. Confirmed requirements (from the client)

| # | Requirement |
|---|---|
| R1 | Any service the owner sells must be addable from the dashboard. Not limited to a fixed list. |
| R2 | A service carries: **price + unit**, **how long it takes**, **service area**, **what is included**, and an **icon** (photo optional). |
| R3 | Services get their own page on the website plus a link in the site menu. |
| R4 | The customer books: picks a service, a preferred date and time, gives address and phone, adds notes. |
| R5 | The customer does **not** pay online. Money is collected after the job is done. |
| R6 | Price per service is **fixed**, not a range or an estimate. |
| R7 | A service booking **also appears in the existing Orders screens**. |
| R8 | Bookings move through a small status flow: booked → assigned → in progress → completed / cancelled. |
| R9 | A worker is assigned to each booking, drawn from the **existing delivery staff**. No new user type. |
| R10 | The worker does the job in the **existing worker portal**. No second portal, no second app, no second login. |

---

## 3. What already exists (verified in code)

| Area | Where | State |
|---|---|---|
| Package layout | `packages/Cartxis/*`, psr-4 `Cartxis\<Pkg>\` in root `composer.json` | Every feature is a self-contained package with its own provider, routes, migrations, models |
| Provider registration | `bootstrap/providers.php` (explicit list, not auto-discovery) | New package must be added here |
| Reference module (content) | `Cartxis\Blog` | Best template: own tables, own `Routes/admin.php` + `Routes/web.php`, Form Requests, menu seeded by a migration |
| Reference module (money) | `Cartxis\Referral` | Best template for `Settings` service over the existing `settings` table, and for event-observer side effects on orders |
| Dashboard menu | `menu_items` table + `Cartxis\Core\Models\MenuItem` | Menu is **database-driven**. A new menu row appears in the sidebar with no frontend change. Fields: `key, title, icon, route, parent_id, order, permission, location, active` |
| Storefront menu | Content → Storefront Menus, read by `useStorefrontMenu()` in `ThemeHeader.vue` | A "Services" nav link is **data**, not code |
| Admin CRUD pattern | `Cartxis\Product\Http\Controllers\Admin\BrandController` | `index/create/store/show/edit/update/destroy` + `bulkDestroy` + `bulkStatus`, `Inertia::render`, `back()->with('success')` |
| Orders | `Cartxis\Shop`, table `orders` | `order_number` unique, `status`, `payment_status`, `subtotal/tax/shipping_cost/discount/total`, `payment_method`, `customer_email/phone`, `source_channel`. Admin Orders list **does not filter by `source_channel`**, so a new kind of order shows up there with no change |
| Order lines | table `order_items` | `product_id` **nullable**, with a name/price/image **snapshot**. So a service can be written onto an order without a product row |
| Cart | table `cart_items` | `product_id` is **NOT NULL** with a hard FK. **The cart cannot hold a service — and does not need to** |
| Worker portal | `/delivery/*`, guard `delivery`, `EnsureDeliveryRole` middleware, `resources/js/layouts/DeliveryLayout.vue`, pages `Delivery/Dashboard.vue`, `Delivery/Deliveries/*` | Separate session cookie, own login. Roles on the one `users` table |
| Jobs/lifecycle table | `deliveries` + `delivery_events` | `deliveries` requires `shipment_id` **NOT NULL**, so a service job **cannot** be a delivery row |
| Settings store | `settings` table via `Cartxis\Core\Services\SettingService` | Grouped key/value, used by `ReferralSettings` exactly as we will use it |
| Images | `Cartxis\CMS` media library (`MediaFile`, `MediaService`) + per-feature `Storage::disk('public')` uploads | Both already in use |
| i18n | `resources/js/Stores/i18n.ts`, `lang/{en,fa,ps}/*.json`, `fa` and `ps` are **RTL** | New screens use `t()` and no hardcoded left/right |
| Tests | Pest, `tests/Feature/<Feature>/` | `RefreshDatabase` on everything in `Feature` |

### Two findings that shape the whole design

1. **The cart is product-locked.** `cart_items.product_id` is a required column
   with a hard foreign key. Since money is collected after the job (R5), a
   service never needs to be in the basket — so **the cart is not touched at
   all**. This removes the single most invasive change we would otherwise need.

2. **A service job cannot be a `deliveries` row.** `deliveries.shipment_id` is
   NOT NULL and a service has no shipment. So a service booking is its own small
   table — but it reuses the *same* workers, the *same* portal, the *same*
   status-timeline idea, and the *same* order row. That is the smallest honest
   design that meets R7, R8, R9 and R10.

---

## 4. Design decisions

1. **A new package `packages/Cartxis/Service/`, namespace `Cartxis\Service\`.**
   Same shape as `Cartxis\Referral`. Keeps services out of Product (a service is
   not a product) and out of Sales (a service job is not a delivery). Touches
   only two shared files, both additively — see §10.

2. **Services are their own catalogue, not products.** A service is a
   *published, bookable thing with a fixed price*, not a thing you put in a
   basket. Reusing `products` would drag shipping, weight, stock, variants, SKU
   and cart logic into a feature that needs none of it.

3. **Every booking creates an `orders` row at the same time** (R7). One
   transaction, so the booking and the order can never disagree. The order gets:
   - `order_number` = `SRV-` + a unique suffix (visibly different from `ORD-`)
   - `status` mirrors the booking status (`pending` → `processing` → `completed`/`cancelled`)
   - `payment_status` = `pending`, `payment_method` = `cod`
   - `subtotal` = `total` = the service price; `tax`/`shipping_cost` = 0 in phase 1
   - `source_channel` = `services` (new value, used for filtering/reporting only)
   - customer name/phone/email copied in, so the order is self-contained
   - one `order_items` row for the service: `product_id` NULL, `product_name`,
     `product_image`, `price`, `total` as the snapshot

   Result: the service booking appears in the Orders list, in Orders search, in
   order counts, in revenue reporting and in CSV export, with **zero changes to
   the Orders screens**.

4. **The booking is the source of truth; the order is the money record.** Status
   changes on the booking drive the order status. The order is never edited by
   hand for a service.

5. **Statuses (5, matching R8, and nothing more):**
   `booked → assigned → in_progress → completed`, with `cancelled` reachable from
   any non-terminal state. Same shape as the delivery transitions map
   (`DeliveryStatusService::allowedTransitions`) so the worker app behaves
   identically for both job types.

6. **Money is collected on the day, by the worker.** On `completed` the worker
   records what was actually collected. It defaults to the booked price but may
   differ (extra hours, extra parts) — both numbers are kept:
   `bookings.price_snapshot` and `bookings.amount_collected`. If they differ, the
   owner's Orders screen shows the collected amount as the order total and the
   booking screen shows the variance. The worker cannot mark a job complete
   without entering an amount.

7. **Workers are the existing delivery staff.** No new role, no new user table,
   no new guard, no new login. The `EnsureDeliveryRole` middleware already
   limits `/delivery/*` to `role = 'delivery'`, so a service worker is simply a
   staff account. Assignment dropdowns in the admin list the same staff the
   delivery screens list.

8. **The worker portal gains a "My Jobs" tab, nothing else.** Same
   `DeliveryLayout.vue`, same dashboard shell, same session. Per job: the service
   name, the address, the customer's name, a **tap-to-call** button, the notes,
   and three actions — **Start job**, **Finish job (enter amount)**,
   **Could not attend**. No map, no GPS sharing, no proof photos for services
   (a plumber does not need to broadcast their location). The delivery map and
   live-location code are untouched.

9. **Lead time and time slots are settings, not code.** Booking lead time (how
   many hours ahead), working hours, the offered time slots, a storewide
   coverage note, and the services phone/WhatsApp number live in the existing
   `settings` table under the `service.*` group, read through a `ServiceSettings`
   service class — the exact pattern `ReferralSettings` already uses. A settings
   screen at **Services → Settings** edits them.

10. **Server is the source of truth.** Slot availability, status transitions,
    price, and lead time are validated on the server. The Vue form mirrors the
    same rules so the customer sees the problem before submitting, and the
    server still refuses it if someone posts directly.

11. **Anything that is not a booked job stays out of the cart and out of
    shipping.** A service order has no shipment, no tracking number, no
    shipping method.

---

## 5. Data model

Four new tables + one settings group. Nothing existing is altered.

### 5.1 `service_categories`
Self-referencing tree so the owner can nest ("Home services" → "Cleaning").

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `parent_id` | fk → `service_categories` | nullable, `nullOnDelete` |
| `name` | string | |
| `slug` | string unique | auto from name, like BlogCategory |
| `description` | text nullable | |
| `icon` | string nullable | lucide-vue-next name, same list as the admin IconPicker |
| `image` | string nullable | stored on `public` disk |
| `status` | enum `enabled,disabled` | default `enabled` |
| `sort_order` | int | default 0 |
| `show_in_menu` | bool | default true |
| `meta_title`, `meta_description` | | SEO, same trio as categories/products |
| timestamps, `softDeletes` | | |

### 5.2 `services`

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `service_category_id` | fk → `service_categories` | nullable, `nullOnDelete` |
| `name` | string | |
| `slug` | string unique | route key (`getRouteKeyName`) |
| `short_description` | text nullable | card text |
| `description` | longText nullable | full page, purified with `mews/purifier` like other rich text |
| `includes` | text nullable | the "what is included" bullet list |
| `excludes` | text nullable | optional, cheap to add, clients always ask |
| `icon` | string nullable | lucide name |
| `image` | string nullable | `public` disk |
| `price` | decimal(12,2) | the fixed price (R6) |
| `price_unit` | enum `per_job,per_hour,per_day,per_sqm` | default `per_job`; the "unit" from R2 |
| `price_note` | string nullable | e.g. "parts not included" |
| `duration_minutes` | int nullable | drives the "how long it takes" display |
| `duration_label` | string nullable | free text override, e.g. "half a day" |
| `service_area` | string nullable | covered areas, free text |
| `icon_only` | bool | admin toggle: show the card as an icon tile, no photo |
| `status` | enum `enabled,disabled` | default `enabled` |
| `featured` | bool | default false, shown on the services page and home page |
| `booking_enabled` | bool | default true — lets the owner publish a service for display only |
| `sort_order` | int | default 0 |
| `meta_title`, `meta_description`, `meta_keywords` | | SEO |
| timestamps, `softDeletes` | | |

Indexes: `slug`, `status`, `service_category_id`, `[status, featured]`.

### 5.3 `service_bookings` (the job)

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `reference` | string unique | customer-facing, e.g. `SRV-7K3M9Q` |
| `order_id` | fk → `orders` | nullable, `cascadeOnDelete` — the money record from §4.3 |
| `service_id` | fk → `services` | nullable, `nullOnDelete` — the service may be deleted later |
| `user_id` | fk → `users` | nullable, `nullOnDelete` (guest booking allowed) |
| `status` | enum `booked,assigned,in_progress,completed,cancelled` | default `booked`, indexed |
| `assigned_to` | fk → `users` | nullable, `nullOnDelete` — **the existing delivery staff**, indexed |
| `assigned_by` | fk → `users` | nullable |
| `assigned_at`, `started_at`, `completed_at`, `cancelled_at` | timestamp nullable | |
| `scheduled_date` | date | indexed |
| `scheduled_slot` | string(40) | the chosen slot label, e.g. `09:00 - 12:00` |
| `service_name` | string | snapshot, survives service deletion |
| `price_snapshot` | decimal(12,2) | the price promised at booking time |
| `amount_collected` | decimal(12,2) nullable | filled by the worker on completion |
| `payment_method` | string(30) | default `cash` |
| `customer_name` | string | |
| `customer_phone` | string(30) | |
| `customer_email` | string nullable | |
| `address` | text | the job address |
| `city` | string nullable | |
| `notes` | text nullable | what the customer typed |
| `internal_notes` | text nullable | owner/staff only, never shown to the customer |
| `cancel_reason` | string(255) nullable | |
| `source` | string(20) | `web` default |
| timestamps | | no soft deletes — a job is a financial record |

### 5.4 `service_booking_events`
Exact mirror of `delivery_events`, so the timeline UI can be reused.

| Column | Type |
|---|---|
| `id` | id |
| `service_booking_id` | fk → `service_bookings`, `cascadeOnDelete`, indexed |
| `actor_id` | fk → `users` nullable |
| `from_status`, `to_status` | string(30) nullable |
| `note` | text nullable |
| `created_at` | timestamp |

### 5.5 Settings group `service.*` (existing `settings` table)
`service.booking_enabled`, `service.lead_time_hours` (default 24),
`service.working_hours_start` / `_end`, `service.time_slots` (array of
`{label, start, end}`), `service.coverage_note`, `service.contact_phone`,
`service.contact_whatsapp`, `service.require_login_to_book` (default false),
`service.auto_assign` (default false), `service.reference_prefix` (`SRV-`).

---

## 6. Package anatomy

```
packages/Cartxis/Service/
  composer.json                     extra.laravel.providers (mirrors Referral)
  src/ServiceServiceProvider.php     loadRoutesFrom admin+web+delivery, loadMigrationsFrom
  src/Config/service.php             booking defaults, mirrors product.php / shop.php
  src/Models/ServiceCategory.php
  src/Models/Service.php
  src/Models/ServiceBooking.php
  src/Models/ServiceBookingEvent.php
  src/Services/ServiceSettings.php   mirrors ReferralSettings
  src/Services/ServiceBookingService.php    create booking + order in one transaction
  src/Services/ServiceBookingStatusService.php   the allowedTransitions map
  src/Services/ServiceSlotService.php         lead time + slot availability
  src/Http/Controllers/Admin/ServiceCategoryController.php
  src/Http/Controllers/Admin/ServiceController.php
  src/Http/Controllers/Admin/ServiceBookingController.php
  src/Http/Controllers/Admin/ServiceSettingController.php
  src/Http/Controllers/ServiceController.php            public: index/category/show
  src/Http/Controllers/ServiceBookingController.php     public: store/show/track
  src/Http/Controllers/Delivery/ServiceJobController.php worker portal (reuses delivery guard)
  src/Http/Requests/StoreServiceRequest.php
  src/Http/Requests/UpdateServiceRequest.php
  src/Http/Requests/StoreServiceCategoryRequest.php
  src/Http/Requests/UpdateServiceCategoryRequest.php
  src/Http/Requests/StoreServiceBookingRequest.php
  src/Http/Requests/StoreServiceSettingsRequest.php
  src/Routes/admin.php        admin/services/...      name admin.services.*
  src/Routes/web.php          /services, /services/{category}, /services/{service}
                             name services.*
  src/Routes/delivery.php     /delivery/jobs...       name delivery.jobs.*
  src/Database/Migrations/…   the 4 tables, the settings seed, the menu items
  src/Database/Seeders/ServiceSeeder.php   optional starter categories/services
```

Migrations dated `2026_09_30_*` so they order after the referral set
(`2026_09_29_*`) and before anything later.

---

## 7. Admin dashboard

Menu (seeded by migration, parented under **Catalog**, order 35):

| Key | Title | Route | Icon |
|---|---|---|---|
| `services` | Services | `admin.services.index` | `wrench` |
| `services-categories` | Service Categories | `admin.services.categories.index` | `folder-tree` |
| `services-bookings` | Service Bookings | `admin.services.bookings.index` | `clipboard-list` |
| `services-settings` | Service Settings | `admin.services.settings.edit` | `settings-2` |

No frontend change needed for the sidebar — `AdminLayout.vue` reads the menu from
the database.

**Screens** (`resources/js/pages/Admin/Services/…`, all `t()`-driven, RTL-safe):

- `Categories/Index.vue` — tree table, add/edit/delete inline or modal, drag to
  reorder, per-category service count, delete blocked while services exist
  (same rule as `BrandController::destroy`)
- `Index.vue` (services) — search, filter by category + status + featured,
  sortable, per-page, **bulk enable/disable/delete**, image thumbnail, price with
  unit, duration, "no services yet" empty state
- `Create.vue` / `Edit.vue` — the service form: name, category, short and full
  description, *what is included*, *what is not included*, icon picker (reuse
  `Admin/System/Menu/IconPicker.vue`), image uploader (reuse
  `Admin/ImageUploader.vue`), price + unit, duration, service area, featured,
  booking enabled, sort order, SEO, live slug check
- `Bookings/Index.vue` — the working screen: filter by status, date range,
  assigned worker, service, search by reference/name/phone. Columns: reference,
  service, customer, phone (tap-to-call), address, scheduled date + slot,
  price, **assign worker dropdown** (the same staff list as Delivery Staff),
  status pill, actions
- `Bookings/Show.vue` — full detail, the event timeline (same component shape as
  the delivery Show page), customer contact, service address, assign/change
  worker, allowed status actions, collected vs promised amount, internal notes,
  **print job sheet**
- `Settings/Index.vue` — the `service.*` settings
- `Bookings/Export.vue` (route only) — CSV of bookings, matching the existing
  orders export

Copy (`.vue`) follows `Admin/Brands/Index.vue` and the shared admin components
that are already there: `Admin/Pagination.vue`, `Admin/ImageUploader.vue`,
`Admin/ConfirmDeleteModal.vue`, `Admin/System/Menu/IconPicker.vue`.

---

## 8. Storefront

Routes (theme-resolved with `ThemeViewResolver`, like Blog):

| URL | Page | Inertia view |
|---|---|---|
| `/services` | All services — hero, category tiles, featured services, search + filters | `Services/Index` |
| `/services/category/{slug}` | Services in one category | `Services/Category` |
| `/services/{slug}` | Service detail: description, includes/excludes, duration, service area, price, and the booking form | `Services/Show` |
| `POST /services/{slug}/book` | Creates the booking + order | — |
| `/services/booked/{reference}` | Confirmation: reference, status, what happens next | `Services/Booked` |
| `POST /services/track` | Look up a booking by reference + phone | `Services/Track` |

Files under
`templates/storefront/general/cartxis-default/resources/views/pages/Services/…`
plus a `blocks/ServicesGridBlock.vue` for the home page section (reusing the
existing block registry, so the UI editor can place it).

**Booking form** (`Services/Show.vue`): preferred date (a real date input,
earliest = today + lead time, validated on the server too), time slot (rendered
from `ServiceSettings::timeSlots()`; a slot is greyed out when past capacity for
that day), name, phone (required), email (optional), address (required), notes.
Inline field errors under each input, all checked at once, focus moved to the
first problem — no silent "button does nothing".

**Nav link:** added in the dashboard under Content → Storefront Menus as a data
row pointing at `services.index`. No change to `ThemeHeader.vue` needed. The
hardcoded fallback nav in `ThemeHeader.vue` is left alone (that file is being
edited in another session — see §10).

**Account area:** `My Bookings` added to the signed-in customer dashboard
(`/account`) listing that user's bookings and their live status. Small — one
page, links into the same public tracking view.

---

## 9. Worker portal (reuse, not rebuild)

`resources/js/layouts/DeliveryLayout.vue` gains one more nav item,
**My Jobs** (`/delivery/jobs`), next to **Deliveries**.

| Route | Name | Action |
|---|---|---|
| `GET /delivery` | `delivery.dashboard` | existing dashboard gains a "Jobs today" count next to the delivery count |
| `GET /delivery/jobs` | `delivery.jobs.index` | my assigned + unassigned service jobs, grouped by date |
| `GET /delivery/jobs/{id}` | `delivery.jobs.show` | detail: service, customer, tap-to-call, address, notes, promised price |
| `POST /delivery/jobs/{id}/start` | `delivery.jobs.start` | `assigned` → `in_progress` |
| `POST /delivery/jobs/{id}/complete` | `delivery.jobs.complete` | `in_progress` → `completed`, requires `amount_collected` |
| `POST /delivery/jobs/{id}/cancel` | `delivery.jobs.cancel` | → `cancelled`, requires a reason |

Same middleware stack as the delivery routes: `['web', 'auth:delivery',
'delivery.access']`. The same `delivery` guard, session cookie, login page and
role check. Same `Delivery/Dashboard.vue` shell with a second tab.

**Not touched:** the delivery map, live-location sharing, proof photos, delivery
reports, `DeliveriesController`, `DeliveryStatusService`, the `deliveries` and
`delivery_events` tables.

One shared component is worth extracting — the status timeline
(`Admin/Sales/Deliveries/Show.vue` and the job Show page draw the same thing) —
but that is a *nice-to-have*, so it lands only if it does not require editing the
delivery pages. If it does, the service job gets its own small timeline
component instead.

---

## 10. Collision safety with the concurrent Referral work

The other session has uncommitted work in these shared files:

| Shared file | Risk | How we stay out of it |
|---|---|---|
| `composer.json` | both need a psr-4 line | 1 additive line inside the existing `Cartxis\` block. Re-read the file immediately before editing; if `Cartxis\Service\` is not there, add it next to `Cartxis\Referral\` and re-verify the JSON parses |
| `bootstrap/providers.php` | both need a provider entry | 1 additive line. Re-read immediately before editing |
| `app/Models/User.php` | referral may add a relation | **we add nothing.** The booking's `assigned_to` is a plain `belongsTo(User)`, no field on `users`, no relation, no migration on `users` |
| `routes/web.php` | other session added the locale route | **we add nothing.** Our public routes are loaded by our own provider from `src/Routes/web.php` |
| `resources/js/app.ts` | other session registering the i18n store | **we add nothing.** We only *use* the `i18n` store that is already there |
| `resources/js/layouts/AdminLayout.vue` | other session edits it | **we add nothing.** Menu is database-driven |
| `.../ThemeHeader.vue` | other session edits it | **we add nothing.** Nav link is a menu data row |
| `tests/Pest.php` | other session added `referral*()` helpers | **we add no helpers there.** Test fixtures live in `tests/Feature/Services/ServiceTestCase.php`… actually Pest has no `TestCase` per folder, so fixtures go in a plain `tests/Feature/Services/helpers.php` required from our own test file |
| `app/Models/User.php` relation `orders()` | — | a service order is a normal `orders` row, so it inherits every existing user/order report for free |

**The one real interaction to agree on with the other session:** the referral
package's `OrderObserver` fires on *every* order whose `payment_status` becomes
`paid`. A service booking flips to `paid` when the worker collects cash, so a
naive implementation would make **service orders earn referral commission**.

**Decided: service orders never participate in referrals.** This was settled
during implementation, and it is handled from *our* side, so the referral
package's file is never touched:

- The order is created with `Order::withoutEvents(...)`, so no observer fires
  while the booking is being written.
- Every later change to the order (`status`, `payment_status`) goes through
  `updateQuietly(...)`, which fires no model events.
- The order keeps `source_channel = 'services'` so it is still identifiable in
  reports and in the Orders screens.

`Cartxis\Referral\Observers\OrderObserver` is their file and is left exactly as
it is. Two things make this safe rather than merely lucky:

1. A service order is a normal `orders` row, so it appears in the existing
   Orders screens and in every existing user/order report, unchanged.
2. The whole write path is covered by
   `tests/Feature/Services/ServiceBookingFlowTest.php` → *"a service order never
   earns a referral commission"*. It books a job, assigns a worker, starts it and
   collects the cash, then asserts the order really is `completed` and `paid`
   while `referral_ledger` and `referral_commissions` are both still empty.

If referrals are ever wanted for service jobs, that is a deliberate new change in
the referral package, made with that session, not an accident to be noticed
later.

**Sequencing rule:** we work in our own new files until the only remaining work
touches a shared file, and then we re-read that file, apply a minimal additive
change, and verify the other session's work is still intact. No `git checkout`,
no `git stash`, no force operations on files we do not own.

---

## 11. Phases

| Phase | Deliverable | Gate |
|---|---|---|
| **1. Package skeleton + catalogue** | New `Cartxis\Service` package, 4 migrations, models, settings service, admin menu, admin Categories + Services CRUD, no storefront yet | `migrate` clean, admin can add a category and a service, tests green |
| **2. Storefront** | `/services`, category, detail, `ServiceSettings` page, storefront menu entry, home-page block, translations for en/fa/ps, RTL checked | Customer can browse and read a service page |
| **3. Booking** | Booking form + lead time + slots, `ServiceBookingService` transaction creating booking + order + order_item, confirmation page, track-by-reference, `My Bookings` in the account area | A test booking appears in the admin Bookings list **and** in the existing Orders list, `payment_status = pending` |
| **4. Admin jobs screen** | `Bookings/Index` + `Show`, assign worker, status actions, timeline, CSV export, print job sheet | Status can be moved only through allowed transitions |
| **5. Worker portal** | `My Jobs` tab, job list + detail, start / finish-with-amount / could-not-attend, dashboard count | A driver logs in at the existing `/delivery/login` and sees only their own jobs; the delivery side is byte-for-byte unchanged |
| **6. Reporting + docs** | Booking CSV, Services section in the owner guide, empty states, RTL pass | — |

Each phase ships with its own tests. Phases 1–2 carry no risk to live trading;
phase 3 is the first one that writes an `orders` row, so it is the first one to
get a full test pass plus a manual booking on staging.

---

## 12. Tests

`tests/Feature/Services/`, Pest, `RefreshDatabase` (already applied to all of
`tests/Feature`).

**Catalogue**
- an admin can create, update and delete a service
- a service cannot be created without a name and a price
- the price must be a positive number; the unit must be one of the four values
- the slug is generated from the name and is unique
- a service is soft-deleted, and its booking keeps `service_name` and
  `price_snapshot` afterwards
- a category with services cannot be deleted
- a non-admin cannot reach `/admin/services` (403)

**Storefront**
- only `enabled` services with `booking_enabled` are listed
- a disabled or soft-deleted service returns 404 on its page
- the services page returns the right Inertia component and view model

**Booking**
- a valid booking creates **both** a `service_booking` and an `orders` row, with
  matching totals, and one `order_items` snapshot row
- the reference number is unique across many bookings
- a date inside the lead time is rejected **by the server**, with an error under
  the field
- a booking for a service with `booking_enabled = false` is rejected
- a booking is rejected when the phone is missing
- **the same-booking-twice case:** the second submit for an identical payload
  creates one booking, not two (double-click protection)
- the guest can book; a signed-in user's booking is linked to them

**Status and order sync**
- `booked → assigned → in_progress → completed` is allowed
- `booked → in_progress` (skipping a step) is refused
- a completed job cannot be reopened
- cancelling writes the reason and cancels the order
- completing requires an amount and sets `payment_status = paid` on the order

**Worker portal**
- a driver sees only their own jobs
- a non-driver is refused by `delivery.access`
- a driver cannot complete a job assigned to somebody else
- the amount collected defaults to the promised price and may be overridden

**Money**
- a service order has `shipping_cost = 0` and no shipment
- the collected amount is what the order total becomes

Deliberate check: each of the three riskiest rules (skipping a status step,
the lead-time refusal, and the cross-driver completion) is written so that
removing the guard makes the test go red — the fix is proven by the failure, not
just the pass.

---

## 13. Out of scope (stated so nobody assumes otherwise)

- Online payment, deposits, or partial payments for services
- Putting a service in the shopping cart
- Recurring jobs, contracts, or subscription services
- A dispatcher's calendar/Gantt view, or automatic driver routing
- Per-service pricing tiers, add-ons, or quote builders
- Coupons or taxes on services (the `tax` column stays `0` in phase 1; adding
  `tax_class_id` later is one nullable column)
- Showing service jobs as pins on the existing delivery map board
- A separate mobile app for the worker

---

## 14. Open questions for the client

1. Should a service order earn referral commission, or be excluded from the
   referral programme? (§10)
2. Do you want a fixed cancellation policy, or is a free-text reason enough?
3. Should a customer be able to cancel or reschedule a booking themselves from
   the account area, or must they phone you?
4. One slot list for the whole business, or different slots per service
   category (a plumber's day is not a deep clean's day)?
5. Is one job per booking the rule, or can a customer book several services for
   the same visit in one go?
