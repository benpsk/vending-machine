# Architecture

## Layout philosophy: hybrid (domain modules + infrastructure layers + central templates)

- **Domain code is grouped by feature/module** — `Products/`, `Users/`, `Auth/`, `Transactions/`. Each module owns its types, business logic, validation rules, and HTTP controllers (web + api).
- **Infrastructure is grouped by layer** — `Http/`, `Routing/`, `Database/Mysql/`, `Validation/`, `Config/`, `Support/`. These are cross-cutting concerns and engine-specific implementations. The `Mysql/` subfolder is the MySQL adapter; future adapters (e.g. `Postgres/`) would sit alongside it.
- **Views are centralised** in `templates/`, mirroring URL/feature structure. Presentation is its own concern, not part of any domain module.
- **Subfolders only when ≥ 2 logically grouped files.** Lone files don't earn a folder; the filename does the disambiguation. Web/Api are split by **filename suffix** (`ProductsController.php` vs `ProductsApiController.php`), not by directory.

This split keeps domain code locally cohesive (one feature, one folder), pulls cross-cutting infrastructure into clear layered locations, and lets a designer browse all templates without touching `src/`.

## Folder structure

```
vending-machine/
├── public/                              # Web root (only this is exposed by the server)
│   ├── index.php                        # Front controller — single entry point
│   └── assets/
│       ├── css/app.css
│       └── js/validation.js             # Client-side validation (Req #13)
│
├── src/                                 # PSR-4: App\
│   ├── Bootstrap.php                    # Builds DI container, loads modules, returns kernel
│   │
│   # ─── Domain modules ────────────────────────────────────────────────────
│   │
│   ├── Products/
│   │   ├── ProductsController.php               # web CRUD + purchase (Req #5–9)
│   │   ├── ProductsApiController.php            # api CRUD + purchase JSON (Req #16)
│   │   ├── Product.php                          # readonly value object
│   │   ├── ProductValidationRules.php           # rule set used by both controllers
│   │   ├── PurchaseService.php                  # txn-locked purchase (Req #6)
│   │   └── Exceptions/
│   │       ├── OutOfStockException.php
│   │       ├── ProductNotFoundException.php
│   │       └── InvalidQuantityException.php
│   │
│   ├── Users/
│   │   ├── User.php                             # readonly value object
│   │   └── Role.php                             # enum Role { case Admin; case User; }
│   │
│   ├── Auth/
│   │   ├── AuthController.php                   # web login / logout (Req #3)
│   │   ├── AuthApiController.php                # api POST /api/auth/login → JWT (Req #17)
│   │   ├── PasswordHasher.php                   # password_hash / password_verify wrapper
│   │   ├── SessionAuthenticator.php             # Req #3
│   │   ├── JwtAuthenticator.php                 # Req #17
│   │   └── Middleware/
│   │       ├── AuthSessionMiddleware.php        # web auth (Req #3)
│   │       ├── AuthJwtMiddleware.php            # api auth (Req #17)
│   │       └── RequireRoleMiddleware.php        # RBAC, reused by both (Req #4)
│   │
│   ├── Transactions/
│   │   └── Transaction.php                      # readonly value object
│   │
│   # ─── Infrastructure (layer-style) ───────────────────────────────────────
│   │
│   ├── Database/
│   │   └── Mysql/
│   │       ├── Connection.php                   # PDO factory (Req #2)
│   │       ├── Migrator.php                     # applies db/migrations/*.sql in order
│   │       ├── ProductRepository.php            # CRUD on `products`
│   │       ├── UserRepository.php               # CRUD on `users`
│   │       └── TransactionRepository.php        # purchase-log writer
│   │
│   ├── Http/
│   │   ├── Kernel.php                           # request → middleware → router → controller → response
│   │   ├── Request.php                          # our own (no PSR-7) — see 01-tech-stack.md
│   │   ├── Response.php                         # json/html/redirect helpers, immutable
│   │   ├── View.php                             # resolves + renders templates/ files with escaping
│   │   └── Middleware/
│   │       ├── MiddlewareInterface.php
│   │       ├── SessionStartMiddleware.php
│   │       └── CsrfMiddleware.php
│   │
│   ├── Routing/
│   │   ├── Route.php                            # the #[Route(...)] attribute (Req #11)
│   │   ├── RouteCollector.php                   # scans controllers, builds route table
│   │   ├── Router.php                           # matches request → controller method (Req #10)
│   │   └── RouteNotFoundException.php
│   │
│   ├── Validation/
│   │   ├── Validator.php                        # tiny rule runner (Req #12)
│   │   ├── ValidationException.php
│   │   └── Rules/
│   │       ├── Required.php
│   │       ├── Numeric.php
│   │       ├── IntegerRule.php
│   │       ├── Min.php
│   │       └── Max.php
│   │
│   ├── Config/
│   │   └── Config.php                           # typed config loaded from env at boot
│   │
│   └── Support/
│       ├── Container.php                        # tiny PSR-11-shaped DI container
│       ├── Csrf.php                             # CSRF token issue/verify
│       └── helpers.php                          # e()/url()/asset() — autoloaded via "files"
│
├── templates/                            # All views, central, mirrors URL/feature structure
│   ├── layout.php                        # shared layout used by every page
│   ├── partials/
│   │   ├── flash.php
│   │   ├── pagination.php
│   │   └── form-errors.php
│   ├── auth/
│   │   └── login.php
│   └── products/
│       ├── index.php                     # list + pagination + sort (Req #8)
│       ├── show.php
│       ├── purchase.php                  # Req #9
│       └── admin/
│           ├── create.php
│           ├── edit.php
│           └── delete.php
│
├── db/
│   ├── migrations/
│   │   └── 0001_initial_schema.sql       # all three tables in one file
│   └── seeds/
│       ├── 0001_seed_admin_user.sql
│       └── 0002_seed_products.sql        # Coke, Pepsi, Water
│
├── tests/                                # See 04-testing-strategy.md
│   ├── Unit/
│   ├── Integration/
│   ├── Feature/
│   └── Support/
│
├── plans/                                # This folder
├── bin/
│   └── migrate.php                       # CLI: applies pending migrations
├── .env.example
├── .env                                  # gitignored
├── .gitignore
├── composer.json
├── composer.lock
├── phpunit.xml
├── phpstan.neon
├── phpcs.xml
├── README.md
└── requirements.md
```

## Why this layout

- **`public/` is the *only* directory served by the web server.** Source, config, secrets, templates, and tests sit one level up.
- **`src/` is one PSR-4 root** (`App\`), so paths and namespaces line up: `App\Products\ProductsController` → `src/Products/ProductsController.php`; `App\Database\Mysql\ProductRepository` → `src/Database/Mysql/ProductRepository.php`.
- **Domain modules own their full vertical slice** — both controllers (web + api), the model, the validation rule set, the service. They contain no SQL and no HTML.
- **Web vs Api separation is by filename**, not folder. `ProductsController.php` returns HTML and runs under session middleware; `ProductsApiController.php` returns JSON and runs under JWT middleware. Same module, same business logic, two thin transport layers.
- **Repositories live in `src/Database/Mysql/`** as concrete classes. Domain modules import them directly. This is the simpler "Option A" of the two extraction styles considered (the alternative — interface in domain, impl in infrastructure — adds files for no concrete benefit while we only have one DB engine).
- **Views live in `templates/`** because presentation is its own concern. The `templates/` tree mirrors URL/feature paths so a designer can find any page without touching `src/`. Controllers reference templates by name through `Http\View` (`View::render('products/index', $data)` → `templates/products/index.php`).
- **Tiny DI container** (~100 lines, reflection-based autowiring) instead of pulling in a heavy one. Enough for this scale.
- **Subfolder rule:** keep a subfolder when it groups ≥ 2 cohesive files (`Auth\Middleware/`, `Products\Exceptions/`, `Database\Mysql/`); flatten when it would only hold one (which is why `Web/` and `Api/` no longer exist as folders).

### Cross-layer dependency rules

A hybrid layout fails fast if the wrong layer reaches into another. The rules:

- **Domain modules may import from infrastructure** (`Database/Mysql/`, `Http/`, `Validation/`). The reverse is forbidden — `src/Http/` and `src/Database/Mysql/` know nothing about `Products/` or `Auth/`.
- **`Products\PurchaseService` is the only place that legitimately spans domain modules** — it imports `Users\User` (passed in from the authenticated request) and `Database\Mysql\TransactionRepository`. The purchase flow is inherently cross-cutting.
- **No module imports another module's controllers.** Controllers are leaves.
- **No code imports from `templates/`.** Templates are loaded by name through `Http\View`, never directly.
- **If a circular dependency emerges, extract a third module** (e.g. `Catalog/`, `Billing/`) rather than relax the rule.

## Request lifecycle

```
Browser request
   │
   ▼
public/index.php  (front controller)
   │  loads vendor/autoload.php, .env, builds container
   ▼
Bootstrap::create()  →  Kernel
   │
   ▼
Kernel::handle($request)
   │
   ├── 1. Run middleware pipeline (in order):
   │      SessionStart  →  Csrf  →  AuthSession (or AuthJwt for /api/*)
   │
   ├── 2. Router::match($request)
   │      reads cached route table built from #[Route] attributes
   │      across every registered controller in every module
   │      returns (controller class, method, path params)
   │
   ├── 3. Route-level middleware (RequireRole, etc.)
   │
   ├── 4. Container resolves controller, injects dependencies
   │      (controllers receive their concrete repositories from Database\Mysql\
   │       and any domain services they need)
   │
   ├── 5. Controller method runs:
   │      - HTML controller (e.g. ProductsController): returns Response with HTML
   │        rendered via Http\View (templates/<feature>/<name>.php)
   │      - JSON controller (e.g. ProductsApiController): returns Response with
   │        a JSON envelope
   │
   ▼
Response::send()  →  emits headers + body to client
```

## Routing (Reqs #10, #11)

`#[Route]` is a PHP 8 attribute defined in `src/Routing/Route.php`. Controllers declare routes inline:

```
#[Route('/products', methods: ['GET'], name: 'products.index')]
public function index(Request $r): Response { ... }

#[Route('/products/{id}/purchase', methods: ['POST'], name: 'products.purchase')]
public function purchase(Request $r, int $id): Response { ... }
```

`RouteCollector` does **one** scan at boot (cached per-environment): it iterates the registered controller list (every controller class across every module), reflects each public method, collects `#[Route]` attributes, and builds a route table keyed by `(method, path-pattern)`. Path params are extracted via a compiled regex.

The full route surface (planned):

| Method | Path                              | Controller::method                                   | Auth          |
|--------|-----------------------------------|------------------------------------------------------|---------------|
| GET    | `/`                               | (root) — small `HomeController` in `src/Http/`       | public        |
| GET    | `/login`                          | `Auth\AuthController::showLogin`                     | public        |
| POST   | `/login`                          | `Auth\AuthController::login`                         | public        |
| POST   | `/logout`                         | `Auth\AuthController::logout`                        | session       |
| GET    | `/products`                       | `Products\ProductsController::index`                 | session       |
| GET    | `/products/{id}`                  | `Products\ProductsController::show`                  | session       |
| GET    | `/products/{id}/purchase`         | `Products\ProductsController::purchaseForm`          | session       |
| POST   | `/products/{id}/purchase`         | `Products\ProductsController::purchase`              | session       |
| GET    | `/admin/products`                 | `Products\ProductsController::adminIndex`            | session+admin |
| GET    | `/admin/products/create`          | `Products\ProductsController::create`                | session+admin |
| POST   | `/admin/products`                 | `Products\ProductsController::store`                 | session+admin |
| GET    | `/admin/products/{id}/edit`       | `Products\ProductsController::edit`                  | session+admin |
| POST   | `/admin/products/{id}`            | `Products\ProductsController::update`                | session+admin |
| POST   | `/admin/products/{id}/delete`     | `Products\ProductsController::destroy`               | session+admin |
| POST   | `/api/auth/login`                 | `Auth\AuthApiController::login`                      | public        |
| GET    | `/api/products`                   | `Products\ProductsApiController::index`              | jwt           |
| GET    | `/api/products/{id}`              | `Products\ProductsApiController::show`               | jwt           |
| POST   | `/api/products`                   | `Products\ProductsApiController::store`              | jwt+admin     |
| PUT    | `/api/products/{id}`              | `Products\ProductsApiController::update`             | jwt+admin     |
| DELETE | `/api/products/{id}`              | `Products\ProductsApiController::destroy`            | jwt+admin     |
| POST   | `/api/products/{id}/purchase`     | `Products\ProductsApiController::purchase`           | jwt           |

## Auth & RBAC (Reqs #3, #4)

- **Web (sessions):** `Auth\SessionAuthenticator` writes `$_SESSION['user_id']` and `$_SESSION['role']` on login. `Auth\Middleware\AuthSessionMiddleware` rehydrates a `Users\User` from `Database\Mysql\UserRepository` on each request and attaches it to the `Request`. `Auth\Middleware\RequireRoleMiddleware('admin')` gates admin routes.
- **API (JWT):** `Auth\JwtAuthenticator` issues tokens on `POST /api/auth/login`. `Auth\Middleware\AuthJwtMiddleware` parses the `Authorization: Bearer …` header, verifies signature + expiry, loads the user. The same `RequireRoleMiddleware` is reused — it doesn't care how the user got there.
- **Passwords:** stored only as bcrypt hashes via `password_hash(PASSWORD_BCRYPT)`. Verified with `password_verify`. No plaintext, ever, anywhere.

## Validation (Reqs #12, #13)

- **Server-side:** `src/Validation/` provides the rule runner. Per-feature rule sets live in their module — e.g. `Products\ProductValidationRules`. `ProductsController::store/update` calls it with:
  ```
  name              => required|string|max:100
  price             => required|numeric|min:0.001
  quantity_available=> required|integer|min:0
  ```
  On failure it throws `ValidationException`, caught by the kernel, which re-renders the form with the errors and the user's input.
- **Client-side:** `public/assets/js/validation.js` mirrors the same rules with HTML5 `required`, `min`, `step` attributes plus a small JS layer for error messages. Server is the source of truth.

## Design patterns used (and why)

| Pattern              | Used for                                               | Why this one                                                  |
|----------------------|--------------------------------------------------------|---------------------------------------------------------------|
| **Front Controller** | `public/index.php`                                     | Single entry point; uniform middleware + routing.             |
| **Domain modules + infra layers** | `src/Products/`, `src/Auth/`, `src/Database/Mysql/`, etc. | Locality of behaviour for features; layered organisation for cross-cutting infrastructure. |
| **Repository**       | `src/Database/Mysql/*Repository.php`                   | Testability (Req #15); SQL stays in one place per table.      |
| **Service Layer**    | `Products\PurchaseService`                             | Multi-step ops (lock row, decrement, insert tx) belong above repos. |
| **Dependency Injection** | Constructors everywhere, container at the edge     | Req #15 hard-requires it. Also keeps controllers pure.        |
| **Middleware pipeline** | Auth, CSRF, session, role checks                    | Composable, ordered, easy to extend (rate limiting, etc.).    |
| **Strategy via attributes** | `#[Route]` declarations                         | Req #11.                                                      |

## Conventions

- **PSR-4** namespace `App\` → `src/`.
- **PSR-12** code style enforced by PHP-CS-Fixer.
- **Strict types** at the top of every file: `declare(strict_types=1);`.
- **Typed properties + return types** everywhere. PHPStan level 8.
- **Immutability** for value objects (`Product`, `User`, `Transaction` use `readonly` properties — they're snapshots, not domain entities with behaviour).
- **No global state.** No `static` mutable state, no singletons reached via static calls. The container is passed; nothing is fetched magically.
- **Files ≤ 400 lines preferred, 800 max.** Repositories may approach 400 due to SQL volume; controllers should stay under 200.
- **View files use `.php` extension** (not `.phtml`) and live in `templates/`, addressed by feature path (`templates/products/index.php` is rendered by `View::render('products/index', …)`).
- **Subfolders only when ≥ 2 cohesive files.** Solo files live in their module root with a clearly-suffixed filename (`*ApiController.php`, `*Service.php`, etc.).
