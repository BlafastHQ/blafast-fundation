# Known failing-test baseline (Task 4 / audit C8)

This manifest is the ONLY sanctioned list of failing tests. CI runs
`php tests/check-baseline.php build/report.junit.xml tests/BASELINE.md` after
Pest and FAILS when the run's failing set is not a subset of this list — a new
red test always breaks the build, even while this list is non-empty. Shrink it
by fixing tests and deleting their lines (Phases 2–6 of
tasks/fix-optimize-tasks.md own every entry; additions require review).

Measured 2026-08-25 on both drivers (sqlite :memory: and the Postgres lane) at
task 3's driver-parity point — the two lanes fail the identical set. Four
order-flaky Country/Currency factory unique-collisions are included even though
they only fire on some random seeds, and one deferred-middleware test that only
fails on the prefer-lowest dependency leg (the deferred subsystem is Phase 4's —
tasks 15/16 own it). Task 10 pruned the healed query/pagination/menu-adjacent
entries. Count: 38.

- Tests\Feature\AuthenticationTest::create token requires name
- Tests\Feature\AuthenticationTest::login requires device name
- Tests\Feature\AuthenticationTest::login requires email
- Tests\Feature\AuthenticationTest::login requires password
- Tests\Feature\DeferredApiRequestTest::`Deferred Request Middleware` → it defers request with X-Blafast-Defer header when config exists
- Tests\Feature\DynamicResourceRoutingTest::dynamic resources macro can register multiple models
- Tests\Feature\MenuEndpointTest::user-menu endpoint returns JSON:API formatted response
- Tests\Feature\MenuEndpointTest::user-menu excludes parent without accessible children or route
- Tests\Feature\MenuEndpointTest::user-menu filters children based on permissions
- Tests\Feature\MenuEndpointTest::user-menu filters items based on permissions
- Tests\Feature\MenuEndpointTest::user-menu handles deeply nested structures
- Tests\Feature\MenuEndpointTest::user-menu includes parent with route even without accessible children
- Tests\Feature\MenuEndpointTest::user-menu respects hierarchical permission structure
- Tests\Feature\MenuEndpointTest::user-menu response includes icon field
- Tests\Feature\MenuEndpointTest::user-menu response includes order field
- Tests\Feature\MenuEndpointTest::user-menu returns items without permission requirement
- Tests\Feature\MenuEndpointTest::user-menu uses slug as id when tag not available
- Tests\Feature\MenuEndpointTest::user-menu uses tag as id when available
- Tests\Feature\MenuEndpointTest::user-menu works with role-based permissions
- Tests\Feature\MenuRegistryServiceTest::complex module interaction scenario
- Tests\Feature\MenuRegistryServiceTest::real-world billing module scenario
- Tests\Feature\MetadataCacheCommandTest::status command displays cache configuration
- Tests\Feature\MetadataCacheInvalidationTest::cache invalidation works with file driver fallback
- Tests\Feature\MetadataCacheInvalidationTest::cache is invalidated when permission is assigned directly to user
- Tests\Feature\MetadataCacheInvalidationTest::cache is invalidated when permission is revoked from user
- Tests\Feature\MetadataCacheInvalidationTest::cache is invalidated when role is attached to user
- Tests\Feature\MetadataCacheInvalidationTest::cache is invalidated when role is detached from user
- Tests\Feature\MetadataCacheInvalidationTest::permission change listener handles events without user gracefully
- Tests\Feature\RateLimitingTest::api rate limiter allows requests under limit
- Tests\Feature\RateLimitingTest::api rate limiter is per-ip when not authenticated
- Tests\Feature\RateLimitingTest::api rate limiter is per-user when authenticated
- Tests\Feature\RateLimitingTest::deferred rate limiter is configured
- Tests\Feature\RateLimitingTest::rate limit exceeded returns JSON:API error format
- Tests\Feature\RateLimitingTest::rate limit headers are added to responses
- Tests\Models\CountryTest::active scope returns only active countries
- Tests\Models\CountryTest::byIsoAlpha2 scope filters by ISO alpha-2 code
- Tests\Models\CountryTest::byIsoAlpha3 scope filters by ISO alpha-3 code
- Tests\Models\CurrencyTest::active scope returns only active currencies
