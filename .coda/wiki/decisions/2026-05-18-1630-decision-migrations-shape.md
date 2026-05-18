---
tags: [decision, infrastructure, migrations]
description: Numbered SQL files in migrations/, applied by tiny PHP runner via seed.php, tracked in schema_migrations
status: proposed
created: 2026-05-18
updated: 2026-05-18
---

# Decision: migrations shape

> **Status: PROPOSED.** Pending Evan's review. Per [[2026-05-18-1740-pattern-collaboration-with-evan]],
> civicplus does not finalize design decisions unilaterally.

## Open question (per PROJECT.md edit)

> "Are there PHP/SQLite specific migration tools/ORMs we should consider?
> What is 'best practice'? What is 'overkill' for us?"

Before locking this decision, civicplus must:
1. Spawn a librarian/explore agent to survey the PHP migration landscape
   (Phinx, Doctrine Migrations, Laravel-style schema builder, raw-PDO patterns)
2. Score each on: dependency weight (does it require Composer?),
   readability for a reviewer, fit for SQLite specifically, time-to-integrate
3. Surface findings + recommendation to Evan before finalizing

The bespoke-runner approach below is civicplus's current best guess.
The research may change it.

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

- **Bake changes back into `schema.sql`.** README explicitly forbids this. Easy to forget the constraint, easy to lose.
- **PHP migration files instead of SQL.** Overkill for this codebase. Adds a syntax decision and a class hierarchy for no payoff. SQLite's `executescript` handles multi-statement SQL fine.
- **A "real" migration tool (Phinx, Doctrine).** Composer is not set up. Adding a dependency manager for one feature is way out of scope. Build the smallest thing that's right.
- **Up + down migrations.** Down migrations are theater in a SQLite + take-home context. Forward-only.
- **Runtime migrations at first PDO connect.** Tempting (no `seed.php` edit needed), but it hides the apply step from review and complicates `tests/test.php`. Explicit in `seed.php` is honest.

## Trade-offs accepted

- Migrations are forward-only → can't easily roll back a bad migration in dev. Re-seed wipes everything anyway, so dev-cycle cost is zero.
- The runner is bespoke → reviewer needs to read 30 lines of glue. Acceptable: it's tiny, in one file, and the README explicitly invites us to invent here.
- `schema.sql` is now the "frozen baseline." Future schema changes go in migrations forever. Worth a note in the video.

## Talking points for the video

- "I built the smallest migration system that satisfies the requirement.
  No Composer dependency, no down-migrations, no DSL — just numbered
  SQL files and a 30-line runner."
- "I considered making the runner more clever (auto-detect on connect,
  PHP-based migrations) and rejected both for the same reason: more
  surface area to read for no real benefit at this scale."

## Related
- [[folio-schema]]
- [[folio-docker]]
