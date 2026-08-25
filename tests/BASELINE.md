# Known failing-test baseline (Task 4 / audit C8)

This manifest is the ONLY sanctioned list of failing tests. CI runs
`php tests/check-baseline.php build/report.junit.xml tests/BASELINE.md` after
Pest and FAILS when the run's failing set is not a subset of this list — a new
red test always breaks the build. Additions require review.

The list is EMPTY: the suite is fully green on both drivers (sqlite `:memory:`
and the Postgres lane), across random seeds, and on the prefer-lowest
dependency leg (which needs `orchestra/testbench ^10.6` — older testbench-core
is incompatible with current laravel/framework `HandleExceptions::flushState`).
History: 62 entries measured 2026-08-25 at task 3's driver-parity point →
pruned by tasks 10–11 → 31 → emptied after task 27 by fixing the remaining
stale tests (JSON:API error shape, org-context + api-guard modernization,
shadowed test routes) and the order-flaky Country/Currency factories.
