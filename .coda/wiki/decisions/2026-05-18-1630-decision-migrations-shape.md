---
tags: [decision, infrastructure, migrations]
description: Numbered SQL files in migrations/, applied by tiny PHP runner via seed.php, tracked in schema_migrations
status: approved
created: 2026-05-18
updated: 2026-05-18
---

# Decision: migrations shape

> **Status: APPROVED** by Evan (session 2, after librarian survey).
> "A works." Option C explicitly noted as considered-and-rejected per
> Evan's direction — not as showing-off but as "more than necessary
> for this scope."

## Research that informed this

Three librarian agents surveyed the PHP migration landscape in parallel:
- Heavyweight tools: Phinx, Doctrine Migrations, Illuminate Database
- Lightweight/raw-PDO patterns + `byjg/php-migration`
- What real PHP+SQLite projects (KanBoard, Phoronix, OPodSync, FreshRSS,
  Selfoss) actually do

Findings: all three converged on "don't use a heavyweight tool" for this
scope (~380 LOC PHP, no Composer setup, ~3hr budget). They disagreed on
what minimal looks like — three options surfaced (A, B, C below).

## Context

README requires: "Schema changes go through a migration file (or files)
you add to the repo, not by editing `schema.sql` directly. There is no
migration system yet — you decide how to organize one. Explain your
approach in your video."

Constraints:
- Has to work with `docker compose up` from a fresh clone
- `seed.php` already wipes `db.sqlite` and applies `schema.sql` on every run
- Three features each need at least one schema change

## Decision

**Numbered SQL files in `migrations/`, applied by a tiny PHP runner
invoked from `seed.php`. Idempotency via a `schema_migrations` tracking
table.**

```
migrations/
  0001_add_publish_at.sql
  0002_add_readable_id.sql
  ...
```

Naming: `NNNN_<slug>.sql`, zero-padded to 4 digits, lexically sortable.
One file = one logical change.

Runner: a small `lib/migrate.php` (≤30 LOC) that:
1. Ensures `schema_migrations (filename TEXT PRIMARY KEY, applied_at TEXT)` exists
2. Scans `migrations/*.sql` in lexical order
3. For each not in `schema_migrations`, executes it in a transaction and records the filename

`seed.php` flow becomes:
1. wipe `db.sqlite`
2. apply `schema.sql` (baseline)
3. apply migrations (`require lib/migrate.php; migrate();`)
4. insert seed rows

## Rejected alternatives

### Option B: `PRAGMA user_version` + inline PHP migrations

Actually idiomatic for SQLite-native projects. Real production use:
KanBoard, Phoronix Test Suite, OPodSync, WatchState, KaraDAV,
DiskLocation. Pattern:

```php
$version = (int) $db->query('PRAGMA user_version')->fetchColumn();
if ($version < 1) {
    $db->exec('BEGIN');
    $db->exec('ALTER TABLE documents ADD COLUMN publish_at TEXT NULL');
    $db->exec('PRAGMA user_version = 1');
    $db->exec('COMMIT');
}
if ($version < 2) { /* ... */ }
```

**Strengths:** ~30 LOC total. No extra tracking table. Atomic
(version pragma is in the SQLite header). Demonstrates we looked at
SQLite-native practice instead of cargo-culting ActiveRecord.

**Why rejected (in favor of A):** migrations live in PHP, not
greppable as `.sql` files. Reviewer running `ls migrations/` and
reading three SQL files top-to-bottom understands the schema history
in 5 seconds. Reading PHP to reconstruct it takes longer. Reviewer-
clarity is the grading axis. Worth showing we considered it.

### Option C: Hybrid — `.sql` files + `PRAGMA user_version` for tracking

`.sql` files in `migrations/`, but the runner uses `PRAGMA user_version`
instead of a `schema_migrations` table. Files named `0001_*.sql`,
`0002_*.sql`; runner reads version, applies files numbered higher,
bumps the pragma.

**Strengths:** smallest schema footprint (no extra table) + reviewer
can still grep `.sql` files. Best-of-both per Evan's framing.

**Why rejected (in favor of A):** Evan's call: "may be more than is
necessary." The bespoke `schema_migrations` table is a tiny amount
of "visible" tracking — a reviewer running `SELECT * FROM
schema_migrations` immediately sees what's applied and when. The
hybrid approach saves one table at the cost of a bit more cleverness
in the runner. For a 3-hour exercise where simplicity is the message,
the explicit table wins. **Worth mentioning in the video that we
considered the hybrid and chose against it deliberately.**

### Heavyweight tools (Phinx, Doctrine Migrations, Illuminate Database)

All rejected as overkill:
- **Phinx** (least-bad): ~10 transitive Composer deps, SQLite-native,
  fluent + raw-SQL support. Still overkill for ~380 LOC PHP.
- **Doctrine Migrations**: ~15-20 deps, enterprise-shaped.
- **Illuminate Database**: PHP 8.3+, ~20-25 deps, requires bootstrap
  capsule.

None of the three real PHP+SQLite production projects we surveyed
use Phinx. It's designed for MySQL/PostgreSQL contexts. Adding any
of them requires introducing Composer to a repo that doesn't have
it — that itself is a meaningful change to the deliverable.

### Smaller rejected alternatives

- **Bake changes back into `schema.sql`.** README explicitly forbids this.
- **PHP migration files instead of SQL.** Overkill for this codebase. Adds a syntax decision and a class hierarchy for no payoff. SQLite's `executescript` handles multi-statement SQL fine.
- **Up + down migrations.** Down migrations are theater in a SQLite + take-home context. Forward-only.
- **Runtime migrations at first PDO connect.** Tempting (no `seed.php` edit needed), but it hides the apply step from review and complicates `tests/test.php`. Explicit in `seed.php` is honest.
- **`byjg/php-migration`.** Real package, 165 stars, SQLite-capable. Requires Composer. Same "introduce a dependency manager for one feature" objection as Phinx.
- **Single-file `schema.sql` with `CREATE TABLE IF NOT EXISTS`** (no migration system). Rejected because README explicitly forbids editing `schema.sql`.

## Trade-offs accepted

- Migrations are forward-only → can't easily roll back a bad migration in dev. Re-seed wipes everything anyway, so dev-cycle cost is zero.
- The runner is bespoke → reviewer needs to read 30 lines of glue. Acceptable: it's tiny, in one file, and the README explicitly invites us to invent here.
- `schema.sql` is now the "frozen baseline." Future schema changes go in migrations forever. Worth a note in the video.

## Talking points for the video

- "I built the smallest migration system that satisfies the requirement.
  No Composer dependency, no down-migrations, no DSL — just numbered
  SQL files and a 30-line runner."
- "I surveyed Phinx, Doctrine, Illuminate, and `byjg/php-migration` —
  all required Composer for ~380 LOC of PHP. None of the SQLite-native
  production projects I looked at (KanBoard, Phoronix, OPodSync) use
  any of them. They use `PRAGMA user_version` with inline PHP migrations."
- "I almost shipped the `PRAGMA user_version` pattern. It's genuinely
  idiomatic for SQLite. I chose the bespoke `schema_migrations` table
  instead because reviewer-clarity is the grading axis — `.sql` files
  in a directory + a tiny visible tracking table reads in 5 seconds.
  `PRAGMA user_version` would need a paragraph of explanation."
- "There's a hybrid (Option C) — `.sql` files with `PRAGMA user_version`
  for tracking. I considered it and chose against it. The bespoke
  tracking table is more honest for a reviewer; the hybrid saves one
  table at the cost of cleverness in the runner."

## Related
- [[folio-schema]]
- [[folio-docker]]
