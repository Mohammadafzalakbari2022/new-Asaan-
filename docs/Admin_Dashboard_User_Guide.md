# Asaan Store — Admin Dashboard User Guide

**Version:** 1.0
**Applies to:** the Asaan Store admin dashboard (deployed and local installations)

---

## Table of Contents

1. [Getting Started — Signing In](#1-getting-started--signing-in)
2. [The Dashboard Home](#2-the-dashboard-home)
3. [Catalog](#3-catalog)
4. [Sales](#4-sales)
5. [Customers](#5-customers)
6. [Marketing](#6-marketing)
7. [Content](#7-content)
8. [Blog](#8-blog)
9. [Reports](#9-reports)
10. [Appearance](#10-appearance)
11. [Settings](#11-settings)
12. [System](#12-system)
13. [Profile, Password & Notifications (top-right menu)](#13-profile-password--notifications-top-right-menu)

---

## 1. Getting Started — Signing In

- Open your browser and go to the admin login page:
  - **`https://YOUR-STORE-URL/admin/login`** (replace with your store's real address, e.g. `https://asaan-store.onrender.com/admin/login`)
- Enter the **email address** and **password** given to you by your store owner.
- Click **Sign in**.
- You arrive at the **Dashboard**, which is the first item in the left sidebar.

> **Note:** Your access is tied to your account. Never share your password. If you forget it, contact the store owner to reset it.

**The sidebar** (left edge of the screen) contains these main sections, in order:

| # | Section | What it controls |
|---|---------|------------------|
| 1 | Dashboard | Store health at a glance |
| 2 | Catalog | Products, categories, attributes, brands, reviews |
| 3 | Sales | Orders, invoices, shipments, deliveries, money |
| 4 | Customers | Customer records, addresses, groups |
| 5 | Marketing | Coupons, promotions, newsletter subscribers |
| 6 | Content | Pages, menus, media, reusable sections |
| 7 | Blog | Blog posts and categories |
| 8 | Reports | Sales, product, customer and delivery reporting |
| 9 | Appearance | Themes and store design |
| 10 | Settings | Store, payment, shipping, tax, email, AI |
| 11 | System | Maintenance, backups, cache, extensions, permissions |

> Parent items with a sub-option (e.g. **Catalog**) expand when you click them, revealing their sub-items underneath.

---

## 2. The Dashboard Home

The first screen after login. It is a live summary of your store — nothing here is editable, it is read-only.

**What you see:**

- **Statistic cards (top row):**
  - **Total Revenue** — all money from orders in the selected period, with the % change vs the previous period (green up arrow = growth, red down arrow = decline).
  - **Paid Revenue** — only the money from orders actually paid.
  - **Orders** — number of orders placed.
  - **Customers** — number of registered customers.
  - **Products** — number of products added.
- **Sales chart** — a 7-day line chart of daily sales volume.
- **Recent Orders** — the latest orders with customer and product summary.
- **Top Selling Products** — best performing products by revenue.
- **Low / Out-of-Stock Products** — a warning list so you reorder stock before you sell out.
- **Recent Activity** — a live feed of recent system events (new orders, new customers, etc.).

**How to use it:**

1. Check the stat cards each morning — if "Orders" is growing but "Paid Revenue" is flat, payments may be failing.
2. Scroll to **Low/Out-of-stock** every couple of days and reorder the flagged items.
3. Use **Recent Orders** to spot and act on large or urgent orders immediately.

**Examples:**

- **Example 1 (daily routine):** You open the dashboard and see "Low/Out-of-stock: 4 products". You click into Catalog → Products, set the stock for the flagged items, and the dashboard stops warning you.
- **Example 2 (spotting a problem):** Revenue shows a **down 12%** arrow this week. You open Reports → Sales Report to find the drop came from 15 cancelled orders, then investigate why (e.g. payment method rejected the card).

---

## 3. Catalog

Everything about what you sell. Five sub-options: **Products, Categories, Attributes, Brands, Reviews.**

### 3.1 Products
*The list of everything you sell, and the main catalog work area.*

**The list page** (`/admin/catalog/products`):

- Shows each product as a row: thumbnail, name, SKU, price, stock, status.
- **Search/filter box** at the top finds a product by name or SKU.
- **Bulk actions:** tick the checkboxes on several rows, then choose **bulk delete** (removes them all) or **bulk status** (turns them all active or draft at once).
- Click a product name to open it; use the **trash icon** to delete a single product.

**Create / Edit Product — the form tabs:**

| Tab | What it does |
|-----|--------------|
| **General** | Product **type** (physical vs digital/downloadable), **name**, **SKU**, **description**, **brand**, **categories**, and the **AI "Generate description"** button (writes a description for you). Also the **Price Comparison** tool — proposes 3 market-based selling prices with the profit & margin for each; pick the one you like. |
| **Images** | Upload product photos, drag to reorder, mark the **main image** (shown first in the store), delete unwanted ones. |
| **Attributes** | Attach your attributes (e.g. Color: Red/Blue, Size: M/L) to this product. Used for variants. |
| **Inventory** | **Pricing:** regular price + your cost (cost feeds profit/margin). **Special Pricing:** sale price with start/end dates. **Inventory:** stock quantity so sold-out items disappear from the storefront. |
| **Downloads** | *(only for digital products)* Attach the file(s) the customer receives after purchase. |
| **SEO** | **Meta title** and **meta description** (text shown in Google), plus the **Publish** block: **Active** (visible in store) or **Draft** (hidden). |

**Examples:**

- **Example 1 (adding a new product):** Click **Create Product**. Set type = Physical, enter name "Noise-Cancelling Headphones" and SKU "HP-NC-100", assign Brand "Sony" and Category "Electronics → Audio". Add a price of $99.99 and stock of 50. Add 3 photos, set the best one as main. Click **Save** — the product is now live in the store.
- **Example 2 (running a sale):** Open the product, go to the **Inventory** tab, set Special Pricing to $79.99 with the date range of the sale week. Save. The storefront shows the discount while the sale runs and automatically restores the regular price when it ends.

### 3.2 Categories
*How products are grouped for browsing, e.g. "Electronics → Audio → Headphones".*

- Categories can be **nested** — create a parent ("Clothing") and children under it ("Men", "Women").
- Each category has a **name**, a URL *slug*, and an **active** status.
- **Bulk status / bulk delete** work on several categories at once.
- Assign products to categories from the product form (tick boxes).

**Examples:**

- **Example 1:** You sell furniture. Create parent **"Home"** → children **"Sofas"**, **"Tables"**, **"Lighting"**. Then assign your new "Oak Coffee Table" product to **Home → Tables**.
- **Example 2:** A category "Old Stock" has outlived its purpose. Open Categories, select it plus another unused one, and **bulk delete** both — the products inside are *not* deleted, they are simply uncategorized.

### 3.3 Attributes
*Reusable option lists that drive product variants: colors, sizes, materials.*

- Create an **attribute** (e.g. "Color") and add its **values/options** (Red, Blue, Green).
- These appear on the product's **Attributes** tab, so one product can be *Color: Red, Size: M*.
- **Bulk delete** clears old attributes in one action.

**Examples:**

- **Example 1:** Create attribute **"Size"** with options **S, M, L, XL**. In a T-shirt product's Attributes tab, set "Size: M" and set a different price/stock per size.
- **Example 2:** Create attribute **"Storage"** with **64GB, 128GB, 256GB**, so a phone product is sold in three capacity variants.

### 3.4 Brands
*The makers of your products, shown as a label and a storefront filter.*

- Add a **brand** (name + optional logo/image).
- Toggle brands **active/inactive** (inactive brands can be hidden from the storefront).
- **Bulk status / bulk delete** are available.

**Examples:**

- **Example 1:** Add brand **"Nike"** with its logo. Then set Brand = Nike on all running-shoe products so shoppers can filter "Nike" in the store.
- **Example 2:** A brand you no longer carry ("Toshiba TVs") — set it **inactive** so it disappears from the customer filter but your product history is preserved.

### 3.5 Reviews
*Customer ratings and comments on products — your moderation queue.*

- A read-only list of every review (product, customer, star rating, text).
- Open one to see the full detail.
- **Change status** (approve / reject) — unapproved reviews stay hidden from the store.
- **Reply** publicly to a review.
- **Delete** spam or offensive reviews; **bulk action** applies to many at once.

**Examples:**

- **Example 1 (moderation):** A customer posts a 1-star review saying "charges more fees than shown" even though the store has no fees. You open the review, **reply** with the correct pricing breakdown so other shoppers see the answer, and leave it approved.
- **Example 2 (spam):** A review contains an unrelated advertising link. You **delete** it before it reaches the storefront.

---

## 4. Sales

Everything after the customer buys: **Orders, Invoices, Shipments, Credit Memos, Transactions, Delivery Board, Deliveries, Delivery Staff.**

### 4.1 Orders
*Order is placed on the website → managed here.*

**The list page** (`/admin/sales/orders`):

- Search & filter orders, see status (e.g. pending/processing/completed/cancelled).
- **Bulk status** — change several orders at once.
- **Export CSV** — download the order list as a spreadsheet.
- **Statistics** endpoint feeds dashboard widgets.

**Open an order — what you can do:**

- **Update order status** (e.g. pending → processing → completed).
- **Update payment status** (unpaid → paid, etc.).
- **Cancel** the order.
- **Print invoice** and **print packing-slip** (shipping document).
- **Create an order manually** (useful for phone orders) via the Create button.

**Examples:**

- **Example 1 (fulfilment):** A new order arrives. Open it, update payment status to **Paid**, print the **packing-slip**, hand it to the warehouse, then move the order status to **Completed**.
- **Example 2 (phone order):** A customer calls. Click **Create**, add the customer, the items and totals, then save the order as if it had come from the website.

### 4.2 Invoices
*The official bill for an order, printable and emailable.*

- The invoice list shows all invoices with their status (unpaid/sent/paid/cancelled).
- **Create from order** — one click builds an invoice from an existing order.
- **Mark as sent**, **mark as paid**, **cancel**, or **delete**.
- **Download PDF** for printing / emailing.
- **Send email** to the customer directly from the page.

**Examples:**

- **Example 1:** An order is paid. Open Invoices → **Create from order**, review totals, click **Mark as paid**, then **Download PDF** for your records.
- **Example 2 (B2B):** A corporate customer needs a bill before paying. Create the invoice, click **Send email**, and the customer receives the PDF.

### 4.3 Shipments
*Packing and sending products to the customer.*

- **Create a shipment** from an order (items, carrier, tracking number).
- **Update tracking** — enter/enhance the tracking information.
- **Mark as shipped**, update **status**, or **cancel** a shipment.
- **Print shipping label**.
- **Integrations:** send the shipment to **Shiprocket** (third-party courier) or to the **in-house delivery** system, and sync tracking from either.

**Examples:**

- **Example 1 (courier):** You pack the headphones order. Create the shipment, call up the courier, enter the tracking number, **Mark as shipped**. The customer can follow the parcel.
- **Example 2 (own fleet):** For local deliveries, use **Deliveries → Create** instead (below) so your own driver handles it and the delivery board tracks it live.

### 4.4 Credit Memos
*The document behind a refund, when you owe money back to the customer.*

- **Create a credit memo** from a specific order.
- View/edit and **process refund** (returns money via the same payment method).
- **Send email** / **download PDF** of the memo.
- **Cancel** a memo, or **bulk delete** old ones.

**Examples:**

- **Example 1 (partial refund):** Customer kept one of two items but returns the other. Open the order → **Create credit memo**, put the returned item's value in, **Process refund**. Money goes back to the customer automatically.
- **Example 2 (price difference):** A sale price was applied after purchase — you create a small credit memo for the difference and email it as proof.

### 4.5 Transactions
*The record of every money movement (payments and refunds).*

- List of all transactions (payment gateway, amount, status, order).
- Open one to see the full detail.
- **Refund** a transaction (money back to the customer).
- **Retry** a failed transaction.
- **Cancel** a pending transaction.
- **Export CSV** of all transactions.

**Examples:**

- **Example 1 (retrying a failed payment):** A card transaction shows **failed**. You open it, click **Retry**, payment goes through, order continues.
- **Example 2 (audit):** The customer says they paid twice. You check Transactions, see both attempts, refund the duplicate and note it in the order.

### 4.6 Delivery Board
*Live map of your delivery drivers while they are out delivering.*

- Opens a **map** showing:
  - **Blue pins** — the drop-off points of active deliveries.
  - **Green/gray dots** — your drivers; **green** means the position updated in the last 20 minutes (live), **gray** means the last position is stale.
- A sidebar lists the active runs.
- The map **refreshes automatically every 20 seconds**; use **Zoom to all** / refresh to re-frame.

**Examples:**

- **Example 1 (daily monitoring):** Open the board at 2pm. Three green dots are moving toward three blue pins — all deliveries progressing. One dot is gray; you call that driver to confirm they are reachable.
- **Example 2 (re-routing):** A blue pin sits far from all drivers. You open that delivery, **reassign** it to the closest driver, and the new driver's dot heads toward it.

### 4.7 Deliveries
*The in-house delivery jobs, one per shipment for your own fleet.*

- **List** of all deliveries with driver, status, dates.
- **Create** a delivery — assign a shipment to a driver and set the destination (coordinates are resolved from the address so it appears on the board).
- **Show** — full detail page.
- **Update** — reassign to another driver, reschedule.
- **Cancel** a delivery.

Statuses: `pending → assigned → out_for_delivery → arriving → delivered / undelivered / cancelled`.

**Examples:**

- **Example 1 (start a delivery):** An online order is ready. Create a delivery, choose driver "Ali" and status **assigned**. It appears on the Delivery Board and in Ali's driver app.
- **Example 2 (reassign):** Your driver is sick. Open the delivery, change the driver to "Sara", save — Sara's live dot now heads to the drop-off.

### 4.8 Delivery Staff
*Your drivers — their user accounts.*

- **Add** a delivery staff member (name, email, password) — this creates their driver login at `/delivery/login`.
- **Edit** their details.
- **Reset password** (when a driver forgets their login).
- **Delete** a driver account.

**Examples:**

- **Example 1 (onboarding):** New driver joins. Click **Add delivery staff**, enter their name/email/password. They log into the driver portal and see their first assigned delivery.
- **Example 2 (password reset):** A driver can't remember their password. Open their row, click **Reset password** and give them the new temporary one.

---

## 5. Customers

People who have accounts with your store. Pages: **Customers, Customer Groups, Addresses.** *(Sub-sections are accessed under the Customers tab.)*

### 5.1 Customers (list)
- Searchable list of all registered customers.
- Open a customer to see their **profile** (contact details, order history).
- **Create** a customer manually (for phone sales), **edit** their details.
- **Bulk update status** — block or activate several at once.
- **Bulk delete** and **Export CSV** (download your customer list).

### 5.2 Customer Addresses
- Accessible from within a customer's profile.
- Add **shipping/billing addresses** for them.
- **Set default shipping** and **set default billing** address (used automatically at checkout).
- Edit or delete addresses.

### 5.3 Customer Groups
- Create **groups** (e.g. "VIP", "Wholesale") to tag customers.
- **Reorder** groups by dragging.
- **Auto-assignment rules** — set rules so customers are moved into a group automatically (e.g. "spent more than $500").
- **Bulk delete / bulk update status** for groups.

**Examples:**

- **Example 1 (VIP group):** Create group **"VIP"** with auto-assignment "total orders above 10". Returning customers are placed in VIP automatically, then you send them a VIP-only coupon (see Marketing).
- **Example 2 (adding a manual address):** A customer orders by phone. In their profile, add their delivery address and click **Set as default shipping** so future orders use it.

---

## 6. Marketing

How you bring customers back and attract new ones. Three sub-options: **Coupons, Promotions, Newsletters.**

### 6.1 Coupons
*Discount codes customers type at checkout (e.g. "WELCOME10").*

- **Create a coupon:** code, type (**percentage** or **fixed amount**), value, **usage limit (total)** and **usage limit per customer**, valid **from/to dates**, and which **customer groups** it applies to.
- **Analytics** per coupon — how often it was used and what it earned.
- Bulk **activate / deactivate / delete** coupons.

**Examples:**

- **Example 1 (welcome discount):** Create code **"WELCOME10"**, 10% off, limit 1 use per customer, valid all year. Add it to an email to new customers.
- **Example 2 (flash sale):** Create code **"FRIDAY20"**, ₹200 off, valid only one Friday, active only that day. Deactivate it with one click after the sale so it can't be used again.

### 6.2 Promotions
*Time-based storewide offers (e.g. "Back to School Sale — 15% off all bags").*

- View active & scheduled promotions in a list.
- **Create/edit:** name, discount, schedule (start/end), target audience/products.
- **Bulk activate/deactivate/delete**, and **analytics** for results.

**Examples:**

- **Example 1 (seasonal):** Schedule "Winter Sale — 20% off all jackets" from Dec 1 to Dec 31. It starts and ends automatically.
- **Example 2 (quick launch):** The owner announces a one-day sale; you **create** the promotion now and **deactivate** it tonight when it ends — clean rollback, no expiry editing.

### 6.3 Newsletters
*The people who subscribed to receive your marketing emails.*

- List of subscribers.
- **Export** — download subscriber emails as a CSV file to use in your email tool.
- **Unsubscribe** a single customer, or **bulk unsubscribe** many (e.g. people who opted out).

**Examples:**

- **Example 1 (new campaign):** Export the subscriber list as CSV and upload it to your email platform for a newsletter blast.
- **Example 2 (cleanup):** Your list has inactive accounts. Your policy says only active customers receive marketing — select them and **bulk unsubscribe** to keep the list trustworthy.

---

## 7. Content

The pages and assets of your storefront. Sub-options: **Pages, Storefront Menus, Media Library, Reusable Sections.**

### 7.1 Pages
*Static pages of your site: Home, About Us, Contact, Shipping Policy, etc.*

- **Create/edit** pages — title, content, slug (URL), layout settings.
- **Check slug** — verifies the URL is free & unique before publishing.
- **Duplicate** a page (clone a template page quickly).
- **Preview** — see the page as a customer.
- **Bulk action** (delete/status on many pages).

**Examples:**

- **Example 1 (legal page):** Build "Refund Policy" with the text, save with slug `refund-policy`, and link it in your store footer.
- **Example 2 (quick template):** You like your "Contact" page layout — click **Duplicate**, rename it to "Wholesale Enquiries", edit the contact details, publish.

### 7.2 Storefront Menus
*The navigation menus customers click (top bar, footer).*

- **Add items** — links to pages, categories, or any URL.
- **Reorder** items by dragging into the order you want.
- **Toggle** item visibility on/off without deleting.

**Examples:**

- **Example 1 (top menu):** Add "Shop", "Categories", "About Us", "Contact" to the main menu and drag "Shop" first.
- **Example 2 (hiding a link):** The "Events" page is under maintenance — toggle the menu item off today, back on next week.

### 7.3 Media Library
*All images/files used across the store, in one place.*

- **Upload** images (drag & drop or browse), edit them.
- **Folders** — organise files (e.g. "Product photos", "Logo", "Blog").
- **Picker** — insert images into pages/products directly from the library.
- **Bulk action** and **delete** for cleanup.

**Examples:**

- **Example 1 (product photo):** Upload today's new product photos into folder "Product photos", then pick them when creating the product.
- **Example 2 (brand logo):** Store your logo in the library once, then reuse it in pages, emails and the store design without re-uploading.

### 7.4 Reusable Sections
*Design blocks saved once and reused across pages (header strip, banner, newsletter signup, etc.).*

- Create a reusable layout region once (e.g. "Christmas banner").
- Reuse it on multiple pages so you edit it in **one place** and it updates everywhere.

**Examples:**

- **Example 1:** Build a "Free shipping banner" section and drop it onto the Home, About and Contact pages. Change the text in the section once — all three pages update.
- **Example 2:** A seasonal "Eid Sale" strip is placed on every page; when the sale ends you update the one section instead of every page.

---

## 8. Blog

Your blog — for articles, news and SEO content. Sub-options: **Posts, Categories.**

### 8.1 Posts
- **Create/edit** posts: title, content (rich editor), featured image from the media library, **category**, and status (published/draft).
- **Check slug** for uniqueness before publish.
- **Bulk action** to publish/unpublish/delete several posts.

**Examples:**

- **Example 1 (launch article):** Write "10 Home Decor Ideas for 2026", add a featured image, assign it to the "Interior Design" category, publish. It appears on your blog home.
- **Example 2 (draft workflow):** Write next week's article, save as **Draft** this week; on publish day open it and set to **Published**. Google-friendly slugs are confirmed with **Check slug**.

### 8.2 Categories
- Create **blog categories** (e.g. "News", "Guides", "Behind the Scenes").
- Edit/delete them; deleting a category does not delete its posts.

**Examples:**

- **Example 1:** Create "Guides" and "Product News" before writing your first articles, so posts have somewhere to go.
- **Example 2:** Merge is not available — if you delete "Old News", its posts become uncategorised and you can reassign them to "News".

---

## 9. Reports

Read-only business intelligence with CSV export. Sub-options: **Sales Reports, Product Reports, Customer Reports, Delivery Report.** *(The Delivery Report is the main report for your delivery operation.)*

### 9.1 Sales Reports
- **Filters:** date range and order status.
- **Summary:** total revenue, order counts, and a breakdown by status.
- **Top orders** list for the period.
- **Export CSV** — download the filtered report.

**Examples:**

- **Example 1 (monthly close):** Set the range to this month and **Export CSV** for your accountant.
- **Example 2 (find the bleeding):** Filter last week by status "cancelled" — see exactly how much revenue was lost to cancellations.

### 9.2 Product Reports
- **Filters:** date range, category, low-stock threshold.
- **Charts:** bar/doughnut/line of performance.
- **Top products** by revenue; **low-stock** highlight.
- **Export CSV.**

**Examples:**

- **Example 1 (restock planning):** Set low-stock threshold to 10; the report lists every product with less than 10 units — that's your purchasing list.
- **Example 2 (hero product):** Find your #1 product by revenue; order its top-selling variant in bulk / feature it on the homepage.

### 9.3 Customer Reports
- **Filters:** date range.
- **Charts** of customer activity; **top customers** list.
- **Export CSV.**

**Examples:**

- **Example 1 (loyalty):** See your top 10 customers; reward them with a personal coupon (Marketing → Coupons).
- **Example 2 (retention):** Export customers active 6 months ago but not in the last month, and email them a "we miss you" offer.

### 9.4 Delivery Report
*The report for your own fleet.*

- **Filters:** date range, driver, status.
- **Stat cards:** total deliveries, delivered, failed, undelivered, and the **delivery rate** (%).
- **Per-driver table:** how many jobs each driver handled and their success rate.
- **Daily chart:** number of deliveries created vs delivered per day.
- **Deliveries table:** the detail rows.
- **Export CSV** — the exact filtered rows as a spreadsheet.

**Examples:**

- **Example 1 (driver performance):** This month Sara handled 120 runs with a 98% rate; Ali had 90 with 15 failed. You schedule training for Ali.
- **Example 2 (process check):** The daily chart shows deliveries created spikes on Saturdays but delivered stays flat — your fleet is overloaded on weekends; you shift cut-off times earlier.

---

## 10. Appearance

How your store looks. Sub-options: **Browse Themes, Theme, Theme Setting.**

### 10.1 Browse Themes
*A gallery of available store designs (the template zone).*

- Browse themes, see previews, and **download / install** the one you want.
- Installed themes move into your **Theme** list.

**Example:** Open Browse Themes, preview "Cartxis Default", install it — it becomes immediately available in your Theme list to activate.

### 10.2 Theme
*The themes installed on your store.*

- List of installed themes with their status.
- **Activate** any theme (the store switches to it).
- **Upload** a theme file you received.
- **Settings** per theme; **import data** and **import layout** to load demo content.
- **Screenshot** — upload/refresh the theme's preview picture.
- **Delete** a theme you no longer use.

**Examples:**

- **Example 1 (switch design):** Install the new theme, click **Activate**. Your storefront changes instantly.
- **Example 2 (demo load):** On a fresh install, open the theme → **Import data** to load example products/pages so you see how it looks, then replace them with real content.

### 10.3 Theme Setting
*Fine-tuning the look of your currently active theme.*

- Change **logo**, **store name**, contact details shown in the header/footer.
- Adjust **colors**, **typography**, and on/off layout options (depending on theme).
- Save and check the storefront.

**Examples:**

- **Example 1 (branding):** Update the logo and accent color to match your brand, save — the whole store restyles.
- **Example 2 (contact info):** Change the phone number and email shown in the footer from the Theme Setting page once — it updates on every page.

---

## 11. Settings

The plumbing of your store. Sub-options: **General Settings, Store Configuration, Locales, Payment Methods, Shipping Methods, Tax Rules, Email Settings, AI Settings.**

### 11.1 General Settings
- Store basics: **store name**, **contact email**, **phone**, **address**, **currency/timezone**, etc.
- Saved centrally; shown across receipts, emails and the storefront footer.

**Example:** You move to a new warehouse address. Open General Settings, update the address and phone, save — new invoices and the footer show the new details.

### 11.2 Store Configuration
- Operational defaults for how the store behaves (order numbering, stock settings, store-facing values).

**Example:** Enable "auto-generate SKU if empty" so new products created without a SKU still get one.

### 11.3 Locales
- **Languages:** add languages for the storefront (e.g. English, Pashto, Dari, Urdu). Every fresh install already ships with **English** (default) and **Pashto** (پښتو) enabled.
- **Currencies:** add currencies, set the default, enable/disable others shown in the store.

**Examples:**

- **Example 1 (multi-language):** Pashto (پښتو) is pre-installed as a right-to-left language — enable/keep it active so customers can switch the store to Pashto. Add other languages (e.g. Dari, Urdu) by entering a code (e.g. `fa`, `ur`), a display name, and the native name.
- **Example 2 (multi-currency):** Add USD as a currency and keep AFN default, so prices display in both for international customers.

### 11.4 Payment Methods
- Turn payment methods **on/off** (Cash on Delivery, Stripe, Razorpay, PhonePe, etc.).
- **Configure** each (API keys, 3-D Secure, supported cards).
- **Set default** method, and **sort** their order at checkout.

**Examples:**

- **Example 1 (enable COD):** Toggle **Cash on Delivery** on so customers paying by cash can order.
- **Example 2 (gateway keys):** Stripe shows "not configured" — open it and enter the API keys from your Stripe account, save, then toggle the method on.

### 11.5 Shipping Methods
- Methods with **rates** per destination/weight (flat rate, free shipping, etc.).
- Set a **default**, toggle method on/off, **add/update/delete rates**, calculate test costs.
- Toggle shipping **extensions**.
- Also contains the **Delivery** settings tab (credentials / connection test for your in-house delivery service) and **Shiprocket** settings (courier account, test connection).

**Examples:**

- **Example 1 (flat rate):** Add rate "Flat $5" for all orders under $50, and "Free" for orders over $50 — thresholds that encourage bigger carts.
- **Example 2 (courier keys):** You subscribed to Shiprocket. Paste the API credentials in the Shiprocket tab and click **Test connection** — a green success means shipments can go to the courier.

### 11.6 Tax Rules
- **Tax zones** (which regions), **tax rates** (e.g. 10% VAT), **tax classes** (Standard, Reduced) and the **rules** linking them (which class at which rate in which zone).
- Bulk status / bulk delete for rules.

**Examples:**

- **Example 1 (VAT):** Create zone "EU", rate "VAT 20%", class "Standard", then a rule "Standard products in EU → 20%". New orders to the EU show it automatically.
- **Example 2 (exempt):** Create an "Export / Exempt" rate of 0% so wholesale export orders carry no tax.

### 11.7 Email Settings
- **SMTP configuration** (mail server, username, password, encryption).
- **Test connection** and **send test email**.
- **Email templates** (order confirmation, shipment, credit memo, etc.): preview, edit, toggle on/off, send a test of each.

**Examples:**

- **Example 1 (connect mailbox):** Enter your SMTP details, click **Test connection**. Success = every automatic email (order confirmations etc.) now really sends.
- **Example 2 (edit template):** Open the "Order Confirmation" template, add your phone number to the footer, save and **send test** to verify it looks right.

### 11.8 AI Settings
- API keys and options for the AI features (AI product descriptions, AI price comparison).

**Example:** Enter your AI provider API key here once, then the AI "Generate description" button in Catalog → Products works on every product.

---

## 12. System

Maintenance tools — careful with these. Sub-options: **Cache Management, Menu Configuration, Extensions, Permissions, Maintenance, Data Migration, API Sync, Backups, Activity Logs.**

### 12.1 Cache Management
- See **stats** of the cache store in use.
- **Clear** (empty) and **rebuild** (empty then regenerate) cache.

**Example:** After a big import the storefront looks stale — open Cache Management and click **Rebuild**. The storefront refreshes.

### 12.2 Menu Configuration
- Manage the **menu items themselves** (the sidebar you are clicking now).
- Add, edit, reorder, toggle menus for admin / storefront.
- Careful: used mostly by developers/owners.

**Example:** A new Sales sub-page is added to the app; it is placed in the sidebar here under the Sales menu.

### 12.3 Extensions
- The installed modules/add-ons of the store.
- **Sync** the extension registry, **install**, **activate/deactivate**, **uninstall** extensions.

**Examples:**

- **Example 1:** The owner installs the "Razorpay" extension; you activate it here and it then appears in Payment Methods.
- **Example 2:** A trial extension ends — you deactivate it (keeps its data) rather than uninstall (removes data).

### 12.4 Permissions
- Roles and their allowed actions (who can see/do what in the admin).
- Create/edit/delete permission rules.

**Example:** Create a "Support" role that can see Orders and Customers but not System; assign it to your support staff account.

### 12.5 Maintenance
- **Maintenance mode:** put the store behind a "be back soon" page.
- **Enable / disable**, **schedule** future maintenance, **settings** (bypass secret so you can still view the site), **history** of past maintenance.

**Examples:**

- **Example 1 (planned upgrade):** Enable maintenance at 2am, do the upgrade, disable it when done — customers saw a friendly message, not errors.
- **Example 2 (emergency):** The site behaves oddly — enable maintenance immediately, investigate, then disable.

### 12.6 Data Migration
- Move data between systems: **test connection** to a source, run **migrate**, view **status**.

**Example:** Importing your product list from an old system: fill in the source connection, **Test connection**, then **Run migration** and check Status for the count of items moved.

### 12.7 API Sync
- Sync between your site and external services (products/orders via API).
- **Status**, **refresh**, and **settings** for the syncs.

**Example:** Products changed on an external channel aren't showing: open API Sync, check Status, click **Refresh** to pull the latest.

### 12.8 Backups
- **Create** a backup of your database/site, **download** it to your computer, **delete** old ones.

**Examples:**

- **Example 1 (before a big change):** Before running a large promotion, **Create** a backup and **download** it — if something breaks, you can restore.
- **Example 2 (routine):** Weekly routine — create a backup every Sunday and download it to cloud storage.

### 12.9 Activity Logs
- A chronological list of admin actions (who did what, when) with filtering/search.

**Example (audit):** A coupon was deleted by mistake — open Activity Logs, find who removed it and when, then recreate it.

---

## 13. Profile, Password & Notifications (top-right menu)

The icons in the very top-right corner of the dashboard.

- **Notifications (bell):** the system's to-do list (new orders, low stock, system messages). Open to see them; **mark all as read** or read them individually.
- **Profile:** update your **name/email/photo** shown on your account.
- **Password:** change your own password at any time (no need to contact anyone).
- **Users:** (admin accounts) create and manage other admin logins, incl. resetting their passwords.
- **Logout:** sign out (always do this on shared computers).

**Example 1 (daily):** The bell shows "3 new orders". You click through to the Sales → Orders list and start fulfilling them.
**Example 2 (security):** You suspect someone used your password — open **Password** and change it immediately.

---

## Handy Checklist — Recommended Everyday Workflow

1. **Morning:** log in → Dashboard: check revenue trend + low stock warnings.
2. **Catalog:** restock low items; add new products.
3. **Sales:** fulfil new orders (payment status → packing slip → shipment or delivery → completed).
4. **Delivery:** assign/check drivers on the Delivery Board; review the Delivery Report weekly.
5. **Marketing:** schedule coupons/promotions; export newsletters for campaigns.
6. **Occasional:** back up (System → Backups) before big changes; check Activity Logs after something odd.

---

*End of guide. If anything on screen differs from this guide, the store may have received an upgrade — ask your store owner for the updated version.*