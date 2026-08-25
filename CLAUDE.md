# CLAUDE.md — blafast-fundation

`blafasthq/blafast-fundation` is a **Laravel 12 / PHP 8.4 package** (Spatie package-tools skeleton, namespace `Blafast\Foundation\`), not an application. It is the technical and functional foundation of the BlaFast ERP: all cross-cutting, non-business features that future modules (billing, inventory, …) and apps will build on. Repo: `github.com/BlafastHQ/blafast-fundation` (branch `main`). No workspace app requires it yet.

**Follow `.ai/guidelines.md` for every change in this repo.** Highlights: `declare(strict_types=1)`, full type hints + generics PHPDoc everywhere, UUID primary keys (`HasUuids`, `foreignUuid`), integer columns + model constants instead of DB enums, API-only (no frontend), form requests for validation, Sanctum auth, spatie permission checks, activitylog on every action, models define `newFactory()`.

## Tooling

- **Tests: Pest 4** on orchestra/testbench — `composer test` runs on **sqlite `:memory:` by default** (fast, no services). Real-Postgres lane (workspace docker stack, port 55433): `DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=55433 DB_DATABASE=blafast_fundation_test DB_USERNAME=blafast DB_PASSWORD=blafast composer test` — create the database once with `docker compose exec postgres psql -U blafast -c 'CREATE DATABASE blafast_fundation_test'` from the workspace root. The suite's schema comes from the package's own timestamped migrations (registered by the provider, run by `migrate:fresh`) plus the test users/addressable tables and Sanctum's vendor migration — the package's own permission migration runs, not the vendor spatie ones.
- `composer analyse` — PHPStan/larastan (baseline in `phpstan-baseline.neon`). `composer format` — Pint.
- CI: GitHub Actions — run-tests matrix (PHP 8.3/8.4 × Laravel 11/12 × prefer-lowest/prefer-stable, ubuntu + windows), phpstan, auto Pint fix, dependabot auto-merge.
- `BlafastServiceProvider` (spatie package-tools) publishes the single `blafast-fundation` config (required framework/vendor settings are applied imperatively at register+boot and asserted via `assertHostConfiguration()`), ships **real timestamped migrations** (auto-run on `php artisan migrate`; publish tag `blafast-fundation-migrations` for forking, paired with `FOUNDATION_RUN_MIGRATIONS=false`), and registers all commands/middleware/policies/macros below.

## Multi-tenancy (organizations)

- `Organization` (UUID, unique auto-suffixed `slug`, `settings`/`contact_details` JSON, `peppol_id`) ⟷ users via `organization_user` pivot (`role`, `is_active`, `joined_at`/`left_at`, `metadata`; soft leave = `removeUser()`).
- Context per request: middleware **`org.resolve`** reads `X-Organization-Id` header (session fallback), validates membership (400 `MISSING_ORGANIZATION` / 403 `ORGANIZATION_ACCESS_DENIED` / 403 `MEMBERSHIP_INACTIVE`); a superadmin without the header gets **global (unscoped) context**. **`org.required`** rejects both missing *and* global context.
- Models opt in with `use BelongsToOrganization`: adds `OrganizationScope` (filters by current org; **fail-closed** — with *no* context at all the scope matches nothing; a superadmin's *global* context sees everything) and auto-fills `organization_id` on create. CLI/system code uses `organization_context()->runAsSystem(...)` or `Model::withoutOrganizationScope()` explicitly. Migrations use the Blueprint macros `$table->organizationId()` (FK) / `organizationIdIndex()` (no FK).
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
- RPC: `GET|POST /api/v1/{slug}/{uuid}/call/{method}` — verb must match the declared one, params validated from `ApiMethodParameter` rules (POST body under `data.attributes`; `array:<type>` element rules included), **cast to the PHP types and bound by name** (declaration order need not match the signature; unprovided optionals fall through to PHP defaults), execution activity-logged with secret-like params redacted. `->queued()` methods return **202** with a deferred-request id and poll link (`/api/v1/deferred/{id}`) — degrading to synchronous execution in global context, for file parameters, or when the deferred subsystem is off. Authorization: Superadmin, else `exec.{slug}`, else `exec.{slug}.{method}`. `apiMethods()` may be a plain list — `build()` embeds the slug and `ApiMethodNormalizer` keys every consumer by it.
- Responses are hand-rolled JSON:API-shaped `{data: {type, id, attributes}}` (the bundled laravel-json-api server has zero schemas registered). Use macros `response()->jsonApiSuccess()/jsonApiCollection()/jsonApiError(ApiErrorCode $code, ...)`. `JsonApiExceptionHandler` maps validation/auth/404/405/429 to JSON:API error objects for `api/*` requests.
- Metadata/menu/settings caching: `MetadataCacheService` (org-scoped keys, tag-based with version-counter fallback, TTL 600s); invalidated by eloquent events on registered models and permission/role changes; `php artisan blafast:cache:metadata warm|clear|status`.

## Permissions

spatie/laravel-permission in **teams mode** with `team_foreign_key = organization_id`, guard **`api`**. Global `Superadmin` role + template org roles `Admin`, `User`, `Viewer`, `Consumer` (cloned per org via `copyTemplateRolesToOrganization()`). Naming: CRUD `view_|create_|update_|delete_|list_{snake_slug}`, exec `exec.{slug}` / `exec.{slug}.{method}`, modules `view_{module}_module`. Sync with `blafast:permissions:sync --models|--all` (uses each model's `defaultRights()`), seeders: `RoleSeeder`, `PermissionSeeder`, `ExecPermissionSeeder`.

## Modules, menus, settings, deferred

- **Modules** are Composer packages flagged by `extra.blafast`, `"type": "blafast-module"`, or the `blafast-module` keyword; discovered from `vendor/composer/installed.json`, cached at `bootstrap/cache/blafast-modules.php` (`blafast:modules:discover` / `blafast:modules:list`). Module service providers load through normal Laravel package discovery. Modules add cron via the `RegisterScheduledTasks` event and menu entries via the `MenuRegistry` facade (tagged items deep-merge; permission-filtered per user; served by `GET /api/v1/user-menu`).
- **Settings**: resolution `organization` (JSON on `organizations.settings`) → `system` (`system_settings` table, typed values) → default; `blafast_setting()` helper; endpoints under `/api/v1/settings/{system|organization|resolved}`.
- **Deferred API requests**: opt-in async execution — `deferred` middleware + a matching `DeferredEndpointConfig` + `X-Blafast-Defer: true` (or `force_deferred`) → stores the request (payload/result **encrypted**), returns **202** with a poll link; `ProcessDeferredApiRequest` replays it internally against `app.url` on queues `deferred[-high|-low]`. Manage via `/api/v1/deferred` (index/show/cancel/retry); purge with `blafast:deferred:cleanup`.

## Host app requirements

`App\Models\User` must use `HasUuids`, Sanctum `HasApiTokens`, spatie `HasRoles`, define `organizations(): BelongsToMany` (through the `organization_user` pivot) and **`isSuperadmin(): bool`** (probed via `method_exists`). A non-enforcing morph map registers the `organization` and `user` aliases (`user` resolves from `auth.providers.users.model`); host models without aliases keep working. Built-in endpoints (all under `/api/v1`): `auth/*` (login/logout/me/tokens), `meta/{slug}`, `user-menu`, `activities`, `notifications`, `settings/*`, `scheduler/status`, `deferred/*`, file upload/delete, method call. Rate limiters: `auth` 60/min per IP, `api` 300/min per user (Superadmins exempt).

## Scheduled tasks & commands

Scheduler (via `ScheduleServiceProvider`): heartbeat file every minute (checked by `blafast:scheduler:health` and `GET /api/v1/scheduler/status`), `blafast:activity:cleanup` 02:00, `blafast:deferred:cleanup` 03:00, `cache:prune-stale-tags` 04:00, `blafast:cache:metadata warm` 05:00, `blafast:modules:discover` weekly. Other commands: `blafast:info`, `blafast:queue:status`, `blafast:queue:retry`.

## Gotchas (verified 2026-08, post fix/optimize pass)

- Permission slugs are canonical **kebab-case** everywhere (`getApiSlug()`); rows created under the old `Str::snake()` derivation can be renamed with `php artisan blafast:permissions:migrate-slugs`.
- Field-level permission filtering in `/meta/{slug}` is not implemented (all fields returned); method filtering only applies to authenticated users.
- `OrganizationPathGenerator` (per-org media paths) ships but is not wired anywhere — opt in by setting `media-library.path_generator` in the host.
- With **no** org context (CLI, tinker, jobs outside `BlaFastJob`), `OrganizationScope` is **fail-closed**: tenant queries match nothing until you enter a context (`organization_context()->set/…->runAsSystem()`) or bypass explicitly (`withoutOrganizationScope()`).
- A bare `vendor/bin/testbench` CLI run writes a default `.env` into `vendor/orchestra/testbench-core/laravel/` that poisons the whole suite (`CACHE_STORE=database`) — delete it if the suite suddenly fails en masse.
