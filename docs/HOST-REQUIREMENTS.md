# Host application requirements

What a Laravel application must do (and know) to install `blafasthq/blafast-fundation`.
Each entry references the fix/optimize task that introduced it.

## Installing the schema (Task 2)

```bash
composer require blafasthq/blafast-fundation
php artisan migrate
```

That is the whole install: the package ships real timestamped migrations and runs them
with `php artisan migrate` automatically (FK-ordered, `2026_08_25_*`).

**Fork path** — if you want to own/modify the schema:

```bash
php artisan vendor:publish --tag=blafast-fundation-migrations
# then, in .env:
FOUNDATION_RUN_MIGRATIONS=false
```

`FOUNDATION_RUN_MIGRATIONS=false` (or `run_migrations` in the published
`config/blafast-fundation.php`) stops the package registering its own copies — leaving it
enabled after publishing registers every migration **twice** and `migrate` fails on
"already exists".

## Database support (Task 3)

**PostgreSQL** is the supported production database (sqlite is used for fast tests).
MySQL/MariaDB are **not** supported: the permission pivot tables enforce uniqueness with
partial unique indexes (`… WHERE organization_id IS NULL / IS NOT NULL`), which MySQL
does not implement. The partial pair exists because the global Superadmin grant lives in
a NULL-organization row — a composite primary key over a nullable column is invalid on
Postgres.

## Table ownership and collisions (Task 3)

| Tables | Behaviour when the table already exists |
|---|---|
| `organizations`, `organization_user`, `addresses`, `countries`, `currencies`, `system_settings`, `deferred_*` | Package-owned names; no collision expected. |
| `jobs`, `job_batches`, `failed_jobs` | Framework-identical schema — silently **skipped** (the Laravel skeleton already ships them). |
| `permissions`, `roles`, `model_has_*`, `role_has_permissions`, `media`, `activity_log`, `notifications` | Package-shaped (has `organization_id`) → skipped, `migrate` twice is a no-op. **Vendor-shaped** (bigint spatie permission/medialibrary/activitylog tables, Laravel's stock notifications) → `migrate` **aborts with a RuntimeException** naming the table: the package needs uuid keys + organization scope, and skipping silently would break every query at runtime. Migrate your data to the package shape (or drop the vendor tables) first. |

## The User model's permission guard (Task 5)

`App\Models\User` must declare:

```php
protected $guard_name = 'api';
```

The package's whole permission runtime (roles, permissions, policies, menu
filtering) lives on the `api` guard. Without an explicit guard, spatie's guard
detection falls back to config order and resolves the session guard on
sanctum-authenticated requests — every `can()`/`hasPermissionTo()` then throws
`PermissionDoesNotExist` for api-guard permissions. See `stubs/User.stub`.

## The users table (Tasks 2–3)

`App\Models\User` must have a **UUID primary key** (`HasUuids`) — see `stubs/User.stub`.
`organization_user.user_id` and `deferred_api_requests.user_id` are `foreignUuid`
constraints against `users.id`; a stock bigint users table fails those FKs on Postgres.

**Sanctum**: its vendor migration creates `personal_access_tokens` with bigint
`morphs('tokenable')`, which cannot hold uuid user ids on Postgres. Publish it and switch
to uuid morphs:

```bash
php artisan vendor:publish --tag=sanctum-migrations
# in the published migration, replace:
#   $table->morphs('tokenable');
# with:
#   $table->uuidMorphs('tokenable');
```

## Open handoffs

- **Task 4 — run CI remotely.** All three pipelines (GitHub Actions `run-tests.yml`,
  `.gitlab-ci.yml`, `.forgejo/workflows/ci.yml`) are authored and their commands verified
  locally (host + a `php:8.4-cli` container, both DB drivers, deliberate-red check). Remote
  execution needs the branch pushed and GitLab/Forgejo remotes + runners configured — the
  repo's only remote is GitHub. Push `ralph/fix-optimize` (or merge) and confirm the legs go
  green; the test jobs pass when the failing set is a subset of `tests/BASELINE.md`.
