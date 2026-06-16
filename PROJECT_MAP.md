# Gob Store Online Shopping System — PROJECT_MAP

## [TECH_STACK]

| Layer        | Technology                  | Version         |
|-------------|----------------------------|-----------------|
| Backend     | PHP                        | 8.2.12          |
| Database    | MariaDB (MySQL-compatible) | 10.4.32         |
| Frontend    | HTML5 + CSS3 + JavaScript  | —               |
| CSS library | Bootstrap 5                | 5.3.x (CDN)     |
| Icons       | Bootstrap Icons            | 1.11.x (CDN)    |
| Server      | Apache (XAMPP)             | —               |
| Auth        | password_hash / verify     | native PHP      |
| DB access   | PDO (prepared statements)  | native PHP      |

## [SYSTEM_FLOW]

```
Guest
  │
  ├─ Register → Login
  │
  ▼
Customer ─────────────────────────────────────────────┐
  │                                                    │
  ├─ Browse / Search Products                          │
  ├─ View Product Details                             │
  ├─ Manage Cart (add / update / remove)              │
  ├─ Checkout                                         │
  │   └─ Select Payment (COD / Zaad / EVC Plus)      │
  │   └─ Place Order → Payment Pending                │
  ├─ View My Orders                                   │
  └─ Track Order Status                               │
                                                      │
                                                      │
Admin ────────────────────────────────────────────────┘
  │
  ├─ Dashboard (summary cards + charts)
  ├─ Manage Products (CRUD)
  ├─ Manage Categories (CRUD)
  ├─ Manage Customers (list / view)
  ├─ Manage Orders (list / update status)
  ├─ Manage Payments (list / update status)
  └─ Reports
      ├─ Daily Sales Report
      ├─ Monthly Sales Report
      ├─ Inventory Report
      ├─ Low Stock Report
      └─ Customer Order Report
```

**Order Lifecycle:**
```
Cart → Checkout → Order (pending) → Processing → Shipped → Delivered
                                                         └→ Cancelled
Payment: pending → paid | failed
```

## [ARCHITECTURE]

### Pattern
Flat PHP with include-based modularity (no framework). Each page is a standalone `.php` file that includes shared components from `/includes/`.

### Request Flow
```
index.php (or any page)
  └─ includes/config.php       (DB connection, constants, session start)
  └─ includes/functions.php    (helper functions)
  └─ includes/header.php       (nav bar, Bootstrap CDN)
  └─ [page content]
  └─ includes/footer.php       (close tags, scripts)
```

### Directory Structure
```
gobstore/
├── admin/
│   ├── index.php              (dashboard)
│   ├── products.php           (manage products)
│   ├── categories.php         (manage categories)
│   ├── orders.php             (manage orders)
│   ├── payments.php           (manage payments)
│   ├── customers.php          (manage customers)
│   ├── reports.php            (sales/inventory reports)
│   ├── logout.php
│   └── includes/
│       ├── header.php
│       ├── sidebar.php
│       └── footer.php
│
├── assets/
│   ├── css/
│   │   └── style.css          (custom styles)
│   ├── js/
│   │   └── main.js            (custom scripts)
│   └── images/
│
├── includes/
│   ├── config.php             (DB, session, constants)
│   ├── functions.php          (helpers: auth, cart, etc.)
│   ├── header.php             (frontend nav)
│   └── footer.php             (frontend footer)
│
├── database/
│   └── schema.sql             (full database DDL)
│
├── uploads/                   (product images)
│
├── index.php                  (home page)
├── login.php
├── register.php
├── profile.php
├── products.php
├── product-details.php
├── cart.php
├── checkout.php
├── my-orders.php
├── logout.php
│
├── PROJECT_MAP.md
└── README.md                  (setup instructions)
```

### Database Schema (8 tables)

**users** — stores customers + admin
| Column       | Type               | Notes                    |
|-------------|--------------------|--------------------------|
| id          | INT AUTO_INCREMENT | PK                       |
| fullname    | VARCHAR(100)       | NOT NULL                 |
| email       | VARCHAR(100)       | UNIQUE, NOT NULL         |
| phone       | VARCHAR(20)        | nullable                 |
| password    | VARCHAR(255)       | password_hash()          |
| role        | ENUM('customer','admin') | DEFAULT 'customer'  |
| created_at  | TIMESTAMP          | DEFAULT CURRENT_TIMESTAMP|

**categories**
| Column | Type               | Notes |
|--------|--------------------|-------|
| id     | INT AUTO_INCREMENT | PK    |
| name   | VARCHAR(100)       | NOT NULL |
| created_at | TIMESTAMP     | DEFAULT CURRENT_TIMESTAMP |

**products**
| Column         | Type               | Notes |
|---------------|--------------------|-------|
| id            | INT AUTO_INCREMENT | PK    |
| category_id   | INT                | FK → categories.id |
| name          | VARCHAR(200)       | NOT NULL |
| description   | TEXT               | nullable |
| price         | DECIMAL(10,2)      | NOT NULL |
| stock_quantity| INT                | DEFAULT 0 |
| image         | VARCHAR(255)       | nullable (filename) |
| status        | ENUM('active','inactive') | DEFAULT 'active' |
| created_at    | TIMESTAMP          | DEFAULT CURRENT_TIMESTAMP |

**cart** — one cart per user
| Column | Type               | Notes |
|--------|--------------------|-------|
| id     | INT AUTO_INCREMENT | PK    |
| user_id| INT                | FK → users.id, UNIQUE |
| created_at | TIMESTAMP     | DEFAULT CURRENT_TIMESTAMP |

**cart_items**
| Column     | Type               | Notes |
|-----------|--------------------|-------|
| id        | INT AUTO_INCREMENT | PK    |
| cart_id   | INT                | FK → cart.id |
| product_id| INT                | FK → products.id |
| quantity  | INT                | DEFAULT 1 |
| price     | DECIMAL(10,2)      | snapshot at add time |

**orders**
| Column         | Type               | Notes |
|---------------|--------------------|-------|
| id            | INT AUTO_INCREMENT | PK    |
| order_number  | VARCHAR(20)        | UNIQUE, e.g. GOB-20260530-0001 |
| user_id       | INT                | FK → users.id |
| total_amount  | DECIMAL(10,2)      | NOT NULL |
| payment_method| ENUM('cod','zaad','evc_plus') | NOT NULL |
| order_status  | ENUM('pending','processing','shipped','delivered','cancelled') | DEFAULT 'pending' |
| created_at    | TIMESTAMP          | DEFAULT CURRENT_TIMESTAMP |

**order_items**
| Column     | Type               | Notes |
|-----------|--------------------|-------|
| id        | INT AUTO_INCREMENT | PK    |
| order_id  | INT                | FK → orders.id |
| product_id| INT                | FK → products.id |
| quantity  | INT                | NOT NULL |
| price     | DECIMAL(10,2)      | snapshot |
| subtotal  | DECIMAL(10,2)      | quantity * price |

**payments**
| Column            | Type               | Notes |
|------------------|--------------------|-------|
| id               | INT AUTO_INCREMENT | PK    |
| order_id         | INT                | FK → orders.id, UNIQUE |
| payment_method   | ENUM('cod','zaad','evc_plus') | NOT NULL |
| reference_number | VARCHAR(100)       | nullable (user-provided for zaad/evc) |
| amount           | DECIMAL(10,2)      | NOT NULL |
| payment_status   | ENUM('pending','paid','failed') | DEFAULT 'pending' |
| created_at       | TIMESTAMP          | DEFAULT CURRENT_TIMESTAMP |

### ERD Relationships
- users 1──* orders
- users 1──1 cart
- cart 1──* cart_items
- categories 1──* products
- products 1──* cart_items
- products 1──* order_items
- orders 1──* order_items
- orders 1──1 payments

### Security
- **Passwords**: `password_hash(PASSWORD_BCRYPT)` / `password_verify()`
- **SQL Injection**: PDO prepared statements everywhere
- **XSS**: `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` on all output
- **Session Auth**: `$_SESSION` with role check on every admin page
- **RBAC**: `isAdmin()` / `isLoggedIn()` guard functions redirect to login
- **File uploads**: validate extension + MIME, store outside webroot alias (uploads/)

### Logging Strategy
Simple file-based logging via `logMessage()` in `includes/functions.php`:
- Levels: `INFO`, `WARN`, `ERROR`
- Appends to `logs/app.log`
- Admin actions (order status change, payment update) logged
- Non-blocking: log writes are fire-and-forget

## [ORPHANS & PENDING]

| Item | Status | Notes |
|------|--------|-------|
| Admin credentials | DONE | admin@gobstore.com / password |
| Bootstrap 5 CDN | DONE | v5.3.3 |
| order_number generation | DONE | GOB-YYYYMMDD-NNNN |
| Placeholder images | DONE | Using placehold.co service |
| Error pages | DONE | 404.php with .htaccess |
| README.md | PENDING | Setup instructions |
