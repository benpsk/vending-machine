# Security

This document is the **security audit baseline** for the vending machine system. Every threat, control, and pre-deploy check listed here is verified before code ships. The implementation plan ([`06-implementation-plan.md`](06-implementation-plan.md)) references this document for every security-touching deliverable; the test plan ([`04-testing-strategy.md`](04-testing-strategy.md)) covers the failure-path cases for each control here.

## Threat model

### Assets we're protecting

- **User credentials** — passwords (bcrypt at rest), session IDs, JWTs.
- **Product inventory** — price tampering, stock manipulation.
- **Transaction history** — financial integrity (immutable purchase log).
- **Admin privileges** — escalation here = full DB write access.

### Trust boundaries

- **Browser ↔ web app** — untrusted; validate everything, escape all output.
- **API client ↔ web app** — untrusted; JWT-authenticated.
- **Web app ↔ MySQL** — trusted within the perimeter; still parameterized SQL only.
- **Admin ↔ user roles** — enforced server-side only; UI is a hint.

### Attackers in scope

- Anonymous external attacker (highest likelihood).
- Authenticated regular user attempting privilege escalation.
- Compromised user account (lateral movement is limited — single tier).

### Out of scope

- State-level adversary, hardware compromise, supply-chain attack on the OS.
- DDoS / volumetric attacks (mitigated at the infrastructure layer).
- Encryption-at-rest of the DB volume (handled by the host / DBaaS).
- Social engineering / phishing of admins.

## OWASP Top 10 (2021) mapping

| # | Item                              | Risk for us | Mitigation                                                                                       | Verified by                          |
|---|-----------------------------------|-------------|--------------------------------------------------------------------------------------------------|--------------------------------------|
| A01 | Broken Access Control           | High        | `RequireRoleMiddleware`; every admin action gated server-side; route table reviewed.             | RBAC feature tests                   |
| A02 | Cryptographic Failures          | Medium      | bcrypt for passwords; HS256 for JWT; HTTPS in prod; no plaintext credentials at rest.            | Unit tests; manual review            |
| A03 | Injection                       | High        | PDO parameterized queries; sort allow-list; `htmlspecialchars` on output; CSP.                   | Repository tests; XSS test cases     |
| A04 | Insecure Design                 | Medium      | Threat model documented here; `FOR UPDATE` locking on purchase; no business-logic-bypass paths.  | Concurrency test; design review      |
| A05 | Security Misconfiguration       | Medium      | Security headers; debug off in prod; `.env` never committed; least-privilege DB user.            | Header tests; deploy checklist       |
| A06 | Vulnerable Components           | Medium      | `composer audit` in CI; pinned versions; quarterly review.                                       | CI gate                              |
| A07 | Identification & Auth Failures  | High        | Rate limiting; secure session cookies; session-fixation protection; no user enumeration.         | Auth feature tests                   |
| A08 | Software & Data Integrity       | Low         | `composer.lock` committed; no remote code loading; no auto-update.                               | n/a                                  |
| A09 | Logging & Monitoring Failures   | Low         | Errors logged with context; no PII / no tokens in logs; failed-login log line.                   | Manual review                        |
| A10 | Server-Side Request Forgery     | n/a         | App makes no outbound HTTP requests driven by user input.                                        | n/a                                  |

## Specific controls

### Authentication (Req #3)

- **Passwords** — stored only as `password_hash($pw, PASSWORD_BCRYPT)`. Compared with `password_verify`. Never logged, never echoed, never sent over the wire after the initial submission.
- **Sessions** — cookie flags `HttpOnly; Secure; SameSite=Lax`. Custom session name (`SESSION_NAME` env). **Session ID rotated on login** via `session_regenerate_id(true)` to prevent fixation.
- **Login enumeration** — same generic error for "user not found" and "wrong password". Same response timing (`password_verify` runs against a dummy hash if the user is missing, so total time is comparable).
- **Rate limiting** — 5 failed attempts per IP per 15 minutes on `/login` and `/api/auth/login`. Lockout returns `429 Too Many Requests` with `Retry-After`. Counter resets on successful login.

### Authorization / RBAC (Req #4)

- Two roles: `admin`, `user` — stored in `users.role`, also cached in session for fast checks.
- **Every admin route** is gated by `RequireRoleMiddleware('admin')`. Tests assert 403 for non-admin on every admin path.
- **Server-side check is the source of truth.** Hiding a link in the UI is a UX nicety, never a control.
- API and web reuse the same `RequireRoleMiddleware`; it doesn't care whether identity came from a session or a JWT.
- No "remember me" or persistent web login (out of scope per requirements).

### JWT (Req #17)

- **Algorithm pinned** — HS256 only. Library configured to **reject** incoming tokens with `alg: none`, `RS256`, or any other value. This blocks the classic algorithm-confusion attack.
- **Required claims** — `sub`, `role`, `iat`, `exp`, `jti`. Missing any → reject. Unknown extra claims → ignored, not trusted.
- **TTL short** — 15 minutes for access tokens; 7 days for refresh tokens. Refresh tokens are stored **hashed** in DB so they can be revoked.
- **Secret** — ≥32 random bytes from `JWT_SECRET` env var. Never hardcoded, never logged.
- **Rotation** — rotating `JWT_SECRET` invalidates all access tokens (acceptable due to short TTL); refresh tokens get a new signing key via versioned `kid` header.
- **No tokens in URLs.** Bearer header only. Logs strip the `Authorization` value.

### Input validation (Reqs #12, #13)

- **Server is the source of truth.** Client-side validation is UX only.
- All controller parameters are strictly typed (PHP 8 `declare(strict_types=1)` everywhere).
- Currency arithmetic uses `bcadd` / `bcmul` — never `(float)` casting. `total_amount` snapshot stored as `DECIMAL`.
- String fields enforce `max` length; integer fields enforce `min: 0` (or `> 0` where required). `name` is rejected at >100 chars.
- Validation errors are returned as a *list*, not just the first failure, so users fix the form once.

### Output encoding (XSS prevention)

- All user-controlled values pass through `htmlspecialchars($v, ENT_QUOTES | ENT_HTML5, 'UTF-8')` before reaching HTML.
- Helper `e()` (in `src/Support/helpers.php`) is the only escape path; `.php` view files **never echo raw `$variables`** — code review rejects any `<?= $x ?>` without `e()`.
- JSON output via `json_encode(JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)`. Never built by string concatenation.
- **CSP (production)**:
  ```
  Content-Security-Policy:
    default-src 'self';
    script-src 'self';
    style-src 'self';
    img-src 'self' data:;
    object-src 'none';
    base-uri 'self';
    frame-ancestors 'none';
  ```

### CSRF

- `CsrfMiddleware` runs on **every non-GET request under web routes** (`/login`, `/products/*`, `/admin/*`). Default-deny: opt-out is explicit and reviewed.
- Token issued in session on first GET, embedded as a hidden form input (`partials/form-errors.php` and admin forms include it).
- Token verified on POST; mismatch → 403, no body leakage.
- API routes (`/api/*`) use JWT bearer auth, not cookies → no CSRF surface (browsers don't auto-attach `Authorization`).

### SQL injection (Req #2)

- All queries use PDO prepared statements with **named** parameters (`:id`, not `?`). Zero string concatenation in SQL.
- Sort columns and direction validated against an **allow-list** before being placed in `ORDER BY` (the only place where a column name is dynamic).
- Pagination `LIMIT` / `OFFSET` are integers, clamped: `min: 1`, `max page size: 100`.
- `PDO::ATTR_ERRMODE => ERRMODE_EXCEPTION`. No silent failures.

### Concurrency (Req #6)

- Purchase is wrapped in a single SQL transaction with row-level locking:
  ```
  BEGIN;
    SELECT … FROM products WHERE id = :id FOR UPDATE;
    -- app verifies quantity, ROLLBACK on insufficient stock
    UPDATE products SET quantity_available = quantity_available - :qty WHERE id = :id;
    INSERT INTO transactions (…) VALUES (…);
  COMMIT;
  ```
- `quantity_available` column is `INT UNSIGNED` — DB-level guarantee against negative values as a backstop.
- Concurrency integration test runs **50× in CI** to catch races deterministically.

### Secrets management

- All secrets in `.env`, gitignored. `.env.example` carries placeholders only.
- Required secrets validated at boot (`Config::__construct` throws on a missing value — fail fast, not first-request).
- Logged errors include **no env values, no headers, no request bodies for `/login` or `/api/auth/login`**.
- Rotation policy:
  - `JWT_SECRET` — rotated immediately on suspected compromise; users must re-login (refresh tokens get a new `kid`).
  - `DB_PASSWORD` — rotated quarterly.
  - `SEED_ADMIN_PASSWORD` — must be changed on the first deploy and never reused.

### Transport security (production)

- HTTPS only. HTTP redirects to HTTPS at the reverse proxy.
- HSTS: `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`.
- Cookies always include `Secure`; the cookie code refuses to issue without it in production.

### Security headers (production)

```
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
Content-Security-Policy: <see CSP block above>
```

### Error handling

- **Production** — generic 500 page (HTML) or generic error envelope (JSON). No stack traces, no class paths, no DB messages reach the client.
- **Development** — full trace and context. Toggled by `APP_DEBUG` env.
- **All errors logged with**: timestamp, request id, user id (if any), path, status, exception class.
- **Errors NEVER log**: passwords, JWTs, refresh tokens, full session contents, full `Authorization` header.

### Logging

- Append-only log files. The application:
  - Logs every failed login: timestamp, IP, **username** (yes — for rate-limit + audit; usernames aren't secret), `failure_reason` class.
  - Logs every successful purchase: user id, product id, qty, transaction id.
  - Does **not** log: passwords (hashed or plaintext), JWTs (header or refresh), session IDs, full request bodies for any auth endpoint.
- Log lines must be parseable (key=value or JSON), one event per line.

### Dependencies

- `composer audit` runs in CI; failure blocks merge.
- Versions pinned in `composer.json`; `composer.lock` committed and treated as source of truth.
- Quarterly review: regenerate lockfile, run full test suite, re-audit.
- Packages installed only from packagist.org by default; any other source requires a code-review note.

## Pre-deploy security checklist

Before any deploy to production:

- [ ] `composer audit` clean.
- [ ] `APP_DEBUG=false` and `APP_ENV=production`.
- [ ] All env secrets present and **not equal to `.env.example` placeholders**.
- [ ] HTTPS terminated at reverse proxy; HSTS header verified live.
- [ ] CSP header verified live; no `unsafe-inline` / `unsafe-eval` slipped in.
- [ ] Database user has only `SELECT/INSERT/UPDATE/DELETE` on the app schema — **no `DROP`, no `ALTER`, no `GRANT`**.
- [ ] No `var_dump` / `print_r` / `xdebug_*` / `dd(` calls in `src/`.
- [ ] All `// TODO security` markers resolved or moved to a tracked issue.
- [ ] Pen test or external scan run since last release (annual minimum).
- [ ] Backup + restore drill performed in the last 90 days.

## Per-PR security review triggers

Any PR that touches the following files **must** be reviewed by a second person specifically for security:

- `src/Auth/**`
- `src/Database/Mysql/**` (any SQL change)
- `src/Http/Middleware/**`
- `src/Routing/**` (route additions or changes)
- `db/migrations/**`
- Anything touching session config, password handling, JWT, or RBAC.

The reviewer signs off in the PR description with: "Security review: <name>, scope: <files>, findings: <none | list>".

## References

- OWASP Top 10 (2021): <https://owasp.org/Top10/>
- Project requirements: [`../requirements.md`](../requirements.md)
- Database concurrency model: [`03-database.md`](03-database.md)
- JWT specifics: [`01-tech-stack.md`](01-tech-stack.md)
- Test scenarios for security: [`04-testing-strategy.md`](04-testing-strategy.md)
- Phased rollout: [`06-implementation-plan.md`](06-implementation-plan.md)
