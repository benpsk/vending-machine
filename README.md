# Vending Machine

PHP 8.4 web app + REST API for a vending machine.

Authoritative design lives in [`plans/`](plans/) and [`requirements.md`](requirements.md). Day-to-day working agreement is in [`CLAUDE.md`](CLAUDE.md).

## Quickstart

```bash
composer install
cp .env.example .env
# fill in the values you need (DB / JWT only matter from Phase 1 onward)

composer test       # run the test suite
composer test:cov   # with coverage report (HTML at coverage/)
composer stan       # PHPStan level 8
composer cs         # PSR-12 check
composer cs:fix     # PSR-12 auto-fix
```

## Local dev server

```bash
composer dev          # runs `php -S localhost:8000 -t public`
```

Open <http://localhost:8000/> — Phase 0 serves a placeholder page.

## Phase status

All 6 phases complete: skeleton, DB+routing, auth+RBAC+middleware, products CRUD, purchase flow, REST API + JWT, hardening + polish. See [`plans/06-implementation-plan.md`](plans/06-implementation-plan.md) for the phased build order.

## Setup checklist

```bash
composer install
sudo apt install php8.4-bcmath          # required for currency math
cp .env.example .env
# edit .env:
#   DB_USER=root  DB_PASSWORD=root  (or whatever fits your local DB)
#   JWT_SECRET=$(php -r 'echo bin2hex(random_bytes(32));')
composer migrate                         # creates vending + vending_test, applies migrations
composer seed:admin                      # seeds the admin user (uses SEED_ADMIN_PASSWORD env)
composer seed:products                   # seeds Coke / Pepsi / Water (idempotent)
```

## REST API

All endpoints accept and return JSON with the envelope:

```json
{ "data": ... | null, "error": { "code": "...", "message": "..." } | null, "meta": { "page": 1, "perPage": 20, "total": 3 } }
```

### Auth

```bash
# Login → returns a 15-minute HS256 JWT
curl -s -X POST http://localhost:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"password"}'
# {"data":{"token":"eyJ...","expires_at":1736435000,"token_type":"Bearer"},"error":null}
```

Save the token in a shell var for the next examples:

```bash
TOKEN=$(curl -s -X POST http://localhost:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"password"}' \
  | php -r '$j=json_decode(file_get_contents("php://stdin"),true); echo $j["data"]["token"];')
```

### Products

```bash
# List (any authenticated user)
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/products
curl -s -H "Authorization: Bearer $TOKEN" "http://localhost:8000/api/products?page=1&perPage=2&sort=price&dir=asc"

# Show
curl -s -H "Authorization: Bearer $TOKEN" http://localhost:8000/api/products/3

# Create (admin only)
curl -s -X POST http://localhost:8000/api/products \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"name":"Sprite","price":"2.50","quantity_available":"15"}'

# Update (admin only)
curl -s -X PUT http://localhost:8000/api/products/3 \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"name":"Coke Zero","price":"4.00","quantity_available":"10"}'

# Delete (admin only) → 204 No Content
curl -i -X DELETE http://localhost:8000/api/products/6 \
  -H "Authorization: Bearer $TOKEN"

# Purchase (any authenticated user)
curl -s -X POST http://localhost:8000/api/products/3/purchase \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"quantity":"2"}'
# {"data":{"id":99,"product_id":3,"quantity":2,"unit_price":"3.990","total_amount":"7.980",...},"error":null}
```

### Errors

| Status | Code                  | When                                   |
|--------|-----------------------|----------------------------------------|
| 400    | `bad_request`         | Disallowed sort column / direction     |
| 401    | `invalid_credentials` | Wrong password or missing user         |
| 401    | `invalid_token`       | Missing/expired/tampered/wrong-alg JWT |
| 403    | (HTML)                | Authenticated but lacks role           |
| 404    | `not_found`           | Product id doesn't exist               |
| 422    | `validation_failed`   | Field validation errors                |
| 422    | `out_of_stock`        | Purchase qty exceeds available         |
| 422    | `invalid_quantity`    | Purchase qty < 1                       |
| 429    | `rate_limited`        | 5+ failed logins in 15 min from one IP |

## Setup

```bash
git clone <repo> vending-machine && cd vending-machine

composer install
sudo apt install php8.4-bcmath          # currency math (decimal arithmetic)

cp .env.example .env
# minimum required values (see Environment variables below):
#   DB_USER=root  DB_PASSWORD=root
#   JWT_SECRET=$(php -r 'echo bin2hex(random_bytes(32));')

composer migrate                         # creates vending + vending_test, applies all migrations
composer seed:admin                      # seeds admin user (uses SEED_ADMIN_PASSWORD)
composer seed:products                   # seeds Coke / Pepsi / Water (idempotent)

composer dev                             # dev server at http://localhost:8000
```

## Environment variables

| Variable                | Default                | Required for prod | Purpose                                                            |
|-------------------------|------------------------|-------------------|--------------------------------------------------------------------|
| `APP_ENV`               | `local`                | yes               | `production` enables HSTS header and disables debug responses.     |
| `APP_DEBUG`             | `false`                | no                | `true` includes exception class + trace in 500 responses.          |
| `DB_HOST`               | `127.0.0.1`            | yes               | MariaDB/MySQL host.                                                |
| `DB_PORT`               | `3306`                 | yes               | DB port.                                                           |
| `DB_USER`               | (empty)                | yes               | DB user.                                                           |
| `DB_PASSWORD`           | (empty)                | yes               | DB password.                                                       |
| `DB_NAME`               | `vending`              | yes               | Production DB name. Tests use `vending_test`.                      |
| `JWT_SECRET`            | (empty)                | **yes**           | HS256 signing key. Bootstrap throws if empty. ≥32 random bytes.    |
| `JWT_TTL_SECONDS`       | `900`                  | no                | Access-token lifetime (default 15 min).                            |
| `SESSION_NAME`          | `VENDING_SID`          | no                | Cookie name for the web session.                                   |
| `SEED_ADMIN_PASSWORD`   | `password`             | yes               | Admin password used by `composer seed:admin`. Change for prod.     |

## Tests

```bash
composer test           # full PHPUnit suite (unit + feature/integration)
composer test:cov       # generates HTML coverage report under coverage/ (needs pcov or Xdebug)
composer cs             # PSR-12 lint
composer cs:fix         # PSR-12 auto-fix
composer stan           # PHPStan level 8
composer audit          # composer security advisories
```

Integration/feature tests need a real `vending_test` schema. `composer migrate` creates and migrates it for you.

The concurrency soak test (`PurchaseConcurrencyTest`) forks 500 child processes that all race on the same product row to confirm the `BEGIN / SELECT … FOR UPDATE / UPDATE / INSERT / COMMIT` transaction cleanly serializes. It needs `pcntl` enabled (default on Linux CLI).

## Troubleshooting

- **`The bcmath PHP extension is required for currency math.`** — `sudo apt install php8.4-bcmath` then restart any FPM/CLI workers.
- **`JWT_SECRET env var is empty`** — generate a fresh one: `php -r "echo bin2hex(random_bytes(32));"` and put it in `.env`.
- **MariaDB auth fails on `composer migrate`** — for local dev, `DB_USER=root DB_PASSWORD=root` matches the default MariaDB-on-Ubuntu setup. For prod, create a least-privilege user.
- **Port 8000 already in use** — pick another: `php -S localhost:8001 -t public`.
- **`No code coverage driver available`** — install pcov (`sudo apt install php8.4-pcov`) or enable Xdebug. pcov is faster for CI.
- **Production logs go nowhere** — `error_log()` writes to whatever `error_log` points to in `php.ini`. For FPM, the default is `/var/log/php_errors.log`; for `php -S`, it's stderr. Set explicitly in prod.
- **HSTS preload "stuck" after toggling `APP_ENV`** — once a browser sees the `preload` directive, it caches it for `max-age` (1 year). Don't enable production HSTS until the deployment is permanent. Submitting to <https://hstspreload.org> is opt-in and not done automatically.

## Pre-deploy checklist

Before shipping, walk through [`plans/05-security.md`](plans/05-security.md). Code-level gates are wired into the test suite and CI commands (`composer cs && composer stan && composer test && composer audit`). Operational gates (HTTPS termination, log shipping, secret rotation, monitoring) live with the deploy environment.
