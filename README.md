# blafast-fundation

[![Latest Version on Packagist](https://img.shields.io/packagist/v/blafasthq/blafast-fundation.svg?style=flat-square)](https://packagist.org/packages/blafasthq/blafast-fundation)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/blafasthq/blafast-fundation/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/blafasthq/blafast-fundation/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/blafasthq/blafast-fundation/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/blafasthq/blafast-fundation/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/blafasthq/blafast-fundation.svg?style=flat-square)](https://packagist.org/packages/blafasthq/blafast-fundation)

The technical and functional foundation of the BlaFast ERP: every cross-cutting,
non-business feature that BlaFast modules and applications build on. **API-only**
(no frontend), Sanctum-authenticated, organization-multi-tenant.

What it provides:

- **Multi-tenant organizations** — `Organization` model, membership pivot with
  soft leave/reactivation, per-request context from the `X-Organization-Id`
  header (`org.resolve` / `org.required` middleware), a fail-closed
  `OrganizationScope` for tenant models (`BelongsToOrganization`), and
  context-preserving queued jobs (`BlaFastJob`).
- **Dynamic model API** — models opt in with `Route::dynamicResource(Model::class)`
  and describe themselves via `ApiStructureBuilder`: generated read-only GET
  endpoints with typed filters, sorts, top-level `?search=`, cursor-only
  pagination, `GET /api/v1/meta/{slug}` metadata for generic frontends, media
  upload/serving, and declared RPC methods (`ApiMethodBuilder`).
- **Permissions** — spatie/laravel-permission in teams mode keyed by
  `organization_id`, guard `api`; global `Superadmin` plus per-organization
  template roles; `blafast:permissions:sync`.
- **Modules, menus, settings** — Composer-package module discovery, a
  permission-filtered menu registry (`GET /api/v1/user-menu`), and
  organization → system → default settings resolution (`blafast_setting()`).
- **Deferred requests** — opt-in async execution of API calls with encrypted
  storage and a poll endpoint (202 + `deferred/*` management routes).
- **Operations** — Horizon-ready queue conventions, scheduler heartbeat +
  `scheduler/status` endpoint, activity logging on every action, JSON:API-shaped
  responses and error rendering (scoped to package routes by default).

## Requirements

| | |
|---|---|
| PHP | ^8.4 |
| Laravel | ^12.0 |
| Database | PostgreSQL (partial unique indexes are used — MySQL is **not** supported; the test suite also runs on sqlite) |

## Installation

```bash
composer require blafasthq/blafast-fundation
php artisan migrate
```

That is the whole schema install: the package ships real timestamped migrations
and registers them with `php artisan migrate` automatically. If you prefer to
own the schema, publish and fork it instead:

```bash
php artisan vendor:publish --tag=blafast-fundation-migrations
# then, in .env:
FOUNDATION_RUN_MIGRATIONS=false
```

Publish the (~200-line) config file if you need to change defaults:

```bash
php artisan vendor:publish --tag=blafast-fundation-config
```

### Host application contract

The package verifies its host at boot (`BlafastServiceProvider::assertHostConfiguration()`)
and fails loudly with actionable messages rather than misbehaving quietly. In
short, your `User` model must use UUIDs, Sanctum's `HasApiTokens`, spatie's
`HasRoles` with `protected $guard_name = 'api';`, and expose
`organizations(): BelongsToMany` + `isSuperadmin(): bool`.

**Read [`docs/HOST-REQUIREMENTS.md`](docs/HOST-REQUIREMENTS.md)** — it is the
authoritative, task-by-task list of everything a host must configure (user
model contract, auth guard, queues, upload defaults, JSON:API error scope,
notification scoping, …).

## Usage

Expose a model on the API:

```php
use Blafast\Foundation\Api\ApiStructureBuilder;
use Blafast\Foundation\Contracts\HasApiStructure;
use Blafast\Foundation\Traits\BelongsToOrganization;
use Blafast\Foundation\Traits\ExposesApiStructure;

class Invoice extends Model implements HasApiStructure
{
    use BelongsToOrganization;
    use ExposesApiStructure;

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
}
```

```php
// routes/api.php of the host
Route::dynamicResource(Invoice::class);
```

This yields `GET /api/v1/invoice` (filter/sort/search/cursor-paginate),
`GET /api/v1/invoice/{id}`, `GET /api/v1/meta/invoice`, and the media endpoints
`POST|GET|DELETE /api/v1/invoice/{id}/files/{collection}` — all behind
`auth:sanctum` + `throttle:api` + `org.resolve` by default. RPC methods are a
separate opt-in via `HasApiMethods` / `ApiMethodBuilder`.

The full feature map — context helpers, permission naming, module/menu/settings
systems, deferred requests, scheduled tasks, gotchas — lives in
[`CLAUDE.md`](CLAUDE.md).

## Testing

```bash
composer test      # Pest, sqlite :memory: by default
composer analyse   # PHPStan (larastan)
composer format    # Pint
```

The same suite runs against a real PostgreSQL by exporting the `DB_*`
environment (see `phpunit.xml.dist` / CI): both lanes are exercised in CI, and
the failing-test baseline gate lives in `tests/BASELINE.md` +
`tests/check-baseline.php`.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [Sébastien Denooz](https://github.com/SebastienDenooz)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
