---
tags: [entity, tests]
description: tests/test.php — homemade test harness + the one seeded smoke test
created: 2026-05-18
updated: 2026-05-18
---

# tests/test.php

48 lines. Single file, no PHPUnit, no autoloader. Bootstraps by `system()`-
calling `seed.php` so every test run starts from a known state.

## Harness

```php
test('name', function () { ... });
assert_true($cond, 'message');
```

Top-level `$pass` / `$fail` counters. Throw to fail. Exit non-zero if any
test failed.

## Existing tests

1. `seeded share link resolves to the seeded document` — joins shares→documents and asserts the seeded title is `Welcome Packet`

## Run

```sh
docker compose exec app php tests/test.php
```

## Pattern for new tests (per [[pattern-test-harness]])

Each of the three features needs at least one `test('...', function () { ... })`
block. Hit the DB directly via `db()`; that's how the existing test does it.
For request-flow coverage, `system('curl ...')` against the running server
is acceptable but heavier; prefer DB-level assertions where they're meaningful.

## Related
- [[pattern-test-harness]]
