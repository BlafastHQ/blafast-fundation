# CLAUDE.md — blafast-fundation

`blafasthq/blafast-fundation` is a **Laravel 12 / PHP 8.4 package** (Spatie package-tools skeleton, namespace `Blafast\Foundation\`), not an application. It is the technical and functional foundation of the BlaFast ERP: all cross-cutting, non-business features that future modules (billing, inventory, …) and apps will build on. Repo: `github.com/BlafastHQ/blafast-fundation` (branch `main`). No workspace app requires it yet.

**Follow `.ai/guidelines.md` for every change in this repo.** Highlights: `declare(strict_types=1)`, full type hints + generics PHPDoc everywhere, UUID primary keys (`HasUuids`, `foreignUuid`), integer columns + model constants instead of DB enums, API-only (no frontend), form requests for validation, Sanctum auth, spatie permission checks, activitylog on every action, models define `newFactory()`.

## Tooling

- **Tests: Pest 4** on orchestra/testbench — `composer test` runs on **sqlite `:memory:` by default** (fast, no services). Real-Postgres lane (workspace docker stack, port 55433): `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55433 DB_DATABASE=blafast_fundation_test DB_USERNAME=blafast DB_PASSWORD=blafast composer test` — create the database once with `docker compose exec postgres psql -U blafast -c 'CREATE DATABASE blafast_fundation_test'` from the workspace root. The suite builds its schema by including the shipped `.php.stub` migrations directly (`TestCase::migrateDatabases()`, FK order) — the package's own permission stub runs, not the vendor spatie migrations.
- `composer analyse` — PHPStan/larastan (baseline in `phpstan-baseline.neon`). `composer format` — Pint.
- CI: GitHub Actions — run-tests matrix (PHP 8.3/8.4 × Laravel 11/12 × prefer-lowest/prefer-stable, ubuntu + windows), phpstan, auto Pint fix, dependabot auto-merge.
- `BlafastServiceProvider` (spatie package-tools) publishes/overrides host configs `blafast-fundation`, `permission`, `auth`, `sanctum`, `jsonapi`, `media-library`, `activitylog`, `queue`, ships migrations as stubs, and registers all commands/middleware/policies/macros below.

## Multi-tenancy (organizations)

- `Organization` (UUID, unique auto-suffixed `slug`, `settings`/`contact_details` JSON, `peppol_id`) ⟷ users via `organization_user` pivot (`role`, `is_active`, `joined_at`/`left_at`, `metadata`; soft leave = `removeUser()`).
- Context per request: middleware **`org.resolve`** reads `X-Organization-Id` header (session fallback), validates membership (400 `MISSING_ORGANIZATION` / 403 `ORGANIZATION_ACCESS_DENIED` / 403 `MEMBERSHIP_INACTIVE`); a superadmin without the header gets **global (unscoped) context**. **`org.required`** rejects both missing *and* global context.
- Models opt in with `use BelongsToOrganization`: adds `OrganizationScope` (filters by current org; **no filter at all** in global context *or* when no context exists — CLI/unauthenticated see everything) and auto-fills `organization_id` on create. Migrations use the Blueprint macros `$table->organizationId()` (FK) / `organizationIdIndex()` (no FK).
- Queued jobs must extend **`BlaFastJob`**: it captures the current org id and restores it in the worker via the `RestoreOrganizationContext` job middleware; failures notify Superadmins.
- Helpers: `organization()`, `organization_id()`, `organization_slug()`, `organization_context()`, `has_organization_context()`, `is_global_organization_context()`, `blafast_setting($key, $default)`, `blafast_setting_with_source($key, $default)`.

## Dynamic model API

There is **no model auto-discovery**: a model joins the API when a route file calls `Route::dynamicResource(Model::class)` (macro also registers it in the `ModelRegistry` singleton). Generated routes are **read-only GET**: `{slug}/` index, `{slug}/{id}` show, `meta/{slug}`, plus media `{slug}/{id}/files/{collection}[/{file}]`. There is no dynamic create/update/delete; writes happen via file upload (`POST|DELETE /api/v1/{slug}/{id}/files/{collection}[/{fileId}]`) and RPC method calls.

Model opt-in — structure (read API) and methods (RPC) are separate:

```php
class Invoice extends Model implements HasApiStructure, HasApiMethods
{
    use ExposesApiStructure, ExposesApiMethods, BelongsToOrganization;

    public static function apiStructure(): array
    {
        return ApiStructureBuilder::make(self::class)
            ->label('invoices.label')
            ->uuid('id', 'invoices.fields.id')
            ->string('number', 'invoices.fields.number', searchable: true, sortable: true)
            ->datetime('created_at', 'invoices.fields.created_at', sortable: true)
            ->relation('customer', 'name', 'invoices.fields.customer')
            ->includes('customer')
            ->pagination(default: 25, max: 100)
            ->build();
    }

    public static function apiMethods(): array
    {
        return [ApiMethodBuilder::make('send', 'sendToPeppol')->post()
            ->requiredParam('recipient_id', ApiMethodParameterType::STRING)
            ->queued()->build()];
    }

    public static function defaultRights(): array  // required by HasApiMethods
    {
        return ['view' => ['Admin', 'User'], 'exec' => ['Admin' => ['*']]];
    }
}
```

- Query features (spatie/laravel-query-builder): filters derived from field types (string → partial, uuid/int/bool → exact, date(time) → `DateRangeFilter` supporting `filter[x]=date` or `filter[x][from]/[to]`, relation → `relation.field` exact); sorts from `sortable` fields; `?search=` is a **top-level param** (grouped `ILIKE` on pgsql, or Postgres full-text with `->fullTextSearch(...)`); pagination is **cursor-only** — `page[per_page]` / `page[cursor]`, capped at 100, response has `links.next` + `meta.page.has_more`, no total count.
- `GET /api/v1/meta/{slug}` returns the model's full metadata (fields, filters, sorts, endpoints, methods filtered by exec permission) for building generic frontends; cached per user+org.
- RPC: `GET|POST /api/v1/{slug}/{uuid}/call/{method}` — verb must match the declared one, params validated from `ApiMethodParameter` rules (POST body under `data.attributes`), **passed positionally** (declaration order must match the PHP signature), execution activity-logged with secret-like params redacted; `->queued()` methods dispatch `ExecuteModelMethod` (still HTTP 200). Authorization: Superadmin, else `exec.{slug}`, else `exec.{slug}.{method}`.
- Responses are hand-rolled JSON:API-shaped `{data: {type, id, attributes}}` (the bundled laravel-json-api server has zero schemas registered). Use macros `response()->jsonApiSuccess()/jsonApiCollection()/jsonApiError(ApiErrorCode $code, ...)`. `JsonApiExceptionHandler` maps validation/auth/404/405/429 to JSON:API error objects for `api/*` requests.
- Metadata/menu/settings caching: `MetadataCacheService` (org-scoped keys, tag-based with version-counter fallback, TTL 600s); invalidated by eloquent events on registered models and permission/role changes; `php artisan blafast:cache:metadata warm|clear|status`.

## Permissions

spatie/laravel-permission in **teams mode** with `team_foreign_key = organization_id`, guard **`api`**. Global `Superadmin` role + template org roles `Admin`, `User`, `Viewer`, `Consumer` (cloned per org via `copyTemplateRolesToOrganization()`). Naming: CRUD `view_|create_|update_|delete_|list_{snake_slug}`, exec `exec.{slug}` / `exec.{slug}.{method}`, modules `view_{module}_module`. Sync with `blafast:permissions:sync --models|--all` (uses each model's `defaultRights()`), seeders: `RoleSeeder`, `PermissionSeeder`, `ExecPermissionSeeder`.

## Modules, menus, settings, deferred

- **Modules** are Composer packages flagged by `extra.blafast`, `"type": "blafast-module"`, or the `blafast-module` keyword; discovered from `vendor/composer/installed.json`, cached at `bootstrap/cache/blafast-modules.php` (`blafast:modules:discover` / `blafast:modules:list`). Module service providers load through normal Laravel package discovery. Modules add cron via the `RegisterScheduledTasks` event and menu entries via the `MenuRegistry` facade (tagged items deep-merge; permission-filtered per user; served by `GET /api/v1/user-menu`).
- **Settings**: resolution `organization` (JSON on `organizations.settings`) → `system` (`system_settings` table, typed values) → default; `blafast_setting()` helper; endpoints under `/api/v1/settings/{system|organization|resolved}`.
- **Deferred API requests**: opt-in async execution — `deferred` middleware + a matching `DeferredEndpointConfig` + `X-Blafast-Defer: true` (or `force_deferred`) → stores the request (payload/result **encrypted**), returns **202** with a poll link; `ProcessDeferredApiRequest` replays it internally against `app.url` on queues `deferred[-high|-low]`. Manage via `/api/v1/deferred` (index/show/cancel/retry); purge with `blafast:deferred:cleanup`.

## Host app requirements

`App\Models\User` must use `HasUuids`, Sanctum `HasApiTokens`, spatie `HasRoles`, define `organizations(): BelongsToMany` (through the `organization_user` pivot) and **`isSuperadmin(): bool`** (probed via `method_exists`). A morph map is enforced (`organization`, `user`). Built-in endpoints (all under `/api/v1`): `auth/*` (login/logout/me/tokens), `meta/{slug}`, `user-menu`, `activities`, `notifications`, `settings/*`, `scheduler/status`, `deferred/*`, file upload/delete, method call. Rate limiters: `auth` 60/min per IP, `api` 300/min per user (Superadmins exempt).

## Scheduled tasks & commands

Scheduler (via `ScheduleServiceProvider`): heartbeat file every minute (checked by `blafast:scheduler:health` and `GET /api/v1/scheduler/status`), `blafast:activity:cleanup` 02:00, `blafast:deferred:cleanup` 03:00, `cache:prune-stale-tags` 04:00, `blafast:cache:metadata warm` 05:00, `blafast:modules:discover` weekly. Other commands: `blafast:info`, `blafast:queue:status`, `blafast:queue:retry`.

## Gotchas (verified 2026-08)

- `BlaFastPermissionRegistrar` derives slugs with `Str::snake()` but runtime checks use the kebab-case `getApiSlug()` — identical for one-word models, **divergent for multi-word models** (`sales_order` created vs `sales-order` checked). Prefer one-word model names or fix before relying on exec permissions.
- `HasApiMethods` / the RPC path has **no fixtures and no test coverage** yet; `Organization` is the only production model wired into the dynamic API.
- Field-level permission filtering in `/meta/{slug}` is not implemented (all fields returned); method filtering only applies to authenticated users.
- Several config keys are currently dead: `organization.header_name`/`session_key` (middleware hardcodes `X-Organization-Id` / `organization_id`), `discovery.enabled`, `modules.manifest_cache` (real cache path is `bootstrap/cache/blafast-modules.php`).
- `ExecuteModelMethod` and `ScheduleServiceProvider` import `App\Models\User` directly — an app-level coupling inside the package.
- `OrganizationPathGenerator` (per-org media paths) exists but is commented out in `config/media-library.php`.
- With **no** org context (unauthenticated routes, CLI, tinker), `OrganizationScope` applies no filter — don't assume tenant isolation outside `org.resolve`d requests.
