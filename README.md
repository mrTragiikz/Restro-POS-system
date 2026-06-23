# Chiya Sansar — Restaurant POS System

A self-hosted Point of Sale and admin system built for Chiya Sansar (Parbatipur, Chitwan). Built in-house, runs on a single cPanel/shared hosting account — no monthly SaaS subscription, no per-terminal licensing fees, no transaction cut.

## Why this instead of a commercial POS

Most restaurant POS products (Petpooja, Posist, Square, etc.) charge **monthly or yearly per-outlet fees**, often with extra charges per additional device, per integration, or per report. For a single small restaurant, that's an ongoing cost for software that mostly does four things: take orders, print receipts, track stock, and manage credit customers.

This system does the same job for the cost of cheap shared hosting (a few hundred rupees a month), because:
- It's self-hosted — you own the code and the data outright.
- No per-device or per-user licensing — add as many waiter tablets/phones as you want.
- No vendor lock-in — your sales data lives in your own MySQL database, exportable any time.
- Customization is free — since it's your own PHP codebase, new features are a code change, not a feature-request ticket.

## How it works

**Two portals, one database:**

- **Waiter Portal** (`/waiter`) — Staff log in, pick a table from the floor plan, add items to an order, send it to the kitchen, and mark items served. Built for use on a phone or tablet at the table.
- **Admin Portal** (`/admin`) — Owner/manager view: live table status, sales reports, stock management, credit customer ledgers, and receipt/report printing.

**Core flows:**

1. **Order taking** — Waiter opens a table session, adds items (`api/save_order.php`), kitchen sees a live ticket (`admin/print_kot.php`), items get marked served as they go out.
2. **Billing** — When a table is settled, the system prints a thermal receipt (`admin/print_receipt.php`) showing items, totals, and payment split across cash/online/credit.
3. **Credit accounts (tab system)** — Regular customers can run a tab instead of paying immediately. The system tracks every credit given and every repayment (`api/create_credit_customer.php`, `api/record_payment.php`), and can print a full statement per customer or for all customers at once (`admin/print_all_credit_customers.php`, `admin/print_customer_audit.php`).
4. **Stock tracking** — Ingredient/item stock levels decrement as orders go through (`includes/stock_deduct.php`), with a management screen to restock and check availability (`admin/stockmanagement.php`).
5. **Reporting** — Daily/period sales and credit repayment reports for the owner (`api/sales_report.php`, `api/credit_repayment_report.php`).

**Tech stack:** PHP + MySQL (PDO, prepared statements throughout), no framework, no build step — works on any standard shared PHP hosting (cPanel, XAMPP locally for development).

## Deployment notes

- Database credentials live in `secure_config/db_credentials.php` (gitignored — copy from `secure_config/db_credentials.example.php` and fill in real values). On live hosting, move the `secure_config` folder **outside** `public_html` for security; `config/config.php` auto-detects it there.
- The local database dump (`chiyasan_sar.sql`) and live PHP session files are intentionally excluded from version control — see `.gitignore`.
- Error display is automatically disabled outside of `localhost` (see `config/config.php`).
