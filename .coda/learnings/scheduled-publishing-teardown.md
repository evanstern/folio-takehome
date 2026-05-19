---
tags: [learning, teardown, scheduled-publishing]
description: What shipped, what surprised, and what to watch for in scheduled-publishing.
created: 2026-05-18
---

# Scheduled-publishing teardown

## What shipped

Single PR on `feature/scheduled-publishing`:

- `migrations/0002_add_publish_at.sql` — `ALTER TABLE documents ADD COLUMN publish_at TEXT NULL`.
- `.env.example` — documents `FOLIO_TZ=America/Chicago` with the punted multi-tz upgrade path note.
- `lib/bootstrap.php` — `date_default_timezone_set(getenv('FOLIO_TZ') ?: 'America/Chicago')`. Single source of truth.
- `public/admin.php` — optional `<input type="datetime-local" name="publish_at">` on create. Empty → NULL. Non-empty → parsed in server tz, stored UTC.
- `public/view.php` — after token resolution, gate on `publish_at > now UTC`. Renders "Not yet available" page with publish time in server tz + abbreviation (`M j, Y \a\t g:i A T`).
- Audit-log payload on doc create now includes `publish_at`.
- Four new tests in `tests/test.php`: future-blocks, past-allows, null-allows (back-compat), audit-log-includes-publish-at.

Verification:

```
docker compose down -v && docker compose up -d && sleep 4 \
  && docker compose exec -T app php tests/test.php
# => 6 passed, 0 failed.
```

`PRAGMA table_info(documents)` confirms `publish_at` column present after seed.

## What surprised

- `public/view.php` uses `require` (not `require_once`). The test harness already
  pulls bootstrap before including view, which would double-declare `db()`. Two
  options: switch view.php's includes to `require_once`, or shell out from tests.
  I did both: tightened view.php to `require_once` (it's already touched by this
  PR), and the tests still invoke view via a `php -r` subprocess because view.php
  calls `exit` after rendering — running it in-process would kill the test runner
  after the first view-rendering test.
- The `require → require_once` swap is the only "touched code I didn't strictly
  need to change for the feature" edit. It's in-file, narrow, and unblocks the
  test plan named in the design doc. Flagging here per "don't refactor untouched
  code" — share.php and admin.php still use bare `require` and I left them alone.

## Pre-existing issues noticed

- `lib/layout.php` renders inline templates from inside helper functions
  (echoing markup via `?>`/`<?php`). Works but resists componentization. Not
  this card's problem; noting for future style work.
- No CSRF on the admin form. Not in scope; flag for the security-pass card.

## Design doc accuracy

The design doc was usable end-to-end. The only practical detail it didn't
spell out was the `require_once` collision in the test harness — caller's
problem, not a design defect.
