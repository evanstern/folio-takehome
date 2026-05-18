---
tags: [design, infra, migrations]
description: Migration runner shape and integration with seed.php. Blocks all feature work.
status: skeleton-awaiting-decision
created: 2026-05-18
updated: 2026-05-18
---

# Design: migrations infrastructure

> **Status: SKELETON.** Body is filled in once Evan locks
> [[2026-05-18-1630-decision-migrations-shape]] from `status: proposed` to
> `status: approved`. Civicplus's recommendation is **Option A** (numbered
> SQL files + tiny PHP runner + `schema_migrations` table) per the
> session-2 librarian survey.

## Problem

The README requires schema changes to go through migration files we add —
not edits to `schema.sql`. The repo ships with no migration system. Before
any of the three features can land, the migration runner must exist and be
wired into the `docker compose up` boot path.

## Decision pending

See [[2026-05-18-1630-decision-migrations-shape]]. Three options surveyed
(see session-2 memory):

- **Option A** — numbered SQL files in `migrations/` + tiny PHP runner +
  `schema_migrations` tracking table. **Civicplus recommends.**
- **Option B** — `PRAGMA user_version` + inline PHP migrations.
- **Option C** — hybrid (`.sql` files + `PRAGMA user_version`).

This design doc fills in once a choice is locked. Sections below are the
**Option A** shape; if Evan picks B or C, the schema-impact and runner
sections rewrite.

## Scope

- [TBD on lock] `migrations/` directory at repo root with numbered `.sql` files
- [TBD on lock] PHP runner module (likely `lib/migrate.php`, ~50-70 LOC)
- [TBD on lock] `schema_migrations` tracking table (Option A) OR `PRAGMA user_version` (Option B)
- [TBD on lock] Integration point: `seed.php` calls runner after creating fresh DB
- [TBD on lock] What happens to existing `schema.sql` — frozen baseline OR generated snapshot

## Schema impact

[TBD pending decision lock]

**If Option A:**
```sql
-- migrations/000_init_migration_tracking.sql (the first migration)
CREATE TABLE schema_migrations (
    version    TEXT PRIMARY KEY,
    applied_at TEXT NOT NULL DEFAULT (datetime('now'))
);
```

**If Option B:** no new table. `PRAGMA user_version` is built in.

## Runner contract

[TBD pending decision lock]

For Option A, the runner:
1. Connects to `db.sqlite` via PDO
2. Creates `schema_migrations` if absent (chicken-and-egg: cannot itself be a migration)
3. `glob('migrations/*.sql')`, sort lexicographically
4. For each file, if `version NOT IN schema_migrations`: `BEGIN; <file contents>; INSERT INTO schema_migrations; COMMIT;` with rollback on exception
5. Report applied / skipped counts

## Audit-log impact

None. Migrations are infrastructure, not user-driven events. The
audit-log pattern ([[2026-05-18-1600-pattern-audit-log]]) is for
document/share/schedule lifecycle, not schema changes.

## Test plan

[TBD pending decision lock]

Minimum coverage in `tests/test.php`:
- Runner applies all unapplied migrations from a fresh DB
- Runner skips already-applied migrations on re-run (idempotency)
- Runner rolls back transaction on SQL error in a migration
- Runner reports counts correctly

## Rejected alternatives

[TBD pending decision lock — section will cover Phinx, Doctrine, Illuminate
from the librarian survey, plus whichever of A/B/C isn't chosen. Key
sound-bites already gathered:]

- **Phinx** — least-bad of the heavy options; ~10 transitive deps, SQLite-
  native, fluent + raw-SQL support. Rejected as overkill for ~380 LOC PHP.
- **Doctrine Migrations** — ~15-20 deps, more enterprise. Rejected.
- **Illuminate Database** — PHP 8.3+, ~20-25 deps. Rejected.
- **`PRAGMA user_version`** — actually idiomatic for SQLite-native projects
  (KanBoard, Phoronix, OPodSync). Strong contender. Rejected (if A wins)
  because migrations live in PHP not greppable `.sql`, and reviewer-clarity
  is the grading axis.
- **No migration system at all** (single `schema.sql` with `IF NOT EXISTS`) —
  rejected because README explicitly forbids editing `schema.sql`.

## Video talking points

[TBD pending decision lock]

- "The README leaves migration shape open. I surveyed Phinx, Doctrine,
  Illuminate, and SQLite-native patterns. All three heavyweights pulled in
  10-25 transitive dependencies for a 380-LOC app."
- "The most idiomatic SQLite-native pattern is actually `PRAGMA user_version`
  — used by KanBoard, Phoronix, OPodSync. I chose [A vs B] because [reason]."
- "The decision was deliberate. Showing the survey AND the rejected options
  IS the judgment the README asks to see."

## Related

- [[2026-05-18-1630-decision-migrations-shape]]
- [[folio-schema]]
- [[folio-docker]] — `seed.php` integration point
- Focus card #1
