---
tags: [decision, feature, scheduled-publishing]
description: Scheduling gates content visibility, not document/ID existence. Pre-publish hits show "not yet available", not 404.
status: proposed
created: 2026-05-18
updated: 2026-05-18
---

# Decision: scheduling gates content, not identity

> **Status: PROPOSED.** Pending Evan's review. Per [[pattern-collaboration-with-evan]],
> civicplus does not finalize design decisions unilaterally.

## Open question (per PROJECT.md edit)

> "How does publish time work with timezones? We don't need/want a CRON
> or task runner to do 'publish' things so we're relying on time as a
> function from PHP or wherever. We have to iron out timezones etc."

Civicplus's current proposal (sub-decision #4 below): **store UTC,
compare UTC at request time, display in `America/Chicago` for staff.**
No background job. The "publish" is implicit — a request after `publish_at`
sees the content; before, sees "not yet available." This means the publish
moment is *as the recipient experiences it*, not "the moment a cron ran."

Civicplus must confirm this with Evan before any feature session starts.
Specifically: is staff-local-time (`America/Chicago` from `lib/bootstrap.php`)
the right display zone, or should the form accept a timezone and store it
per-document?

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
useful. Render the time in the staff timezone (`America/Chicago`,
matching `lib/bootstrap.php`'s `date_default_timezone_set`).

### 4. Storage is UTC, comparison is UTC, display is local
SQLite's `datetime('now')` returns UTC and existing `created_at` columns
already store UTC. Store `publish_at` as UTC ISO-ish (`YYYY-MM-DD HH:MM:SS`).
Compare with `gmdate('Y-m-d H:i:s')` in PHP. Convert for display with
`DateTime` + `setTimezone(new DateTimeZone('America/Chicago'))`.

### 5. Audit-log entries
- On document create with a `publish_at`: include `publish_at` in the existing `audit_log('create', 'document', ...)` details
- New action: `audit_log('schedule', 'document', $docId, ['publish_at' => $when])` when an existing document's `publish_at` is changed (if we expose edit UI; otherwise this only fires on create)

Per README requirement: "Document creation, scheduling changes, and share
actions should be logged to `audit_log`."

### 6. Interaction with readable IDs
Pre-publish hits to `/d/<readable-id>?token=<hex>` resolve the document
(ID exists), check publish_at, then either show body or "not yet available."
Token validity is checked first; invalid token = 404. Future-published with
valid token = "not yet available." See [[decision-readable-ids-complement]].

## Rejected alternatives

- **Make `publish_at` NOT NULL with a default of now().** Cleaner schema, but a non-trivial behavior change for existing rows on migration. Null-as-immediate is more honest.
- **Hide the publish time on the "not yet available" page** (privacy theater). The recipient already has the token; revealing when they can come back is fine and useful.
- **Block share-link creation for unpublished docs.** Defeats the "prepare in advance" use case.
- **Store `publish_at` as Unix timestamp.** Diverges from existing columns. Consistency wins.

## Talking points for the video

- "The README answered the main UX question, but left the schema and
  interaction calls open. The interesting ones were: do you let staff
  share unpublished docs? (Yes — that's the feature.) Do you tell the
  recipient when to come back? (Yes — half-useful otherwise.)"
- "The timezone thing is the trap. The existing code already had a
  timezone set, but SQLite stores UTC. Display ≠ storage."

## Related
- [[folio-schema]]
- [[folio-view-page]]
- [[folio-bootstrap]]
- [[pattern-audit-log]]
- [[decision-readable-ids-complement]]
