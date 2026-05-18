---
tags: [entity, page, admin]
description: public/admin.php — list documents and create new ones
created: 2026-05-18
updated: 2026-05-18
---

# public/admin.php

96 lines. The only place documents are created and listed.

## Behavior

- **GET:** lists all documents (JOIN to staff for creator name) ordered by `created_at DESC`. Renders create-form on top.
- **POST:** validates non-empty title + body, inserts into `documents`, calls `audit_log('create', 'document', $docId, ['title' => $title])`, redirects to `?created=N`.

## Surfaces affected by features

- **Search:** new search input + filtered list. Likely a `?q=...` query param.
- **Scheduled publishing:** new `publish_at` field in the create form, displayed in the doc table column.
- **Readable IDs:** new column in the doc table; share link in `share.php` will reference it.

## Dependencies
Requires both `lib/bootstrap.php` (db, audit_log, current_staff, h)
and `lib/layout.php` (render_header, render_footer).

## Related
- [[folio-schema]]
- [[folio-share-page]]
- [[folio-layout]]
- [[pattern-audit-log]]
