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

## Framework configuration (Task 6)

The package no longer ships copies of `auth`/`permission`/`sanctum`/`queue`/
`media-library`/`activitylog` configs (a config-file merge is won by the host, so
the required settings silently never applied — with `teams=false`, an org role
would grant in EVERY tenant). Instead the provider applies the required keys
imperatively at register + boot time:

- `permission`: `teams = true`, `team_foreign_key = organization_id`,
  `model_morph_key = model_uuid`, the package's uuid `Role`/`Permission` models;
- `activitylog`: the package's uuid + org-scoped `Activity` model;
- `media-library`: the package's uuid `Media` model (unless the host set a
  custom one);
- `auth.guards.api`: created **only if absent** — driver `sanctum`, pointing at
  the host's default user provider. Nothing else in `auth` is touched (your
  default guard and web login stay yours).

A boot-time sanity check (`BlafastServiceProvider::assertHostConfiguration()`)
fails loudly with an actionable message if the api guard is missing/broken or
teams mode was overridden after boot. You may call it from a deploy smoke test.

Host knobs the package deliberately does NOT set any more: queue tuning
(`after_commit`, batching/failed connections) and `sanctum.expiration` — set
them in your own configs (`SANCTUM_EXPIRATION=525600` was the old shipped
default).

## Route and scope security defaults (Task 12)

`Route::dynamicResource()` attaches `auth:sanctum` + `throttle:api` +
`org.resolve` by default; your `middleware` option APPENDS. `OrganizationScope`
fails closed: no organization context ⇒ zero rows, in every runtime. Cross-org
code must opt out explicitly — `Model::withoutOrganizationScope()`,
`OrganizationContext::setGlobalContext()` (user optional), or
`OrganizationContext::runAsSystem(fn () => …)` in seeders, scheduled commands
and migrations. Audit any host command/job/Filament resource that queries a
scoped model outside a request: it now reads nothing instead of everything.

## RPC methods: slugs and the queued contract (Task 27)

`apiMethods()` may be the documented plain list — the builder embeds the slug and
every consumer (meta, permission sync, exec checks, `call/{slug}` routing) keys by
it. If you previously worked around the numeric-key bug by hand-keying the array,
that still works (an explicit `slug` in the definition wins over the array key).
If `blafast:permissions:sync` ever ran against a plain-list model, delete the junk
`exec.{model}.0`-style permission rows — nothing can ever match them.

**Behaviour change:** `->queued()` methods now return **202 Accepted** with a
deferred-request id and a poll link (`GET /api/v1/deferred/{id}`) where the stored
result appears once processed — not the old HTTP 200 with a fabricated
`executed_at`. They degrade to synchronous execution (normal 200 + result) in
global superadmin context, for calls carrying file parameters, or when
`blafast-fundation.deferred.enabled` is off. The `ExecuteModelMethod` job is gone.

## Dependency slimming (Task 25)

`spatie/laravel-tags` and `laravel-json-api/laravel` are no longer installed by
this package, and `knuckleswtf/scribe` is dev-only. A host that used any of them
transitively must `composer require` it directly. Media conversions are queued
by default; set `BLAFAST_MEDIA_QUEUE_CONVERSIONS=false` to generate them inline.

## Upload safety defaults (Task 24)

Uploads through the package's file endpoints enforce a MIME allow-list: a media
collection that declares no `accepted_mimes` gets a safe default (raster
images, PDF, plain text/CSV, Office OpenXML — never SVG/HTML/scripts), and the
default disk is the package-shipped **private** `blafast-private` (temporary
signed URLs; define your own `filesystems.disks.blafast-private` or set
`BLAFAST_MEDIA_DISK` to override). Public serving is an explicit per-collection
opt-in: `->useDisk('public')` in `registerMediaCollections()`. Stored filenames
are normalised (slugged basename + lowercase extension).

## JSON:API error rendering scope (Task 22)

By default the package's JSON:API error renderer applies **only to its own
routes** (and to requests sending `Accept: application/vnd.api+json`) — your
app's established JSON error contract is untouched. Set
`blafast-fundation.api_errors.scope = 'all'` (env `FOUNDATION_JSON_API_ERRORS`)
to render every api/JSON error in the JSON:API shape instead.

## Organization-scoped notifications (Task 18)

`App\Models\User` must override `notifications()` to route through the
package's org-scoped model — Laravel offers no config to swap its base
`DatabaseNotification`, and without the override notifications are NOT
tenant-isolated (a member of orgs A and B reads both orgs' notifications):

```php
public function notifications(): MorphMany
{
    return $this->morphMany(\Blafast\Foundation\Models\DatabaseNotification::class, 'notifiable')->latest();
}
```

See `stubs/User.stub`. The package logs a boot-time warning when the base model
is still in use. Notifications written with no org context (system jobs) carry
`organization_id = NULL` and stay visible in every context.

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

- **Task 4 — run CI remotely (GitLab/Forgejo legs only).** `ralph/fix-optimize` was pushed
  2026-08-25 and the GitHub Actions runs are **green** (run-tests matrix, PHPStan, Pint) —
  with `tests/BASELINE.md` now empty, the test jobs require a fully green suite. Remaining:
  `.gitlab-ci.yml` and `.forgejo/workflows/ci.yml` are authored and locally verified, but the
  repo has no GitLab/Forgejo remotes or runners — configure those and push to run them.
