# OmniPOS - Universal Retail & Cloud Store Sync POS

**OmniPOS** is a high-speed, modern Point of Sale (POS) web application built with **PHP 8, MySQL, Tailwind CSS, and Vanilla JavaScript**.

Designed specifically for Pakistani retail stores, marts, pharmacies, and clothing boutiques, with a built-in **Real-Time Cloud Sync Engine** compatible with [Scora.com.pk](https://scora.com.pk) and modern GraphQL e-commerce platforms.

---

## 🌟 Key Features

1. **⚡ Fast Cashier Terminal (POS Counter):**
   - Instant Barcode Scanning with pleasant Web Audio scanner beeps.
   - Hotkeys: `F2` (Focus Search), `F8` (Discount), `F9` (Complete Sale), `Esc` (Close Modals).
   - Category filtering and visual product cards with live stock indicators.
   - Quick Cash Tender buttons (`Exact`, `500`, `1,000`, `5,000`) and auto **Change Due** calculation.

2. **🖨️ Thermal Receipt Printing (80mm & 58mm):**
   - Clean, ESC/POS-ready thermal receipt layout.
   - Supports instant browser printing, preview modal, and PDF generation.

3. **💳 Multi-Payment & Pakistani Khata (Udhaar) Engine:**
   - Cash (Naqad), Card, JazzCash, and EasyPaisa.
   - Full **Udhaar / Khata Ledger**: Customer balance tracking, partial payments (Vasooli), opening balance, and digital ledger statements.

4. **☁️ Real-Time Cloud Store Sync:**
   - Connects to your online store (Scora GraphQL API v2026-08).
   - **Auto Stock Deduction:** When an item is sold at the counter, the online store inventory is instantly decremented by the same quantity.
   - **1-Click Catalog Import:** Import your entire online product catalog directly into the local POS without manual re-entry.
   - **Inbound Webhook Receiver (`api/webhook.php`):** Deducts local stock when customers buy online.

5. **📊 Business Analytics & Profit/Loss:**
   - Real-time Gross Profit (Munafa) calculation (`Selling Price - Cost Price`).
   - Daily and monthly revenue metrics.
   - Low stock warning alerts with custom threshold.
   - Top-selling fast moving products.

---

## 🚀 Quick Setup & Installation

### Requirements:
- PHP 8.0 or newer (with `pdo_mysql` and `curl` extensions enabled)
- MySQL / MariaDB (e.g., XAMPP)

### Steps:
1. Start MySQL from XAMPP or terminal.
2. In the project folder, open terminal and run:
   ```bash
   php install.php
   ```
   *(This automatically creates the database and populates it with sample Pakistani products, categories, and customer records).*

3. Start the local server:
   ```bash
   php -S 127.0.0.1:8000
   ```

4. Open your browser and navigate to:
   ```
   http://127.0.0.1:8000
   ```

---

## 📁 Project Architecture

```
pos-web-app/
├── config/
│   ├── database.php        # MySQL PDO connection & configuration
│   ├── sync.php            # CloudSyncClient (Universal GraphQL Sync)
│   └── schema.sql          # Complete MySQL database schema & sample data
├── includes/
│   ├── header.php          # Top navigation & live sync indicator
│   ├── footer.php          # Modals, toasts, and thermal print container
│   └── auth.php            # Session & authentication management
├── api/
│   ├── checkout.php        # Transaction processing, stock deduction & cloud sync
│   ├── products.php        # Live search, barcode lookup, category filtering
│   ├── sync.php            # Test connection & 1-click catalog import
│   └── webhook.php         # Inbound webhook for online store sales
├── assets/
│   ├── css/
│   │   └── pos.css         # Styling & ESC/POS @media print rules
│   └── js/
│       └── pos.js          # Cart, audio beep, hotkeys & thermal receipt logic
├── index.php               # Main POS Cashier Terminal
├── products.php            # Inventory & Product Catalog Management
├── categories.php          # Category & Department Management
├── sales.php               # Sales History, Invoice Reprinting & Voiding
├── customers.php           # Khata (Udhaar) Ledger & Vasooli Management
├── dashboard.php           # Profit/Loss, Analytics & Inventory Warnings
├── settings.php            # Outlet receipt info & Cloud API Configuration
├── receipt.php             # Standalone & JSON thermal receipt renderer
└── install.php             # 1-Click Database Setup Wizard
```

---

## 💼 How to List on Scora App Store & Monetize

1. Register as a partner in the [Scora Developer Program](https://scora.com.pk/SC/developer-doc).
2. Go to **Apps Management** and create your App named **OmniPOS**.
3. Set your Redirect Callback URI to `https://your-domain.com/oauth/callback`.
4. Request permissions (Scopes): `products:write`, `inventory:write`, `orders:write`.
5. Set up monthly billing via `appSubscriptionRequest` (e.g. PKR 1,500/month or PKR 2,500/month).
6. Submit for review! Because the App Store is currently in its early stages with zero approved POS apps, you have an enormous **first-mover advantage** in Pakistan!
