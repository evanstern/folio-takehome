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

## Existing call sites

| Where | Call |
|---|---|
| `admin.php` (doc create) | `audit_log('create', 'document', $docId, ['title' => $title]);` |
| `share.php` (share create) | `audit_log('create', 'share', $shareId, ['document_id' => $doc['id'], 'recipient_email' => $email]);` |

## Required new call sites (per README)

> "Document creation, scheduling changes, and share actions should be logged."

| Where | Call |
|---|---|
| `admin.php` (doc create with publish_at) | `audit_log('create', 'document', $docId, ['title' => $title, 'publish_at' => $when]);` — extend existing call, same action |
| (optional) edit publish_at | `audit_log('schedule', 'document', $docId, ['publish_at' => $when, 'previous' => $prev]);` |
| `share.php` (already covered) | no change beyond existing — share creation IS the audit point |
| (optional) `view.php` open | `audit_log('view', 'share', $shareId, []);` — not required, design call |

## Conventions to follow

- `action`: lowercase verb (`create`, `schedule`, future: `revoke`, `view`)
- `entity_type`: lowercase singular noun matching the table
- `entity_id`: int, the row's PK
- `details`: associative array, keys are snake_case, values are scalars or short strings (it's JSON in the column — keep it small)

## Related
- [[folio-bootstrap]]
- [[folio-schema]]
- [[decision-scheduling-gates-content]]
