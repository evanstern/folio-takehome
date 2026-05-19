---
schema_version: 2
id: 1
uuid: 019e3d5a-bb1a-7710-b8f2-c7ab29317e30
title: 'migrations-infra: numbered SQL files + tiny PHP runner'
type: card
status: done
priority: p0
project: folio-takehome
created: 2026-05-18
---
## Overview

Folio has no migration system. The README requires one (schema changes must go through migration files, not edits to `schema.sql`). This card delivers the runner that the other three feature cards depend on.

Proposed shape: numbered SQL files in `migrations/` (e.g. `001_add_publish_at.sql`), applied in order by a tiny PHP runner invoked from `seed.php`, tracked in a `schema_migrations` table. No down-migrations, no DSL. Final shape pending the librarian survey (heavyweight tools, lightweight patterns, real PHP+SQLite practice — three parallel agents running).

## Design doc

See [.coda/designs/migrations-infra.md](.coda/designs/migrations-infra.md) (TBD — written after librarian survey lands and decision is locked with Evan).

Related: [[2026-05-18-1630-decision-migrations-shape]] — currently `status: proposed`.

## Acceptance

- [ ] `migrations/` directory with `001_*.sql` style numbered files (or whatever the locked decision specifies)
- [ ] PHP runner applies unapplied migrations in order on boot (via `seed.php`)
- [ ] `schema_migrations` tracking table (or `PRAGMA user_version` — TBD)
- [ ] Idempotent: re-running does nothing if all migrations applied
- [ ] `schema.sql` becomes the post-migrations snapshot (or is left as the initial-schema baseline — TBD in design doc)
- [ ] `docker compose up` works from fresh clone with seed → migrate → ready
- [ ] One test in `tests/test.php` proves the runner skips applied migrations

## Blocks

Every other feature card. Cannot land scheduled-publishing, readable-ids, or search-by-name until this is in.

## Tags

`infra`, `migrations-infra`, `p0`
