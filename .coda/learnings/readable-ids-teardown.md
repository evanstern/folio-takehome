---
tags: [learnings, teardown, readable-ids]
description: Session teardown for the readable-ids feature.
created: 2026-05-18
---

# Teardown: readable-ids

## What shipped

- `migrations/0003_add_readable_id.sql` — `ALTER TABLE documents ADD COLUMN readable_id TEXT;`
- `migrations/0004_readable_id_unique.sql` — `CREATE UNIQUE INDEX idx_documents_readable_id`
- `lib/readable_id.php` — `generate_readable_id()` + `generate_readable_id_unique()`
- `lib/bootstrap.php` — pulls in `readable_id.php`
- `public/admin.php` — generates `readable_id` on doc create; audit-log payload includes it
- `public/share.php` — builds share URL as `view.php?d=<rid>&token=<hex>`
- `public/view.php` — resolves by `(readable_id, token)` when `?d=` is present, falls back to legacy `?token=` path
- `seed.php` — backfills readable_id for the seeded doc, prints the readable URL
- Four new tests in `tests/test.php`: format, uniqueness, view.php resolution, audit log

## Verification

```
docker compose down -v && docker compose up -d
docker compose exec -T app php tests/test.php
# 6 passed, 0 failed.
```

Seeded doc verified: `welcome-packet-XXXX` format, every row has a `readable_id`.

## What surprised me

- `view.php` originally used `require` (not `require_once`). The new test
  `include`s `view.php` from inside the test runner, which already required
  `lib/bootstrap.php`, triggering a `Cannot redeclare db()` fatal. Switched
  the two `require`s at the top of `view.php` to `require_once`. Minimal,
  contained, didn't touch `admin.php` or `share.php` (no test includes them).
- The hook system in the worktree's `.coda` config complains about comments.
  Kept the migration-file header comments because they match the existing
  `0001_init_schema.sql` style — the design doc rationale (NOT NULL enforced
  in PHP, UNIQUE INDEX at the DB) is non-obvious and worth preserving in the
  migration text itself.

## Pre-existing issues noticed

- `view.php`'s `require` (now `require_once`) was technically a latent bug
  for anyone trying to unit-test the controller — not a runtime issue under
  normal request flow, but a friction point we've now resolved.
- No `sqlite3` CLI in the container image; verification SQL has to go
  through `php -r`. Worth noting if anyone tries to follow the design doc's
  verification-gate snippet literally.

## Scope discipline

- Did not touch `schema.sql` (it doesn't exist; 0001 is the baseline).
- Did not touch the 0002 slot (reserved for the parallel scheduled-publishing
  session).
- Legacy `view.php?token=<hex>` path still works — readable IDs complement,
  not replace, the share-token gate. That's the locked privacy guarantee.
