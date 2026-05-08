# CLAUDE.md

Project-specific working agreement. Read this whenever you start a session in this repo. Authoritative design lives in [`plans/`](plans/) and [`requirements.md`](requirements.md); this file is the day-to-day cheat sheet.

## Project at a glance

- **Vending machine** — PHP 8.4 web app + REST API.
- Web side: session auth, server-rendered HTML.
- API side: JWT (HS256) bearer auth, JSON responses.
- MySQL 8 via PDO. No ORM. No framework.
- Tests: PHPUnit 13. Static analysis: PHPStan level 8.

## Read the plans before non-trivial work

| When you're about to…                                | Read first                                                              |
|------------------------------------------------------|-------------------------------------------------------------------------|
| Pick what to build next                              | [`plans/06-implementation-plan.md`](plans/06-implementation-plan.md)    |
| Add or change a route                                | [`plans/02-architecture.md`](plans/02-architecture.md) (route table)    |
| Touch SQL or migrations                              | [`plans/03-database.md`](plans/03-database.md)                          |
| Write or change tests                                | [`plans/04-testing-strategy.md`](plans/04-testing-strategy.md)          |
| Touch auth, validation, or anything sensitive        | [`plans/05-security.md`](plans/05-security.md)                          |
| Add a dependency                                     | [`plans/01-tech-stack.md`](plans/01-tech-stack.md)                      |

## Architecture rules (hard)

- **Hybrid layout**: domain modules + infrastructure layers + central templates.
- **Domain modules** — `src/Products/`, `src/Auth/`, `src/Users/`, `src/Transactions/`. Each owns its controllers, service, value object, validation rules, exceptions.
- **Infrastructure** — `src/Http/`, `src/Routing/`, `src/Database/Mysql/`, `src/Validation/`, `src/Config/`, `src/Support/`. Cross-cutting; never references any domain module.
- **Templates** live in `templates/`, mirroring URL structure. Never put views inside `src/`.
- **Repositories** live only in `src/Database/Mysql/`. Domain modules import them; HTTP and templates do not.
- **Web vs Api by filename suffix**, not folder: `ProductsController.php` (HTML/sessions) and `ProductsApiController.php` (JSON/JWT) — both at the module root.
- **Subfolder heuristic**: keep a subfolder only when it holds ≥ 2 cohesive files. Solo files sit at the module root.
- **PSR-4** `App\` → `src/`; class name matches file name exactly.
- **`Products\PurchaseService` is the only place that legitimately spans domain modules** (it imports `Users\User` and `Database\Mysql\TransactionRepository`). Any other cross-module import is a code-review red flag.

## Coding rules

- `declare(strict_types=1);` at the top of every PHP file.
- Typed properties and return types everywhere. PHPStan level 8 is the gate.
- **PSR-12** enforced by PHP-CS-Fixer.
- **Immutable value objects** (`Product`, `User`, `Transaction`) — `readonly` properties, no setters, no mutation.
- **No global state, no static mutable state, no singletons fetched statically.** Constructors take dependencies; the container resolves them.
- **Files**: ≤ 400 lines preferred, hard cap 800. Split if larger.
- **Functions**: ≤ 50 lines; nesting > 4 levels → use early returns.
- **Naming**: `camelCase` for variables/functions, `PascalCase` for classes/enums, `UPPER_SNAKE_CASE` for constants. Booleans use `is/has/should/can` prefix.
- **Default to no comments.** Add one only when the *why* is non-obvious; don't restate what well-named code already says.

## Database rules

- **All queries parameterized** with `:named` placeholders. Never concatenate values into SQL.
- `PDO::ATTR_ERRMODE => ERRMODE_EXCEPTION` is set on `Connection`. Don't disable it.
- **Sort columns must come from an allow-list** before reaching `ORDER BY`. No raw user input in column position.
- **Pagination**: integer-clamp `LIMIT` (max 100) and `OFFSET`.
- **Currency**: `DECIMAL(10, 3)` for prices, `bcadd`/`bcmul` for math. Never `(float)`.
- **Quantity**: `INT UNSIGNED` (DB-level guarantee against negatives).
- **Purchase** is a single SQL transaction with `SELECT … FOR UPDATE` + `UPDATE` + `INSERT` + `COMMIT`. See [`plans/03-database.md`](plans/03-database.md).
- **Migrations**: `db/migrations/NNNN_description.sql`. One logical change per file. Sequential.
- SQL queries: just write all sql queries in lowercase.

## Testing rules (TDD mandatory)

- **Write the failing test first.** RED → GREEN → IMPROVE.
- **Coverage**: ≥ 80% line; ≥ 90% on each module's controllers + service and on `src/Database/Mysql/`.
- **Default: real collaborators.** Mocks only at:
  - The **controller-unit-test layer** — Req #15. One dedicated test class per controller (`Products\ProductsControllerTest`, `Products\ProductsApiControllerTest`, etc.).
  - **Genuinely external boundaries** — clock, randomness, JWT signer when we want determinism.
- **Repositories and services** use real MySQL via `tests/Support/DatabaseTestCase` (per-test transaction rollback).
- **Table-driven by default** for input→output logic. Use `#[DataProvider]` with **named keys**, not positional.
- **Every controller method**: a mocked unit test **and** a parallel real-DB feature test. The feature test is the source of truth.
- `PurchaseServiceConcurrencyTest` runs 50× in CI. Don't skip it.

## Security rules (zero tolerance)

Build-breakers. Full audit baseline in [`plans/05-security.md`](plans/05-security.md).

- **Never log**: passwords (any form), JWTs (access or refresh), session IDs, full `Authorization` header, `/login` or `/api/auth/login` request bodies.
- **Always escape** HTML output via `e()`. No raw `<?= $var ?>` in templates.
- **All SQL parameterized.** Zero concatenation.
- **CSRF middleware** on every non-GET web request. Default-deny; opt-outs are reviewed.
- **Sessions**: cookies `HttpOnly; Secure; SameSite=Lax`. Rotate session ID on login (`session_regenerate_id(true)`).
- **JWT alg pinned to HS256.** Reject `none`, `RS256`, anything else.
- **Passwords**: `password_hash(PASSWORD_BCRYPT)` + `password_verify`. Never compare plaintext.
- **Login enumeration**: same generic error and response time for "user not found" vs "wrong password".
- **No secrets in code.** Validate required env vars at boot; fail fast.
- **Errors in production**: generic page/envelope only — no stack traces, class paths, or DB messages leak.

## Routing

- Declare routes inline with `#[Route(path: '...', methods: [...], name: '...')]` on the controller method.
- The collector reflects all registered controllers at boot. New controllers must be registered in `Bootstrap`.

## Validation

- **Server-side is the source of truth**; client-side is UX only.
- Per-feature rule sets live in the module (e.g. `Products\ProductValidationRules`).
- Validator collects **all** errors per submission, not just the first.

## Composer scripts (run often)

- `composer test` — full suite
- `composer test:cov` — with coverage report (≥ 80% gate)
- `composer stan` — PHPStan level 8
- `composer cs` — PHP-CS check
- `composer cs:fix` — auto-fix
- `composer migrate` — apply pending migrations
- `composer seed:admin` / `composer seed:products` — seed dev data
- `composer dev` — local dev server at http://localhost:8000

## Pre-commit / CI order

1. `composer cs`
2. `composer stan`
3. `composer test`
4. `composer test:cov` with the 80% threshold

A failure at any step blocks the commit.

## What NOT to do

- Don't add an ORM (Eloquent, Doctrine).
- Don't add a templating engine (Twig, Blade).
- Don't add a validation library (Respect, Symfony Validator).
- Don't add `psr/http-message` / `nyholm/psr7` without re-reading the side note in [`plans/01-tech-stack.md`](plans/01-tech-stack.md).
- Don't put views anywhere outside `templates/`.
- Don't put SQL anywhere outside `src/Database/Mysql/`.
- Don't co-locate views inside a domain module.
- Don't create a `Web/` or `Api/` subfolder for a single controller.
- Don't mutate value objects.
- Don't use `static` mutable state or magic singletons.
- Don't treat client-side validation as a control.
- Don't silence PHPStan with `@phpstan-ignore` without a one-line "why" comment.
- Don't compare passwords with `===` — only `password_verify`.
- Don't catch and swallow exceptions silently.
- Don't commit `.env`, coverage HTML, or local dumps.

## When in doubt

Read the plans first; ask before deviating. Plans are versioned design decisions; this file is shorthand.
