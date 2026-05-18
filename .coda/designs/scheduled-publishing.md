---
tags: [design, feature, scheduled-publishing]
description: Schedule documents for future publication; pre-publish recipients see "not yet available".
status: skeleton-awaiting-decision
created: 2026-05-18
updated: 2026-05-18
---

# Design: scheduled publishing

> **Status: SKELETON.** Body fills in once Evan locks
> [[2026-05-18-1635-decision-scheduling-gates-content]] from
> `status: proposed` to `status: approved`. The shape below assumes
> civicplus's recommendation: Q1=A (implicit `America/Chicago` for staff
> input), Q2=A (display in `America/Chicago` with tz abbreviation for
> recipients). If Evan picks differently, the storage section is
> unchanged but the display section rewrites.

## Problem

> README: "Staff should be able to prepare a document in advance and
> have it become visible to recipients at a specific date and time.
> Before that time, someone hitting the share link should see a 'not
> yet available' message instead of the document."

The interesting calls are: timezone handling, where the gate lives (admin
vs view), and whether share-link creation is blocked for unpublished docs.

## Decisions in scope

Locked in [[2026-05-18-1635-decision-scheduling-gates-content]] (subject
to Evan's nod):

1. `publish_at` is nullable; null = published immediately
2. Gate lives in `view.php`, not `share.php`
3. "Not yet available" page shows the publish time
4. Storage UTC, comparison UTC, display local
5. Audit-log entries on create (with `publish_at`) and on schedule change
6. Interaction with readable IDs handled in `view.php` after token validation

## Decisions pending Evan

Timezone story (two sub-questions, civicplus recommends A on both):

**Q1: What tz does staff input mean?**
- A: implicit `America/Chicago` (server default). **Recommended.**
- B: browser-local via JS (untrusted client input).
- C: explicit per-document `<select>`.

**Q2: What tz do recipients see?**
- A: `America/Chicago` with tz abbreviation (`"9:00 AM CDT"`). **Recommended.**
- B: UTC.
- C: browser-local via JS.
- D: both A and B.

## Scope

- [TBD] Migration: add `documents.publish_at TEXT NULL` (UTC string)
- [TBD] `admin.php` form: add optional `publish_at` datetime field
- [TBD] `view.php`: check `publish_at` after token validation; before-publish renders "not yet available" with formatted future date
- [TBD] `share.php`: NO blocking; optional banner "this will be visible on …"
- [TBD] Audit-log: `create` includes `publish_at` in details JSON
- [TBD] No background job. No CRON. Implicit publish at request time.

## Schema impact

```sql
-- migrations/00X_add_publish_at.sql
ALTER TABLE documents ADD COLUMN publish_at TEXT NULL;
-- Null means "published immediately" — back-compat with existing rows.
```

SQLite `ADD COLUMN` is safe (SQLite 3.25+). No table rebuild needed.

## Audit-log impact

Per [[2026-05-18-1600-pattern-audit-log]]:

- `audit_log('create', 'document', $docId, ['title' => $title, 'publish_at' => $publishAt])`
  on doc create (extend existing call with `publish_at` in details)
- `audit_log('schedule', 'document', $docId, ['publish_at' => $newWhen])`
  on subsequent edits (only if edit UI is exposed — likely NOT in this card)

## Test plan

In `tests/test.php`:

- `test('future publish_at blocks recipient view')` — create doc with
  `publish_at` 1 hour in future, hit `view.php`, assert "not yet available"
- `test('past publish_at allows recipient view')` — create doc with
  `publish_at` 1 hour in past, hit `view.php`, assert doc body shown
- `test('null publish_at allows recipient view')` — current behavior, no break
- `test('create audit log includes publish_at')` — assert audit_log row
  has `publish_at` in details when set

## Rejected alternatives

- **`publish_at NOT NULL DEFAULT now()`** — cleaner schema but non-trivial
  behavior change for existing rows. Null-as-immediate is more honest.
- **Hide publish time on "not yet available" page** (privacy theater) —
  recipient already has the token; revealing when to come back is useful.
- **Block share-link creation for unpublished docs** — defeats the
  "prepare in advance" use case.
- **Store `publish_at` as Unix timestamp** — diverges from existing
  `created_at` columns. Consistency wins.
- **Background CRON / task runner for "publish" events** — explicitly
  rejected in PROJECT.md. Implicit publish at request time is simpler
  and accurate.
- **Browser-local JS for recipient timezone display** (Q2 option C) —
  adds JS to a zero-JS codebase. Tz abbreviation in the rendered string
  removes ambiguity without JS.

## Video talking points

- "The README answered the main UX question — pre-publish shows 'not
  yet available', not 404. But it left the schema and interaction calls
  open. The interesting ones: do you let staff share unpublished docs?
  (Yes — that's the feature.) Do you tell the recipient when to come
  back? (Yes — half-useful otherwise.)"
- "The timezone thing is the trap. The existing code already had a
  timezone set in `lib/bootstrap.php`. SQLite stores UTC. Display ≠
  storage. I leaned into the one tz the codebase already had instead
  of building multi-tz infrastructure for a one-staff app."
- "No CRON, no background worker. The publish is implicit — view.php
  compares the current UTC time to `publish_at` on every request. For
  this scale, that's the right shape."

## Related

- [[2026-05-18-1635-decision-scheduling-gates-content]]
- [[folio-schema]]
- [[folio-view-page]]
- [[folio-bootstrap]]
- [[2026-05-18-1600-pattern-audit-log]]
- [[2026-05-18-1640-decision-readable-ids-complement]] — readable-id
  resolution happens before publish-at gate in view.php
- Focus card #2
