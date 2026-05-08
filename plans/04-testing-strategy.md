# Testing Strategy

Covers requirements **#14** (PHPUnit tests for `ProductsController`) and **#15** (DI + mocking).

## Goals

- ≥ **80% line coverage** across `src/`.
- ≥ **90%** on each module's controllers / service plus `src/Database/Mysql/` (the parts that hold real logic).
- Every requirement (#1–#17) has at least one test exercising the happy path; security-critical flows (auth, RBAC, purchase concurrency) have failure-path tests too.
- Tests run in **< 30 seconds** locally for the unit + feature suites combined.

## Tooling

| Tool                 | Purpose                                      |
|----------------------|----------------------------------------------|
| **PHPUnit 13**       | Test runner (Req #14).                       |
| **Mockery 1.6**      | Cleaner mocks for collaborators (Req #15) — used sparingly, see below. |
| **PHPUnit coverage** | HTML + clover XML report (`--coverage-html`, `--coverage-clover`). Requires Xdebug or PCOV; PCOV recommended for speed. |
| **PHPStan level 8**  | Caught at static-analysis time, not runtime. |

## Folder layout

```
tests/
├── Unit/                    # Pure, fast, no I/O
│   ├── Validation/
│   │   └── ValidatorTest.php                # table-driven via #[DataProvider]
│   ├── Routing/
│   │   ├── RouteCollectorTest.php
│   │   └── RouterTest.php                   # table-driven
│   ├── Auth/
│   │   ├── PasswordHasherTest.php
│   │   └── JwtAuthenticatorTest.php         # table-driven (claim cases)
│   └── Products/
│       ├── ProductsControllerTest.php       # ← the Req #15 mocking layer (web)
│       └── ProductsApiControllerTest.php    #   and api
│
├── Integration/             # Hits real MySQL (test DB), no HTTP
│   ├── Database/Mysql/
│   │   ├── ProductRepositoryTest.php
│   │   ├── UserRepositoryTest.php
│   │   └── TransactionRepositoryTest.php
│   └── Products/
│       ├── PurchaseServiceTest.php
│       └── PurchaseServiceConcurrencyTest.php
│
├── Feature/                 # End-to-end through the kernel
│   ├── Web/
│   │   ├── AuthFlowTest.php
│   │   ├── ProductsListTest.php
│   │   ├── ProductCrudTest.php
│   │   ├── PurchaseFlowTest.php
│   │   └── RbacTest.php
│   └── Api/
│       ├── AuthApiTest.php
│       ├── ProductsApiTest.php
│       └── JwtSecurityTest.php
│
├── Support/
│   ├── TestCase.php             # Base class with container + DB transaction wrap
│   ├── DatabaseTestCase.php     # Extends TestCase, opens/rollbacks per test
│   ├── HttpTestCase.php         # Helper for issuing requests through the kernel
│   ├── Factories/
│   │   ├── UserFactory.php
│   │   └── ProductFactory.php
│   └── Fixtures/
│       └── seed.sql
│
└── bootstrap.php            # Loads vendor autoload, .env.testing, runs migrations
```

The test tree mirrors `src/`: `tests/Unit/Products/` corresponds 1:1 with `src/Products/`; `tests/Integration/Database/Mysql/` mirrors `src/Database/Mysql/`. Service tests live under their owning module (`tests/Integration/Products/PurchaseServiceTest.php`). The `tests/Feature/Web/` and `tests/Feature/Api/` split is by transport (kernel-level), not by source-folder layout.

## Test pyramid (target shape)

```
              ▲
              │   Feature (~15 tests)        end-to-end through kernel
              │
              │   Integration (~25 tests)    repositories, services, concurrency
              │
              │   Unit (~80 tests)           controllers, validators, router, hashers
              ▼
```

We invest most heavily in unit tests for **input → output** logic (validators, router, JWT) using `#[DataProvider]`, plus the dedicated controller-mocking layer required by Req #15. Integration tests cover everything that touches the database. Feature tests prove the seams hold together; we don't need 200 of them.

## Three test layers, three rules

### 1. Unit tests — **prefer real collaborators; mock only where Req #15 requires it or at genuine external boundaries**

Default: instantiate real classes. Mocks lie when the real thing changes, so we limit them to two situations:
- **The controller-unit-test layer** — Req #15 explicitly requires DI + mocking so controller tests don't touch the database. This is *one* dedicated test class per controller (`Products\ProductsControllerTest`, `Products\ProductsApiControllerTest`).
- **Genuinely external/non-deterministic boundaries** — clock, randomness, JWT signer when we want determinism.

Validators, hashers, route collectors, the router, JSON envelopes, exceptions — all unit-tested with **real instances**, no mocks. PDO is **not mocked**; data-access logic is exercised by integration tests (next layer down).

Concrete example for Req #15: `ProductsController` receives a `Database\Mysql\ProductRepository`, a `Validator`, and a `Clock`. The dedicated controller unit-test class instantiates the controller with `Mockery::mock(ProductRepository::class)`, sets expectations (`shouldReceive('findById')->with(42)->andReturn($fakeProduct)`), invokes the method, and asserts the response.

**Crucial pairing:** every controller method that has a mocked unit test also has a parallel feature test that hits a real DB. The feature test is the source of truth; the mocked unit test proves the wiring + branching is right.

### 2. Integration tests — **hit real MySQL, no HTTP**

Repositories and services that orchestrate SQL run against a real `vending_test` database. Mocked DBs hide ordering bugs, type-coercion bugs, transaction-isolation bugs, and constraint-violation bugs — exactly the things that break in production. Each test wraps its work in a transaction that rolls back, so the DB stays clean and tests parallelise safely.

`PurchaseServiceConcurrencyTest` deserves a special call-out: it spawns two PDO connections in the same test, has both call `purchase()` against a product with `quantity_available = 1`, and asserts that exactly one succeeds.

### 3. Feature tests — **full kernel, real router, real middleware, mocked clock only**

These boot the kernel, send a synthetic request, and assert on the response. They cover the integration of:
- routing (Req #10, #11)
- middleware (auth, RBAC — Req #3, #4)
- controllers (Req #5)
- views (Req #7, #8, #9) — assert on rendered HTML structure, not exact text
- API responses (Req #16) — assert JSON structure
- JWT (Req #17) — issue a token, hit a protected endpoint, assert 200; replay an expired token, assert 401

DB is real. External time/randomness is mocked via injected `Clock` and `IdGenerator` services so token `iat`/`jti` are deterministic.

## Table-driven tests by default

For any **input → output** logic — validators, parsers, formatters, route matchers, JWT claim checks, price arithmetic — use PHPUnit's `#[DataProvider]` attribute instead of writing N near-identical methods.

```php
#[DataProvider('priceValidationCases')]
public function testPriceValidation(mixed $input, bool $expectedValid, ?string $expectedError): void
{
    // arrange + act + assert
}

public static function priceValidationCases(): iterable
{
    yield 'positive decimal'    => [3.99,  true,  null];
    yield 'three-decimal price' => [6.885, true,  null];
    yield 'zero rejected'       => [0,     false, 'price must be > 0'];
    yield 'negative rejected'   => [-1.0,  false, 'price must be > 0'];
    yield 'string rejected'     => ['abc', false, 'price must be numeric'];
}
```

Use **named keys** (`'positive decimal'`, not `'0'`, `'1'`…) so failures point to the offending case immediately.

Stateful flows (login → access → logout, full purchase) stay as discrete test methods with AAA structure; data providers are the wrong shape there.

## Required test scenarios (mapped to requirements)

### Auth & RBAC (Req #3, #4)

- `login` with correct credentials → session populated, redirect to `/products`.
- `login` with wrong password → no session, error flash.
- `login` with non-existent user → indistinguishable error from wrong password (no user enumeration).
- `password_verify` is called against `password_hash` — never plaintext comparison.
- `User` accessing `/admin/products` → 403.
- `Admin` accessing `/admin/products` → 200.
- Logged-out user accessing `/products` → 302 to `/login`.

### `ProductsController` CRUD (Req #5, #7, #14, #15)

For each of `index`, `show`, `create`, `store`, `edit`, `update`, `destroy`:
- Happy path with mocked repository (Req #15) **and** a parallel feature test with the real repository.
- Validation failure path renders form with errors (Req #12, #13).
- 404 when product id doesn't exist.
- 403 when non-admin hits a write endpoint.
- Repository receives the expected method call with the expected arguments (verified via Mockery expectation in the unit test class).

### Listing (Req #8)

- Default page returns first N products.
- `?page=2` offsets correctly.
- `?sort=price&dir=asc` orders correctly.
- `?sort=evil_column` is rejected (allow-list — see [`03-database.md`](03-database.md)).
- `?page=999` returns empty list, not error.

(All four sorting/paging cases via one `#[DataProvider]`.)

### Purchasing (Req #6, #9)

- Purchase decrements `quantity_available`. **Integration, real DB.**
- Purchase writes a `transactions` row with snapshot price. **Integration, real DB.**
- Purchase fails cleanly when `quantity_available = 0`.
- Concurrent purchases against last unit: exactly one succeeds (`PurchaseServiceConcurrencyTest`).
- Purchase view shows current price + quantity.

### Validation (Req #12, #13)

- All required fields trigger errors when missing.
- `price = 0` → rejected.
- `price = -1` → rejected.
- `quantity_available = -1` → rejected.
- `name` too long → rejected.
- Validator returns *all* errors per submission, not just the first.

(All single-rule cases via `#[DataProvider]`. Multi-error case is its own test method.)

### Routing (Req #10, #11)

- `RouteCollector` discovers `#[Route]` attributes on a controller method.
- Multiple routes per method (e.g. GET + HEAD) compile correctly.
- Path params (`{id}`) extract and pass into the method as the right type.
- Unknown route → 404 from kernel.
- Disallowed method on a known path → 405.

(Match/no-match cases via `#[DataProvider]`.)

### REST API (Req #16, #17)

- `POST /api/auth/login` returns a valid JWT.
- Protected endpoint without `Authorization` header → 401.
- Protected endpoint with expired JWT → 401, with `WWW-Authenticate: Bearer error="invalid_token"`.
- Protected endpoint with tampered JWT signature → 401.
- `POST /api/products` as user (not admin) → 403.
- `POST /api/products/{id}/purchase` decrements stock and returns a transaction record.

(Token-validity cases via `#[DataProvider]` in `JwtAuthenticatorTest`.)

## TDD workflow (per global rules)

For every new piece of behaviour:
1. **RED** — write the failing test first.
2. **GREEN** — write the minimum code to pass it.
3. **IMPROVE** — refactor with the safety net of the green test.

Bug fixes follow the same loop: reproduce with a failing test first, then fix.

## CI hooks (planned)

Local pre-commit and CI run, in order:
1. `composer cs` (PSR-12)
2. `composer stan` (PHPStan level 8)
3. `composer test` (unit + integration + feature)
4. `composer test:cov` with `--coverage-text` and a fail threshold of 80%

A failure at any step blocks the commit / PR merge.

## What we do **not** test

- Third-party libraries (`firebase/php-jwt`, `phpdotenv`) — assumed correct.
- Trivial getters on readonly value objects.
- The framework wiring itself (`Container::resolve`) beyond a small smoke test — its correctness shows up in feature tests.
