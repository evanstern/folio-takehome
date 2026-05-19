---
tags: [pattern, audit, existing-code]
description: How audit_log() is invoked across the codebase, and what fields go where
created: 2026-05-18
updated: 2026-05-18
---

# Pattern: audit_log

`audit_log(string $action, string $entity_type, int $entity_id, array $details = []): void`

Defined in `lib/bootstrap.php`. Writes one row to `audit_log` with
`staff_id` resolved via `current_staff()`. `details` is JSON-encoded.

## Call sites as shipped

| Where | Call | Source |
|---|---|---|
| `admin.php` (doc create) | `audit_log('create', 'document', $docId, ['title' => $title, 'publish_at' => $publishAt, 'readable_id' => $readableId]);` | baseline call site; payload extended by both feature branches |
| `share.php` (share create) | `audit_log('create', 'share', $shareId, ['document_id' => $doc['id'], 'recipient_email' => $email]);` | baseline, unchanged |

**Net change from feature work:** zero new call sites, two new payload
keys on the existing `create` document event (`publish_at`,
`readable_id`).

## Coverage vs. README

The README asks for `creation`, `scheduling changes`, `share actions`.
Coverage is partial — see [[flag-audit-coverage-partial]] for the full
analysis. Short version:

- Creation: logged.
- Share actions: logged.
- Scheduling **changes**: only logged at creation (folded into the
  `create` event). There is no reschedule UI, so no change event exists
  to log.

## Speculative / not-shipped call sites

These were sketched during design but did **not** ship. Listed for
"what we'd do with more time" video material:

| Where | Call | Why not shipped |
|---|---|---|
| edit publish_at | `audit_log('schedule', 'document', $docId, ['publish_at' => $when, 'previous' => $prev]);` | no edit/reschedule UI built |
| `view.php` open | `audit_log('view', 'share', $shareId, ['document_id' => $doc['id']]);` | not required; adds a write per page-load |

## Conventions to follow

- `action`: lowercase verb (`create`, `schedule`, future: `revoke`, `view`)
- `entity_type`: lowercase singular noun matching the table
- `entity_id`: int, the row's PK
- `details`: associative array, keys are snake_case, values are scalars or short strings (it's JSON in the column — keep it small)

## Related
- [[folio-bootstrap]]
- [[folio-schema]]
- [[2026-05-18-1635-decision-scheduling-gates-content]]
