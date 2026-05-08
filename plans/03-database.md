# Database

## Engine & charset

- **MySQL 8.0+**, InnoDB engine (required for transactions and row-level locking).
- Charset `utf8mb4`, collation `utf8mb4_0900_ai_ci`.
- All tables get `created_at` / `updated_at` `TIMESTAMP` columns.

## Tables

### `users` (Req #1, #3, #4)

| Column          | Type                       | Constraints                         | Notes                                |
|-----------------|----------------------------|-------------------------------------|--------------------------------------|
| `id`            | `BIGINT UNSIGNED`          | PK, AUTO_INCREMENT                  |                                      |
| `username`      | `VARCHAR(50)`              | NOT NULL, UNIQUE                    | Login identifier.                    |
| `email`         | `VARCHAR(255)`             | NOT NULL, UNIQUE                    |                                      |
| `password_hash` | `VARCHAR(255)`             | NOT NULL                            | bcrypt output (~60 chars; 255 future-proof). |
| `role`          | `ENUM('admin','user')`     | NOT NULL, DEFAULT `'user'`          | RBAC source (Req #4).                |
| `created_at`    | `TIMESTAMP`                | NOT NULL, DEFAULT CURRENT_TIMESTAMP |                                      |
| `updated_at`    | `TIMESTAMP`                | NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |  |

Indexes:
- `UNIQUE KEY uq_users_username (username)`
- `UNIQUE KEY uq_users_email (email)`

### `products` (Req #1)

| Column               | Type                | Constraints                                  | Notes                                  |
|----------------------|---------------------|----------------------------------------------|----------------------------------------|
| `id`                 | `BIGINT UNSIGNED`   | PK, AUTO_INCREMENT                           |                                        |
| `name`               | `VARCHAR(100)`      | NOT NULL                                     |                                        |
| `price`              | `DECIMAL(10, 3)`    | NOT NULL, CHECK (`price` > 0)                | **3 decimals because Pepsi = 6.885.** |
| `quantity_available` | `INT UNSIGNED`      | NOT NULL, DEFAULT 0                          | UNSIGNED enforces ≥ 0 (Req #12).       |
| `created_at`         | `TIMESTAMP`         | NOT NULL, DEFAULT CURRENT_TIMESTAMP          |                                        |
| `updated_at`         | `TIMESTAMP`         | NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP |    |

Indexes:
- `KEY idx_products_name (name)` — supports name-based sorting on the listing page (Req #8).

Notes:
- We use `DECIMAL`, never `FLOAT`/`DOUBLE`, to avoid binary rounding on currency.
- `UNSIGNED INT` for `quantity_available` makes the "non-negative" constraint a database-level guarantee, on top of the application validator.

### `transactions` (Req #6)

A purchase log. One row per successful purchase.

| Column              | Type               | Constraints                                                | Notes                                                           |
|---------------------|--------------------|------------------------------------------------------------|-----------------------------------------------------------------|
| `id`                | `BIGINT UNSIGNED`  | PK, AUTO_INCREMENT                                         |                                                                 |
| `user_id`           | `BIGINT UNSIGNED`  | NOT NULL, FK → `users.id` ON DELETE RESTRICT               | Who bought it.                                                  |
| `product_id`        | `BIGINT UNSIGNED`  | NOT NULL, FK → `products.id` ON DELETE RESTRICT            | What was bought.                                                |
| `quantity`          | `INT UNSIGNED`     | NOT NULL, CHECK (`quantity` > 0)                           | Units in this purchase.                                         |
| `unit_price`        | `DECIMAL(10, 3)`   | NOT NULL                                                   | Snapshot of price at purchase time (price changes won't rewrite history). |
| `total_amount`      | `DECIMAL(12, 3)`   | NOT NULL                                                   | `quantity * unit_price`. Stored, not computed, for audit clarity. |
| `created_at`        | `TIMESTAMP`        | NOT NULL, DEFAULT CURRENT_TIMESTAMP                        |                                                                 |

Indexes:
- `KEY idx_tx_user (user_id, created_at)`
- `KEY idx_tx_product (product_id, created_at)`

`ON DELETE RESTRICT` is intentional: products with purchase history must not vanish silently — admin would need to handle that explicitly.

## Entity relationship diagram

```
┌─────────────────┐         ┌─────────────────────┐         ┌─────────────────┐
│      users      │         │     transactions    │         │     products    │
├─────────────────┤         ├─────────────────────┤         ├─────────────────┤
│ id (PK)         │ 1     N │ id (PK)             │ N     1 │ id (PK)         │
│ username        │─────────│ user_id (FK)        │─────────│ name            │
│ email           │         │ product_id (FK)     │         │ price           │
│ password_hash   │         │ quantity            │         │ quantity_avail  │
│ role            │         │ unit_price          │         │ created_at      │
│ created_at      │         │ total_amount        │         │ updated_at      │
│ updated_at      │         │ created_at          │         └─────────────────┘
└─────────────────┘         └─────────────────────┘
```

- `users` (1) — (N) `transactions`
- `products` (1) — (N) `transactions`
- No `users ↔ products` direct link; `transactions` is the join.

## Concurrency: the purchase flow (Req #6)

The most-likely bug in any vending-machine implementation is selling stock you don't have. Two users hitting "Buy" on the last Coke at the same moment must not both succeed.

The purchase is wrapped in a single SQL transaction with row-level locking:

```
BEGIN;

  -- 1. Lock the product row for the duration of the transaction
  SELECT id, price, quantity_available
    FROM products
   WHERE id = :product_id
   FOR UPDATE;

  -- 2. (App-side) verify quantity_available >= requested_qty.
  --    If not, ROLLBACK and surface "out of stock" to the user.

  -- 3. Decrement stock
  UPDATE products
     SET quantity_available = quantity_available - :qty
   WHERE id = :product_id;

  -- 4. Log the transaction
  INSERT INTO transactions
        (user_id, product_id, quantity, unit_price, total_amount)
  VALUES (:user_id, :product_id, :qty, :unit_price, :total);

COMMIT;
```

Notes:
- Without `FOR UPDATE`, two concurrent purchases would each read `quantity_available = 1`, both decrement, and one row would end up at `-1` (or violate the UNSIGNED constraint and fail unpredictably).
- All of this lives in `PurchaseService::purchase()`. Controllers don't speak SQL.
- Failure modes (insufficient stock, product not found) raise typed exceptions caught by the controller, which renders a flash error.

## Migrations

Plain SQL files in `db/migrations/`, applied in filename order by a small PHP runner. Format: `NNNN_description.sql`. A single migration file may contain multiple statements separated by `;` — keep one *logical change* per file.

```
db/migrations/
└── 0001_initial_schema.sql       # users + products + transactions in one file
```

Future schema changes get their own files (e.g. `0002_add_idx_products_price.sql`) so production DBs only re-run new ones.

Applied migrations are tracked in a `schema_migrations` table with one row per filename + applied timestamp.

## Seed data

`db/seeds/0002_seed_products.sql`:

```sql
INSERT INTO products (name, price, quantity_available) VALUES
  ('Coke',  3.99,  20),
  ('Pepsi', 6.885, 20),
  ('Water', 0.500, 50);
```

`db/seeds/0001_seed_admin_user.sql` creates one admin (password is set via the runner using `password_hash()`, not as plaintext SQL):

```
username: admin
email:    admin@vending.local
password: (provided via env: SEED_ADMIN_PASSWORD)
role:     admin
```

## Test database

- Separate database name (e.g. `vending_test`).
- `phpunit.xml` configures the env to point at it.
- Each test (or test class) wraps work in a transaction it rolls back at the end — fast, isolates state.
- Integration tests for repositories use a real MySQL instance (mocked DBs hide bugs); unit tests for controllers mock the repositories.

## Backup & migration safety

Out of scope for the build, but worth noting:
- Production deploys: run migrations before deploying new code.
- `ALTER TABLE` on `products` should be rare; if added, prefer online DDL.

## SQL hygiene rules

- **All queries are parameterized.** No string concatenation, ever. PDO with `:named` parameters.
- **No `SELECT *` in app code.** Explicit column lists.
- **Pagination uses `LIMIT ? OFFSET ?`** with clamped values (max page size 100). Sort columns are validated against an allow-list (Req #8) — never interpolated raw from the query string.
- **Errors throw, never silently fail.** PDO is configured with `ATTR_ERRMODE => ERRMODE_EXCEPTION`.
