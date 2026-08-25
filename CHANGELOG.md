# Changelog

All notable changes to `blafast-fundation` will be documented in this file.

## [Unreleased]

### Changed
- **Breaking (Task 2):** migrations are now real timestamped `.php` files that run
  automatically with `php artisan migrate` — no `vendor:publish` step. Hosts that DO
  publish them (tag `blafast-fundation-migrations`) must set
  `FOUNDATION_RUN_MIGRATIONS=false` (new config key `blafast-fundation.run_migrations`),
  otherwise every migration registers twice. See `docs/HOST-REQUIREMENTS.md`.
- **Breaking (Task 3):** the `model_has_roles` / `model_has_permissions` composite
  primary keys (Postgres-fatal over the nullable team column) are replaced by partial
  unique index pairs — global (NULL-organization) grants now insert on Postgres, and the
  same role stays grantable per organization. MySQL is not supported (partial indexes).
- `deferred_api_requests.payload/headers/result` columns are `text` instead of `json`:
  they store `encrypted:json` casts, whose opaque encrypted string Postgres's `json`
  type rejected (Task 3).
- Framework/vendor table collisions handled (Task 3): existing `jobs`/`job_batches`/
  `failed_jobs` are skipped (framework-identical); existing VENDOR-shaped
  `media`/`activity_log`/`notifications`/permission tables make `migrate` abort with an
  actionable error instead of colliding or being silently skipped; package-shaped ones
  are skipped so `migrate` twice is a no-op.
- `AuthController::revokeToken()` returns 404 for non-numeric token ids instead of a
  Postgres 500 (Task 3).

### Fixed
- The spatie permission team id now follows the organization context on every
  transition — request middleware, job middleware, `OrganizationContext`
  `set/setGlobalContext/clear/with*` (Task 5). Org-scoped roles and permissions
  finally resolve per organization; before, every check ran against team NULL and
  denied all members.
- A stateless request without `X-Organization-Id` gets the designed
  `400 MISSING_ORGANIZATION` instead of a 500 (unguarded session fallback, Task 5).

### Changed (Task 14)
- **Response shape:** dynamic show/index responses now carry a `relationships`
  member with embedded `{type,id,attributes}` objects for relations loaded via
  a validated `?include=` (before, `?include=` ran the queries and returned a
  byte-identical payload). Unknown includes are a 400 on BOTH index and show
  (show used to ignore them silently). `/meta` advertises relation filters
  under their registered `{relation}.{field}` names; `Organization`'s
  primaryAddress filter targets the real `city` column. The `Addressable`
  trait's non-relation helper is renamed `getPrimaryAddress()`.

### Changed (Task 12)
- **Breaking:** `Route::dynamicResource()` routes now default to
  `auth:sanctum` + `throttle:api` + `org.resolve`; caller middleware APPENDS and
  cannot strip the defaults (the old default was NO middleware — an index that
  returned every organization's rows to anyone). Unauthenticated requests are
  401 now.
- **Breaking:** `OrganizationScope` (and the activity/notification scopes) FAIL
  CLOSED: with no organization context — in any runtime — scoped queries return
  zero rows instead of every tenant's. Escape hatches:
  `Model::withoutOrganizationScope()`, `OrganizationContext::setGlobalContext()`
  (user optional now) or the new `OrganizationContext::runAsSystem(callable)`
  for seeders/commands/migrations. Queued-job model restoration bypasses the
  scope (restoration by primary key runs before the job middleware restores
  context). The notification scope keeps `organization_id IS NULL` system rows
  visible.
- The macro's unauthorized `meta/{slug}` route is removed — the global,
  authorized `/api/v1/meta/{slug}` endpoint is the only meta route.

### Fixed (Task 9)
- **Behaviour change:** `GET /settings/resolved` now returns only `is_public`
  system settings to non-superadmin users — any authenticated member could read
  every system setting before. Superadmins keep the full set (and
  `/settings/system`). Hosts that read private values through `resolved` must
  use per-key server-side reads (`blafast_setting()`) or the system index.
- Organization settings endpoints authorize a real ability (`manageSettings` →
  `update_organization`); the old nonexistent `manage` ability denied everyone,
  superadmins included.
- Organization settings writes are atomic (lock + re-read in a transaction) —
  concurrent writers no longer erase each other's keys.
- `systemUpdate` persists `is_public`/`group`/`description`; `/settings/resolved`
  resolves dotted keys exactly like per-key `get()`.

### Fixed (Task 7)
- Permission slugs unified on `getApiSlug()` (kebab-case) across the registrar,
  checker, metadata and routes — `permissions:sync` used `Str::snake()`, so every
  multi-word model's grants (`exec.sales_order.*`) were checked as
  `exec.sales-order.*` and never matched. `blafast:permissions:migrate-slugs`
  renames rows created under the old scheme (also the legacy plural
  `*_organizations` names, now singular canonical).
- Granted permissions actually authorize the dynamic endpoints: a generic gate
  hook maps viewAny/view/create/update/delete to `{action}_{slug}` for
  registered models without an explicit policy (superadmins pass); before this,
  module models were denied permanently regardless of grants.

### Changed (Task 6)
- **Breaking:** the package no longer ships/publishes `auth.php`,
  `permission.php`, `sanctum.php`, `queue.php`, `media-library.php`,
  `activitylog.php`. Required settings (spatie teams mode + models, the
  activitylog/medialibrary models, an `api` guard when absent) are applied
  imperatively by the provider and verified by a boot-time sanity check that
  fails with an actionable message. Hosts that relied on the shipped `queue.php`
  tuning or `SANCTUM_EXPIRATION` default must set those themselves — see
  `docs/HOST-REQUIREMENTS.md`.

### Host actions required
- `App\Models\User` must declare `protected $guard_name = 'api';` (Task 5) — see
  `stubs/User.stub`. Without it, spatie resolves the session guard on
  sanctum-authenticated requests and every api-guard permission check throws.
- None for a fresh install (`composer require` + `php artisan migrate`).
- Hosts with vendor spatie permission/medialibrary/activitylog tables or Laravel's stock
  `notifications` table: migrate them to the package shape first —
  `docs/HOST-REQUIREMENTS.md` has the table-ownership map.
- Uuid `users.id` required; publish Sanctum's migration with `uuidMorphs('tokenable')`
  (`docs/HOST-REQUIREMENTS.md`).
