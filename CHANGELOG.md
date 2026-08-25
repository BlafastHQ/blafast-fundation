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

### Host actions required
- None for a fresh install (`composer require` + `php artisan migrate`).
- Hosts with vendor spatie permission/medialibrary/activitylog tables or Laravel's stock
  `notifications` table: migrate them to the package shape first —
  `docs/HOST-REQUIREMENTS.md` has the table-ownership map.
- Uuid `users.id` required; publish Sanctum's migration with `uuidMorphs('tokenable')`
  (`docs/HOST-REQUIREMENTS.md`).
