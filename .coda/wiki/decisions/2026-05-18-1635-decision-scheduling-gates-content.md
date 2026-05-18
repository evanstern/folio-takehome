---
tags: [decision, feature, scheduled-publishing]
description: Scheduling gates content visibility, not document/ID existence. Pre-publish hits show "not yet available", not 404.
status: approved
created: 2026-05-18
updated: 2026-05-18
---

# Decision: scheduling gates content, not identity

> **Status: APPROVED** by Evan (session 2). Q1 = A with `.env`
> configurability layer ("for our use-case we don't expect anyone
> outside of Central to ever use this"). Q2 = A with explicit honesty
> in the rendered string ("not because it's best UX but because we
> decided to simplify"). Multi-tz upgrade path documented in a
> punted focus card.

## Resolved timezone story

Two sub-decisions Evan locked in session 2:

**Q1 — staff input tz:** Implicit, defaulting to `America/Chicago`,
**configurable via `.env`** (`FOLIO_TZ`). The single existing
`date_default_timezone_set` call in `lib/bootstrap.php` reads the
env variable, defaulting to `America/Chicago`. Staff input is
interpreted in whatever `date_default_timezone_set` is set to at
the time of submission.

This is a small generalization over "hardcoded Central" with a real
benefit: the test suite can pin tz for deterministic comparisons,
deployments to other regions can flip the env, and the migration
path to per-document tz (if ever needed) starts here.

**Q2 — recipient display tz:** Recipients see times in the server-
configured tz (`America/Chicago` by default), rendered with the tz
abbreviation in the string (e.g. `"9:00 AM CDT"` / `"9:00 AM CST"`).
PHP's `DateTime::format('T')` provides the abbreviation. **NOT
because this is the best UX** — a recipient in Tokyo still has to
do mental math — **but because we explicitly chose to simplify** for
this exercise. The honest framing in the video is "we decided to
punt multi-tz."

The "what we'd do with more time" beat is captured in a backlog
focus card (see [[2026-05-18-2010-pattern-punt-cards]]).

## Context

README:
> Staff should be able to prepare a document in advance and have it
> become visible to recipients at a specific date and time. Before
> that time, someone hitting the share link should see a "not yet
> available" message instead of the document.

The README itself answers the main UX question: pre-publish = "not yet
available", not 404. But it leaves several sub-decisions open.

## Decisions

### 1. `publish_at` is nullable; null = published immediately
Documents created without a schedule behave exactly as they do today. No
breaking change to existing rows. Migration adds `documents.publish_at TEXT NULL`.

### 2. The gate lives in `view.php`, not `share.php`
Staff can generate share links for unpublished docs (they're preparing
in advance — that's the whole feature). The recipient-side view is
where we check. `share.php` displays a heads-up like "this document
will be visible on YYYY-MM-DD HH:MM CT" but doesn't block.

### 3. "Not yet available" shows the publish time
Recipients see when to come back. Without this, the feature is half-
useful. Render in the server-configured tz (`FOLIO_TZ`, defaulting to
`America/Chicago`) with the tz abbreviation appended via
`DateTime::format('T')`. See "Resolved timezone story" above for the
honest framing — this is a simplification choice, not a UX optimum.

### 4. Storage is UTC, comparison is UTC, display is server-local
SQLite's `datetime('now')` returns UTC and existing `created_at` columns
already store UTC. Store `publish_at` as UTC ISO-ish (`YYYY-MM-DD HH:MM:SS`).
Compare with `gmdate('Y-m-d H:i:s')` in PHP. Convert for display with
`DateTime` + `setTimezone(new DateTimeZone(getenv('FOLIO_TZ') ?: 'America/Chicago'))`.

### 5. Audit-log entries
- On document create with a `publish_at`: include `publish_at` in the existing `audit_log('create', 'document', ...)` details
- New action: `audit_log('schedule', 'document', $docId, ['publish_at' => $when])` when an existing document's `publish_at` is changed (if we expose edit UI; otherwise this only fires on create)

Per README requirement: "Document creation, scheduling changes, and share
actions should be logged to `audit_log`."

### 6. Interaction with readable IDs
Pre-publish hits to `/d/<readable-id>?token=<hex>` resolve the document
(ID exists), check publish_at, then either show body or "not yet available."
Token validity is checked first; invalid token = 404. Future-published with
valid token = "not yet available." See [[2026-05-18-1640-decision-readable-ids-complement]].

## Rejected alternatives

### Timezone alternatives (Q1 — staff input tz)

- **Browser-local via JS** (Q1 option B). A hidden form field
  populated by `Intl.DateTimeFormat().resolvedOptions().timeZone` on
  submit. Rejected: adds JS to a zero-JS codebase, adds attack
  surface (untrusted client-supplied tz), and the UX win is marginal
  for a single-staff app where everyone is in the same office.
- **Explicit per-document `<select>` for tz** (Q1 option C). Form has
  a tz dropdown; selection stored on the document. Rejected: new
  column, bigger UI, bigger migration. Right answer for multi-region
  staff or per-recipient scheduling — wrong answer at this scale.
  Upgrade path is clean (add `documents.tz` defaulting to env value).
  Punted to backlog focus card #5.

### Timezone alternatives (Q2 — recipient display tz)

- **Render publish time in UTC.** Rejected: looks like an error
  message, not a UI. Recipients shouldn't have to parse ISO 8601.
- **Browser-local via JS** (Q2 option C). Best recipient UX. Rejected:
  adds JS to view.php and forces us to maintain a `data-utc` attribute
  on the rendered time. Cost > benefit for this exercise.
- **Render in both server-tz and UTC** (Q2 option D). Honest about
  the conversion. Rejected: noisy. The tz abbreviation in the
  rendered string is already unambiguous.

### Schema/behavior alternatives

- **Make `publish_at` NOT NULL with a default of now().** Cleaner schema, but a non-trivial behavior change for existing rows on migration. Null-as-immediate is more honest.
- **Hide the publish time on the "not yet available" page** (privacy theater). The recipient already has the token; revealing when they can come back is fine and useful.
- **Block share-link creation for unpublished docs.** Defeats the "prepare in advance" use case.
- **Store `publish_at` as Unix timestamp.** Diverges from existing columns. Consistency wins.

### Infrastructure alternatives

- **CRON / background worker for "publish" events.** Explicitly
  rejected in PROJECT.md. The implicit-publish-at-request-time model
  is simpler, accurate, and doesn't require new infrastructure. The
  publish moment is *as the recipient experiences it*.

## Talking points for the video

- "The README answered the main UX question, but left the schema and
  interaction calls open. The interesting ones were: do you let staff
  share unpublished docs? (Yes — that's the feature.) Do you tell the
  recipient when to come back? (Yes — half-useful otherwise.)"
- "The timezone thing is the trap. The existing code already had a
  timezone set, but SQLite stores UTC. Display ≠ storage. I made the
  server tz configurable via `FOLIO_TZ` in `.env` — small generalization
  over hardcoded Central, with a real benefit: deterministic test
  comparisons, and a clean upgrade path if Folio ever needs multi-region."
- "I considered three timezone options for recipient display and
  shipped the simplest one — server-local with the tz abbreviation
  in the rendered string. The honest framing is 'we punted multi-tz'
  — a recipient in Tokyo still has to do mental math. Per-document
  or per-recipient tz is a real upgrade path; it's parked as a
  backlog focus card so a reviewer can see we considered it."

## Related
- [[folio-schema]]
- [[folio-view-page]]
- [[folio-bootstrap]]
- [[2026-05-18-1600-pattern-audit-log]]
- [[2026-05-18-1640-decision-readable-ids-complement]]
