---
tags: [entity, page, view, recipient]
description: public/view.php — recipient view by share token
created: 2026-05-18
updated: 2026-05-18
---

# public/view.php

38 lines. Renders the document body when given a valid `?token=...`.
404s if token is unknown.

## Behavior

- `SELECT d.*, s.recipient_email FROM shares s JOIN documents d ON d.id = s.document_id WHERE s.token = ?`
- 404 if no row
- Renders title + recipient email + body inside `<pre>`

## Surfaces affected by features

- **Scheduled publishing:** this is THE gate. If `d.publish_at` is set and in the future, render "not yet available" instead of body. Do NOT 404 — the share is valid, the content just isn't yet. Per [[decision-scheduling-gates-content]].
- **Readable IDs:** the share-link URL may change shape, but the route handler may move to a new file (`d.php` or similar) — decided in the readable-IDs design doc.
- **Search:** no impact.
- **Audit log:** consider logging `audit_log('view', 'share', $shareId)` when the recipient opens — currently not logged. Spec doesn't require it for "share actions"; design call.

## Dependencies
Requires `lib/bootstrap.php` and `lib/layout.php`. **Note:** unlike
admin/share, view.php does NOT call `render_header($title, $staff)` — it
calls `render_header($title)` with no staff arg, so the recipient page
intentionally omits the staff nav user-pill. Worth preserving when
scheduled-publishing adds the "not yet available" state.

## Related
- [[decision-scheduling-gates-content]]
- [[decision-readable-ids-complement]]
- [[folio-layout]]
- [[pattern-audit-log]]
