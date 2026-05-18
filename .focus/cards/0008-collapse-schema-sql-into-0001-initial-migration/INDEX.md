---
schema_version: 2
id: 8
uuid: 019e3d79-053b-7956-8c3e-59f8eda2025a
title: Collapse schema.sql into 0001 initial migration
type: card
status: active
priority: p1
project: main
created: 2026-05-18
---
## Overview

The current `migrations-infra` design treats `schema.sql` as a frozen
baseline (with a "do not edit" header comment) and layers numbered
migrations on top. Evan asked: can we instead **drop `schema.sql`
entirely** and make `0001_init_schema.sql` create the full baseline
schema, so the migration system is the only source of truth?

This card is a design-call to surface to Evan, not an in-flight task.
**Status: proposed, awaiting Evan's nod before touching the
migrations-infra feature session.**

## Why it matters

- Today: two sources of truth (`schema.sql` + `migrations/`) glued
  together by a comment header and runner ordering. The "frozen
  baseline" framing is a workaround, not a design.
- Proposed: one source of truth (`migrations/`). Migration system
  actually owns the schema. Cleaner video story:
  > "schema lives in `migrations/`, full stop."

## Two shapes

### A. Status quo (current design)
- `schema.sql` exists, frozen, applied first by `seed.php`
- `migrations/0001_init_schema_migrations.sql` only creates the
  `schema_migrations` tracking table
- Future feature migrations are 0002+
- `seed.php`: `exec(schema.sql)` → `migrate()` → seed rows

### B. Collapse (proposed)
- `schema.sql` deleted from repo
- `migrations/0001_init_schema.sql` creates **everything**:
  staff, documents, shares, audit_log, AND schema_migrations
  (the runner still needs `CREATE TABLE IF NOT EXISTS` for
  `schema_migrations` to bootstrap the chicken-and-egg)
- Future feature migrations are 0002+ as before
- `seed.php`: `migrate()` → seed rows

## Tradeoffs

| Axis | A (status quo) | B (collapse) |
|---|---|---|
| Sources of truth | 2 (schema.sql + migrations) | 1 (migrations only) |
| Initial baseline scannable | yes, one file | yes, one file (0001) |
| README compatibility | literal — `schema.sql` exists | strong reading — no `schema.sql` to edit |
| Video narrative | "frozen baseline + deltas" | "migrations own the schema" |
| Initial migration size | ~3 lines (tracking table) | ~30 lines (full schema + tracking) |
| Reviewer first stop | `schema.sql` (familiar) | `migrations/0001_init_schema.sql` |
| Drift risk | `schema.sql` and migrations could diverge | none — single source |
| `seed.php` complexity | applies schema then migrates | just migrates |

## README check

The README says:

> Schema changes go through a migration file (or files) you add to
> the repo, **not by editing `schema.sql` directly**.

That sentence assumes `schema.sql` exists. But the stronger reading
is: there should be no schema-edit path outside migrations. Removing
`schema.sql` enforces that by construction. Defensible either way;
worth calling out in the video.

## Recommendation

**Option B.** The frozen-baseline framing is a workaround we invented
to ship migrations-infra without disturbing the existing `schema.sql`.
Now that the migration runner exists, the workaround has earned its
removal. The video beat "I considered keeping schema.sql as a frozen
file and decided against it — one source of truth is honest" is
exactly the kind of judgment the README asks for.

The only argument for staying with A is "less churn on the in-flight
PR." But the migrations-infra branch is not yet merged — this is
the cheapest moment to make the change.

## Acceptance (if approved)

- [ ] `schema.sql` deleted
- [ ] `migrations/0001_init_schema_migrations.sql` renamed to
      `0001_init_schema.sql` and expanded to contain the full baseline
      (staff, documents, shares, audit_log) plus `schema_migrations`
- [ ] `seed.php` no longer references `schema.sql`; just calls
      `migrate()` then inserts seed rows
- [ ] `migrations-infra` design doc updated to reflect new shape;
      "frozen baseline" language replaced with "migrations own the
      schema"
- [ ] Decision page [[2026-05-18-1630-decision-migrations-shape]]
      updated with a new "Revised after Evan question" section
- [ ] Existing idempotency test still passes
- [ ] `docker compose up` from fresh clone produces same DB state
- [ ] Video talking-points updated in design doc

## Blocks / blocked by

- **Blocks:** none directly, but the longer this sits the more
  feature-session migrations (0002, 0003) we have to renumber if
  we land it late. Resolve before spawning scheduled-publishing
  or readable-ids feature sessions.
- **Blocked by:** Evan's nod.

## Related

- [[2026-05-18-1630-decision-migrations-shape]] — original decision
- `.coda/designs/migrations-infra.md` — current design doc
- focus card #1 `migrations-infra` — the in-flight feature session
  this card would amend

## Tags

design, infra, migrations, propose-and-wait
