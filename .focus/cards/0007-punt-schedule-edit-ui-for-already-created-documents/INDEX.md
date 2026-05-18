---
schema_version: 2
id: 7
uuid: 019e3d6b-a3fb-738d-a82f-53aadfe3b5a7
title: 'punt: schedule edit UI for already-created documents'
type: card
status: backlog
priority: p3
project: folio-takehome
created: 2026-05-18
---
## Overview

Scheduled publishing lets staff set a `publish_at` time when creating
a document. There is no UI for **changing** `publish_at` after creation.
The `audit_log('schedule', 'document', …)` action is defined for that
purpose but does not fire today because nothing triggers it. This card
captures the missing edit path.

## Why we punted

Per [[2026-05-18-1635-decision-scheduling-gates-content]] scope:

- The README requires "scheduling changes" to be logged to `audit_log`
- We satisfied the requirement structurally (audit action defined,
  payload shape locked) but didn't expose a UI to trigger it
- Adding an edit UI is: a new admin route, an edit form, validation
  logic, a new audit event firing path, and tests for all three
- For a 3-hour exercise where the showcase is the decision-making,
  not the surface area, this is the cleanest punt

The audit event is intentionally "load-bearing but unused" today —
shipping the schema for it now means the eventual edit UI is purely
additive, no data migration required.

## What "doing it properly" looks like

**Route + form:**
- `admin.php?edit=<id>` shows a form pre-filled with the doc's
  current `title`, `publish_at`, and `readable_id` (display-only)
- POST handler updates `documents.publish_at`; preserves `readable_id`,
  `created_at`, `created_by` unchanged
- Validates `publish_at` parses as a datetime; empty = unschedule

**Audit:**
```php
audit_log('schedule', 'document', $docId, [
    'old_publish_at' => $oldValue,
    'new_publish_at' => $newValue,
]);
```

Fires only when `publish_at` actually changes (not on every save).

**UX edge cases:**
- Editing `publish_at` to a past time effectively "publishes now" —
  fine, the view-side gate handles it transparently
- Editing `publish_at` of a doc whose old `publish_at` was null:
  scheduling a previously-immediate doc retroactively. Audit-log
  captures both values.
- Editing `title`: out of scope of this punt card unless the FTS5
  upgrade (card #6) lands, in which case the title sync trigger
  handles it

## Trigger to revisit

Any one of:
- Real customer asks "can I reschedule a draft I sent yesterday?"
- A formal compliance audit notices the gap and asks "show me the
  scheduling change log"
- The doc count grows past the point where re-creating with new
  schedule is impractical

## Migration risk

Zero. No schema changes — the `publish_at` column and
`audit_log('schedule', …)` payload are both already in place.
This is purely UI + handler work.

## Test plan when revisited

- Edit existing doc's `publish_at` from null to future → audit row
  fires with `old_publish_at: null, new_publish_at: <utc>`
- Edit from future to a different future → audit fires with both
  old and new
- Save form without changing `publish_at` → no audit row
- Try to edit nonexistent doc id → 404

## Related

- [[2026-05-18-1635-decision-scheduling-gates-content]]
- [[.coda/designs/scheduled-publishing.md]] — its "Out of scope"
  section names this
- [[2026-05-18-1600-pattern-audit-log]] — the `schedule` action is
  already in the canonical event list
- [[2026-05-18-1928-pattern-punt-cards]]

## Tags

`punt`, `scheduled-publishing`, `edit-ui`, `p3`
