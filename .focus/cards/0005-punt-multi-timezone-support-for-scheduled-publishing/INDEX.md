---
schema_version: 2
id: 5
uuid: 019e3d6b-8b9e-7861-ab50-4c1c327f8643
title: 'punt: multi-timezone support for scheduled publishing'
type: card
status: backlog
priority: p3
project: folio-takehome
created: 2026-05-18
---
## Overview

Folio currently runs in a single configured timezone (`FOLIO_TZ`,
default `America/Chicago`). All staff input is interpreted in that
zone; all recipients see times rendered in that zone with the
abbreviation appended. This works because the assumed user base is
single-region.

This card captures the upgrade path to **per-document timezone
support**, deferred deliberately during the scheduled-publishing
feature session.

## Why we punted

Per [[2026-05-18-1635-decision-scheduling-gates-content]] and Evan's
session-2 direction:

- For this exercise, all expected users are in Central time
- The single-tz simplification is "not because it's best UX but
  because we decided to simplify"
- Multi-tz adds: new column (`documents.tz`), form widget, render-
  time tz resolution per document, test fixtures with explicit zones
- Cost: real. Benefit at current scale: zero.

## What "doing it properly" looks like

**Schema:**
```sql
-- migrations/00XX_add_document_tz.sql
ALTER TABLE documents ADD COLUMN tz TEXT NOT NULL DEFAULT 'America/Chicago';
-- Default backfills existing rows. New rows can override.
```

Note: setting the default to the current `FOLIO_TZ` value is the
cleanest migration. If the env was changed mid-deployment, the
backfill default should match what was in use at the time.

**Form widget:**
- Drop-down on the `admin.php` create form, sourced from PHP's
  `DateTimeZone::listIdentifiers(DateTimeZone::ALL)` (limit to common
  zones for sane UI, full list under "more…" if needed)
- Default selection: current `FOLIO_TZ` value
- Stored per-document; immutable after creation (or expose via the
  also-punted schedule-edit UI, card #7)

**View render:**
- Replace `date_default_timezone_get()` with `$doc['tz']` in
  `view.php` and `share.php` display paths
- Storage and comparison stay UTC — only display changes

**Recipient view consideration:**
- Two further sub-decisions if/when we land this:
  - Render in document's tz (current direction post-upgrade)
  - Render in recipient's browser tz via JS (adds JS dep)
  - Render in both (most honest)
- Decision can be deferred until we have an actual multi-tz user

## Trigger to revisit

Any one of:
- Folio hires a second staff member outside Central time
- A customer asks "can we schedule for an East Coast recipient
  audience?" with a real use case
- The exercise grows past a single-org deployment

## Migration risk

Low. `ALTER TABLE ADD COLUMN` with NOT NULL DEFAULT is safe in SQLite
3.25+. Existing rows backfill cleanly to the default.

## Related

- [[2026-05-18-1635-decision-scheduling-gates-content]] — the locked
  decision that triggered this punt
- [[.coda/designs/scheduled-publishing.md]] — the design doc whose
  "Rejected alternatives → Q1 option C" section points here
- [[2026-05-18-1928-pattern-punt-cards]] — the pattern this card follows

## Tags

`punt`, `scheduled-publishing`, `multi-tz`, `p3`
