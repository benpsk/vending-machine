# Tech Stack

## Language & runtime

| Component   | Choice           | Reason                                                                 |
|-------------|------------------|------------------------------------------------------------------------|
| PHP         | **8.4**          | Native attribute support (Req #11), enums, readonly props, property hooks, asymmetric visibility, typed everything. |
| Web server  | PHP built-in for dev, Nginx + PHP-FPM for prod | Built-in server (`php -S`) is enough for local + tests. |
| Database    | **MySQL 8.0+**   | Required by `requirements.md`. 8.0 gives us `SELECT … FOR UPDATE` with proper row locking and CTEs. |
| Package mgr | **Composer 2.x** | Standard. PSR-4 autoloading.                                           |

## Framework decision

**No framework — custom minimal MVC.**

The requirements explicitly walk through each layer (PDO connection class, session auth, router, controllers, views, attribute routing, JWT). Pulling in Laravel/Symfony would skip past the very things the spec asks us to build. We'll use focused libraries instead of one big framework.

Alternatives considered and rejected (for now):

| Alternative   | Why rejected                                                         |
|---------------|----------------------------------------------------------------------|
| **Laravel**   | Eloquent replaces PDO (Req #2). Built-in auth replaces Req #3, #4. Too much would be hidden. |
| **Symfony**   | Same — Doctrine + Security bundle would invalidate the learning intent of Reqs #1–#4. |
| **Slim 4**    | Closest fit; we'd still write nearly all the same code. Acceptable as a fallback if a custom router becomes painful, but defaulting to none. |
| **CodeIgniter** | Outdated patterns; doesn't help with attribute routing.            |

## Direct dependencies (Composer packages)

| Package                          | Purpose                                  | Notes                                  |
|----------------------------------|------------------------------------------|----------------------------------------|
| `firebase/php-jwt`               | JWT encode/decode for API auth (Req #17) | De facto standard, well audited.       |
| `vlucas/phpdotenv`               | `.env` loading                           | Keeps secrets out of code.             |

HTTP request/response handling uses our own `App\Http\Request` / `App\Http\Response` (in `src/Http/`). No ORM, no validation library, no templating engine — Req #12 is small enough to write directly, and native `.php` templates with explicit `htmlspecialchars()` escaping cover the views.

> **Side note — when to revisit PSR-7**
>
> Add `psr/http-message` (the PSR-7 interfaces) and an implementation such as `nyholm/psr7` if/when:
> - We pull in third-party **PSR-15 middleware** — CORS, rate limiting, request-id, token-bucket throttling, body parsing, etc. Most modern PHP middleware targets PSR-7/15 directly.
> - We adopt or migrate to a **framework that speaks PSR-7 natively** (Slim 4, Mezzio, Symfony via bridge).
> - We integrate **HTTP testing utilities** that expect PSR-7 messages.
>
> Migration cost is small: write an adapter from `App\Http\Request` to `Psr\Http\Message\ServerRequestInterface` (~30 lines) and wire it into the kernel.

## Dev dependencies

| Package                    | Purpose                                                  |
|----------------------------|----------------------------------------------------------|
| `phpunit/phpunit` ^13      | Unit + integration tests (Req #14)                       |
| `mockery/mockery` ^1.6     | Cleaner mocks than PHPUnit's built-in for collaborators (Req #15) — used sparingly, see [`04-testing-strategy.md`](04-testing-strategy.md) |
| `phpstan/phpstan` ^1.10    | Static analysis at level 8                               |
| `squizlabs/php_codesniffer`| PSR-12 enforcement                                       |
| `friendsofphp/php-cs-fixer`| Auto-formatting                                          |

## Frontend

- **Native PHP templates** (`.php` files) — no Twig, no Blade. The spec talks about "PHP views" directly.
- **Vanilla JS + a small `validation.js` helper** for client-side validation (Req #13). No React/Vue. The forms are simple enough.
- **Plain CSS** in a single `public/assets/css/app.css`. No Tailwind setup overhead.

## JWT specifics (Req #17)

- Algorithm: **HS256** (symmetric, simpler ops than RS256 for a single-service deployment).
- Secret: `JWT_SECRET` env var, ≥32 random bytes, generated at deploy time.
- Token lifetime: **15 minutes access** + **7-day refresh** (refresh stored hashed in DB).
- Claims: `sub` (user id), `role` (`admin`|`user`), `iat`, `exp`, `jti`.
- Transport: `Authorization: Bearer <token>`.

## Tooling & scripts

`composer.json` will expose:
- `composer test` → PHPUnit
- `composer test:cov` → PHPUnit with coverage report (HTML + text)
- `composer stan` → PHPStan level 8
- `composer cs` → PHP_CodeSniffer check
- `composer cs:fix` → CS Fixer

## Environment & configuration

- `.env` for local secrets (gitignored).
- `.env.example` checked in with placeholder values.
- Config loaded once at bootstrap into a typed `Config` object. No `getenv()` scattered through code.

Required env vars:
```
APP_ENV=local|test|production
APP_DEBUG=true|false
DB_HOST=
DB_PORT=3306
DB_NAME=
DB_USER=
DB_PASSWORD=
JWT_SECRET=
JWT_TTL_SECONDS=900
SESSION_NAME=VENDING_SID
```

## Versions to pin

```
php: ^8.4
mysql: ^8.0
composer: ^2.7
phpunit: ^13.0
```

These are locked in `composer.json` `require` / `require-dev` and `composer.lock` is committed.
