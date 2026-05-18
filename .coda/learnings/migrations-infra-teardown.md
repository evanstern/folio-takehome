---
tags: [learning, teardown, migrations]
description: Teardown for the migrations-infra feature session — what shipped, what surprised, recommendations for downstream feature sessions.
created: 2026-05-18
---

# Teardown — migrations-infra

## What shipped

- `migrations/0001_init_schema_migrations.sql` — declares the
  `schema_migrations` tracking table with `CREATE TABLE IF NOT EXISTS`
  so the runner-bootstrapped table doesn't collide with the migration's
  own `exec()` pass.
- `lib/migrate.php` — single function `migrate(PDO, string): array`,
  ~60 LOC including doc comment. Idempotent, per-file transaction,
  re-throws with filename context on failure. Zero external deps
  beyond PDO.
- `tests/test.php` — added `migrate() applies pending migrations and
  skips applied ones` against an in-memory SQLite db with a tmp
  fixture dir. Pre-existing share test still green.
- `seed.php` — requires the runner and invokes
  `migrate($pdo, __DIR__ . '/migrations')` after the `schema.sql`
  baseline, before seed-row inserts.
- `schema.sql` — one-line frozen-baseline comment at the top.
  Nothing else touched.

## What was surprising

- **Chicken-and-egg in the design doc.** The contract says the runner
  bootstraps the tracking table with `CREATE TABLE IF NOT EXISTS` AND
  that `0001_init_schema_migrations.sql` is a tracked migration. If
  the migration file used a bare `CREATE TABLE`, the standard apply
  path would explode on first run because the runner has already
  created the table. The cleanest reading is to use
  `CREATE TABLE IF NOT EXISTS` in the migration file itself so it's
  a safe no-op on the apply path — that way the chicken-and-egg
  resolves without special-casing `0001` in the runner. Worth flagging
  in case civicplus wants to make this explicit in the design doc.
- **Nothing else.** Setup ran cleanly. Docker rebuilt in ~30s, seed
  printed the URL, tests green on first run.

## Pre-existing issues noticed

- `tests/test.php` boots by `system()`-ing `seed.php`, which
  unconditionally wipes `db.sqlite`. That's fine here but it means
  any future test that wants persistent state has to work around it.
  Out of scope.
- `lib/bootstrap.php` `db()` memoizes a single PDO at module scope
  keyed on `__DIR__ . '/../db.sqlite'`. If a feature session ever
  wants to test against an alternate db, it'll need to refactor.
  Out of scope; flagging for awareness.
- `docker compose.yml` runs `php seed.php && php -S ...` so every
  container restart re-seeds (i.e. wipes) `db.sqlite`. README
  documents this, but it's worth knowing for downstream sessions
  that any state added between restarts is lost.

## Recommendations for downstream feature sessions

- **Pick the next free sequence number.** `0002_*.sql` is the next
  slot. Gaps are fine if a feature gets dropped (search-by-name
  probably needs no migration at all — design doc explicitly says so).
- **Multi-statement SQL works.** `PDO::exec()` handles multiple
  statements per file, so you can `ALTER`/`CREATE INDEX`/etc. in
  the same migration.
- **Transactions are per-file, not per-statement.** If your migration
  has multiple statements that must succeed together, that's already
  handled — they share one transaction. If one fails the whole file
  rolls back.
- **SQLite has limited `ALTER TABLE` powers.** Adding a column is
  fine (`ALTER TABLE documents ADD COLUMN publish_at TEXT`), but
  changing types or dropping columns is a copy-table dance. Plan
  accordingly.
- **Don't touch `schema.sql`.** It's frozen. The comment at the top
  spells it out. Reviewers will be looking for this.
- **The runner does not log to `audit_log`.** Migrations are infra,
  not user-driven events. Don't add audit entries to your migration
  files — that pattern is for runtime document/share/schedule
  lifecycle.
- **Re-seeding between dev iterations is cheap.** `docker compose
  down -v && docker compose up -d` from your worktree rebuilds and
  re-seeds in well under a minute.

## Verification evidence

- `docker compose up -d` → seed printed admin URL.
- `docker compose exec app php tests/test.php` →
  `2 passed, 0 failed.` (the new idempotency test and the
  pre-existing share-link test).
- `SELECT * FROM schema_migrations;` →
  `0001_init_schema_migrations.sql | 2026-05-18 23:35:12`.
