# Vending Machine — Plan Overview

## Purpose

This `plans/` folder is the complete, code-free design for the PHP vending machine system described in [`../requirements.md`](../requirements.md). It locks in the tech stack, folder layout, database schema, testing approach, security audit baseline, and a phased implementation plan **before** any code is written.

## What we're building (one-paragraph recap)

A PHP web application + REST API for a vending machine. Admins manage product inventory (CRUD); regular Users browse a paginated/sortable product list and purchase items. Every purchase decrements stock and writes a transaction record. The web side uses session-based auth; the API uses JWT. The system must be tested with PHPUnit at ≥80% coverage and written with explicit dependency injection so controller tests can mock the database.

## Domain model (from requirements)

A **Product** has:
- `ID` (int)
- `Name` (string)
- `Price` (decimal — note: Pepsi at **6.885 USD** forces 3 decimal places)
- `QuantityAvailable` (int, ≥ 0)

Seed data:
| Name  | Price (USD) |
|-------|-------------|
| Coke  | 3.99        |
| Pepsi | 6.885       |
| Water | 0.50        |

## Requirement-to-plan mapping

Every numbered task in `requirements.md` is covered by one or more plan documents:

| Req # | Topic                                      | Covered in                                              |
|-------|--------------------------------------------|---------------------------------------------------------|
| 1     | DB design (tables, fields, relationships)  | [`03-database.md`](03-database.md)                      |
| 2     | PDO connection class + CRUD                | [`02-architecture.md`](02-architecture.md), [`06-implementation-plan.md`](06-implementation-plan.md) Phase 1 |
| 3     | Session auth + password hashing            | [`05-security.md`](05-security.md), [`06-implementation-plan.md`](06-implementation-plan.md) Phase 2 |
| 4     | Role-based access control (Admin/User)     | [`02-architecture.md`](02-architecture.md), [`05-security.md`](05-security.md), Phase 2 |
| 5     | `ProductsController` CRUD                  | [`02-architecture.md`](02-architecture.md), Phase 3     |
| 6     | Purchase action (stock + transaction log)  | [`03-database.md`](03-database.md), [`05-security.md`](05-security.md), Phase 4 |
| 7     | CRUD views, admin-gated                    | [`02-architecture.md`](02-architecture.md), Phase 3     |
| 8     | Product list view + pagination + sorting   | Phase 3                                                 |
| 9     | Purchase view                              | Phase 4                                                 |
| 10    | Routing for ProductsController             | [`02-architecture.md`](02-architecture.md), Phase 1     |
| 11    | Attribute routing (`#[Route(...)]`)        | [`02-architecture.md`](02-architecture.md), Phase 1     |
| 12    | Form validation (required, price>0, qty≥0) | [`02-architecture.md`](02-architecture.md), [`05-security.md`](05-security.md), Phase 3 |
| 13    | Server + client-side validation            | Phase 3                                                 |
| 14    | PHPUnit tests for ProductsController       | [`04-testing-strategy.md`](04-testing-strategy.md)      |
| 15    | DI + mocking                               | [`02-architecture.md`](02-architecture.md), [`04-testing-strategy.md`](04-testing-strategy.md) |
| 16    | RESTful API                                | Phase 5                                                 |
| 17    | JWT auth for API                           | [`01-tech-stack.md`](01-tech-stack.md), [`05-security.md`](05-security.md), Phase 5 |

> **Security cross-cuts** Reqs #3, #4, #6, #12, #13, #17. The audit baseline, threat model, OWASP mapping, and pre-deploy checklist are in [`05-security.md`](05-security.md). Each phase in [`06-implementation-plan.md`](06-implementation-plan.md) ends with a security check derived from that document.

## Design principles

- **No heavy framework.** The spec walks every layer (PDO, sessions, router, controllers, views, attribute routing, JWT). A hand-rolled minimal MVC keeps the implementation aligned with the requirements and demonstrates understanding of each layer. Slim/Symfony alternatives are noted in [`01-tech-stack.md`](01-tech-stack.md) but not adopted.
- **Hybrid layout.** Domain modules (`Products/`, `Users/`, `Auth/`, `Transactions/`) for feature locality; infrastructure layers (`Http/`, `Routing/`, `Database/Mysql/`, `Validation/`, `Config/`, `Support/`) for cross-cutting concerns; centralised `templates/` for views.
- **PSR standards.** PSR-4 autoloading, PSR-12 coding style.
- **Explicit dependency injection.** No global state, no singletons reached via static calls. Constructors take their dependencies. This is what makes Req #15 (mocking in tests) actually work.
- **Two auth systems coexist.** Sessions for the web UI (`/admin/*`, `/products/*`), JWT bearer tokens for `/api/*`. They share the same user store but resolve identity differently.
- **Immutable response objects, parameterized SQL, escaped output.** Non-negotiable.

## Reading order

1. `00-overview.md` — this file
2. `01-tech-stack.md` — what we're using and why
3. `02-architecture.md` — folder layout, request flow, design patterns
4. `03-database.md` — schema, relationships, concurrency
5. `04-testing-strategy.md` — how we hit 80%+ coverage
6. `05-security.md` — threat model, OWASP mapping, controls, checklists
7. `06-implementation-plan.md` — phased build order with acceptance criteria

## Out of scope (explicit)

The following are **not** in the requirements and will **not** be built unless added later:
- Payment processing / real money
- Multi-currency support
- Product images / file uploads
- Email notifications, password reset flows
- Audit log beyond the `transactions` table
- Admin user management UI (admin user is seeded directly)
- Internationalization (English only)
- Frontend SPA (server-rendered views only)
