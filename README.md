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
│   ├── retailer/       ← Retailer dashboard
│   ├── admin/          ← Admin panel
│   └── assets/         ← CSS, JS, images
├── includes/           ← Core libraries (config, helpers, freshness, FEFO)
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
