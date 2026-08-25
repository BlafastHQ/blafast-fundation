# Changelog

All notable changes to `blafast-fundation` will be documented in this file.

## [Unreleased]

### Fixed (post-task suite-green pass)
- The test suite is fully green on both drivers with an **empty** failing-test
  baseline (`tests/BASELINE.md`): stale tests modernized (JSON:API validation
  error shape; org-context + `api`-guard setup for the user-menu suite; test
  routes moved off a shadowed `api/v1/test/*` prefix; JSON:API 429 shape asserted
  under `api_errors.scope=all`), `MenuRegistry::getByTag()` now returns
  order-sorted children like `all()`, Country/Currency factories generate unique
  synthetic ISO codes (the only order-flaky failures), and
  `orchestra/testbench` requires `^10.6` — older testbench-core is incompatible
  with current laravel/framework `HandleExceptions::flushState` on the
  prefer-lowest CI leg.

### Fixed (Tasks 26-27, RPC)
- **The documented plain-list `apiMethods()` pattern works** (Task 27): the builder
  emits the slug and one normalizer keys every consumer by `slug ?? key` — before,
  plain lists produced numeric keys everywhere (meta advertised `slug => 0`,
  permission sync created unmatchable `exec.{model}.0`, `call/{slug}` 404'd).
- `array:<type>` parameters validate per element under `attribute.*` and return 422
  — the raw-modifier parent rule that 500'd every modified-array input is gone
  (Task 26).
- RPC arguments are cast to the PHP types after validation and bound by NAME via
  reflection: GET string integers no longer TypeError, unprovided optionals fall
  through to the PHP signature default instead of a forced null, and declaration
  order no longer has to match the signature (Task 26). `file` parameters reach the
  method and are logged as a placeholder instead of crashing the activity write.

### Changed (Task 27)
- **Breaking:** `->queued()` RPC methods return **202** with a deferred-request id
  and poll link (result retrievable at `GET /api/v1/deferred/{id}`) instead of
  HTTP 200 with a fabricated `executed_at` and no way to learn the outcome. They
  degrade to synchronous execution in global context, for file parameters, or when
  the deferred subsystem is disabled. The `ExecuteModelMethod` job is removed.

### Removed (Task 25)
- **Production dependencies dropped:** `spatie/laravel-tags` (never used) and
  `laravel-json-api/laravel` (zero schemas registered — the JSON:API responses and
  error rendering are hand-rolled and use none of it) are gone from `require`;
  `knuckleswtf/scribe` moved to `require-dev`. Hosts relying on any of these being
  transitively installed must require them directly.
- Dead code deleted: the `AddRateLimitHeaders` middleware (read request attributes
  nothing sets; alias applied to no route), the unreferenced `PasswordResetNotification`
  / `WelcomeNotification` and their blade views (no such flow exists), the empty
  JSON:API `Server` class + `config/jsonapi.php`, the zero-resource `JsonApiRoute`
  block, and the empty published views directory.

### Fixed (Task 25)
- Media conversions honour `blafast-fundation.media.queue_conversions` instead of an
  unconditional `nonQueued()` that generated every conversion inline in the upload
  request.
- `HasMediaCollections::registerMediaConversions()` types spatie's `Media` (what the
  `HasMedia` interface declares) — the old package-model narrowing was a fatal
  signature incompatibility for every adopting model.
- README rewritten to describe the real package (install, host contract, usage,
  supported versions) instead of the unmodified skeleton.

### Changed
- **Breaking (Task 24):** file uploads are safe by default. Collections that declare no
  `accepted_mimes` now enforce a default MIME allow-list (raster images, PDF, plain
  text/CSV, Office OpenXML — never SVG/HTML/scripts) instead of accepting everything;
  the default media disk is the package-shipped **private** `blafast-private`
  (temporary signed URLs; override via `BLAFAST_MEDIA_DISK` or by defining the disk
  yourself), and stored filenames are normalised (slugged basename + lowercase
  extension). Public serving is an explicit per-collection `->useDisk('public')`
  opt-in. See `docs/HOST-REQUIREMENTS.md`.
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

### Changed (Task 23)
- **Config cleanup:** ~50 dead `blafast-fundation.*` keys are removed
  (`api.version`, `api.pagination.type`, `api.rate_limiting.*.decay_minutes`,
  `api.errors.format`, `auth.guard`, `auth.token.name`,
  `organization.require_context`, `permissions.*`,
  `cache.driver/prefix/tagging`, `queue.connection`, `queue.timeouts.*`,
  `queue.failed.notify_after_attempts`,
  `activity_log.enabled/log_events/include_organization`, `discovery.*`,
  `menu.*`, `media.responsive_images`, `modules.auto_discover/cache_enabled`,
  `localization.*`, `peppol.*`). Newly honoured knobs:
  `auth.token.expiration` (login/token-create lifetime, minutes),
  `organization.header_name/session_fallback/session_key`,
  `activity_log.retention_days` (cleanup default),
  `modules.manifest_cache` (now defaulting to the real
  `bootstrap/cache/blafast-modules.php`), `cache.settings_ttl`,
  `deferred.timeout/result_ttl/priority`. A test sweep now fails on any
  shipped-but-unread key.
- `Role::$organization_id` no longer shadows the Eloquent attribute —
  `isGlobal()`/`isSuperadmin()` were true for ANY role named "Superadmin".

### Fixed (Task 15)
- **The deferred (202/poll) subsystem actually works now.** Deferred requests
  execute in process as the original user with the organization context restored
  by the real middleware stack — the old implementation replayed over HTTP with
  the Authorization header stripped, so every `auth:sanctum` target answered 401
  … which was then stored as a *successful* result. **Contract change:**
  non-2xx outcomes are recorded as failures (`error_code = HTTP_{status}`, real
  status + body stored); 5xx and transport errors are retried per
  `max_attempts` with queue backoff before failing. Poll clients must treat
  `status = failed` + `result_status_code` as the real downstream outcome.

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
