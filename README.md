# Chiya Sansar — Restaurant POS System

A self-hosted Point of Sale and admin system built for Chiya Sansar (Parbatipur, Chitwan). Built in-house, runs on a single cPanel/shared hosting account. No monthly SaaS subscription, no per-terminal licensing fees, no transaction cut.

## Why this instead of a commercial POS

Most restaurant POS products (Petpooja, Posist, Square, etc.) charge monthly or yearly per-outlet fees, often with extra charges per additional device, per integration, or per report. For a single small restaurant, that's an ongoing cost for software that mostly does four things: take orders, print receipts, track stock, and manage credit customers.

This system does the same job for the cost of cheap shared hosting (a few hundred rupees a month):
- Self-hosted, so you own the code and the data outright.
- No per-device or per-user licensing. Add as many waiter tablets/phones as you want.
- No vendor lock-in. Your sales data lives in your own MySQL database, exportable any time.
- Customization is free. Since it's your own PHP codebase, new features are a code change, not a feature-request ticket.

## Client demo walkthrough

Use this as a script to show the system end to end.

### 1. Waiter takes an order
- Open `/waiter`, log in with a waiter account.
- Pick a table from the floor plan.
- Add items to the order and send it to the kitchen (`api/save_order.php`).
- The kitchen prints/views a live order ticket (`admin/print_kot.php`) so staff know what to cook.
- As items go out, the waiter marks them served.

### 2. Stock goes down automatically
- Every item in the menu can be linked to ingredient stock.
- The moment an order is saved, the stock for each ingredient used is deducted automatically (`includes/stock_deduct.php`). No manual stock entry per order.
- Open `admin/stockmanagement.php` to show the live stock levels dropping in real time as orders come in.
- If an ingredient runs low or out, the system can flag the item as unavailable on the menu (`includes/stock_availability.php`) so waiters can't oversell something the kitchen can't make.
- Restocking (e.g. a new sack of sugar or carton of milk arrives) is a simple add-stock entry in the same screen.

### 3. Customer pays (or runs a tab)
- When the table is settled, the system prints a thermal receipt (`admin/print_receipt.php`) showing items, totals, and how the bill was paid: cash, online, or credit.
- Regular customers can run a tab instead of paying immediately. Demonstrate by creating a credit customer (`api/create_credit_customer.php`), giving credit on an order, then recording a partial repayment (`api/record_payment.php`).
- Show the full statement for one customer (`admin/print_customer_audit.php`) or for every credit customer at once (`admin/print_all_credit_customers.php`), including item-level history.

### 4. Owner checks the day
- Open `/admin` to show the live table/floor plan view, no need to walk the floor to know what's occupied.
- Pull up the sales report (`api/sales_report.php`) for a day or date range.
- Pull up the credit repayment report (`api/credit_repayment_report.php`) to see who owes what and what's been collected.

## Tech stack

PHP + MySQL (PDO, prepared statements throughout). No framework, no build step. Works on any standard shared PHP hosting (cPanel, XAMPP locally for development).

## Deployment notes

- Database credentials live in `secure_config/db_credentials.php` (gitignored, copy from `secure_config/db_credentials.example.php` and fill in real values). On live hosting, move the `secure_config` folder outside `public_html` for security; `config/config.php` auto-detects it there.
- The local database dump (`chiyasan_sar.sql`) and live PHP session files are intentionally excluded from version control. See `.gitignore`.
- Error display is automatically disabled outside of `localhost` (see `config/config.php`).
