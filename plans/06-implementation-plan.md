# Implementation Plan

A phased build order. Each phase is a vertical slice that ends in a runnable, tested state. We do not move to phase N+1 until phase N is green (lint + static analysis + tests + the relevant security checks from [`05-security.md`](05-security.md)).

Each phase lists:
- **Goal** — what's working at the end.
- **Requirements covered** — the numbered tasks from `requirements.md`.
- **Deliverables** — the concrete artefacts.
- **Tests** — what proves it's done.
- **Acceptance criteria** — the gate to the next phase.

---

## Phase 0 — Project skeleton

**Goal:** an empty-but-correct repository that boots, runs PHPUnit, and serves a "hello" page.

**Requirements covered:** none directly; foundation for all 17.

**Deliverables:**
- `composer.json` with PSR-4 autoload (`App\` → `src/`), dev/prod dependencies pinned (see [`01-tech-stack.md`](01-tech-stack.md)).
- `.gitignore`, `.env.example`, `.editorconfig`.
- `phpunit.xml`, `phpstan.neon`, `phpcs.xml` configured.
- `public/index.php` front controller returning a placeholder response.
- `src/Bootstrap.php`, `src/Http/Kernel.php`, `src/Http/Request.php`, `src/Http/Response.php`, `src/Http/View.php` skeletons (our own — no PSR-7).
- `src/Support/Container.php` (small reflection-based DI container).
- `templates/layout.php` placeholder shell.
- README with setup commands.

**Tests:**
- `tests/Unit/Support/ContainerTest.php`: container resolves a class with no dependencies; resolves a class whose dependencies are themselves resolvable; throws on cycles.
- A smoke feature test that boots the kernel and asserts a 200 from `/`.

**Acceptance:**
- `composer install && composer test` is green.
- `php -S localhost:8000 -t public` serves `/`.
- `composer stan && composer cs` are green.

---

## Phase 1 — Database + routing + product repository

**Goal:** the app can talk to MySQL via PDO, route requests via `#[Route]` attributes, and the `ProductRepository` performs CRUD.

**Requirements covered:** **#1**, **#2**, **#10**, **#11**.

**Deliverables:**
- `db/migrations/0001_initial_schema.sql` — `users`, `products`, `transactions`, `schema_migrations` (schema per [`03-database.md`](03-database.md)).
- `db/seeds/0002_seed_products.sql` — Coke / Pepsi / Water.
- `src/Database/Mysql/Connection.php` — PDO factory, exception-mode, utf8mb4.
- `src/Database/Mysql/Migrator.php` + `bin/migrate.php` — applies pending migrations in order.
- `src/Products/Product.php` — readonly value object.
- `src/Database/Mysql/ProductRepository.php` — `findById`, `paginate(page, perPage, sort, dir)`, `create`, `update`, `delete`.
- `src/Routing/Route.php` — the `#[Route]` attribute.
- `src/Routing/RouteCollector.php` and `Router.php`.

**Tests:**
- Integration: `ProductRepository` against real `vending_test` DB. Cover every method, including pagination edge cases and the sort allow-list. Use `#[DataProvider]` for the pagination/sort case matrix. (Lives at `tests/Integration/Database/Mysql/ProductRepositoryTest.php`.)
- Unit: `RouteCollector` (discovers `#[Route]`; multiple routes per method; path params), real attribute classes, no mocks.
- Unit: `Router` matching cases via `#[DataProvider]` (matches; rejects unknown method with 405; rejects unknown path with 404).

**Acceptance:**
- Migrations apply cleanly to an empty DB.
- Routing tests pass without any HTTP layer involved.
- Coverage on `src/Database/Mysql/` and `src/Routing/` ≥ 90%.
- **Security check:** sort allow-list rejects unknown columns; all queries are parameterized (grep `src/Database/Mysql/` for `"."` concat in SQL strings — must be zero hits).

---

## Phase 2 — Authentication + RBAC + middleware pipeline

**Goal:** users can register/log in via the web, sessions persist, and admin-only pages are gated.

**Requirements covered:** **#3**, **#4**.

**Deliverables:**
- `src/Users/User.php`, `src/Users/Role.php` (enum).
- `src/Database/Mysql/UserRepository.php`.
- `src/Auth/PasswordHasher.php`, `src/Auth/SessionAuthenticator.php`.
- `src/Http/Middleware/SessionStartMiddleware.php`, `CsrfMiddleware.php`.
- `src/Auth/Middleware/AuthSessionMiddleware.php`, `RequireRoleMiddleware.php`.
- `src/Auth/AuthController.php` (login/logout).
- Templates: `templates/auth/login.php`, `templates/layout.php` (filled out), `templates/partials/flash.php`.
- `src/Support/Csrf.php`.
- Seed admin user (password from `SEED_ADMIN_PASSWORD` env), via `db/seeds/0001_seed_admin_user.sql` plus a small PHP runner that hashes the password before insert.

**Tests:**
- Unit: `PasswordHasher` (hash uses bcrypt; verify rejects wrong password) — real, no mocks.
- Unit: `SessionAuthenticator` — table-driven cases for login result (correct password, wrong password, missing user) via `#[DataProvider]`. Real `UserRepository`-against-test-DB; only `$_SESSION` is the test seam.
- Feature: full login → redirect → access protected page; wrong password shows error; non-admin gets 403 on admin route; logged-out user gets 302 from a protected route.
- Feature: CSRF token required on POST `/login`.
- Feature: login with non-existent user returns the **same generic error** as wrong-password (no enumeration).

**Acceptance:**
- Login flow works end-to-end in a browser against a real DB.
- All RBAC tests pass.
- No plaintext passwords appear anywhere in code or logs.
- **Security check (per [`05-security.md`](05-security.md)):** session ID rotates on login (`session_regenerate_id(true)`); cookie flags `HttpOnly; Secure; SameSite=Lax`; rate limiting on `/login` (5/15min/IP).

---

## Phase 3 — Products CRUD (web) + listing + validation

**Goal:** admins can manage the product catalogue from the web; everyone sees a paginated, sortable list.

**Requirements covered:** **#5**, **#7**, **#8**, **#12**, **#13**.

**Deliverables:**
- `src/Validation/Validator.php` and rule classes (`Required`, `Numeric`, `IntegerRule`, `Min`, `Max`).
- `src/Validation/ValidationException.php` + kernel handler that re-renders the form.
- `src/Products/ProductValidationRules.php` — the rule set used by both controllers.
- `src/Products/ProductsController.php` — actions: `index`, `show`, `create`, `store`, `edit`, `update`, `destroy`.
- Templates in `templates/products/`:
  - `index.php` (list with pagination + sort headers)
  - `show.php`
  - `admin/create.php`, `admin/edit.php`, `admin/delete.php`
- Shared partials: `templates/partials/pagination.php`, `templates/partials/form-errors.php`.
- `public/assets/js/validation.js` — client-side mirror of the rules.
- `public/assets/css/app.css` — minimal styling.

**Tests:**
- Unit (Req #14, #15): `Products\ProductsControllerTest` — every action with mocked `ProductRepository` + `Validator`. Cover happy path, validation failure, 404, 403.
- Unit: `Validator` rules — table-driven via `#[DataProvider]`; real rule instances; combined rule sets; verifies *all* errors are collected, not just the first.
- Feature: list page renders, paginates, sorts; sort allow-list rejects evil columns. Real DB.
- Feature: admin can create/edit/delete; non-admin cannot reach those routes. Real DB.
- Feature: form validation errors render with inputs preserved. Real DB.

**Acceptance:**
- ≥ 90% coverage on `src/Products/ProductsController.php` and `src/Validation/`.
- Manual sanity check in a browser: create Coke duplicate, verify pagination at perPage=2, sort by price asc/desc.
- **Security check:** every dynamic value in views is escaped via `e()`; grep templates for raw `<?= $`/`<?php echo $` without `e(`.

---

## Phase 4 — Purchasing (web)

**Goal:** logged-in users can buy a product; stock decrements; a transaction row is written; concurrent buyers can't oversell.

**Requirements covered:** **#6**, **#9**.

**Deliverables:**
- `src/Transactions/Transaction.php`.
- `src/Database/Mysql/TransactionRepository.php`.
- `src/Products/PurchaseService.php` — orchestrates `BEGIN` / `SELECT … FOR UPDATE` / `UPDATE` / `INSERT` / `COMMIT` (per [`03-database.md`](03-database.md)).
- `src/Products/Exceptions/OutOfStockException.php`, `ProductNotFoundException.php`, `InvalidQuantityException.php`.
- New `Products\ProductsController` actions: `purchaseForm` (GET) and `purchase` (POST).
- Template: `templates/products/purchase.php`.

**Tests:**
- Integration: `PurchaseService` against real DB — happy path, out-of-stock, invalid qty, exception propagation. **No repo mocking** — the service's job *is* the SQL transaction; mocking would invalidate the test.
- Integration concurrency: two PDO connections, both call `purchase()` against `qty=1`; exactly one wins, `quantity_available` ends at 0, exactly one transaction row exists. Run 50× in CI for confidence.
- Feature: full purchase flow through the kernel; success shows confirmation; out-of-stock surfaces a flash error.

**Acceptance:**
- Concurrency test passes reliably.
- `quantity_available` never goes negative under any test path.
- Receipt view shows total = unit_price × qty.
- **Security check:** `total_amount` computed via `bcmul` / `bcadd`, never `(float)`; `INT UNSIGNED` constraint on `quantity_available` confirmed live in the DB.

---

## Phase 5 — REST API + JWT

**Goal:** all product operations and the purchase action are reachable as JSON over HTTP, authenticated with bearer JWTs.

**Requirements covered:** **#16**, **#17**.

**Deliverables:**
- `src/Auth/JwtAuthenticator.php` (issue + verify with `firebase/php-jwt`, HS256, env-driven secret + TTL).
- `src/Auth/Middleware/AuthJwtMiddleware.php` (parses `Authorization: Bearer …`, verifies, loads user; reuses `RequireRoleMiddleware` for RBAC).
- `src/Auth/AuthApiController.php` — `POST /api/auth/login` returns `{ token, expires_at }`.
- `src/Products/ProductsApiController.php` — `index`, `show`, `store`, `update`, `destroy`, `purchase`. JSON in / JSON out, consistent envelope:
  ```json
  { "data": {...} | [...], "error": null, "meta": { "page": 1, "perPage": 20, "total": 3 } }
  ```
- Errors return matching envelope: `{ "data": null, "error": { "code": "...", "message": "..." } }`.

**Tests:**
- Unit: `JwtAuthenticator` — table-driven via `#[DataProvider]` (valid; expired; tampered signature; wrong-alg `none`/`RS256`; missing claims). Real signer with a fixed test secret + injected `Clock` for determinism.
- Unit (Req #15): `Products\ProductsApiControllerTest` with mocked repos.
- Feature: login returns a valid JWT; protected endpoint with valid token → 200; missing → 401; expired → 401; tampered → 401; non-admin → 403 on admin endpoints; full purchase via API decrements stock and returns the transaction.

**Acceptance:**
- All API endpoints documented in `README.md` with curl examples.
- ≥ 90% coverage on `src/Products/ProductsApiController.php`, `src/Auth/AuthApiController.php`, `src/Auth/JwtAuthenticator.php`.
- **Security check (per [`05-security.md`](05-security.md)):** signing alg pinned to HS256 (the verify path **rejects** `none` and `RS256`); tokens never appear in URLs or logs; rate limiting on `/api/auth/login` matches the web.

---

## Phase 6 — Hardening, polish, documentation

**Goal:** the project is presentable: clean docs, predictable errors, nothing dangling.

**Requirements covered:** cross-cutting; closes the gap between "works" and "shippable".

**Deliverables:**
- Global error handler: in production, returns generic 500 page + JSON; in dev, shows full trace. Errors logged with context (no PII / no tokens).
- Rate limiting on `/login` and `/api/auth/login` finalised (if not already done in Phases 2 / 5).
- Security headers: `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, full CSP for the web side (per [`05-security.md`](05-security.md)).
- README sections: setup, environment, running tests, API reference, troubleshooting.
- `composer audit` clean.
- PHPStan level 8 passes with no baseline.
- **Pre-deploy security checklist** from [`05-security.md`](05-security.md) walked through and ticked off.

**Tests:**
- Final coverage report ≥ 80% line, ≥ 90% on each module's controllers / service plus `src/Database/Mysql/`.
- Full suite green in < 30s locally.

**Acceptance:**
- A new developer can clone, run `composer install`, copy `.env.example` to `.env`, run `bin/migrate.php`, run `composer test`, and `php -S localhost:8000 -t public`, then have a working app — with no extra steps.
- Every item on the [`05-security.md`](05-security.md) pre-deploy checklist passes.

---

## Risks & mitigations

| Risk                                                                 | Mitigation                                                                                  |
|----------------------------------------------------------------------|---------------------------------------------------------------------------------------------|
| Custom router becomes complex (caching, regex compilation, edge cases). | Start with the simplest matcher; only optimise once measured. Fall back to Slim 4 if it grows past ~300 lines. |
| Concurrency bug in `PurchaseService` only shows up under load.       | Dedicated concurrency integration test that runs 50× in CI; production also keeps the DB constraint as a safety net. |
| Decimal price arithmetic drifts due to `float` casting in PHP.       | Use `bcmath` (`bcmul`, `bcadd`) when computing `total_amount` server-side; store as `DECIMAL`. |
| JWT secret rotation invalidates all tokens at once.                  | Keep token TTL short (15 min); refresh tokens stored hashed in DB so revocation is possible. |
| CSRF on web forms forgotten on a single endpoint.                    | `CsrfMiddleware` runs on **all** non-GET requests under web routes by default; opt-out is explicit and code-reviewed. |
| Tests grow slow as integration suite expands.                        | Per-test transaction wrap; PCOV instead of Xdebug for coverage; parallelise once the suite > 5s. |
| Domain modules drift toward depending on each other directly.        | Enforced rule: only `Products\PurchaseService` legitimately spans domain modules; flag any other cross-import in code review. |
| `Database/Mysql/` directly imported by HTTP layer or templates.      | Forbidden direction; flag in code review. Repositories are consumed by domain modules only. |
| JWT `alg=none` / algorithm-confusion attack.                         | `JwtAuthenticator` pins HS256 server-side and explicitly rejects other algs; covered by `JwtAuthenticatorTest`. |

## Effort estimate (rough, single developer)

| Phase | Description                          | Effort       |
|-------|--------------------------------------|--------------|
| 0     | Skeleton                             | 0.5 day      |
| 1     | DB + routing + product repo          | 1.5 days     |
| 2     | Auth + RBAC + middleware             | 1 day        |
| 3     | Products CRUD + listing + validation | 1.5 days     |
| 4     | Purchasing                           | 1 day        |
| 5     | REST API + JWT                       | 1 day        |
| 6     | Hardening + docs                     | 0.5 day      |
| **Total** |                                  | **~7 days**  |

## Definition of done (whole project)

- [ ] All 17 requirements satisfied and explicitly mapped (see [`00-overview.md`](00-overview.md)).
- [ ] PHPUnit ≥ 80% line coverage; ≥ 90% on each module's controllers / service plus `src/Database/Mysql/`.
- [ ] PHPStan level 8 clean.
- [ ] PSR-12 clean (PHP-CS-Fixer + PHP_CodeSniffer).
- [ ] `composer audit` clean.
- [ ] No hardcoded secrets; `.env.example` documents every env var.
- [ ] README walks a fresh dev from clone to running app + tests.
- [ ] Every controller method has at least one unit test using mocked dependencies (Req #15) and a parallel feature test against a real DB.
- [ ] All API endpoints documented with curl examples and example responses.
- [ ] Every item on [`05-security.md`](05-security.md)'s pre-deploy checklist passes.
