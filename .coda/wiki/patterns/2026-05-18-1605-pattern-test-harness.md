---
tags: [pattern, tests]
description: The in-file test() + assert_true() pattern in tests/test.php and how to extend it
created: 2026-05-18
updated: 2026-05-18
---

# Pattern: test harness

The codebase has a homemade harness in `tests/test.php`. README:
> At least one test covers each feature you build (see `tests/test.php`
> for the existing pattern).

Don't introduce PHPUnit. Don't add Composer. Match what's there.

## Anatomy

```php
test('seeded share link resolves to the seeded document', function () {
    $stmt = db()->prepare('...');
    $stmt->execute();
    $row = $stmt->fetch();
    assert_true($row !== false, 'expected the seeded share to resolve');
    assert_true($row['title'] === 'Welcome Packet', 'unexpected title');
});
```

- Each `test()` is independent
- They share the seeded state (no isolation between tests; tests should be additive or query-only)
- Throw to fail; harness counts and reports

## Conventions for the three features

Each feature session adds at least one `test()` block. Minimum coverage:

- **Scheduled publishing:** insert a doc with `publish_at` in the future, share it, assert that the view-flow (DB-level) sees the doc as "not yet visible." Either query directly or `system('curl -s http://localhost:8000/view.php?token=...')` and grep for the message.
- **Readable IDs:** insert a doc, assert `readable_id` was populated, format-matches the regex, and is unique.
- **Search:** insert two docs with distinct titles, query the search SQL, assert the right one is returned.

## Run

```sh
docker compose exec app php tests/test.php
```

Seeds wipe `db.sqlite` first (the harness calls `seed.php`), so tests
can assume a known starting state.

## Things NOT to add

- A test runner framework
- A test isolation system (transactions, separate DBs)
- Async tests
- Mocks of the DB

If a test needs fresh state, it can call `seed.php` again itself — but
probably it doesn't need to.

## Related
- [[folio-tests]]
