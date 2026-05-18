---
schema_version: 2
id: 2
uuid: 019e3d5a-c115-7084-b34e-8620b94cf282
title: 'scheduled-publishing: gate content visibility by publish_at'
type: card
status: backlog
priority: p1
project: folio-takehome
created: 2026-05-18
---
## Overview

Staff can prepare a document in advance with a `publish_at` time. Before that moment, hitting the share link shows "not yet available" instead of the document body. After, normal view. The gate lives in `view.php`; share-link creation is not blocked (staff are preparing in advance — that's the point).

No background job. The "publish" is implicit — `view.php` compares `gmdate('Y-m-d H:i:s')` to `publish_at` (stored UTC) on every request. Display in `America/Chicago` to match `lib/bootstrap.php`'s timezone.

## Design doc

See [.coda/designs/scheduled-publishing.md](.coda/designs/scheduled-publishing.md) (TBD — written after timezone question resolves with Evan).

Related: [[2026-05-18-1635-decision-scheduling-gates-content]] — `status: proposed`, has open timezone sub-question.

## Acceptance

- [ ] Migration adds `documents.publish_at TEXT NULL` (null = published immediately)
- [ ] `admin.php` create form accepts an optional publish-at field
- [ ] `view.php` checks `publish_at` after token validation; before-publish shows "not yet available" with the future date displayed in `America/Chicago`
- [ ] `share.php` does NOT block link generation for unpublished docs; optionally displays "this will be visible on …"
- [ ] `audit_log('create', 'document', …)` includes `publish_at` in details
- [ ] `audit_log('schedule', 'document', …)` fires if/when an existing doc's `publish_at` changes (only if edit UI is exposed — likely not in this card)
- [ ] One test in `tests/test.php` covers: future `publish_at` blocks, past `publish_at` shows body, null `publish_at` shows body

## Blocks

Depends on card #1 (migrations-infra).

## Tags

`feature`, `scheduled-publishing`, `p1`
