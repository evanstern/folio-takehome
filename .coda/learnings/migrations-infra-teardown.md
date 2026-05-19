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

## Session 3 amendment — schema.sql collapsed into 0001

Evan approved revising Option A: collapse `schema.sql` into the
migration history instead of holding it as a frozen baseline. The
runner is unchanged; the data shape moves.

### What shipped (in addition to the initial pass)

- `git mv migrations/0001_init_schema_migrations.sql
  migrations/0001_init_schema.sql` and expanded the file to contain
  the full baseline (schema_migrations + staff + documents + shares
  + audit_log). The four domain tables are byte-for-byte the same
  statements that lived in `schema.sql` — verified via
  `diff <(grep '^CREATE TABLE' old_schema.sql | sort)
       <(grep '^CREATE TABLE' new_0001 | sort)`.
- `git rm schema.sql`. The file is no longer in the repo, full stop.
- `seed.php` — dropped the
  `$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'))` line and
  the now-stale "incremental migrations on top of frozen baseline"
  comment. Replaced with a single one-liner pointing to the new
  invariant: "Migrations own the schema. 0001 is the baseline."
- `lib/migrate.php` — unchanged. The runner already bootstraps the
  tracking table with `CREATE TABLE IF NOT EXISTS`, so 0001's own
  `IF NOT EXISTS` for the same table makes the apply pass a safe
  no-op. The domain tables in 0001 use bare `CREATE TABLE` so a
  re-apply on a populated DB fails loudly instead of silently
  masking schema drift.
- Idempotency test in `tests/test.php` — unchanged. Still uses
  `::memory:` SQLite and a tmpdir fixture; doesn't touch
  `db.sqlite` or any real migration file.

### Rationale

The README forbids *editing* `schema.sql`. We're deleting it —
different verb, different intent. Collapsing into 0001 means the
migration history is the single, complete, executable description
of the schema. `SELECT version FROM schema_migrations` + the
contents of `migrations/` together tell the whole story. No parallel
narratives, no "frozen baseline plus deltas" mental composition.

### What was surprising (this session)

- **`git log --follow` traces to `schema.sql`, not the renamed file.**
  The amendment doc expected `--follow` on
  `migrations/0001_init_schema.sql` to chain back to
  `0001_init_schema_migrations.sql`. Git's rename-detection
  heuristic picked the higher-similarity ancestor (`schema.sql` at
  56%, since 4 of the 5 `CREATE TABLE` statements came from there)
  over the renamed-but-now-mostly-overwritten original (13 lines,
  most replaced). The substantive lineage is the one git found —
  most of the bytes in 0001 *did* come from schema.sql — so the
  history is honest, just on a different axis. Flagging because the
  amendment's done-condition language assumed the other chain.
- **`.coda/designs/migrations-infra.md` and the decision-shape page
  were NOT updated** before this session ran, despite the inbox
  message claiming they would be. Followed the IMPLEMENT.md
  amendment block as the operative contract (per the standing rule
  "if the design doc is wrong, flag it in the teardown, don't
  silently deviate"). Civicplus to reconcile.
- **A spurious commit `285256a` (later force-pushed away)** carried
  the message "migrate: guard beginTransaction()/rollBack() and
  validate dir up front" but in practice contained only a file
  rename. The runner edits that message described had been applied
  to the worktree out-of-band by civicplus in parallel with a
  Copilot review pass, then accidentally captured under the wrong
  commit message. Path-2 redo: hard-reset to `1286f62`, single
  clean commit on top, `push --force-with-lease`. The Copilot fixes
  are being reconciled separately by civicplus.

### Verification evidence (amendment)

- `git ls-files | grep -E '(^|/)schema\.sql$'` → empty.
- `docker compose down -v && docker compose up -d` → seed printed
  admin URL with no schema.sql reference in the seed output.
- `SELECT name FROM sqlite_master WHERE type='table' ORDER BY name`
  → `audit_log, documents, schema_migrations, shares,
  sqlite_sequence, staff`. All expected.
- `SELECT version, applied_at FROM schema_migrations` →
  `0001_init_schema.sql @ 2026-05-19 00:02:29`.
- `docker compose exec app php tests/test.php` →
  `2 passed, 0 failed.`
- `curl -sS http://localhost:8089/admin.php` → HTTP 200, 2 `<tr`
  rows (header + the seeded document).
