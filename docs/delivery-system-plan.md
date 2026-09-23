# Asaan In-House Delivery System — Implementation Plan

Status: **Approved — in progress**
Stack: Laravel 13 + Inertia/Vue 3 + Tailwind 4 (Cartxis), PostgreSQL on Render, optional MySQL/SQLite for dev/tests.

---

## 1. Goal

Let the store owner assign packages to in-house delivery staff from the admin dashboard, and give each delivery person their own login and mobile-first screen where they see assigned packages, the customer location on a map, call the customer, and step the package through the delivery lifecycle (pending → assigned → out for delivery → arriving → delivered / undelivered), including proof of delivery and cash-on-delivery collection.

Deliverable: a real, installable Android APK eventually; delivery staff reach the portal through the same app (or any browser). No third-party delivery company required.

---

## 2. What already exists (verified in code)

| Area | Where | State |
|---|---|---|
| Orders | `Cartxis\Shop`, table `orders` | Full: customer, phone, polymorphic shipping/billing address, COD support |
| Shipments | `Cartxis\Sales`, table `shipments` | Statuses `pending, shipped, in_transit, out_for_delivery, delivered, failed, cancelled`; admin pages, timeline, Shiprocket + Delhivery integrations |
| Admin auth | `/admin/login`, guard `admin`, own session cookie `*_admin` | Admins = `users` rows with `role = 'admin'` |
| Storefront auth | `/login`, guard `web` | Customers only |
| Realtime | None | The platform polls (admin notifications poll every 30s) — we follow the same pattern |

Missing pieces this plan adds: a `delivery` role, a driver login + portal, shipment→driver assignment, delivery lifecycle + status sync to shipments/orders, customer coordinates, proof of delivery (photo + COD), maps, driver live location sharing, per-driver reports.

---

## 3. Design decisions

1. **One users table, three roles.** `users.role` gains `delivery`. Admins (`role=admin`), customers (`customer`), drivers (`delivery`). No new user table.
2. **Separate `delivery` auth guard** (session driver, same `users` provider) and a **separate session cookie** for `/delivery/*` so a driver session never collides with an admin or storefront session in the same browser/app.
3. **Same login screen, two tabs.** `Admin/Auth/Login.vue` becomes a tab switcher: **Admin** (posts to `/admin/login`) and **Delivery Person** (posts to `/delivery/login`). Drivers cannot enter the admin panel; admins cannot enter the driver portal.
4. **Delivery entity is separate from a shipment.** A `deliveries` row links a shipment/order to a driver — one shipment has at most one active delivery run. The delivery lifecycle drives the shipment status, which in turn drives the customer-visible order timeline.
5. **Status model** (delivery): `pending → assigned → out_for_delivery → arriving → delivered` (with `undelivered` and `cancelled` as terminal/failure states). Delivery → shipment mapping: `assigned → shipped`, `out_for_delivery → out_for_delivery`, `arriving → out_for_delivery`, `delivered → delivered` (+ `delivered_at`, order `completed`), `undelivered → failed`, `cancelled → cancelled`.
6. **Maps = Leaflet + OpenStreetMap** (free, no API key, works in Kabul and major Afghan cities). Admin board shows all active deliveries + live driver markers; the driver screen shows the customer pin and an **"Open in Google Maps directions"** deep link (reliable fallback in Afghanistan). Missing coordinates are normal — the driver calls the customer (one-tap phone number), which is how Afghan deliveries actually work.
7. **Live tracking is opt-in and honest.** Only "live" while the driver toggles location sharing on; the app then sends the position every ~15 s (browser geolocation works in the Capacitor WebView). No fake tracking ever.
8. **Commerce rule applies on both sides.** Status transitions, roles, phone and amounts are validated server-side; the Vue UI mirrors the same rules. The server is always the source of truth.
9. **No websockets.** Polling, consistent with the rest of the platform and Render's free tier.

---

## 4. Data model additions

### 4.1 `users.role` — add `delivery`

New migration. Cross-database (`enum` handling differs):
- MySQL: `MODIFY COLUMN role ENUM('admin','customer','delivery') NOT NULL DEFAULT 'customer'`
- PostgreSQL: Laravel renders `enum` as `varchar` + check constraint `users_role_check` → drop that check (role then stays a plain varchar, validated in code)
- SQLite (tests): rebuild column to `string` via `->change()` (no enum lock-in)

### 4.2 New table `deliveries`

| Column | Purpose |
|---|---|
| `id` | PK |
| `shipment_id` (FK, cascade) | the package being delivered |
| `order_id` (FK, cascade) | denormalized convenience + customer address lookup |
| `assigned_by` (FK users, null on delete) | which admin created/assigned it |
| `assigned_to` (FK users, null on delete) | the driver |
| `status` | `pending / assigned / out_for_delivery / arriving / delivered / undelivered / cancelled` |
| `scheduled_date` (datetime, nullable) | "arriving today" style scheduling |
| `priority` | normal / high |
| `customer_phone` | snapshot for one-tap call |
| `cod_amount`, `cod_received` | expected vs collected cash |
| `recipient_name`, `delivered_photo_path` | proof of delivery |
| `failure_reason` | `customer_unavailable / wrong_address / no_response / other` + `failure_note` |
| `last_latitude`, `last_longitude`, `last_location_at` | live driver position |
| `notes`, `timestamps` | |

### 4.3 New table `delivery_events`

Audit timeline (mirrors `order_histories`): `delivery_id` FK, `actor_id` (users), `from_status`, `to_status`, `note`, `created_at`. Every transition appends a row — this is the step-by-step trail shown in admin and driver screens.

### 4.4 `addresses` += `latitude`, `longitude` (nullable)

Customer coordinates. Optional — admin can pin them, checkout can add them later, and the driver app always has the address + phone + Google Maps fallback.

---

## 5. Authentication & routes

### 5.1 New guard `delivery`

`config/auth.php` → guards: `'delivery' => ['driver' => 'session', 'provider' => 'users']`.

### 5.2 Session cookie

`SetAdminSessionCookie` (Core package) currently keys on `/admin` and appends `_admin`. Extend it: `/delivery` → append `_delivery` (single middleware, one place — the shared chokepoint).

### 5.3 Middleware (mirror the admin set)

- `EnsureDeliveryRole` (`delivery.access`) — on `/delivery/*`: logged-in user must have `role === 'delivery'` and be active; otherwise logout + redirect to `delivery.login`. Prevents admins/customers entering the portal.
- `RedirectIfDeliveryAuthenticated` — on `/delivery/login`: if a valid delivery session exists, redirect to `delivery.dashboard`.

Register aliases and add `/delivery/* → delivery.login` to `redirectGuestsTo` in `bootstrap/app.php`.

### 5.4 Routes

**Driver portal** (`packages/Cartxis/Sales/src/Routes/delivery.php`, loaded by `SalesServiceProvider`):
```
GET  /delivery/login                       → delivery.login          (guest)
POST /delivery/login                       → delivery.login.store    (guest, throttled)
POST /delivery/logout                      → delivery.logout
GET  /delivery                             → delivery.dashboard
GET  /delivery/deliveries                  → delivery.deliveries.index
GET  /delivery/deliveries/{id}             → delivery.deliveries.show
POST /delivery/deliveries/{id}/start       → delivery.deliveries.start          (assigned → out_for_delivery)
POST /delivery/deliveries/{id}/arriving    → delivery.deliveries.arriving       (→ arriving)
POST /delivery/deliveries/{id}/deliver     → delivery.deliveries.deliver        (photo, recipient, COD → delivered)
POST /delivery/deliveries/{id}/undelivered → delivery.deliveries.undelivered    (reason → undelivered)
POST /delivery/location                    → delivery.location.update           (live share, throttled)
```
All authenticated with `auth:delivery` + `delivery.access`.

**Admin** (extend `packages/Cartxis/Sales/src/Routes/admin.php`):
```
admin/sales/deliveries            GET index?page=&status=&driver=&search=      list + filters
admin/sales/deliveries            POST create/assign (shipment, driver, date)   assign from admin
admin/sales/deliveries/{id}       GET show                                     detail + events + proof
admin/sales/deliveries/{id}       PUT update                                   reschedule / notes / reassign
admin/sales/deliveries/{id}/cancel POST cancel
admin/sales/deliveries/board      GET board                                    map of active runs + drivers
admin/sales/delivery-staff        GET/POST/PUT/DELETE                          manage driver accounts (role=delivery)
admin/sales/delivery-staff/{id}   POST reset-password
admin/reports/delivery            GET report                                   per-driver stats
admin/reports/delivery/export     GET export/csv
```

### 5.5 Shared Inertia props

`HandleInertiaRequests` currently shares `auth.user` from the `web` guard. Add: for `/delivery/*`, share `$request->user('delivery')`. The driver portal has its own layout with a mobile-first shell.

---

## 6. Status service (single source of truth)

`Cartxis\Sales\Services\DeliveryStatusService`:

- `transition(Delivery $d, string $to, array $data)` — validates the transition (terminal states are locked), applies side effects, writes a `delivery_events` row.
- Side effects per transition:
  - `assigned → out_for_delivery`: shipment → `out_for_delivery`; timeline entry visible to customer.
  - → `arriving`: shipment stays `out_for_delivery`; entry "Arriving today".
  - → `delivered`: shipment → `delivered` + `delivered_at`; order → `completed`; save photo + recipient + COD collected (COD collected now → `cod_received`).
  - → `undelivered`: shipment → `failed`; record reason.
  - → `cancelled`: shipment → `cancelled` (admin only).
- Reassignment: `assigned_to` change + event row; if `assigned` re-created, shipment → `shipped`.
- Permissions: driver transitions only on deliveries assigned to them and only on allowed moves; admin can do anything. Both sides validated in the controllers too.

---

## 7. Frontend pages

### 7.1 Shared

- `components/Delivery/LeafletMap.vue` — thin Leaflet wrapper (markers, optional driver polylines). Auto-mirrors RTL-safe, mobile-ready.
- `types/delivery.ts` — Delivery, DeliveryEvent, DeliveryStaff interfaces.
- Add `leaflet` (+ types) to `package.json`. Wayfinder regenerates typed routes on build.

### 7.2 Driver portal (`resources/js/pages/Delivery/*`, layout `layouts/DeliveryLayout.vue`)

- **Login**: lives on the shared `Admin/Auth/Login.vue` tabs.
- **Dashboard**: today’s counts (new assignments, out for delivery, delivered, COD collected today).
- **Deliveries**: list assigned, filter by status; top-to-bottom priority order.
- **Delivery detail**: order items, customer name/phone (tap to call), address, Leaflet pin, "Open in Google Maps directions" link, COD amount, status timeline, actions (Start / Arriving / Delivered / Couldn’t deliver) with the proof form (photo upload, recipient name, COD collected) and failure form (reason + note).
- **Location share** toggle in the header: while on, `navigator.geolocation` posts every 15 s to `delivery.location.update`.

### 7.3 Admin (`resources/js/pages/Admin/Sales/*`)

- `Deliveries/Index.vue` — list, filters, assign modal (select driver + date from shipments), reassign, cancel.
- `Deliveries/Board.vue` — full-screen Leaflet board, driver + delivery markers, courtesy zoom, polling every 20 s.
- `Deliveries/Show.vue` — detail, events timeline, proof photo / COD, cancel, reschedule.
- `DeliveryStaff/Index.vue` + modals — create/edit/deactivate/reset password.
- `Reports/Delivery.vue` — per-driver report + CSV export.
- Sales **Orders/Shipments** show pages gain an **"Assign to delivery"** action.
- Admin menu (seeded): new **Delivery** section → Delivery Board, Deliveries, Delivery Staff.

---

## 8. Afghanistan-specific behaviour

- Phone: `+93` snapshots; tap-to-call everywhere.
- Precise home coordinates are rare → three-tier strategy: admin pins on the board, optional checkout pick, and the always-works fallback (Google Maps directions deep link + phone call).
- COD is common → `cod_amount` / `cod_received` tracked and reported.
- Navigation deep link: `https://www.google.com/maps/dir/?api=1&destination={query|lat,lng}` opens the phone’s Maps app (works in the Capacitor WebView).

---

## 9. Implementation phases

| Phase | Scope | Verification |
|---|---|---|
| **1** | `delivery` role migration; `delivery` guard + cookie; middleware; login tabs; minimal driver dashboard | Migrate on sqlite + mysql; driver login OK; admin blocked from portal; admin login OK; build |
| **2** | `deliveries`, `delivery_events`, `addresses.lat/lng` migrations; models + relations; admin assign/staff management; menu | Assign → DB row; reassign; staff CRUD; admin-only enforcement |
| **3** | Driver portal pages; `DeliveryStatusService` + shipment/order sync; COD + proof | Full pending→delivered happy path; undelivered; order completes; tests |
| **4** | Leaflet driver map + admin board; live location polling | Marker shows; location updates in DB; throttled |
| **5** | Delivery report + export; lint (`vue-tsc`, `eslint`), PHP tests, full `npm run build`, fresh `migrate --seed` on Postgres | All green |

Each phase ends with: run the tests, typecheck, build — then commit and push.

---

## 10. Risks & guardrails

- **Postgres enum**: handled with the check-constraint drop; verified on a fresh PG container before deploy.
- **Wayfinder typed routes**: new routes require a frontend build pass to regenerate `resources/js/routes/*`.
- **No websockets**: live board is polling; documented as near-real-time.
- **Geolocation in a WebView**: requires the user to allow location; falls back gracefully (no map pin, deep link still works).
- **Phasing**: each phase is pushed independently so the store keeps working while unfinished work is not merged.

---

## 11. Definition of done

A package can be created (or an order checked out), admin assigns it to a driver from the dashboard, the driver logs in from the app/browser, sees it on their list and map, steps it through to delivered (photo + COD), the order auto-completes, the customer timeline shows the steps, and the admin board and report reflect everything — all verified by tests and a clean build.