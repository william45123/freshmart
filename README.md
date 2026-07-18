# FreshMart 🥬

A freshness-first online grocery for fresh produce in Malaysia.

Built with PHP 8 + MariaDB + vanilla CSS/JS. Final Year Project, MMU.

---

## 🚀 How to Run the Website

You need **XAMPP** installed (PHP + MariaDB included). Get it from https://www.apachefriends.org/.

### 1. Place the code

Extract the project so the final path is:

```
C:\xampp\htdocs\freshmart\
```

### 2. Start XAMPP

Open **XAMPP Control Panel** → click **Start** next to:
- ✅ **Apache**
- ✅ **MySQL**

### 3. Create the database

1. Open http://localhost/phpmyadmin
2. Click **New** (left sidebar) → create database named `freshmart`
3. Click into `freshmart` → click **Import** tab
4. Choose `database\schema.sql` → click **Go**
5. Click **Import** again → choose `database\seed.sql` → click **Go**

### 4. Open the site

Go to:

```
http://localhost/freshmart/public
```

Done! 🎉

---

## 🔑 Test Accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@freshmart.my` | `Admin@123` |
| Retailer | `retailer@cameron.my` | `Retailer@123` |
| Customer | `cherry@example.my` | `Customer@123` |

Login at: http://localhost/freshmart/public/auth/login.php

After login the site auto-redirects:
- **Admin** → admin dashboard
- **Retailer** → retailer dashboard
- **Customer** → home page

---

## 🌟 Core Features

| Feature | What it does |
|---|---|
| **Freshness Indicator** | Power-law decay formula — every product shows live freshness % (Very Fresh → Last Chance) |
| **FEFO Inventory** | First-Expired-First-Out batch selection at checkout — minimizes food waste |
| **Auto-Discount** | Items entering Last Chance automatically drop 15% |
| **Wallet & Refunds** | Customers request refunds or cancel orders; money is credited to an in-app wallet and can be spent at checkout |
| **Commission Model** | The platform earns a commission on each sale; retailers see a transparent net payout |
| **Voucher System** | Admin creates promo codes, customers apply at checkout |
| **Review Moderation** | Admin approves reviews, retailers can reply (like Shopee) |

---

## 📁 Project Structure

```
freshmart/
├── public/             ← Web root (what XAMPP serves)
│   ├── index.php       ← Homepage
│   ├── auth/           ← Login, register, logout
│   ├── shop/           ← Customer pages (browse, cart, checkout, etc.)
│   ├── wallet.php      ← Customer wallet (top-up, balance, transactions)
│   ├── retailer/       ← Retailer dashboard
│   ├── admin/          ← Admin panel
│   └── assets/         ← CSS, JS, images
├── includes/           ← Core libraries (config, helpers, freshness, FEFO, wallet)
├── database/           ← SQL schema + seed data
└── cron/               ← Background jobs (auto-expire, auto-discount)
```

---

## 🛠 Common Issues

**"Database connection failed"**
→ Open `includes\config.php` and check `DB_USER='root'` and `DB_PASS=''` (XAMPP default).

**Pages look unstyled / broken layout**
→ Make sure `public\assets\css\main.css` exists.

**"Access denied" when login**
→ The seed data uses bcrypt passwords. If you reseeded and login fails, re-import `database\seed.sql`.

---

Made by Cherry · MMU Computer Science FYP

## 📸 Screenshots

### Customer Storefront

A walkthrough of the customer shopping flow.

**Home** — live store stats (active products, food saved from waste, last-chance items, approved retailers), a farm-fresh hero, and a "Fresh produce, honest freshness" promise.

![Home page](docs/screenshots/01-home.png)

**Shop by Category** — eight fresh categories plus a dedicated Last Chance section, with an automatic 15%-off promo banner.

![Home categories](docs/screenshots/01b-home-categories.png)

**Vouchers & Fresh Picks** — usable promo codes and a "Today's Fresh Picks" carousel, each product showing its freshness score.

![Home fresh picks](docs/screenshots/01c-home-picks.png)

**Customer Reviews** — real reviews from verified buyers displayed on the homepage.

![Home reviews](docs/screenshots/01d-home-reviews.png)

**Freshness Promise** — an explainer of the four freshness levels (Very Fresh / Fresh / Enjoy Soon / Last Chance) and the total food rescued from waste.

![Home freshness promise](docs/screenshots/01e-home-freshness.png)

**Browse Products** — search and filter by category, availability, and freshness. Each card shows the freshness badge, auto-discount, origin, and best-before date.

![Browse all products](docs/screenshots/02-browse.png)

**Product Detail** — a **live freshness meter** and a **7-day freshness forecast** computed from the batch's age using the power-law decay model `(1 - t/T)^n`, where `n` is the category's decay exponent. Batch selection follows FEFO (First-Expired-First-Out).

![Product detail with freshness forecast](docs/screenshots/03-product-detail.png)

**Product Detail (add to cart)** — smart quantity stepper, batch info (received date, best-before, stock), storage tips, and recently-viewed items.

![Product detail add to cart](docs/screenshots/03b-product-detail-cart.png)

**Cart** — order summary, free-shipping progress, and unlockable promo codes.

![Shopping cart](docs/screenshots/04-cart.png)

**Checkout** — shipping address, preferred delivery day, and payment method — including paying directly from the **FreshMart Wallet**. Payment is simulated for the demo.

![Checkout](docs/screenshots/05-checkout.png)

**Checkout (FEFO preview)** — before placing the order, the customer sees exactly which stock batches will fulfil it (earliest expiry first).

![Checkout FEFO allocation preview](docs/screenshots/05b-checkout-fefo.png)

**Order Confirmation** — order number, fulfilled batch + freshness state, shipping details, and a tracking code.

![Order placed confirmation](docs/screenshots/06-order-confirm.png)

**Order Confirmation (tracking)** — total paid, shipping address, and a generated tracking reference.

![Order confirmation tracking](docs/screenshots/06b-order-confirm-tracking.png)

**Notifications** — in-app notifications for order status updates.

![Notifications](docs/screenshots/07-notifications.png)

**Wishlist** — save products for later by tapping the heart on any product.

![Wishlist](docs/screenshots/08-wishlist.png)

**My Wallet** — refunds and cancelled orders are credited here, and the balance can be spent at checkout. Includes a simulated top-up (preset amounts or a custom value).

![My wallet with top-up](docs/screenshots/09-wallet.png)

**Wallet Transactions** — a full ledger of credits and debits: order payments, top-ups, and refunds.

![Wallet transaction history](docs/screenshots/09b-wallet-transactions.png)

**Order Detail & Cancel** — a full status timeline (Placed → Processing → Quality Check → Packed → Out for Delivery → Delivered), with the option to cancel an order before it ships.

![Order detail with cancel](docs/screenshots/11-order-detail-cancel.png)

**Request a Refund** — for a delivered order, customers can request a full or partial refund, pick a reason, and submit it to the seller. Approved refunds are credited to the wallet.

![Order refund request](docs/screenshots/12-order-refund-request.png)

**My Profile** — account details, food rescued from waste, and saved delivery addresses.

![My profile](docs/screenshots/10-profile.png)

**Add Address** — add and manage multiple saved delivery addresses.

![Add new address](docs/screenshots/10b-profile-address.png)

---

### Retailer Panel

Retailers manage their own products, stock batches, orders, refunds, reviews, and performance.

**Dashboard** — total products, active stock batches, low-stock alerts, and a "Batches Expiring Soon" table with live freshness and a forecast of how much may expire unsold.

![Retailer dashboard](docs/screenshots/20-retailer-dashboard.png)

**My Products** — all listings with category, price, stock, batch count, views, and status, plus search and filters.

![Retailer products](docs/screenshots/21-retailer-products.png)

**New Product** — create a product with name, SKU, price, category, and unit.

![Retailer new product](docs/screenshots/22-retailer-new-product.png)

**New Product (freshness settings)** — optional per-product shelf life and decay-exponent override, on top of the category defaults.

![Retailer new product freshness](docs/screenshots/22b-retailer-new-product-freshness.png)

**Inventory (FEFO Batches)** — stock batches sorted by expiry (earliest first), so the next batch to sell is always at the top. Each row shows received date, expiry, quantity, freshness, and status.

![Retailer inventory FEFO batches](docs/screenshots/23-retailer-inventory.png)

**Add New Batch** — restock an existing product as a new batch with its own received date, expiry, quantity, and cost. The expiry is auto-suggested from the category shelf life.

![Retailer add new batch](docs/screenshots/23b-retailer-new-batch.png)

**Orders** — incoming orders showing the retailer's own sales, with a status workflow (Placed → Processing → Quality Check → Packed → Out for Delivery → Delivered) and a pick list.

![Retailer orders](docs/screenshots/24-retailer-orders.png)

**Refunds** — customer refund requests come to the seller first, who can approve (crediting the customer's wallet), reject, or escalate to an admin.

![Retailer refunds](docs/screenshots/25-retailer-refunds.png)

**Customer Reviews** — reviews on the retailer's products, with average rating, an awaiting-reply count, and the ability to reply.

![Retailer reviews](docs/screenshots/26-retailer-reviews.png)

**Product Performance Report** — gross sales, platform commission, net payout, units sold, orders, and units saved from waste, with a date-range filter and CSV export.

![Retailer performance report](docs/screenshots/27-retailer-reports.png)

**Freshness Discounts** — set a custom auto-discount percentage for each freshness level (thresholds are set by the admin).

![Retailer discounts](docs/screenshots/28-retailer-discounts.png)

**Notifications** — alerts for cancelled orders and new refund requests.

![Retailer notifications](docs/screenshots/29-retailer-notifications.png)

**My Profile** — account status, sales stats, personal info, business details (company, SSM number, address), and password change.

![Retailer profile](docs/screenshots/30-retailer-profile.png)

**Business Info** — company name, SSM business registration, contact, and business address.

![Retailer profile business info](docs/screenshots/30b-retailer-profile-business.png)

---

### Admin Panel

Admins oversee the entire platform — revenue, users, retailers, orders, refunds, reviews, promos, and system settings.

**Dashboard (top)** — platform commission earned, lifetime orders, active customers, units saved from waste, total products, retailers, active batches, and expired count.

![Admin dashboard](docs/screenshots/admin/31-admin-dashboard.png)

**Dashboard (waste & rescue)** — last-30-day rescued vs discarded units, waste rate, action-required alerts, a revenue line chart (last 14 days), and an orders-by-status donut chart.

![Admin dashboard waste and rescue](docs/screenshots/admin/31b-admin-dashboard-waste.png)

**Dashboard (business insights)** — top-selling products bar chart, customer growth line chart, revenue by category bar chart, and a recent orders table.

![Admin dashboard business insights](docs/screenshots/admin/31c-admin-dashboard-insights.png)

**Users** — all accounts (customers, retailers, admins) with role, status, order count, and join date. Admins can activate pending retailers or suspend active users.

![Admin users](docs/screenshots/admin/32-admin-users.png)

**Retailer Management** — pending retailer applications with SSM number, contact, address, and applied date. Admins approve or reject with one click.

![Admin retailer management](docs/screenshots/admin/33-admin-retailers.png)

**All Orders** — platform-wide order list with tabs by status (Placed, Processing, Quality Check, Packed, Out for Delivery, Delivered, Cancelled, Refunded), total GMV, and per-order view.

![Admin all orders](docs/screenshots/admin/34-admin-orders.png)

**Refund Requests** — escalated refunds (retailers can escalate to admin for a final decision), plus Approved and Rejected tabs.

![Admin refund requests](docs/screenshots/admin/35-admin-refunds.png)

**Review Moderation** — total reviews, awaiting-moderation count, approved (visible) count, average rating, and filter by star rating.

![Admin review moderation](docs/screenshots/admin/36-admin-reviews.png)

**Promo Codes** — all vouchers with code, discount type/value, min order, usage count, validity window, and active/inactive status.

![Admin promo codes](docs/screenshots/admin/37-admin-promos.png)

**New Promo Code** — create a voucher with code, description, discount type (% or flat), value, min order, max discount cap, total usage limit, per-user limit, and start/expiry dates.

![Admin new promo code](docs/screenshots/admin/37b-admin-new-promo.png)

**System Settings — Store Info** — site name, support email, currency code, and currency symbol.

![Admin settings store info](docs/screenshots/admin/38-admin-settings.png)

**System Settings — Shipping & Tax / Commission** — default shipping fee, free-shipping threshold, tax rate, and platform commission rate.

![Admin settings shipping and commission](docs/screenshots/admin/38b-admin-settings-shipping.png)

**System Settings — Cart & Freshness** — guest cart lifetime, max image size, max images per product, freshness recalc interval (cron), and maintenance mode toggle.

![Admin settings cart and freshness](docs/screenshots/admin/38c-admin-settings-cart.png)

**System Settings — Freshness Levels** — configure the four freshness tiers (Very Fresh / Fresh / Enjoy Soon / Last Chance) with min %, max %, colour, and auto-discount %. Changes apply store-wide immediately.

![Admin settings freshness levels](docs/screenshots/admin/38d-admin-settings-freshness.png)

**Admin Profile** — account details (name, email, phone, member since) and saved delivery addresses.

![Admin profile](docs/screenshots/admin/39-admin-profile.png)

**Admin Profile — Add Address** — add a new delivery address with label, recipient, phone, postcode, address lines, city, and state.

![Admin profile add address](docs/screenshots/admin/39b-admin-profile-address.png)
