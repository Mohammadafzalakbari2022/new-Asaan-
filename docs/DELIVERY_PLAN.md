# Asaan Store — Delivery Plan

Prepared: 2026-09-29. Live URL: `https://asaan-store.onrender.com`
Repo: `https://github.com/Mohammadafzalakbari2022/new-Asaan-.git`

---

## 1. Where the project actually stands

### Done and live

| Commit | What it fixed |
|---|---|
| `7c00a29` | Services pages imported an uncommitted translation file → Render build failed |
| `8b0bd43` | MySQL-style `status = 1` compared against PostgreSQL boolean/enum → 500s |
| `e9f262b` | `APP_DEBUG` was hardcoded and could not be turned off from the environment |
| `68988b2` | Migrations only ran on a brand-new database → Services/Referral tables never created |
| `344e6dc` | `$t` was never defined, so 38 theme templates crashed on render (the black screen) |
| `4eee97b` | Reverse proxy not trusted, so `route()` emitted `http://` → admin forms blocked as Mixed Content |

All storefront pages return `200`.

### Delivered features

- **Referral programme** — codes, commissions, ledger, settings, attribution, checkout application, admin + customer UI, reversal/refund handling, deletion protection. 142 tests pass.
- **Services** — catalogue, categories, bookings, booking events, tracking. 7 migrations.
- **Delivery system** — built previously.

### Known non-issues

Do not chase these — they are not your code:

- `SyncoRedux`, `classifier.js`, `adblock-picreplacement.js`, *"Could not establish connection. Receiving end does not exist."* → a browser extension installed in your browser.
- `Slow network is detected` font warnings → harmless.
- 404 on `/storage/...png` → see §6, not a code bug.

### Open risk: two editors on one repo

There is **uncommitted translation work in the working tree** (15 untracked files plus modified Vue/PHP files) that another process is still writing to. Every commit from here is a partial-stage commit. Never run `git add -A`. Confirm with the other editor before touching `resources/js/Stores/i18n.ts`, `lang/**`, or the translation edits inside storefront templates.

---

## 2. Phase 1 — Single shop: **ALREADY DONE, no work required**

Verified, not assumed:

- No `Shop` model anywhere.
- No `shops` table in any migration.
- **No `shop_id` column in any table** in the entire project.
- No tenancy package (`stancl/tenancy`, `spatie/laravel-multitenancy`) in `composer.json`.
- No shop switcher in the admin UI.

`Cartxis\Shop` is a **module namespace** (the storefront/ordering package), not multi-tenancy. Shop name and logo come from the `settings` table via `packages\Cartxis\Core\src\Services\BrandingService.php` and are edited in the admin dashboard — exactly as you want.

**Action: none.** Do not remove anything. A shop switcher does not exist, so there is nothing to hide.

---

## 3. Phase 2 — Languages: Dari (default), Pashto, English

**Owner: you / the other editor.** I will not touch this until you confirm the dictionaries are finished.

Current state (uncommitted): `lang/{fa,ps,en}.json`, `lang/{en,fa,ps}/{storefront,admin}.json`, `resources/js/Stores/i18n.ts`, and an `APP_LOCALE=fa` change in `entrypoint.sh`.

What still needs deciding by you:

1. **Default locale.** `APP_LOCALE=fa` in `entrypoint.sh` is not committed. Must be set server-side as a Render env var, not only in a file, or it resets on every deploy.
2. **Guest vs. logged-in language.** A guest sees Dari. If someone explicitly picks English, that choice must survive login and page loads.
3. **Fallback chain.** Decide what a visitor sees for a key that has no Dari translation. Recommendation: Dari → English → show the key.
4. **Admin panel too, or storefront only?** Admin in Dari/English is realistic; admin in Pashto is a lot more work.

Note: the `$t` helper shipped in `344e6dc` is a temporary English stand-in. It is the **single function to replace** when dictionaries land — `resources/js/lib/text.ts`. Nothing else needs to change.

### Pashto gap to watch

RTL. Pashto is written right-to-left. `dir="rtl"` must be set on `<html>`, and any hard-coded left/right in CSS, padding, or icon direction must flip. This is the part that is usually missed and it affects the entire storefront.

---

## 4. Phase 3 — Afghani (Solar Hijri) calendar

**Nothing exists yet.** No Jalali, Shamsi, Hijri, or Persian date code anywhere in `app/`, `packages/`, `resources/`, or `config/`. This is built from zero.

### The trap you need to know about

`Intl.DateTimeFormat` can convert to the Solar Hijri calendar, but **it gives you Iranian month names**, not Afghan ones. They differ. The first six months are similar, but from month 7 they diverge — Iran says Mehr / Aban / Azar / Dey / Bahman / Esfand, while Afghan usage commonly follows the Arabic-derived names (Aqrab, Qaws, Jadda, and so on).

So a locale change alone will **not** give you the Afghan names you asked for. It needs a custom 12-month name table plus the week-day names, and it must be applied in three places: PHP (server-rendered dates, invoices, order records), JavaScript (date pickers, tables, filters), and Blade templates.

Also required: the **Persian** numerals you want (`۰۱۲۳۴۵۶۷۸۹`) vs. Latin (`0123456789`) — decide this once, globally, because invoices must match what customers read.

### Storage decision, decide first

- Store dates as Gregorian/timestamps in the database, convert only for display. **Recommended** — keeps sorting, filtering and reporting correct.
- Never store pre-formatted Persian date strings.

### Where dates are used (scope estimate)

Order list and detail, invoices, booking dates, delivery scheduling, admin reports, the date picker component, and any input[type=date].

---

## 5. Phase 4 — Currency: AFN primary, USD secondary

**Decision taken: manual conversion.** No live exchange-rate API. The rate is entered by hand.

Current state: a full multi-currency system already exists — `currencies` table, `CurrencySeeder`, `CountrySeeder`, and an exchange-rate layer. It is currently seeded for the Indian market (INR, with Razorpay as the payment gateway).

**Low-effort approach** (no rewrite):

1. Add AFN to `CurrencySeeder` with symbol `؋`, and keep USD.
2. Set AFN as the base/default currency and the shop's display currency.
3. **Disable** the other currencies rather than deleting them — deleting rows risks breaking historical orders that reference them.
4. Hide non-enabled currencies in the admin currency settings screen.
5. Manual rate: one editable AFN→USD rate, stored in settings, with the date it was last set. Price conversion is then a pure function of that stored number, so historical orders keep the rate that applied when they were placed.

**Why manual is the right call here:** no external dependency, no API key, no rate-limit failures, and the rate is auditable. The one risk is that someone forgets to update it, so the settings screen should show the rate and the date it was last changed.

**Still undecided:** whether the payment gateway stays Razorpay or moves to an Afghan one. This is the largest remaining unknown in this phase.

---

## 6. Not a bug — uploaded images disappear

Reported as a 404 for `/storage/services/….png`.

**Cause:** the Render service is on the **free plan with no persistent disk**. Every deploy wipes the container's filesystem, including `storage/app/public`. Any image uploaded through the admin panel is gone after the next deploy. `storage/app/public` is also gitignored, so images are never in the repository.

This cannot be patched in code. It needs one of:

- **A Render persistent disk** (paid plan), or
- **External object storage** (S3-compatible) — the only option that works on the free plan.

Until then, expect to re-upload images after each deploy. 22 images (877 KB) currently exist only on your local machine.

---

## 7. Final phase — legal and security (do at the end)

- **No `LICENSE` file exists**, but `composer.json` declares `license: MIT`. MIT requires the licence text and copyright notice to be distributed with the code. This must be added before the project goes to a client.
- Attribution to the original author is required under MIT. Keep the upstream credit.
- Admin password is weak and was exposed during debugging. Rotate it.
- The Render API key was exposed in this session. Revoke and reissue.
- `APP_KEY` was readable during debugging. Rotate if the instance is shared.
- Invalidated sessions should be cleared after the password change.

---

## 8. Suggested order and effort

| # | Phase | Effort | Risk |
|---|---|---|---|
| 1 | Reverse proxy fix | Done | — |
| 2 | Single shop | **None required** | — |
| 3 | Languages + Dari default | Medium (owned by you) | RTL for Pashto |
| 4 | Afghani calendar | **Large** — built from zero | Date correctness |
| 5 | Currency AFN + USD | Small–medium | Payment gateway fit |
| 6 | Images persist | Small config, but **paid plan** | Deployment |
| 7 | License + credentials | Small | None |

**Fastest path to a client-ready single shop:** the storefront is already working, already single-shop, and the only hard blocker left is the calendar.

---

## 9. Open questions I need answered

1. Currency: is USD live-converted, or a manually maintained rate?
2. Is payment still Razorpay, or moving to an Afghan gateway?
3. Afghan calendar numerals — Persian (`۱۲۳`) or Latin (`123`)?
4. Admin panel in Dari too, or storefront only?
5. Render paid plan for a persistent disk, or object storage?
6. Should I wait for the translation work to finish before touching shared files?
