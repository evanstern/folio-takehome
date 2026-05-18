---
tags: [design, infra, migrations]
description: Numbered SQL files in migrations/, applied by tiny PHP runner from seed.php, tracked in schema_migrations. 0001 owns the full baseline schema (no separate schema.sql). Forward-only. Blocks all feature work.
status: approved
created: 2026-05-18
updated: 2026-05-18
---

# Design: migrations infrastructure

> **Status: APPROVED.** Option A locked in
> [[2026-05-18-1630-decision-migrations-shape]] (session 2). This
> doc is the contract for the `migrations-infra` feature session
> (focus card #1).
>
> **Revised in session 3** per Evan's question (focus card #8):
> `schema.sql` is removed entirely and `migrations/0001_init_schema.sql`
> owns the full baseline. The "frozen baseline" framing is dropped;
> migrations are the only source of truth for schema. See the
> decision page's "Revision: schema.sql collapsed into 0001" section
> for the rationale and video talking points.

## Problem

The README requires schema changes to go through migration files we add —
not edits to `schema.sql`. The repo ships with no migration system. Before
any of the three features can land, the migration runner must exist and be
wired into the `docker compose up` boot path.

## Scope

Single feature session delivers all of the following:

1. **`migrations/` directory** at repo root with `0001_init_schema.sql`
   containing the full baseline schema (staff, documents, shares,
   audit_log, schema_migrations) — see "Schema impact" below
2. **PHP runner** at `lib/migrate.php` (~30-50 LOC) implementing the contract below
3. **`seed.php` integration:** runner is invoked first; no more
   `exec(file_get_contents('schema.sql'))` call. Seed-row inserts
   come after.
4. **No `schema.sql` in the repo.** The migration system is the only
   source of truth for schema. Deletion (not editing) preserves the
   README's "don't edit `schema.sql`" requirement.
5. **One test in `tests/test.php`** proving the runner skips applied
   migrations on re-run (idempotency)

Out of scope for this session:
- Any actual schema changes (those are the feature sessions: scheduled-
  publishing, readable-ids, search-by-name)
- Down migrations (rejected — see decision page)
- Migration generators or CLI scaffolding (overkill)

## Schema impact

`migrations/0001_init_schema.sql` contains the full baseline:

```sql
-- 0001_init_schema.sql
--
-- Baseline schema for folio. This is the only source of truth for
-- the schema — there is no separate schema.sql. All future schema
-- changes go in additional migrations (0002+).
--
-- schema_migrations uses IF NOT EXISTS so the runner can bootstrap
-- the tracking table before it discovers and records this very file.
CREATE TABLE IF NOT EXISTS schema_migrations (
    version    TEXT PRIMARY KEY,
    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE staff (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL
);

CREATE TABLE documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (created_by) REFERENCES staff(id)
);

CREATE TABLE shares (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_id INTEGER NOT NULL,
    token TEXT NOT NULL UNIQUE,
    recipient_email TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    FOREIGN KEY (document_id) REFERENCES documents(id)
);

CREATE TABLE audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    staff_id INTEGER,
    action TEXT NOT NULL,
    entity_type TEXT,
    entity_id INTEGER,
    details TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
```

The runner still ensures `schema_migrations` exists via
`CREATE TABLE IF NOT EXISTS` before discovering migrations. That
plus the `IF NOT EXISTS` inside 0001 itself makes the bootstrap a
clean no-op the first time through: runner creates the tracking
table, finds 0001 unapplied, executes the file (which re-runs the
`IF NOT EXISTS` on the tracking table harmlessly, then creates the
four real tables), and records `0001_init_schema.sql` in
`schema_migrations`.

## Runner contract

`lib/migrate.php` exports one function:

```php
function migrate(PDO $db, string $migrationsDir): array
```

Returns an associative array `['applied' => [...filenames], 'skipped' => [...filenames]]`
so `seed.php` and tests can assert on outcomes.

Behavior:

1. **Bootstrap tracking table.** Execute `CREATE TABLE IF NOT EXISTS
   schema_migrations (version TEXT PRIMARY KEY, applied_at TEXT NOT
   NULL DEFAULT (datetime('now')))`. Idempotent; first-run safe.
2. **Discover migrations.** `glob($migrationsDir . '/*.sql')`, sort
   lexicographically. Filenames are the source of truth for ordering.
3. **Load applied set.** `SELECT version FROM schema_migrations` →
   in-memory set keyed by `basename`.
4. **For each file not in applied set:**
   - `$db->beginTransaction()`
   - Read file contents, `$db->exec($sql)` (multi-statement SQL ok)
   - `INSERT INTO schema_migrations (version) VALUES (?)` with `basename($file)`
   - `$db->commit()`
   - On exception: `$db->rollBack()`, rethrow with file context (`"migration $basename failed: $message"`)
5. **Return** `['applied' => $newlyApplied, 'skipped' => $alreadyApplied]`.

Constraints:
- Runner has zero external dependencies beyond PDO (already in use)
- Runner does NOT touch `schema.sql` — that's pre-applied by `seed.php`
- Runner uses `PDO::ERRMODE_EXCEPTION` (already set in `lib/bootstrap.php`)

## File naming convention

`NNNN_<slug>.sql` — four-digit zero-padded sequence number, underscore,
short slug, `.sql` extension.

- `0001_init_schema.sql` — full baseline schema, this design doc
- `0002_add_publish_at.sql` — scheduled-publishing
- `0003_add_readable_id.sql` — readable-ids
- (search-by-name needs no migration — no schema changes for LIKE)

Numbers are assigned by the feature session at the time of writing.
Gaps are fine if a feature is dropped. Sequence is for sort order,
not semantic versioning.

## seed.php integration

Original `seed.php` flow (pre-migrations):
1. Wipe `db.sqlite` if present
2. Apply `schema.sql` baseline
3. Insert seed rows (1 staff, 2 documents)
4. Print "Open http://localhost:$port/admin.php"

New flow:
1. Wipe `db.sqlite` if present
2. **`require __DIR__ . '/lib/migrate.php'; migrate($db, __DIR__ . '/migrations');`**
3. Insert seed rows
4. Print URL

No `schema.sql` step. The migration runner is the only path to a
populated schema. `0001_init_schema.sql` creates the baseline tables;
`0002+` add deltas; `seed.php` doesn't need to know which is which.

## Audit-log impact

**None.** Migrations are infrastructure, not user-driven events. The
audit-log pattern ([[2026-05-18-1600-pattern-audit-log]]) is for
document/share/schedule lifecycle, not schema changes.

## Test plan

In `tests/test.php`, add:

```php
test('migrate() applies pending migrations and skips applied ones', function () {
    $db = new PDO('sqlite::memory:');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Use a fixture migrations dir with one no-op migration
    $tmpDir = sys_get_temp_dir() . '/folio-migrate-test-' . uniqid();
    mkdir($tmpDir);
    file_put_contents($tmpDir . '/0001_test.sql',
        'CREATE TABLE test_table (id INTEGER PRIMARY KEY);');

    $result1 = migrate($db, $tmpDir);
    assert_true(count($result1['applied']) === 1, 'first run applies migration');
    assert_true(count($result1['skipped']) === 0, 'first run skips nothing');

    $result2 = migrate($db, $tmpDir);
    assert_true(count($result2['applied']) === 0, 'second run applies nothing');
    assert_true(count($result2['skipped']) === 1, 'second run skips applied');

    // Cleanup
    unlink($tmpDir . '/0001_test.sql');
    rmdir($tmpDir);
});
```

Note: uses `::memory:` SQLite, doesn't touch the real `db.sqlite`.

## Rejected alternatives

Captured in full in [[2026-05-18-1630-decision-migrations-shape]].
Summary:

- **Option B (`PRAGMA user_version` + inline PHP migrations)** —
  idiomatic for SQLite-native projects (KanBoard, Phoronix, OPodSync)
  but migrations live in PHP, not greppable as `.sql`. Reviewer-clarity
  wins for Option A.
- **Option C (hybrid: `.sql` files + `PRAGMA user_version`)** — Evan
  flagged this explicitly: "may be more than is necessary." Saves one
  table at the cost of cleverness in the runner. Considered and
  rejected per Evan's framing.
- **Phinx, Doctrine Migrations, Illuminate Database** — all require
  Composer for a repo that doesn't have it. ~10-25 transitive deps for
  ~380 LOC of PHP.
- **`byjg/php-migration`** — same Composer objection.
- **Single `schema.sql` with `IF NOT EXISTS`** — README forbids editing
  `schema.sql`.
- **Up + down migrations** — theater in this context. Forward-only.
- **Runtime migrations at first PDO connect** — hides the apply step
  from review. Explicit in `seed.php` is honest.

## Video talking points

- "I built the smallest migration system that satisfies the requirement.
  No Composer dependency, no DSL, no down-migrations — just numbered
  SQL files and a 30-line runner."
- "I surveyed Phinx, Doctrine Migrations, Illuminate Database, and
  `byjg/php-migration`. All four required introducing Composer for ~380
  LOC of PHP. None of the SQLite-native production projects I looked
  at (KanBoard, Phoronix Test Suite, OPodSync) use any of them."
- "The genuinely idiomatic SQLite pattern is `PRAGMA user_version` with
  inline PHP migrations. I almost shipped that and chose against it —
  migrations in PHP are less greppable than `.sql` files in a directory,
  and reviewer-clarity is the grading axis."
- "Option C was a real consideration — `.sql` files but `PRAGMA
  user_version` for tracking instead of a `schema_migrations` table.
  Trades one visible table for cleverness in the runner. I went with
  the explicit table because `SELECT * FROM schema_migrations` is the
  most honest answer to 'what's been applied here.'"
- "The first pass kept `schema.sql` as a frozen baseline with
  migrations layered on top. Evan asked whether that was earning its
  keep — it wasn't. I deleted `schema.sql` and moved the full
  baseline into `0001_init_schema.sql`. One source of truth.
  Note the verb: I **deleted** `schema.sql`, didn't edit it. The
  README says don't edit it; the strong reading is no schema-edit
  path outside migrations at all."

## Related

- [[2026-05-18-1630-decision-migrations-shape]]
- [[folio-schema]]
- [[folio-docker]] — `seed.php` integration point
- [[folio-tests]]
- Focus card #1 `migrations-infra`
